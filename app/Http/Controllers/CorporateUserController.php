<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use \Stratum\Controller\BullhornController;
use \App\Http\Controllers\FormResponseController;
use \Stratum\Model\Candidate;
use \Stratum\Model\CorporateUser;
use App\Prospect;
use Log;
use Cache;
use Auth;

class CorporateUserController extends Controller
{
  public function index() {
      $frc = new FormResponseController();
      return $frc->index(0);
  }


  public function flushCandidatesFromCache() {
      Cache::flush();
  }

  public function refresh() {
      //$this->flushCandidatesFromCache();
      return $this->index();
  }

  private function replace_key_function($array, $key1, $key2) {
      if ($array) {
          $keys = array_keys($array);
          $index = array_search($key1, $keys);

          if ($index !== false) {
              $keys[$index] = $key2;
              $array = array_combine($keys, $array);
          }
      }
      return $array;
  }

}
