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
        $fc = new \Stratum\Controller\FormController();
        $cc = new \Stratum\Controller\CandidateController();
        $cuc = new CorporateUserController();
        $form = $fc->setupForm();
        $formResult = new \Stratum\Model\FormResult();
        $formResult->set("form", $form);
        $candidate = new \Stratum\Model\Candidate();
        $candidate = $cc->populateFromRequest($candidate, $request->all(), $formResult);
        $candidate->set("customText20", $source);

        //$data['message'] = 'Debugging only, nothing uploaded to Bullhorn';

        $bc = new \Stratum\Controller\BullhornController();
        $retval = $bc->submit($candidate);
        if (array_key_exists("errorMessage", $retval)) {
            $data['errormessage']['message'] = $retval['errorMessage'];
            $data['errormessage']['errors'] = $retval['errors'];
            $data['message'] = "Problem uploading data";
        } else {
            $data['message'] = "Data Uploaded";
        }
        $fc = new \Stratum\Controller\FormController();
        $data['form'] = $fc->setupForm();
        $data = $this->setupColours($source, $data);
        $data['thecandidate'] = $candidate;
        return view('candidate')->with($data);
    }

    private function setupColours($source, $data) {
        if ($source == "Brix") {
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
