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

class FormResponseController extends Controller
{
    public function index($source) {
        $cuc = new CorporateUserController();
        $controller = new \Stratum\Controller\FormController();
        $ccontroller = new \Stratum\Controller\CandidateController();
        //$entityBody = Storage::disk('local')->get($id.'.txt');
        $form = $controller->setupForm();
        $formResult = new \Stratum\Model\FormResult();
        $formResult->set("form", $form);
        $candidate = new \Stratum\Model\Candidate();
        //expand/collapse all button
        $data['form'] = $form;
        $data['formResult'] = $formResult;
        $data['candidate'] = $candidate;
        $data['page_title'] = "Register with $source";
        $data = $this->setupColours($source, $data);
        return view('formresponse')->with($data);
    }



    public function confirmValues(Request $request) {
        $source = $request->input("source");
        Log::debug("Source is $source");

        $fc = new \Stratum\Controller\FormController();
        $cc = new \Stratum\Controller\CandidateController();
        $cuc = new CorporateUserController();
        $form = $fc->setupForm();
        $formResult = new \Stratum\Model\FormResult();
        $formResult->set("form", $form);

        $candidate = new \Stratum\Model\Candidate();
        $candidate = $cc->populateFromRequest($candidate, $request->all(), $formResult);
        $candidate->set("customText20", $source);
        Log::debug("received ".$candidate->getName()); //triggers setting name value

        Log::debug("confirming required values present");
        $missing = $candidate->missingRequired();
        if ($missing) {
            $data['errormessage']['message'] = "Missing Required Values";
            $data['errormessage']['errors'][0]['propertyName'] = $missing;
            $data['errormessage']['errors'][0]['severity'] = 'Required Value';
            $data['errormessage']['errors'][0]['type'] = 'No Value Found';
            $data['message'] = "Unable to upload your data";
        } else {
            $dateError = $candidate->errorInBirthDate();
            if ($dateError) {
                $data['errormessage']['message'] = "Properly Formatted Birth Date Required";
                $data['errormessage']['errors'][0]['propertyName'] = "dateOfBirth";
                $data['errormessage']['errors'][0]['severity'] = 'Required Value';
                $data['errormessage']['errors'][0]['type'] = "Improperly Formatted Value: $dateError";
                $data['message'] = "Unable to upload your data";
            } else {
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
                $candidate->set("customDate2", $candidate->get("dateOfBirth"));
                $retval = $bc->submit($candidate);
                if (array_key_exists("errorMessage", $retval)) {
                    $data['errormessage']['message'] = $retval['errorMessage'];
                    $data['errormessage']['errors'] = $retval['errors'];
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
                            } else {
                                $type = "H&SGCIC(White Card)";
                            }
                            $bc->submit_file_as_string($candidate, $filename, $filebody, $type);
                        }
                    }
                }
            }
        }
        $fc = new \Stratum\Controller\FormController();
        $data['form'] = $fc->setupForm();
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
