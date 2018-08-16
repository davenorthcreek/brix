<?php
/*
 * Form.php
 * Base model for form
 * Data model for transfer between WorldApp and Bullhorn
 *
 * Copyright 2015
 * @category    Stratum
 * @package     Stratum
 * @copyright   Copyright (c) 2015 North Creek Consulting, Inc. <dave@northcreek.ca>
 *
 */

namespace Stratum\Model;

use Storage;

class Form extends ModelObject
{

    /**
     * Array of attributes codes needed for product load
     *
     * @var array of tag/values
     */
    protected $_fields = [ //put various fields in here
						  'humanToJson'       =>'',
						  'jsonToHuman'       =>'',
						  'questionConfig'    =>[], //map of configs
						  'questionMappings'  =>[], //by Q#
                          'sections'          =>[],
                          'sectionHeaders'    =>[],
						  'WAMappings'        =>[], //by WA Answer Name
						  'BHMappings'        =>[], //by BH Field
						  'SNMappings'        =>[]  //
						  ];

	public function get_question($qId) {
		$questionMappings = $this->get("questionMappings");
		if ($questionMappings) {
			if (array_key_exists($qId, $questionMappings)) {
				return $questionMappings[$qId];
			}
		}
	}

	public function parse_mapping($index) {
        if ($index > env('SUBFORMS')) {
            $index = '';
        }
		$answers = [];
        $sectionCounter = -1;  //so first section header goes to 0
        $sections = [];
        $sectionHeaders = [];
        $fname = base_path()."/storage/app/QandA".$index.".txt";
        $this->log_debug("Filename $fname");
		$handle = fopen($fname, "r");
		$questionMappings = $this->get("questionMappings");
		$waMappings = $this->get("WAMappings");
		$bhMappings = $this->get("BHMappings");
		$snMappings = $this->get("SNMappings");
		if ($handle) {
			$currentQ = "";
			$mapKey = "";
			$choice_flag = false;
			//for recommenders 1 and 2, we need prefixes
			$bullhorn_prefix = "";
			$wa_prefix = "";
			while (($line = fgets($handle)) !== false) {
				// process the line read.
				//answerId first, then text value
                $this->log_debug($line);
				$elements = preg_split("/\s+/", $line);
				$first = $elements[0];
                $second = $elements[1];
                $this->log_debug($first);
                if ($first == "Section") {
                    if ($currentQ) {
                        $sections[$sectionCounter][] = $currentQ;
                        $answers[] = $currentQ;
                        $questionMappings[$mapKey] = $currentQ;
                        $currentQ = null;
                        $choice_flag = false;
                    }
                    $sectionCounter++;
                    $sectionHeaders[$sectionCounter] = $this->collectMultiWordString($elements, 1);
                } else if (preg_match("/brix00rf\d+\.A\d+/", $first)) {
					$q = new QuestionMapping();
					$q->set("form", $this);
					$q->set("QAId", $elements[0]);
                    if ($choice_flag) {
						//this is just one of the options in a choice list
						$q->set("type", $currentQ->get("type"));
						$value = $this->collectMultiWordString($elements, 1);
						$q->set("Value", $value);  //only time answer value is in QandA.txt!
						$q->set("BullhornField", $currentQ->get("BullhornField"));
						$q->set("WorldAppAnswerName", $currentQ->get("WorldAppAnswerName"));
						$waMappings[$currentQ->get("WorldAppAnswerName")][] = $q;
						$bhMappings[$currentQ->get("BullhornField")][] = $q;
                    } else {
                        //sub-answer, probably recommender
    					$q->set("type", $elements[1]);
    					$q->set("BullhornField", $bullhorn_prefix.$elements[2]);
    					//re-assemble world app label
    					$waName = $this->collectMultiWordString($elements, 3);
    					$q->set("WorldAppAnswerName", $wa_prefix.$waName);
    					$waMappings[$wa_prefix.$waName][] = $q;
    					$bhMappings[$bullhorn_prefix.$elements[2]][] = $q;
                    }
                    $questionMappings[$first] = $q;
					$currentQ->add_answer($q);
				} else if (preg_match("/brix.*/", $first)) {
					//normal full line
                    if (!in_array($second, [
                            'Text',
                            'Tel',
                            'textarea',
                            'Date',
                            'choice',
                            'multichoice',
                            'multichoice2',
                            'upload',
                            'multiple',
                            'List',
                            'radio'
                            ]
                        )) {
                        $q = new QuestionMapping();
    					$q->set("form", $this);
    					$q->set("QAId", $elements[0]);
						//this is just one of the options in a choice list
						$q->set("type", $currentQ->get("type"));
						$value = $this->collectMultiWordString($elements, 1);
						$q->set("Value", $value);  //only time answer value is in QandA.txt!
						$q->set("BullhornField", $currentQ->get("BullhornField"));
						$q->set("WorldAppAnswerName", $currentQ->get("WorldAppAnswerName"));
						$waMappings[$currentQ->get("WorldAppAnswerName")][] = $q;
						$bhMappings[$currentQ->get("BullhornField")][] = $q;
                        $questionMappings[$first] = $q;
                        $currentQ->add_answer($q);
                    } else {  //$second is in the list of types
					    //store previous question
                        //check for increment in section
                        $choice_flag = false; //reset
                        if ($currentQ) {
						    if ($currentQ->get("multipleAnswers")) {
							    $currentQ->set("BullhornField", NULL);
							    $currentQ->set("WorldAppAnswerName", NULL);
							    //have to avoid finding the parent
						    }
    						$answers[] = $currentQ;
                            $sections[$sectionCounter][] = $currentQ;
                            //$currentQ->dump();
                            $questionMappings[$mapKey] = $currentQ;
    						$bullhorn_prefix = "";
    						$wa_prefix = "";
    					}
    					//initial mention of this question
    					$currentQ = new QuestionMapping();
    					$mapKey = $first;
    					$currentQ->set("form", $this);
    					$currentQ->set("QId", $first);
    					if (in_array($elements[1], ["choice", "multichoice", "multichoice2", "radio"])) {
    						//choose one of the following options, like boolean [OR]
                            //choose one or more of the following options
    						$currentQ->set("BullhornField", $elements[2]);
    						$currentQ->set("type", $elements[1]);
    						$waName = $this->collectMultiWordString($elements, 3);
    						$currentQ->set("WorldAppAnswerName", $waName);
    						$choice_flag = true;
                        } else if ($elements[1] == "multiple") {
                            //multiple subquestions under this question
                            $currentQ->set("type", "multiple");
                            $currentQ->set("multiple", true);
    						if (count($elements) > 3) {
    							$bullhorn_prefix = $elements[2]."_";
    						}
    						if (count($elements) > 4) {
    							$wa_prefix = $this->collectMultiWordString($elements, 3)." ";
    						}
    						//normal multiple-answer question
                        } else if ($elements[1] == 'List') {
                            //select from a list instead of individual answers
    						$this->log_debug("Looking up answer for ".$first." in file ".$elements[2]);
                            $currentQ->set("type", "list");
                            $list_file = $elements[2];
    						$currentQ->set("configFile", $list_file);
    						$currentQ->set("BullhornField", $elements[3]);
    						$waName =  $this->collectMultiWordString($elements, 4);
    						$currentQ->set("WorldAppAnswerName", $waName);
    						$bhMappings[$elements[3]][] = $currentQ;
    						$waMappings[$waName][] = $currentQ;
                        } else {
    						//this is a normal field assigned to a top-level question ID
    						$currentQ->set("type", $elements[1]);
    						$currentQ->set("BullhornField", $elements[2]);
    						//re-assemble world app label
    						$waName = $this->collectMultiWordString($elements, 3);
    						$currentQ->set("WorldAppAnswerName", $waName);
    						$waMappings[$waName][] = $currentQ;
    						$bhMappings[$elements[2]][] = $currentQ;
                            if ($elements[1] == 'upload') {
                                $currentQ->dump();
                            }
    					}
                    }
				}
                if ($currentQ && !$currentQ->get("required")) { //skip this if we know it's required
                    $waName = $currentQ->get("WorldAppAnswerName");
                    $currentQ->set("required", $this->check_required($waName));
                    if ($currentQ->get("required")) {
                        $waName = trim(substr($waName, 0, strlen($waName) - 8));
                        $currentQ->set("WorldAppAnswerName", $waName);
                    }
                }
			}
			$answers[] = $currentQ;
            $sections[$sectionCounter][] = $currentQ;
			$questionMappings[$mapKey] = $currentQ;
			//$currentQ->dump();
			//echo "FINISHED WITH QandA.txt!!!!!!!!!\n\n";
			fclose($handle);
			$this->set("questionMappings", $questionMappings);
            $this->set("sections", $sections);
            $this->set("sectionHeaders", $sectionHeaders);
		} else {
			// error opening the file.
			die ("Unable to open form input file");
		}
		$configs = $this->get("questionConfig");
		$configs[] = $answers;
		$this->set("questionConfig", $configs);
		$this->set("WAMappings", $waMappings);
		$this->set("BHMappings", $bhMappings);
		$this->set("SNMappings", $snMappings);
        //$this->output_sections();
		return $this;
	}

    private function check_required($waName) {
        $length = strlen($waName);
        //if $waName ends with required, return true.
        $pos = $length - 8; //required has 8 characters
        if (strrpos($waName, 'required') == $pos) {
            return true;
        }
        return false;
    }

    private function output_sections() {
        $sections = $this->get("sections");
        //should be an array of arrays of QuestionMappings
        $this->log_debug("Checking section parsing");
        $first = true;
        $index = 0;
        foreach ($sections as $sec) {
            $this->log_debug("Section ".$index);
            $index++;
            foreach($sec as $qmap) {
                if (is_a($qmap, 'Stratum\Model\QuestionMapping')) {
                    if ($first) {
                        $first = false;
                        //$qmap->dump();
                    } else {
                        $id = $qmap->getBestId();
                        $this->log_debug("Also Qmap ".$id);
                    }
                } else {
                    $this->log_debug("Qmap is not a Qmap: ".$qmap);
                }
            }
            $first = true;
        }
    }

	private function collectMultiWordString($elements, $index) {
		//re-assemble label
		$waName = $elements[$index];
		for ($i=$index+1; $i<count($elements); $i++) {
			$waName = $waName." ".$elements[$i];
		}
		$waName = trim($waName); //remove trailing space
		return $waName;
	}


	public function dump() {
		$this->log_debug("---------------------------");
		$this->log_debug("Stratum\Model\Form");
		foreach ($this->_fields as $key=>$there) {
            if ($key == 'humanToJson') {
                $this->log_debug("HumanToJson available");
            } else if ($key == 'jsonToHuman') {
                $this->log_debug("jsonToHuman available");
            } else if ($key == 'questionConfig') {
                $this->log_debug("list of configs available");
            } else if ($key == 'questionMappings') {
                foreach ($there as $q=>$qmap) {
                    $this->log_debug("Question ".$q);
                    $this->log_debug("BHName: ".$qmap->get("BullhornField"));
                    $this->log_debug("WAAN  : ".$qmap->get("WorldAppAnswerName"));
                    $this->log_debug("Value : ".$qmap->get("Value"));
                }
            } else if ($key == 'sections') {
                $this->log_debug("sections available");
            } else if ($key == 'WAMappings') {
                $this->log_debug("WorldApp Mappings available");
            } else if ($there) {
				$this->log_debug($key.": ");
				//$this->var_debug($there);
			}
		}
		$this->log_debug("---------------------------");
	}



}
