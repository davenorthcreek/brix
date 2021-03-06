<?php
/*
 * CandidateController.php
 * Controller for interactions with candidate
 * for transfer of data between WorldApp and Bullhorn
 *
 * Copyright 2015
 * @category    Stratum
 * @package     Stratum
 * @copyright   Copyright (c) 2015 North Creek Consulting, Inc. <dave@northcreek.ca>
 *
 */

namespace Stratum\Controller;

  use Log;

class CandidateController
{

	//allow someone to pass in a $logger
	protected $_logger;

	public function setLogger($lgr) {
		//$lgr better be a logger of some sort -missing real OOP here
		$this->_logger = $lgr;
	}

    function var_debug($object=null) {
        ob_start();                    // start buffer capture
        var_dump( $object );           // dump the values
        $contents = ob_get_contents(); // put the buffer into a variable
        ob_end_clean();                // end capture
        $this->log_debug( $contents ); // log contents of the result of var_dump( $object )
    }


	protected function log_debug($str) {
		if (!is_null($this->_logger)) {
			$e = debug_backtrace(true, 2);
			//$this->_logger->debug(var_dump($e[0]));
			$result = date("Ymd H:i:s");
			$result .= ":";
			$result .= $e[1]["line"];
			$result .= ":";
			$result .= $e[1]['function'];
			$result .= ': '.$str;
			$this->_logger->debug($result);
		} else {  //no logger configured
			\Log::debug( $str);
		}
	}


	public function getIdentity($candidate, $formResult) {
		$name = '';
		//extract reference number, name from form result
		$candidate->set("name", $this->extractName($formResult));

		$this->log_debug("Getting identity for ".$candidate->get("name"));
		//$candidate->dump();
		return $candidate;
	}

	public function populate($candidate, $formResult) {
		//assumes there is no current data
		//everything should be written over
		$form = $formResult->get("form");
		foreach ($form->get("BHMappings") as $key=>$qmaps) {
			//now all qmaps will be arrays of answers
            $this->log_debug("Collating $key for ".$candidate->get("id"));
			$this->collate($candidate, $key, $formResult);
		}
		//$candidate->dump();
		$this->log_debug("Loading custom Object 1");
		$candidate->loadCustomObject(1);
		//$this->log_debug("Loading custom Object 3");
		//$candidate->loadCustomObject(3);
		$this->log_debug("Loading references");
		$candidate->loadReferences();
		return $candidate;
	}

	private function collate($candidate, $the_key, $formResult) {
		$arr = $formResult->findByBullhorn($the_key);
        $this->collateWithArray($candidate, $the_key, $arr);
    }

    private function collateWithArray($candidate, $the_key, $arr) {
        //$arr has either index numbers or waan as keys
        //and the values in an array
		$total = "";
		if ($the_key == 'customText20') {
			$pt1 = '';
			$pt2 = '';
			if (array_key_exists('Expected Local Gross Salary', $arr)) {
				$pt1 = $arr['Expected Local Gross Salary']['value'];
			}
			if (array_key_exists('Expected Local Salary Currency', $arr)) {
				$pt2 = $arr['Expected Local Salary Currency']['value'];
			}
			$total = $pt1.' ('.$pt2.')';
            $this->log_debug("Setting $the_key to $total");
			$candidate->set($the_key, $total);
		} else if ($arr) {
			$multiple = false;
			if (count($arr)>1) {
				$multiple = true;
			}
			$separator = ', ';
			$remove = 2;
			if ($the_key == 'customText4' ||
				$the_key == 'categoryID' ||
				$the_key == 'educationDegree' ||
				$the_key == 'customTextBlock1' ||
				$the_key == 'customTextBlock2' ||
				$the_key == 'customText1' ||
				$the_key == 'customText17' ||
				$the_key == 'customText19' ||
				$the_key == 'customTextBlock4' ||
				$the_key == 'customText3' ||
				$the_key == 'degreeList' ||
				$the_key == 'certifications' ||
				$the_key == 'customTextBlock3' ||
				$the_key == 'customText10') {
				$separator = '; ';
			} else if ($the_key == 'specialtyCategoryID' ||
					   $the_key == 'skillID') {
				$separator = "\n";
				$remove = 1;
			} else if ($the_key == 'customObject1.textBlock3') {
				//Additional Candidate Notes
				$separator = "\n\n";
				$remove = 2;
			}
			$keys = array_keys($arr);
			$numeric_keys = array_filter($keys, function($k) {return is_numeric($k);
			});
			if ($numeric_keys) {
				sort($numeric_keys);
				foreach ($numeric_keys as $key) {
					$res = $arr[$key];
					// removed this from the total addition:
					// ($multiple? "$key: ":"").
					$total .= $res.$separator;
				}
			} else {
				foreach ($arr as $key=>$res) {
                    if (array_key_exists("combined", $res)) {
                        $uncombined = explode(", ", $res['combined']);
                        foreach ($uncombined as $list_element) {
                            $total .= $list_element.$separator;
                        }
                    } else {
					    $total .= ($multiple? "$key: ":"").$res['value'].$separator;
                    }
				}
			}
			//clip the last, trailing comma and space
			$total = substr($total, 0, strlen($total)-$remove);
            $this->log_debug("Setting $the_key to $total");
			$candidate->set($the_key, $total);
		}
	}


	protected function extractName($formResult) {
		$firstName = $formResult->findByWorldApp("First Name");
		$lastName = $formResult->findByWorldApp("Last Name");
		if ($firstName && $lastName) {
			$name = $firstName[0]." ".$lastName[0];
		}
		return $name;
		$candidate->set("name", $name);
	}

    private function assign($candidate, $label, $value, $separator) {
        $result_split = [];
        $existing = $candidate->get($label);
        if ($existing) {
            $result_split = explode(", ", $existing);
        }
        $value_split = explode(", ", $value);
        foreach ($value_split as $val) {
            if (!in_array($val, $result_split)) {
                $result_split[] = $val;
            }
        }
        $value = implode($separator, $result_split);
        $candidate->set($label, $value);
    }

    public function populateFromRequest($candidate, $req, $formResult) {
        //we have an existing formResult for this person - let's use that
        //to set up the keys for the candidate - that has been debugged
        $refs = [];
        $cos = [];
        $address = [];
        $address2 = [];
        $note = [];
        // end of customText20 specific values
        $ctb5 = []; //customTextBlock5
        //$this->var_debug($req);
        foreach ($req as $jointkey=>$values) {
            $this->log_debug("key: $jointkey");
            if (!$values || $values == " ") {
                $this->log_debug("nothing in Values, skipping");
                continue;
            }
            if (is_array($values) && count($values)==1 && $values[0]=="") {
                $this->log_debug("nothing in Values, skipping");
                continue;
            } else {
                $this->var_debug($values);
            }
            //now split key and we get both bh and waan
            $key = "";
            $waan = "";
            $star = strpos($jointkey, "*");
            if ($star) {
                $key = substr($jointkey, 0, $star);
                $waan = substr($jointkey, $star + 1);
            }
            $waan = preg_replace("/_/", " ", $waan);
            $this->log_debug("BH: $key");
            $this->log_debug("WAAN: $waan");
            if ($jointkey == "_token" || $jointkey == "id") {
            } else if (preg_match("/customObject(\d)_(.*)/", $key, $m)) {
                $existing = "";
                $this->log_debug("Found Custom Object".$m[1]." field: ".$m[2]);
                if ($key == "customObject1_textBlock3") {

                    $waan2 = preg_replace("/Additional Candidate Notes: /", "", $waan);
                    $values = "$waan2: ".$values[0];
                    $this->log_debug("Values now $values");
                } else {
                    //filter $values - remove empty values
                    $count = count($values); //unset lowers the count in the middle of the loop!
                    for ($i = 0; $i<$count; $i++) {
                        if (!$values[$i]) {
                            unset($values[$i]);
                        } else if (is_array($values[$i]) && array_key_exists("Other", $values[$i])) {
                            $cos = $this->addOtherNote($cos, $waan, $values[$i]["Other"]);
                            unset($values[$i]);
                        }
                    }
                    $val_split = implode($values, ',');
                    $this->log_debug("Imploding to $val_split");
                    $values = $val_split;
                }
                //$this->var_debug($cos);
                if (array_key_exists($m[1], $cos)) {
                    if (array_key_exists($m[2], $cos[$m[1]] )) {
                        $existing = $cos[$m[1]][$m[2]];
                    }
                }
                if ($existing) {
                    $cos[$m[1]][$m[2]] = "$existing\n\n$values";
                } else {
                    $cos[$m[1]][$m[2]] = $values;
                }
            } else if (preg_match("/recommender(\d)_(.*)/", $key, $m)) {
                //$this->log_debug("Found Recommender".$m[1]." data: ".$m[2]);
                $refs[$m[1]][$m[2]] = $values;
            } else if (preg_match("/address\((.*)\)/", $key, $m)) {
                $address[$m[1]] = $values;
            } else if (preg_match("/secondaryAddress\((.*)\)/", $key, $m)) {
                $address2[$m[1]] = $values;
            } else if ($key == "skillID") {
                $this->log_debug($key);
                $this->var_debug($values);
                $existing = $candidate->get($key);
                if ($existing) {
                    $values[] = $existing;
                }
                $value = "";
                foreach ($values as $val) {
                    $value .= $val."\n";
                }
                $value = substr($value, 0, strlen($value)-1);
                $candidate->set("skillID", $value);
            } else if ($key == "specialtyCategoryID") {
                $this->log_debug($key);
                $this->var_debug($values);
                $idvalues = [];
                $existing = $candidate->get("specialties");
                if ($existing) {
                    $idvalues[] = $existing;
                }
                foreach ($values as $val) {
                    $value = $this->find_specialty($val);
                    $skillid = $value->get("id");
                    $this->log_debug("Found specialty $val as $skillid");
                    $this->var_debug($value);
                    $idvalues[] = ["id"=>$skillid];
                }
                $candidate->set("specialties", $idvalues);
            } else if ($key == "categoryID") {
                $this->log_debug($key);
                $this->var_debug($values);
                $idvalues = [];
                $existing = $candidate->get("categories");
                if ($existing) {
                    $idvalues[] = $existing;
                }
                foreach ($values as $val) {
                    $value = $this->find_category($val);
                    $skillid = $value->get("id");
                    $this->log_debug("Found category $val as $skillid");
                    $idvalues[] = ["id"=>$skillid];
                }
                $candidate->set("categories", $idvalues);
            } else if ($key == "id") {
                $this->var_debug($values[0]);
                $id = $values[0]; //repeat of $id=$req["id"]?
                $candidate->set("id", $id);
                $this->log_debug("Set candidate id to ".$id);
            } else if ($key == "Note") {
                //build a Note object, PUT it with an association to the Candidate
                if ($this->endsWith($waan, "Yes") || $this->endsWith($waan, "No")) {
                    $waan = substr($waan, 0, strrpos($waan, ' '));
                } else {
                    foreach ($values as $val) {
                        if (is_array($val)) {
                            if (array_key_exists("Other", $val) && $val['Other']) {
                                if (strpos($waan, 'CV Guidelines') !== false) {
                                    $val_string = "Other: ".$val['Other'];
                                    $this->log_debug("CV guidelines stay in note");
                                    $this->log_debug("$waan: $val_string");
                                    $note[] = "$waan: $val_string";
                                } else {
                                    $cos = $this->addOtherNote($cos, $waan, $val["Other"]);
                                }
                            } // if no value in "Other", do nothing
                        } else {
                            $this->log_debug("Note: $waan: $val");
                            $note[] = "$waan: $val";
                        }
                    }
                }
            } else if ($candidate->validField($key)) {

                $previous = $candidate->get($key);

                $qmaps = $formResult->findByBullhorn($key);
                $this->log_debug("$key: previous $previous");
                $this->var_debug($qmaps);
                //$qmaps is a Human Readable (WAAN) label and
                //an array of answers (from WorldApp result)

                $splitvals = []; //this will be the definitive formResult list
                $splithash = []; // and this is the uniqued version
                $rhash = [];     // and this is the unique list of req values
                //$this->var_debug($qmaps);
                if ($qmaps && is_numeric(array_keys($qmaps)[0])) {
                    $toSort = array_keys($qmaps);
                    sort($toSort);
                    foreach ($toSort as $numKey) {
                        $this->log_debug("FormResult(numeric): $numKey: ".$qmaps[$numKey]);
                        $splitvals[] = $qmaps[$numKey];
                    }
                    foreach ($splitvals as $v) {
                        $splithash[$v] = 1;
                    }
                } else if ($qmaps) {
                    foreach($qmaps as $frwaan=>$frvals) {
                        if (is_array($frvals)) {
                            foreach (array_keys($frvals) as $frlabel) {
                                $frval = $frvals[$frlabel];
                                if (is_array($frval)) {
                                    if (array_key_exists("combined", $frval)) {
                                        $frval = $frval['combined'];
                                        $splitvals = array_merge($splitvals, explode(", ", $frval));
                                        foreach ($splitvals as $splitval) {
                                            $this->log_debug("Split into $splitval");
                                        }
                                    } else if (array_key_exists("value", $frval)) {
                                        $frval = $frval['value'];
                                        $splitvals[] = $frval;
                                    } else {
                                        $frval = "can't parse";
                                    }
                                    $this->log_debug("FormResult (from array): $frwaan: $frlabel: $frval");

                                } else {
                                    //frval is not an array
                                    $this->log_debug("FormResult (non array): $frwaan: $frlabel: $frval");
                                    if ($frlabel == "combined") {
                                        $splitvals = array_merge($splitvals, explode(", ", $frval));
                                        foreach ($splitvals as $splitval) {
                                            $this->log_debug("Split into $splitval");
                                        }
                                    } else {
                                        $splitvals[] = $frval;
                                    }
                                }
                            }
                        } else {
                            //frvals is not an array
                            $this->log_debug("FormResult (simple): $frwaan: $frvals");
                            $splitvals[] = $frvals;
                        }
                        foreach ($splitvals as $v) {
                            $splithash[$v] = 1;
                        }
                    }
                }
                //have to deal with "Other" values before we get to default cases
                for ($i=0; $i < count($values); $i++) {
                    if (is_array($values[$i]) && array_key_exists("Other", $values[$i])) {
                        $otherVal = $values[$i]['Other'];
                        if ($key == "educationDegree") {
                            //edu note
                            $this->addOtherEduNote($candidate, $waan, $otherVal);
                        } else {
                            $cos = $this->addOtherNote($cos, $waan, $otherVal);
                        }
                        unset($values[$i]);
                    }
                }

                //now back to dealing with request values
                foreach($values as $val) {
                    if ($key == "customTextBlock5") {
                        $val = "$waan: $val";
                    }

                    $rhash[$val] = 1;

                }
                //$previous is what is returned from the loaded candidate on a 'get' call
                foreach (explode(", ", $previous) as $valprev) {
                    if ($valprev) {
                        $rhash[$valprev] = 1;
                    }
                }
                $final = [];
                //add the formResult values in order
                //splithash comes from FormResult
                //want to maintain order, that's why all this gymnastics
                foreach ($splithash as $frval=>$nothing) {
                    if (array_key_exists($frval, $rhash)) {
                        //only preserve if it's still there in the request
                        $final[$frval] = 1;
                    }
                }
                //now add the leftover request values
                //rhash comes from the request
                //only add if it's not there already (to preserve order)
                foreach ($rhash as $rval=>$nothing) {
                    if (!array_key_exists($rval, $final)) {
                        $final[$rval] = 1;
                    }
                }
                $value = implode(", ", array_keys($final));
                $this->log_debug("setting $key to $value");
                $this->assign($candidate, $key, $value, ", ");
            } else {
                $this->log_debug("Invalid Field: $key");
                $this->var_debug($values);
            }
        }
        $this->loadReferencesFromRequest($candidate, $refs);
        $this->loadCustomObjectFromRequest($candidate, $cos);
        $this->loadAddressesFromRequest($candidate, $address, $address2);
        $this->loadNoteFromRequest($candidate, $note);
        return $candidate;
    }

    private function endsWith($haystack, $needle) {
        // search forward starting from end minus needle length characters
        return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
    }

    private function addOtherEduNote($candidate, $waan, $otherVal) {
        //Eucation other values have to get stuffed into recentClientList

        $this->log_debug("At addOtherEduNote with $waan and $otherVal");
        if (!$otherVal) {
            return;
        }
        $value = $candidate->get("recentClientList");
        if ($value) {
            $value = $value.", ".$otherVal;
        } else {
            $value = $otherVal;
        }
        $candidate->set("recentClientList", $value);
    }

    private function addOtherNote($cos, $waan, $otherVal) {
        //has to go to customObject1.textBlock3 (Additional Candidate Notes)
        //unless it's from education (Q15/Q17)
        //$this->var_debug($cos);
        $this->log_debug("At addOtherNote with $waan and $otherVal");
        if (!$otherVal) {
            return $cos;
        }
        $comment = "";
        if (array_key_exists(1, $cos) && array_key_exists("textBlock3", $cos[1])) {
            $comment = $cos[1]["textBlock3"];
        }
        $comment .= "\n\n$waan: $otherVal";
        $cos[1]["textBlock3"] = $comment;
        return $cos;
    }


    private function loadAddressesFromRequest($candidate, $address, $address2) {
        $add1 = $candidate->get("address");
        if (!$add1) {
            $add1 = new \Stratum\Model\Address();
        }
        $add2 = $candidate->get("secondaryAddress");
        if (!$add2) {
            $add2 = new \Stratum\Model\Address();
        }
        foreach ($address as $index=>$sub) {
            $answerHash = [];
            foreach ($sub as $subsub) {
                $answerHash[$subsub] = 1;
            }
            $value = implode(", ", array_keys($answerHash));
            $add1->set($index, $value);
        }
        foreach ($address2 as $index=>$sub) {
            $answerHash = [];
            foreach ($sub as $subsub) {
                $answerHash[$subsub] = 1;
            }
            $value = implode(", ", array_keys($answerHash));
            $add2->set($index, $value);
                    }
        $candidate->set("address", $add1);
        $candidate->set("secondaryAddress", $add2);
    }

    private function loadNoteFromRequest($candidate, $note) {
        $existing = $candidate->get("Note");
        $newNote = [];
        $comment = "";
        if ($existing) {
            $comment = $existing["comments"];
        }
        foreach ($note as $noteDetail) {
            $comment .= $noteDetail."\n";
        }
        $newNote["comments"] = $comment;
        $newNote["action"] = "Uploaded Information";
        $candidate->set("Note", $newNote);
    }


    private function loadCustomObjectFromRequest($candidate, $cos) {
        foreach ($cos as $index=>$co) {
            $obj = new \Stratum\Model\CustomObject();
            foreach ($co as $key=>$values) {
                if (is_array($values)) {
                    $value = implode(",", $values);
                } else {
                    $value = $values;
                }
                //if (strpos($value, "Other")===0) {
                //    $otherVal = substr($value, 7);
                //    if ($otherVal) {
                //        $value = "Other";
                //    }
                //}
                $obj->set($key, $value);
                $this->log_debug("Setting custom object ".$index." $key to $value");
            }
            $label = "customObject".$index."s";
            $candidate->set($label, $obj);
        }

    }

    private function loadReferencesFromRequest($candidate, $refs) {
        $reference[0] = new \Stratum\Model\CandidateReference();
        $reference[1] = new \Stratum\Model\CandidateReference();
        $index = 0;
        foreach ($refs as $ref) {

            foreach ($ref as $key=>$values) {
                $value = implode(",", $values);
                $reference[$index]->set($key, $value);
                //$this->log_debug("Setting reference ".($index + 1)." $key to $value");
            }
            $index++;
        }
        $candidate->set("references", $reference);
    }

    public function find_category($skill_name) {
        $skill_json = \Storage::get("Categories.json");
        $skill_list = json_decode($skill_json, true)['data'];
        $skill = new \Stratum\Model\Skill();
        foreach ($skill_list as $valLabel) {
            if ($valLabel['label'] == $skill_name) {
                $skill->set("id", $valLabel['value']);
                $skill->set("name", $valLabel['label']);
            }
        }
        return $skill;
    }

    public function find_specialty($skill_name) {
        $skill_name = preg_replace("/–/", "-", $skill_name);
        $skill_json = \Storage::get("Specialties.json");
        $skill_list = json_decode($skill_json, true)['data'];
        $skill = new \Stratum\Model\Skill();
        foreach ($skill_list as $valLabel) {
            if (strcmp($valLabel['label'], $skill_name) == 0) {
                $skill->set("id", $valLabel['value']);
                $skill->set("name", $valLabel['label']);
            }
        }
        return $skill;
    }


}
