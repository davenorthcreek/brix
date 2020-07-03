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
    public function kickoff(Request $request) {
        return $this->index($request, env('SOURCE'), 1);
    }

    public function legacy(Request $request, $source) {
        return $this->index($request, $source, 1);
    }

    public function index(Request $request, $source, $subform=1) {
        $recaptcha_token = $request->input("g-recaptcha-response");

        //have to define $candidate outsite of the if block, so pull from request
        $candidate = Cache::get($request->input("email"));

        $data = [];
        $data['gtm'] = env('GTM_ID')?env('GTM_ID'):'GTM-PHVNHQ8'; //default to Brix
        $recaptcha = new \ReCaptcha\ReCaptcha(env("CAPTCHA_SECRET"));
        $resp = $recaptcha->setExpectedHostname(env("URL"))
            ->setScoreThreshold(0.5)
            ->verify($recaptcha_token, $_SERVER['REMOTE_ADDR']);
        if (!$resp->isSuccess()) {
            $subform = 1;
        } else {
            if ($candidate) {
                $data['candidate'] = $candidate;
            }
        }
        //load candidate from cache if we've had this candidate before
        if ($subform == 1) { //start of process
            //set up exceptions for complex html
            $data['exceptions'] = $this->setExceptions($candidate);
            $data['candidate'] = new \Stratum\Model\Candidate();
            $data['email'] = '';
            $data = $this->setupColours($source, $data);
            $data = $this->setupFormControllers($data, $source, $subform);
            return view('formresponse')->with($data);
        }
        if ($subform > env("SUBFORMS")) { //finished process
            return $this->confirmValues($request, $data, $source);
        }
        if ($subform <= env("SUBFORMS")) { //middle of process
            $data = $this->acceptFormValues($request, $data);
            $candidate = $data['candidate'];
            $data = $this->checkMissingValues($candidate, $data);
            if ($request->input("dateOfBirth*Birthdate")) { //form code for birthdate
                $data = $this->checkBirthDate($candidate, $data);
            }
            if (!array_key_exists('errormessage', $data)) {
                $this->submitCandidateToBullhorn($candidate, false);
            } else {
                $subform--;
            }
            //set up exceptions for complex html
            $data['exceptions'] = $this->setExceptions($candidate);
            $email = $candidate->get("email");
            $data['email'] = $email;
            Cache::put($email, $candidate, 60);
            $data = $this->setupColours($source, $data);
            $data = $this->setupFormControllers($data, $source, $subform);

            //submit what we have so far
            Log::debug($data['exceptions']);

            return view('formresponse')->with($data);
        }
    }

    private function setupFormControllers($data, $source, $subform) {
        $controller = new \Stratum\Controller\FormController();
        $subform = min(env('SUBFORMS'), $subform);
        $form = $controller->setupForm($subform);
        $formResult = new \Stratum\Model\FormResult();
        $formResult->set("form", $form);
        //expand/collapse all button
        $data['form'] = $form;
        $data['formResult'] = $formResult;
        $data['page_title'] = "Register with ".$data['fullSource'];
        $data['index'] = ++$subform;
        if ($subform > env('SUBFORMS')) {
            $data['next'] = 'Submit Values to '.$data['fullSource'];
        } else {
            $data['next'] = 'NEXT';
        }
        return $data;
    }

    private function acceptFormValues(Request $request, $data) {
        $source = $request->input("source");
        $index = $request->input("subform");
        Log::debug("Source is $source, Index is $index");
        //$index = min(env('SUBFORMS'), $index);
        $fc = new \Stratum\Controller\FormController();
        $cc = new \Stratum\Controller\CandidateController();
        $form = $fc->setupForm($index-1); // we want qmaps from current questions
        $formResult = new \Stratum\Model\FormResult();
        $formResult->set("form", $form);

        if (array_key_exists("candidate", $data)) {
            $candidate = $data['candidate'];
        } else {
            $candidate = new \Stratum\Model\Candidate();
        }
        $candidate = $cc->populateFromRequest($candidate, $request->all(), $formResult);
        $candidate->set("customText20", $source);
        //copy address(state) to customText14
        $address = $candidate->get("address");
        if ($address) {
            $state = $address->get("state");
            if ($state) {
                $candidate->set("customText2", $state);
            }
        }
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
            $data['errormessage']['message'] = "Properly Formatted, Legal Birth Date Required";
            $data['errormessage']['errors'][0]['propertyName'] = "dateOfBirth";
            $data['errormessage']['errors'][0]['severity'] = 'Required Value';
            $data['errormessage']['errors'][0]['type'] = "Improperly Formatted Value: $dateError";
            $data['message'] = "Unable to upload your data";
        }
        return $data;
    }

    private function submitCandidateToBullhorn($candidate, $complete) {
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

        $retval = $bc->submit($candidate, $complete);

        return $retval;
    }

    public function confirmValues(Request $request, $data, $source) {
        $bc = new \Stratum\Controller\BullhornController();
        $data = $this->acceptFormValues($request, $data);
        $candidate = $data['candidate'];
        $data = $this->checkMissingValues($candidate, $data);
        $data = $this->checkBirthDate($candidate, $data);
        if (!array_key_exists('errormessage', $data)) {
            //no error so far
            $retval = $this->submitCandidateToBullhorn($candidate, true);
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
                        } else if (strpos($label, 'Ticket') !== false) {
                            $type = "Ticket";
                        } else if (strpos($label, 'Passport') !== false) {
                            $type = "Passport";
                        } else if (strpos($label, 'Driver') !== false) {
                            $type = "License";
                        } else {
                            $type = "Additional";
                        }
                        $bc->submit_file_as_string($candidate, $filename, $filebody, $type);
                    }
                }
            }
        }
        $fc = new \Stratum\Controller\FormController();
        $data['form'] = $fc->setupForm(env('SUBFORMS') + 1);
        $data = $this->setupColours($source, $data);
        $data['thecandidate'] = $candidate;
        return view('candidate')->with($data);
    }

    private function setupColours($source, $data) {
        if (strcasecmp($source, "brix") == 0) {
            $data['fullSource'] = 'Brix Projects';
            $data['short']      = 'Brix';
            $data['adminEmail'] = 'admin@brixprojects.com.au';
            $data['colour']    = "blue";
            $data['box_style'] = "primary";
            $data['home']      = "https://www.brixprojects.com.au/";
            $data['homepage']  = "https://www.brixprojects.com.au/";
        } else if (strcasecmp($source, "civilform") == 0) {
            $data['fullSource'] = 'CivilForm';
            $data['short']      = 'CF';
            $data['adminEmail'] = 'careers@civilform.com.au';
            $data['colour']    = "blue-light";
            $data['box_style'] = "warning";
            $data['home']      = "https://www.civilform.com.au";
            $data['homepage']  = "https://www.civilform.com.au";
        } else {
            $data['fullSource'] = 'Advanced Group Services';
            $data['short']      = 'AGS';
            $data['adminEmail'] = 'admin@advancedgroupservices.com.au';
            $data['colour']    = "yellow";
            $data['box_style'] = "warning";
            $data['home']      = "https://www.advancedgroupservices.com.au/";
            $data['homepage']  = "https://www.advancedgroupservices.com.au/";
        }
        $data['source'] = $source;
        return $data;
    }

    private function setExceptions($candidate) {

        if ($candidate && $candidate->get("customText12") == 'Australian Citizen') {
            $exc['brix00rf0014'] = '<!-- unnnecessary -->';
        } else {
            $exc['brix00rf0014'] = <<<EOT
<div class="form-group">
<label for="customText17*Passport Number[]">Passport Number (Non Australian citizens only)</label>
<input class="form-control" name="customText17*Passport Number[]" value="" type="text">
</div>
EOT;
        }
        return $exc;
    }
}
