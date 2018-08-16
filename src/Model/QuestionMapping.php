<?php
/*
 * QuestionMapping.php
 * mapping between WorldApp Question JSON and Bullhorn Candidate Attribute
 * Data model for transfer between WorldApp and Bullhorn
 *
 * Copyright 2015
 * @category    Stratum
 * @package     Stratum
 * @copyright   Copyright (c) 2015 North Creek Consulting, Inc. <dave@northcreek.ca>
 *
 */

namespace Stratum\Model;
class QuestionMapping extends ModelObject
{

    /**
     * Array of attributes codes needed for product load
     *
     * @var array of tag/values
     */
    protected $_fields = [ //put various fields in here
						  'form'=>'',
						  'type'=>'',
						  'QId'=>'',
						  'QAId'=>'',
						  'QACId'=>'',
						  'BullhornField'=>'',
						  'BullhornFieldType'=>'',
						  'configFile'=>'',
						  'WorldAppAnswerName'=>'',
						  'StratumName'=>'',
						  'Value'=>'',
						  'multipleAnswers'=>FALSE,
						  'answerMappings'=>[]
						  ];


	public function add_answer($answer) {
		$answers = $this->get("answerMappings");
		if (count($answers)>0) {
			$this->set("multipleAnswers", TRUE);
			//remove the A1 answers from the parent - no longer relevant
			$this->set("BullhornFieldType", NULL);
			$this->set("QAId", NULL);
			$this->set("Value", NULL);
		} else {
			//so far, single answer, so let's push the A1 answers to the parent
			$this->set("type", $answer->get("type"));
			$this->set("QAId", $answer->get("QAId"));
			$this->set("BullhornFieldType", $answer->get("BullhornFieldType"));
			$this->set("BullhornField", $answer->get("BullhornField"));
			$this->set("WorldAppAnswerName", $answer->get("WorldAppAnswerName"));
			$this->set("Value", $answer->get("Value"));
		}
		$answers[] = $answer;
		$this->set("answerMappings", $answers);
	}

	public function init($question) {
		//$question is a Stratum\Model\Question
		//check_and_add("type", $question);
		//need to load the question index data files from Stratum

	}

	function update($question) {
		//$question is a Stratum\Model\Question
		//need to load the question index data files from Stratum

	}

    public function getBestId() {
        $answerId = $this->get("QACId");
        if (!$answerId) {
            $answerId = $this->get("QAId");
        }
        if (!$answerId) {
            $answerId = $this->get("QId");
        }
        return $answerId;
    }

	function check_and_add($key, $array) {
		if (array_key_exists($key, $array)) {
			$this->set($key, $array[$key]);
		}
		return $this;
	}

    private function addOtherWrapper($human, $label, $valueMap) {
        $other = '';
        if (in_array($human, ['Q15', 'Q17', 'Q19', 'Q27', 'Q43', 'Q52', 'Q55', 'Q57', 'Q62',
                          'Q86', 'Q93', 'Q103', 'Q104'])) {
            //need to take care of 'Other'
            $other = "<label class='control-label col-sm-2' for='".$label."[other]'>Other:</label>\n";
            $other.= "<input class='form-control' name='".$label."[Other]' type='text' value='";
            $this->log_debug("Question $human may contain Other");
            $this->var_debug($valueMap);
        }
        return $other;
    }


    public function exportQMToHTML($human, $configs, $candidate, $formResult, $exceptions=[]) {

        $form = $this->get('form');
        $questionMaps = $form->get('questionMappings');
        $mult = false;
        $valueMap = [];
        $answermap=null;
        $qanswers = [];
        $values = [];

        $val = htmlentities(implode(',', array_keys($valueMap)), ENT_QUOTES);

        $type = $this->get("type");
        if ($type == "multichoice" || $type == "multichoice2") {
            $mult = true;
        }
        $id = $this->getBestId();
        $qlabel = '';
        if (!$qanswers) {
            $qlabel = $human;
            //there doesn't have to be an answer to every question
        } else {
            $qlabel = $qanswers[0]->get("humanQACId");
            if (!$qlabel || !array_key_exists($qlabel, $questionMaps)) {
                $qlabel = $qanswers[0]->get("humanQAId");
            }
            if (!$qlabel || !array_key_exists($qlabel, $questionMaps)) {
                $qlabel = $qanswers[0]->get("humanQuestionId");
            }
        }
        $answermap = $questionMaps[$qlabel];
        $waan = $answermap->get("WorldAppAnswerName");
        $bh = $this->get("BullhornField");
        if (!$bh) {
            $bh = $answermap->get("BullhornField");
            if (!$bh) {
                foreach ($answermap->get("answerMappings") as $q2) {
                    $bh = $q2->get("BullhornField");
                    if ($bh) {
                        break;
                    }
                }
            }
        }
        if (!$waan) {
            //go one deeper, if it is there
            foreach ($answermap->get("answerMappings") as $q2) {
                $waan = $q2->get("WorldAppAnswerName");
                if ($waan) {
                    break;
                }
            }
        }
        if ($type == 'upload') {
            $this->log_debug("Mysterious waan: $waan");
            $this->log_debug($this->get("WorldAppAnswerName"));
        }
        if ($bh) {
            $label = $bh;
        } else {
            $label = $qlabel;
        }
        $visible = $waan;
        if ($type == 'boolean') {
            //remove trailing yes or no
            $visible = substr($visible, 0, strrpos($visible, ' '));
        }
        //going to put both bullhorn and worldapp in the label
        $label .= "*".$waan."[]";

        //now we repeat for every response in $q
        $answermap = null;
        if ($qanswers) {
            //foreach ($qanswers as $q) {
            //now have to look at $answermap again, based on THIS $qanswer
            $qlabel = $q->get("humanQACId");
            if (!$qlabel || !array_key_exists($qlabel, $questionMaps)) {
                $qlabel = $q->get("humanQAId");
            }
            if (!$qlabel || !array_key_exists($qlabel, $questionMaps)) {
                $qlabel = $q->get("humanQuestionId");
            }
            $answermap = $questionMaps[$qlabel];
        }

        $required = false;
        if ($this->get("required") || $candidate->isRequired($bh)) {
            $required = true; // from QandA files
            $visible .= " *";
        }

        /******************************/
        /* We are now ready to output */
        /******************************/

        $qlabel = htmlentities($qlabel, ENT_QUOTES);
        $label = htmlentities($label, ENT_QUOTES);
        $visible = htmlentities($visible, ENT_QUOTES);
        if (array_key_exists($id, $exceptions)) {
            $this->log_debug("using exception for $id");
            echo $exceptions[$id];
        } else {
            echo "\n<div class='form-group'>";
            //echo "\n<button class='btn btn-info btn-sm' style='pointer-events: none;'>".$qlabel."</button>";
            echo("\n<label for='$label'>$visible</label>\n");
            if ($id == 'brix00rf0024') {
                echo "\n<div style='display: none;' id='brix00rf0024_label'>\n";
                echo "Once you are placed in work you will be sent a log in for our on-line payroll portal. ";
                echo "Please make sure you complete bank details, superannuation, tax file declaration and upload your visa.";
                echo "\n</div>";
            }
            if ($id == 'brix00rf0025') {
                echo "\n<div style='display: none;' id='brix00rf0025_label'>\n";
                echo "If you are unable to provide your ABN or bank details please ensure your email these to ";
                echo "<a href='mailto:".env("TIMESHEETS_EMAIL")."'>".env("TIMESHEET_EMAIL")."</a>";
                echo "\n</div>";
            }
            $file = $this->get("configFile");
            if ($type == 'boolean') {
                if ($answermap) {
                    $waan = $answermap->get("WorldAppAnswerName");
                }
                //$waan ends with yes or no
                $yn = substr($waan, strrpos($waan, ' '));
                $shorter = substr($waan, 0, strrpos($waan, ' '));
                echo "<label class='radio-inline'><input type='radio' name='$label' value='yes'";
                if ($answermap && strcasecmp($yn, " no")) {
                    echo " CHECKED";
                }
                echo ">Yes</label>\n";
                echo "<label class='radio-inline'><input type='radio' name='$label' value='no'";
                if ($answermap && strcasecmp($yn, " yes")) {
                    echo " CHECKED";
                }
                echo ">No</label>\n";
            } else if ($file) {
                $otherVal = '';
                $other = $this->addOtherWrapper($human, $label, $valueMap);

                //may have to create configFile entry
                if (!array_key_exists($file, $configs)) {
                    $this->log_debug("looking up $file");
                    $configs = $this->parse_option_file($file, $configs);
                }
                //must look up
                if (array_key_exists($file, $configs)) {
                    $configFile = $configs[$file];
                    //now render a select form input
                    echo "<select class='form-control select2' ";
                    //if ($mult) {
                        echo "multiple='multiple'";
                    //}
                    echo " id='$label' data-placeholder='$visible' name='$label'";
                    echo " style='width: 100%;'";
                    if ($required) {
                        echo ' required="true"';
                    }
                    echo ">\n";
                    echo "<option></option>\n"; //empty option
                    $first_not_found = [];
                    foreach(array_keys($valueMap) as $v) {
                        $first_not_found[$v] = true; //so duplicates are only selected once
                    }
                    foreach ($configFile as $op) {
                        echo "<option ";
                        if ($valueMap && array_key_exists($op, $valueMap) and $first_not_found[$op]) {
                            echo("SELECTED ");
                            $first_not_found[$op] = false;
                            $this->log_debug("Found $op in select for $human");
                        }
                        $op = htmlentities($op, ENT_QUOTES);
                        echo 'VALUE="'.$op.'">'.$op."</option>\n";
                    }
                    echo "</select>";
                }
                if ($other) {
                    foreach (array_keys($first_not_found) as $answer) {
                        if ($first_not_found[$answer]) {
                            $answer = htmlentities($answer, ENT_QUOTES);
                            $other .= $answer;
                            $this->log_debug("Other value was $answer");
                        }
                    }
                    $other .= "'>\n";
                    echo $other;
                }
            } else if ($type == 'choice' || $type == 'multichoice' || $type == 'multichoice2') {
                $otherVal = '';
                $other = $this->addOtherWrapper($human, $label, $valueMap);
                $all_listed = false;
                foreach (array_keys($valueMap) as $vm) {
                    if ($vm == "All Listed") {
                        $all_listed = true;
                    }
                }
                echo "<select class='form-control ";
                if ($type == 'multichoice2') {
                    echo "select2_2";
                } else {
                    echo "select2";
                }
                echo "' ";
                if ($type == 'multichoice' || $type == 'multichoice2') {
                    echo "multiple='multiple'";
                }
                echo " id='$label' data-placeholder='$visible' name='$label'";
                echo " style='width: 100%;'";
                if ($required) {
                    echo ' required="true"';
                }
                echo ">\n";
                echo "<option VALUE=''></option>";
                $qmap2 = $questionMaps[$human];
                foreach ($qmap2->get('answerMappings') as $amap) {
                    $aval = $amap->get("Value");
                    if ($aval && $aval != "All Listed") { //skip the all listed option
                        echo "<option ";
                        if ($valueMap) {
                            foreach (array_keys($valueMap) as $vm) {
                                if ($all_listed  || substr($vm, 0, strlen($aval)) === $aval) {
                                    $this->log_debug("Found $vm matching $aval in $human");
                                    echo "SELECTED ";
                                    if ($aval == "Other" && $vm != "All Listed") {
                                        $otherVal = preg_replace("/Other: /", "", $vm);
                                        $otherVal = htmlentities($otherVal, ENT_QUOTES);
                                        $other .= $otherVal;
                                    }
                                }
                            }
                        }
                        $aval = htmlentities($aval, ENT_QUOTES);
                        echo 'VALUE="'.$aval.'">'.$aval."</option>\n";
                    }
                }
                echo "</select>";
                if ($other) {
                    $other .= "'>\n";
                    echo $other;
                }
            } else if ($type == 'radio') {
                foreach ($this->get('answerMappings') as $amap) {
                    $aval = $amap->get("Value");
                    $aval = htmlentities($aval, ENT_QUOTES);
                    echo "<label class='radio-inline'><input type='radio' id='$id' name='$label' value='$aval'";
                    echo ">$aval</label>\n";
                }
            } else if ($type == "Date") {
                if ($label == 'dateOfBirth*Birthdate[]') {
                    $pickerName = 'mydateofbirthpicker';
                } else {
                    $pickerName = 'mydatepicker';
                }
                echo '<div class="input-group date">'."\n".'<div class="input-group-addon">'."\n";
                echo '<i class="fa fa-calendar"></i>'."\n";
                echo '</div><input type="text" class="form-control pull-right '.$pickerName.'" id="';
                echo $label.'" name="'.$label.'" value="'.$val.'"';
                if ($required) {
                    echo ' required="true"';
                }
                echo '>'."\n";
                echo '</div>'."\n<!-- /.input group -->\n";
            } else if ($type == "upload") {
                $this->log_debug("File Upload element: $label $id");
                echo("<input class='form-control' name='$label' type='file' id='$id' value='".$val."'>");
            } else if ($type == "textarea") {
                echo("<textarea class='form-control' name='$label' rows='4' id='$id' placeholder='Enter...'>$val</textarea>");
            } else if ($type == "Tel") {
                echo("<input class='form-control my_phone_number' name='$label' type='tel' id='$id' value='".$val."'");
                if ($required) {
                    echo ' required="true"';
                }
                echo ">";
                echo '<span id="phone-valid-msg" class="hide">✓ Valid</span>';
                echo '<span id="phone-error-msg" class="hide">Invalid number</span>';
            } else {
                echo("<input class='form-control' name='$label' type='text' id='$id' value='".$val."'");
                if ($required) {
                    echo ' required="true"';
                }
                if (in_array($id, ['brix00rf0024', 'brix00rf0025'])) {
                    echo ' disabled="true"';
                }
                echo ">";
            }
            echo "\n</div>\n";
        }
    }

    public function parse_option_file($theFileName, $configs) {
		if (array_key_exists($theFileName, $configs)) {
			return $configs;
		}
		//load provided txt file
		$answers = [];
        $fullFileName = base_path()."/storage/app/".$theFileName;
		$handle = fopen($fullFileName, "r");
		if ($handle) {
			while (($line = fgets($handle)) !== false) {
				// process the line read.
				//answerId first, then text value
                $keyvalue = preg_split("/[\s]+/", $line, 2);
				$answers[$keyvalue[0]]=trim($keyvalue[1]);
                //$this->log_debug("Answer: ".$keyvalue[0]." Value: ".$keyvalue[1]."");
			}
			fclose($handle);
		} else {
			$this->log_debug("Error opening ".$theFileName);
		}
		$configs[$theFileName] = $answers;
		return $configs;
	}


	public function dump($recursion = 0) {
		$tab = "";
		for ($i=0; $i<$recursion; $i++) {
			$tab .= "----";
		}
		$this->log_debug($tab."dumping QuestionMapping");
		$this->log_debug($tab."Type:         ".$this->get('type'));
		$this->log_debug($tab."QId:          ".$this->get('QId'));
		$this->log_debug($tab."QAId:         ".$this->get('QAId'));
		$this->log_debug($tab."QACId:        ".$this->get('QACId'));
		$this->log_debug($tab."BullhField:   ".$this->get('BullhornField'));
		$this->log_debug($tab."BullhornFT:   ".$this->get('BullhornFieldType'));
		$this->log_debug($tab."configFile:   ".$this->get('configFile'));
		$this->log_debug($tab."WorldAppAns:  ".$this->get('WorldAppAnswerName'));
		$this->log_debug($tab."StratumName:  ".$this->get('StratumName'));
		$this->log_debug($tab."Value:        ".$this->get('Value'));
		$this->log_debug($tab."multAnswers:  ".($this->get('multipleAnswers')?"TRUE":"FALSE"));
		$mult = $this->get("answerMappings");
		$recursion++; //one level deeper
		foreach ($mult as $sub) {
			$this->log_debug("Sub Question ".$sub->get("QAId"));
			$sub->dump($recursion);
		}
		$this->log_debug($tab."End of QuestionMapping");
	}

}
