<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use \Stratum\Controller\BullhornController;
use \Stratum\Model\Candidate;
use \Log;

class FindCandidate extends Command
{
    private $client;
    private $muteFlag = false;
    private $updateConfirm = false;
    private $deleteConfirm = false;
    private $deleteFlag = false;
    private $updateFlag = false;
    private $transfer = false;
    private $transferFrom;
    private $transferTo;
    private $updateField;
    private $updateValue;
    private $fields;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'findCandidate {id?} {--delete} {--mute}"
    .   " {--query=} {--transferFrom=} {--transferTo=} {--fields=*}'
    .   " {--updateField=} {--updateValue=}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find all candidates (optionally with id) with field list';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->client = new BullhornController();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->parseOptions();
        $candidate = new Candidate();
        $id = $this->argument('id');
        $query = $this->option('query');
        if ($id) {
            $this->displayAndEdit($id);
        } else if ($query) {
            $data = $this->client->findByQuery($query);
            if (is_array($data)) {
                Log::debug($data);
                foreach($data as $anId) {
                    $this->info("Found candidate $anId");
                    $this->displayAndEdit($anId);
                }
            } else {
                $this->info("Found candidate $data");
                $this->displayAndEdit($data);
            }
        }
    }

    private function parseOptions() {
        $this->deleteFlag = $this->option('delete');
        $this->muteFlag = $this->option("mute");
        $this->fields = $this->option('fields');
        if (!$this->fields) {
            $this->fields[0] = "*";
        }
        $this->transferFrom = $this->option("transferFrom");
        $this->transferTo = $this->option("transferTo");
        if ($this->transferFrom && $this->transferTo) {
            $this->updateFlag = true;
            $this->transfer = true;
            if ($this->fields[0] != "*") {
                $this->fields[0] .= ",$this->transferFrom,$this->transferTo";
            }
        }
        $this->updateField = $this->option("updateField");
        $this->updateValue = $this->option("updateValue");
        if ($this->updateField && $this->updateValue) {
            $this->updateFlag = true;
            $this->transfer = false;
            if ($this->fields[0] != "*") {
                $this->fields[0] .= ",$this->updateField";
            }
        }
    }

    private function displayAndEdit($id) {
        $reset = false;

        if ($this->updateFlag) {
            $candidate = $this->displayCandidate($id, false);
            if ($this->transfer) {
                $newValue = $candidate->get($this->transferFrom);
                $oldValue = $candidate->get($this->transferTo);
                Log::debug("$this->transferFrom to $this->transferTo: $oldValue will become $newValue");
                if ($newValue || $oldValue) {
                    //there is a new value, or the request is to blank the
                    //old value
                    Log::debug("Will Update ".$this->transferTo);
                    $this->updateValue = $newValue;
                } else {
                    //no value in either field
                    //update would be a wasted operation
                    Log::debug("Will skip update for $id");
                    $reset = true;
                    $this->updateFlag = false;
                }
                $this->updateField = $this->transferTo;
                $this->updateValue = $candidate->get("transferFrom");
            }
        } else {
            $candidate = $this->displayCandidate($id, $this->muteFlag);
        }

        $this->checkForDelete($id);

        $this->checkForUpdate($id);
        if ($reset) {
            $this->parseOptions();
        }
    }

    private function displayCandidate($id, $muteFlag) {
        if ($muteFlag)
            return;
        $candidate = new Candidate();
        $candidate->set("id", $id);
        $candidate = $this->client->load($candidate);
        if ($this->fields[0] == "*") {
            $json = $candidate->marshalToJSON();
            $this->info($json);
        } else {
            foreach(array_unique(explode(",", $this->fields[0])) as $f) {
                $this->info($f . ": ". $candidate->get($f));
                if (preg_match("/date/i", $f)) {
                    Log::debug($f);
                    Log::debug($candidate->get($f));
                } 
            }
        }
        return $candidate;
    }

    private function checkForUpdate($id)
    {
        if ($this->updateFlag) {
            $updated = false;
            if (!$this->updateConfirm) {
                $d = $this->ask("Are you sure you want to update $id? (Y/N/A)");
                if (strcasecmp("Y", $d) == 0) {
                    $updated = $this->update($id);
                } else if (strcasecmp("A", $d) == 0) {
                    $this->updateConfirm = true;
                    $updated = $this->update($id);
                }
            } else { //update has been confirmed
                $updated = $this->update($id);
            }
            if ($updated) {
                $this->displayCandidate($id, $muteFlag);
            }
        }
    }

    private function update($id) {
        $candidate = new Candidate();
        $candidate->set("id", $id);
        $candidate->set($this->updateField, $this->updateValue);
        $response = $this->client->updateCandidate($candidate);
        $this->displayResponse("Update", $response);
    }

    private function displayResponse($type, $response) {
        if (is_array($response) && array_key_exists("changedEntityId", $response)) {
            $this->info("Successful $type");
            return true;
        } else {
            $this->error("Unsuccessful $type:");
            $this->error($response["errorMessage"]);
            foreach($response["errors"] as $err) {
                $this->error($err["detailMessage"]);
                $this->error($err["propertyName"]);
                $this->error($err["type"]);
            }
            return false;
        }
    }


    private function checkForDelete($id) {
        if ($this->deleteFlag) {
            if (!$this->deleteConfirm) {
                $d = $this->ask("Are you sure you want to delete $id? (Y/N/A)");
                if (strcasecmp("Y", $d) == 0) {
                    $this->delete($id);
                } else if (strcasecmp("A", $d) == 0) {
                    $this->deleteConfirm = true;
                    $this->delete($id);
                }
            } else { //delete has been confirmed
                $this->delete($id);
            }
        }
    }

    private function delete($id) {
        $candidate = new Candidate();
        $candidate->set("id", $id);
        $response = $this->client->deleteCandidate($candidate);
        $this->displayResponse("Deletion", $response);
    }

}
