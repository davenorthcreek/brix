<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CandidateController as CanCon;
use \Stratum\Controller\FormController;
use \Stratum\Controller\CandidateController;
use \Stratum\Model\FormResult;
use \Stratum\Model\Candidate;
use Storage;
use Log;
use Auth;
use Mail;
use Cache;

class FormResponseController extends Controller
{
    public function index(Request $request, $source, $subform=1) {
        //load candidate from cache if we've had this candidate before
        $candidate = Cache::get($request->input("email"));
        if ($candidate) {
            $data['candidate'] = $candidate;
        }
        if ($subform >= env("SUBFORMS")) {
            return $this->confirmValues($request, $data, $source); //finished process
        }
        if ($subform == 1) {
            $data = $this->setupFormControllers([], $source, $subform);
            $data['candidate'] = new \Stratum\Model\Candidate();
            $data['email'] = '';
            $data = $this->setupColours($source, $data);
            return view('formresponse')->with($data);
        }
        if ($subform < env("SUBFORMS")) {
            $data = $this->acceptFormValues($request, $data);
            $data = $this->setupFormControllers($data, $source, $subform);
            $email = $data['candidate']->get("email");
            $data['email'] = $email;
            Cache::put($email, $data['candidate'], 60);
            $data = $this->setupColours($source, $data);
            return view('formresponse')->with($data);
        }
    }

    private function setupFormControllers($data, $source, $subform) {
        $controller = new \Stratum\Controller\FormController();
        $form = $controller->setupForm($subform);
        $formResult = new \Stratum\Model\FormResult();
        $formResult->set("form", $form);
        //expand/collapse all button
        $data['form'] = $form;
        $data['formResult'] = $formResult;
        $data['page_title'] = "Register with $source";
        $data['index'] = ++$subform;
        return $data;
    }

    private function acceptFormValues(Request $request, $data) {
        $source = $request->input("source");
        Log::debug("Source is $source");
        $index = $request->input("subform");

        $fc = new \Stratum\Controller\FormController();
        $cc = new \Stratum\Controller\CandidateController();
        $form = $fc->setupForm($index);
        $formResult = new \Stratum\Model\FormResult();
        $formResult->set("form", $form);

        if (array_key_exists("candidate", $data)) {
            $candidate = $data['candidate'];
        } else {
            $candidate = new \Stratum\Model\Candidate();
        }
        $candidate = $cc->populateFromRequest($candidate, $request->all(), $formResult);
        $candidate->set("customText20", $source);
        Log::debug("received ".$candidate->getName()); //triggers setting name value
        $data['candidate'] = $candidate;

        return $data;
    }

    private function checkMissingValues($candidate, $data) {
        Log::debug("confirming required values present");
        $missing = $candidate->missingRequired();
        if ($missing) {
            $data['errormessage']['message'] = "Missing Required Values";
            $data['errormessage']['errors'][0]['propertyName'] = $missing;
            $data['errormessage']['errors'][0]['severity'] = 'Required Value';
            $data['errormessage']['errors'][0]['type'] = 'No Value Found';
            $data['message'] = "Unable to upload your data";
        }
        return $data;
    }

    private function checkBirthDate($candidate, $data) {
        $dateError = $candidate->errorInBirthDate();
        if ($dateError) {
            $data['errormessage']['message'] = "Properly Formatted Birth Date Required";
            $data['errormessage']['errors'][0]['propertyName'] = "dateOfBirth";
            $data['errormessage']['errors'][0]['severity'] = 'Required Value';
            $data['errormessage']['errors'][0]['type'] = "Improperly Formatted Value: $dateError";
            $data['message'] = "Unable to upload your data";
        }
        return $data;
    }

    private function submitCandidateToBullhorn($candidate) {
        $bc = new \Stratum\Controller\BullhornController();
        $email = $candidate->get("email");
        $prev_id = $bc->findByEmail($email);
        if ($prev_id) {
            if (is_array($prev_id)) {
                $prev_id = $prev_id[0];
            }
            $candidate->set("id", $prev_id); //will trigger update rather than create
            Log::debug("updating $prev_id rather than creating a new candidate based on $email");
        }
        //Brix is using customDate2 for birthdate despite the presence of the dateOfBirth field
        if ($candidate->get("dateOfBirth")) {
            $candidate->set("customDate2", $candidate->get("dateOfBirth"));
        }

        //Brix and AGS need smsOptIn to be set to true for all candidates
        //update - this is causing a problem because of Bullhorn permissions
        //$candidate->set("smsOptIn", true);

        //$retval = $bc->submit($candidate);
        Log::debug("This is where we would be submitting to Bullhorn\n.\n.\n...");
        $retval =[];
        return $retval;
    }

    public function confirmValues(Request $request, $data, $source) {
        $data = $this->acceptFormValues($request, $data);
        $candidate = $data['candidate'];
        $data = $this->checkMissingValues($candidate, $data);
        $data = $this->checkBirthDate($candidate, $data);
        if (!array_key_exists('errormessage', $data)) {
            //no error so far
            $retval = $this->submitCandidateToBullhorn($candidate);
            if (array_key_exists("errorMessage", $retval)) {
                $data['errormessage']['message'] = $retval['errorMessage'];
                $data['errormessage']['errors'] = $retval;
                $data['errormessage']['errors'][0]['propertyName'] = "id";
                $data['errormessage']['errors'][0]['severity'] = 'Unable to upload data';
                $data['errormessage']['errors'][0]['type'] = $retval['errorCode']||'';
                $data['message'] = "Problem uploading data";
            } else {
                $data['message'] = "Data Uploaded";
                //file handling
                foreach ($_FILES as $label=>$thefile) {
                    $filename = $thefile['name'][0];
                    $filesize = $thefile['size'][0];
                    $filepath = $thefile['tmp_name'][0];
                    if ($filepath) {
                        $filebody = file_get_contents($filepath);
                        if (strpos($label, 'resume') !== false) {
                            //file2, the resume
                            $type = "Resume";
                        } else if (strpos($label, 'White') !== false) {
                            $type = "H&SGCIC(White Card)";
                        } else {
                            $type = "Additional";
                        }
                        $bc->submit_file_as_string($candidate, $filename, $filebody, $type);
                    }
                }
            }
        }
        $fc = new \Stratum\Controller\FormController();
        $data['form'] = $fc->setupForm(env('SUBFORMS'));
        $data = $this->setupColours($source, $data);
        $data['thecandidate'] = $candidate;
        return view('candidate')->with($data);
    }

    private function setupColours($source, $data) {
        if (strcasecmp($source, "brix") == 0) {
            $colour = "blue";
            $box = "primary";
            $home = "http://www.brixprojects.com.au/";
        } else {
            $colour = "yellow";
            $box = "warning";
            $home = "http://www.advancedgroupservices.com.au/";
        }
        $data['colour'] = $colour;
        $data['box_style'] = $box;
        $data['home'] = $home;
        $data['source'] = $source;
        return $data;
    }
}
