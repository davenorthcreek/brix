<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use \Stratum\Controller\BullhornController;
use \Stratum\Model\Candidate;
use \Log;

class FindCandidate extends Command
{
    private $client;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'findCandidate {id?} {--delete} {--mute}"
    .   " {--query=} {--transferFrom=} {--transferTo=} {--fields=*}';

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
        $deleteFlag = $this->option('delete');
        $muteFlag = $this->option("mute");
        $deleteConfirm = false;
        $updateConfirm = false;
        $candidate = new Candidate();
        $id = $this->argument('id');
        $query = $this->option('query');
        $fields = $this->option('fields');
        if (!$fields) {
            $fields[0] = "*";
        }
        $transferFrom = $this->option("transferFrom");
        $transferTo = $this->option("transferTo");
        if ($transferFrom && $transferTo) {
            $updateFlag = true;
            if ($fields[0] != "*") {
                $fields[0] .= "$transferFrom,$transferTo";
            }
        }
        if ($id) {
             $this->displayCandidate($id, $fields, $muteFlag);
        } else if ($query) {
            $data = $this->client->findByQuery($query);
            if (is_array($data)) {
                Log::debug($data);
                foreach($data as $anId) {
                    $this->info("Found candidate $anId");
                    $candidate = $this->displayCandidate($anId, $fields, true);
                    $deleteConfirm = $this->checkForDelete($deleteFlag, $deleteConfirm, $anId);
                    $updateConfirm = $this->checkForUpdate(
                        $updateFlag,
                        $updateConfirm,
                        $anId,
                        $transferTo,
                        $candidate->get($transferFrom),
                        $fields,
                        $muteFlag
                    );
                }
            } else {
                $this->info("Found candidate $data");
                $candidate = $this->displayCandidate($data, $fields, true);
                $deleteConfirm = $this->checkForDelete($deleteFlag, $deleteConfirm, $data);
                $updateConfirm = $this->checkForUpdate(
                    $updateFlag,
                    $updateConfirm,
                    $data,
                    $transferTo,
                    $candidate->get($transferFrom),
                    $fields,
                    $muteFlag
                );
            }
        }
    }

    private function displayCandidate($id, $fields="*", $muteFlag) {
        if ($muteFlag)
            return;
        $candidate = new Candidate();
        $candidate->set("id", $id);
        $candidate = $this->client->load($candidate);
        if ($fields[0] == "*") {
            $json = $candidate->marshalToJSON();
            $this->info($json);
        } else {
            foreach(array_unique(explode(",", $fields[0])) as $f) {
                $this->info($f . ": ". $candidate->get($f));
            }
        }
        return $candidate;
    }

    private function checkForUpdate(
            $updateFlag,
            $updateConfirm,
            $id,
            $field,
            $newValue,
            $fields,
            $muteFlag)
    {
        if ($updateFlag) {
            $updated = false;
            if (!$updateConfirm) {
                $d = $this->ask("Are you sure you want to update $id? (Y/N/A)");
                if (strcasecmp("Y", $d) == 0) {
                    $updated = $this->update($id, $field, $newValue);
                } else if (strcasecmp("A", $d) == 0) {
                    $updateConfirm = true;
                    $updated = $this->update($id, $field, $newValue);
                }
            } else { //update has been confirmed
                $updated = $this->update($id, $field, $newValue);
            }
            if ($updated) {
                $this->displayCandidate($id, $fields, $muteFlag);
            }
        }
        return $updateConfirm;
    }

    private function update($id, $field, $newValue) {
        $candidate = new Candidate();
        $candidate->set("id", $id);
        $candidate->set($field, $newValue);
        $response = $this->client->updateCandidate($candidate);
        if (is_array($response) && array_key_exists("changedEntityId", $response)) {
            $this->info("Successful Update");
            return true;
        } else {
            $this->error("Unsuccessful Update:");
            $this->error($response["errorMessage"]);
            foreach($response["errors"] as $err) {
                $this->error($err["detailMessage"]);
                $this->error($err["propertyName"]);
                $this->error($err["type"]);
            }
            return false;
        }
    }


    private function checkForDelete($deleteFlag, $deleteConfirm, $id) {
        if ($deleteFlag) {
            if (!$deleteConfirm) {
                $d = $this->ask("Are you sure you want to delete $id? (Y/N/A)");
                if (strcasecmp("Y", $d) == 0) {
                    $this->delete($id);
                } else if (strcasecmp("A", $d) == 0) {
                    $deleteConfirm = true;
                    $this->delete($id);
                }
            } else { //delete has been confirmed
                $this->delete($id);
            }
        }
        return $deleteConfirm;
    }

    private function delete($id) {
        $candidate = new Candidate();
        $candidate->set("id", $id);
        $this->client->deleteCandidate($candidate);
    }

}
