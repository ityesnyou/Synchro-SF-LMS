<?

class SalesforceActions
{
    const LOG_FILE  = "hub.log";
    const CONF_FILE = "config.php";
    public  $conf  = array();
    private $supra = array();
    private $useProductionConf   = false;

    public function __construct($useLogging = true, $useProd = false)
    {
        if (true === $useProd) {
            $this->useProductionConf = true;
        }
        $this->loadConf($useProd);
    }

    protected function loadConf($useProd = false)
    {
        $this->supra = require_once __DIR__ . DIRECTORY_SEPARATOR . self::CONF_FILE;
        $this->log("Loaded conf:");
        $this->log("  " . print_r($this->supra, 1));
        if ($useProd) {
            $this->conf = $this->supra["PRODUCTION"];
        } else {
            $this->conf = $this->supra["SANDBOX"];
        }
    }

    public function checkBasics()
    {
        $this->log("Checking basic config info");
        $ok = true;
        if (empty($this->conf["APP_REDIRECT_URI"])) {
            $this->log("  No APP_REDIRECT_URI in conf");
            exit("The configuration file must return an array with key 'APP_REDIRECT_URI' but it contains '" . print_r(
                    $this->conf,
                    1
                ) . "'");
        }
        if (empty($this->conf["SF_CLIENT_ID"])) {
            $this->log("  No SF_CLIENT_ID in conf");
            exit("The configuration file must return an array with key 'SF_CLIENT_ID'");
        }
        if (empty($this->conf["SF_CLIENT_SECRET"])) {
            $this->log("  No SF_CLIENT_SECRET in conf");
            exit("The configuration file must return an array with key 'SF_CLIENT_SECRET'");
        }
        if (empty($this->conf["SF_ACCESS_TOKEN"])) {
            $this->log("  No SF_ACCESS_TOKEN in conf");
            $ok = false;
        }
        if (empty($this->conf["SF_INSTANCE_URL"])) {
            $this->log("  No SF_INSTANCE_URL in conf");
            $ok = false;
        }
        if (!$ok) {
            $this->log("  Redirecting to SF oAuth process: https://" . ($this->useProductionConf ? "login" : "test") . ".salesforce.com/services/oauth2/authorize?response_type=code&client_id=" . $this->conf["SF_CLIENT_ID"] . "&redirect_uri=" . $this->conf["APP_REDIRECT_URI"]);
            header("Location: https://" . ($this->useProductionConf ? "login" : "test") . ".salesforce.com/services/oauth2/authorize?response_type=code&client_id=" . $this->conf["SF_CLIENT_ID"] . "&redirect_uri=" . $this->conf["APP_REDIRECT_URI"]);
            exit(0);
        }
    }

    public function getAccessToken($url, $postParams)
    {
        $this->log("Get access token");
        $this->log("  Using parameters: ");
        foreach ($postParams as $paramName => $paramValue) {
            $this->log(sprintf("    - %s: %s", $paramName, $paramValue));
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, count($postParams));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: application/x-www-form-urlencoded"));
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postParams));
        $json = curl_exec($ch);
        $this->log("  cURL sent: ");
        $this->log(print_r(curl_getinfo($ch, CURLINFO_HEADER_OUT), 1));
        $this->log("  Got answer: " . $json);
        if (false === $json) {
            $this->log("  cURL request failed");
            exit("damn. cURL request failed.");
        }
        $jsonObj = json_decode($json);
        if (!isset($jsonObj->access_token)) {
            $this->log("  no access token in JSON");
            exit("fart. no access token in JSON: " . print_r($jsonObj, 1));
        }
        $this->log("  Returning stdObj with access and refresh token from JSON: " . $json);
        return $jsonObj;
    }

    public function refreshAccessToken()
    {
        $this->log("Refresh access token using SF_REFRESH_TOKEN from conf");
        if ($this->useProductionConf) {
            $url = "https://login.salesforce.com/services/oauth2/token";
        } else {
            $url = "https://test.salesforce.com/services/oauth2/token";
        }
        $postParams = array(
            "grant_type"    => "refresh_token",
            "client_id"     => $this->conf["SF_CLIENT_ID"],
            "client_secret" => $this->conf["SF_CLIENT_SECRET"],
            "refresh_token" => $this->conf["SF_REFRESH_TOKEN"],
            "redirect_uri"  => $this->conf["APP_REDIRECT_URI"]
        );
        return $this->getAccessToken($url, $postParams);
    }

    public function updateLocalTokens($json)
    {
        $this->log("Updating local tokens with:");
        $this->log("  SF_ACCESS_TOKEN: " . $json->access_token);
        $this->conf["SF_ACCESS_TOKEN"] = $json->access_token;
        if (isset($json->refresh_token)) {
            $this->log("  SF_REFRESH_TOKEN: " . $json->refresh_token);
            $this->conf["SF_REFRESH_TOKEN"] = $json->refresh_token;
        }
    }

    public function writeToConf($json)
    {
        $this->log("Writing new tokens to conf");
        $str = "<?php\n";
        $str .= "return array(\n";
        if ($this->useProductionConf) {
            $str .= "    'PRODUCTION' => array(\n";
            $str .= "        'APP_REDIRECT_URI'  => '{$this->conf["APP_REDIRECT_URI"]}',\n";
            $str .= "        'SF_CLIENT_ID'      => '{$this->conf["SF_CLIENT_ID"]}',\n";
            $str .= "        'SF_CLIENT_SECRET'  => '{$this->conf["SF_CLIENT_SECRET"]}',\n";
            $str .= "        'SF_ACCESS_TOKEN'   => '$json->access_token',\n";
            if (isset($json->refresh_token)) {
                $str .= "        'SF_REFRESH_TOKEN'  => '$json->refresh_token',\n";
            } else {
                $str .= "        'SF_REFRESH_TOKEN'  => '{$this->conf["SF_REFRESH_TOKEN"]}',\n";
            }
            $str .= "        'SF_INSTANCE_URL'   => '$json->instance_url'\n";
            $str .= "    ),\n";
            $str .= "    'SANDBOX'    => array(\n";
            $str .= "        'APP_REDIRECT_URI'  => '{$this->supra["SANDBOX"]["APP_REDIRECT_URI"]}',\n";
            $str .= "        'SF_CLIENT_ID'      => '{$this->supra["SANDBOX"]["SF_CLIENT_ID"]}',\n";
            $str .= "        'SF_CLIENT_SECRET'  => '{$this->supra["SANDBOX"]["SF_CLIENT_SECRET"]}',\n";
            $str .= "    )\n";
        } else {
            $str .= "    'PRODUCTION' => array(\n";
            $str .= "        'APP_REDIRECT_URI'  => '{$this->supra["PRODUCTION"]["APP_REDIRECT_URI"]}',\n";
            $str .= "        'SF_CLIENT_ID'      => '{$this->supra["PRODUCTION"]["SF_CLIENT_ID"]}',\n";
            $str .= "        'SF_CLIENT_SECRET'  => '{$this->supra["PRODUCTION"]["SF_CLIENT_SECRET"]}',\n";
            $str .= "    ),\n";
            $str .= "    'SANDBOX' => array(\n";
            $str .= "        'APP_REDIRECT_URI'  => '{$this->supra["SANDBOX"]["APP_REDIRECT_URI"]}',\n";
            $str .= "        'SF_CLIENT_ID'      => '{$this->supra["SANDBOX"]["SF_CLIENT_ID"]}',\n";
            $str .= "        'SF_CLIENT_SECRET'  => '{$this->supra["SANDBOX"]["SF_CLIENT_SECRET"]}',\n";
            $str .= "        'SF_ACCESS_TOKEN'   => '$json->access_token',\n";
            if (isset($json->refresh_token)) {
                $str .= "        'SF_REFRESH_TOKEN'  => '$json->refresh_token',\n";
            } else {
                $str .= "        'SF_REFRESH_TOKEN'  => '{$this->conf["SF_REFRESH_TOKEN"]}',\n";
            }
            $str .= "        'SF_INSTANCE_URL'   => '$json->instance_url'\n";
            $str .= "    )\n";
        }
        $str .= ");\n";
        $fPath = realpath(__DIR__ . DIRECTORY_SEPARATOR . "config.php");
        if (false === file_put_contents($fPath, $str)) {
            exit("burp. unable to write to file $fPath");
        }
        return true;
    }

    public function query($query)
    {
        $url = $this->conf["SF_INSTANCE_URL"] . "/services/data/v34.0/query/?q=" . urlencode($query);
        $this->log("  URL: " . $url);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: application/x-www-form-urlencoded", "Authorization: Bearer " . $this->conf["SF_ACCESS_TOKEN"]));
        $json = curl_exec($ch);
        $this->log("  Answer: " . $json);
        $jsonObj = json_decode($json);
        if (is_array($jsonObj) && 0 < count($jsonObj)) {
            $jsonObj = $jsonObj[0];
        }
        if (isset($jsonObj->errorCode) && $jsonObj->errorCode == "INVALID_SESSION_ID") {
            $this->log("  Session expired, re-init");
            $refresh = $this->refreshAccessToken();
            $this->updateLocalTokens($refresh);
            $this->writeToConf($refresh);
            $json = $this->query($query);
        }
        return $json;
    }

    public function fetchTrainingInfoForContract($contract)
    {
        $this->log("Issue request with contract=" . $contract);
        $query = sprintf(
            "SELECT Id, Account__c, Contract2__r.OwnerId, Contract2__r.Id, Approval_Status__c, Product__c, Name, Training_LMS_interface_language__c, Contract2__r.AccountId, Contract2__r.ContractNumber, Contract2__r.HR_Manager__c, Contract2__r.HR_Manager__r.Salutation, Contract2__r.HR_Manager__r.FirstName, Contract2__r.HR_Manager__r.LastName, Contract2__r.HR_Manager__r.email, (SELECT Learners_Training__c.Learner__r.Id FROM Training__c.Learners_Trainings__r) FROM Training__c WHERE Contract2__r.ContractNumber='%s'", //  and Approval_Status__c='Approved'
            $contract
        );
        // SELECT Division from user where id='00520000002bORBAA2'
        return $this->query($query);
    }

    public function fetchBranchInfoForUserId($userId)
    {
        $this->log("Issue request with userId=" . $userId);
        $query = sprintf(
            "SELECT CompanyName, Email, FirstName, LastName, Phone, MobilePhone, SmallPhotoUrl, IsActive, Division, Department, Street, PostalCode, City, Country FROM User WHERE Id='%s'",
            $userId
        );
        return $this->query($query);
    }

    public function fetchLearnerInfoPerTraining($trainings)
    {
        $fetched = array();
        if (!is_array($trainings)) {
            $trainings = (array)$trainings;
        }
        foreach ($trainings as $trainingId => $training) {
            if (isset($training["learners"])) {
                foreach ($training["learners"] as $learner => $data) {
                    $this->log("Issue request with learner ID=$learner");
                    if (array_key_exists($learner, $fetched)) {
                        $this->log("  Return info from cache");
                    } else {
                        $this->log("  Return info from query");
                        $query             = sprintf(
                            "SELECT Salutation__c, First_name__c, Name, Email__c, Email_address_2__c, Date_of_birth__c, Job_Title__c, Learner_type__c, Center__c, LMS_Interface_language__c, LSAT_is_Valid__c, Last_LSAT_Date__c, Last_LSAT_Level__c, Last_LSAT_Comment__c, Level__c, Recommended_Level__c, Login__c, Phone__c, Mobile_phone__c, Date_of_First_Training__c, Date_of_Last_Training__c FROM Learner__c where Id = '%s'",
                            $learner
                        );
                        $result            = json_decode($this->query($query));
                        $fetched[$learner] = $result->records[0];
                    }
                    $trainings[$trainingId]["learners"][$learner] = $fetched[$learner];
                }
            }
        }
        return $trainings;
    }

    public function getLearnerIdsPerTraining(stdClass $jArray)
    {
        $trainings = array();
        foreach ($jArray->records as $record) {
            foreach ($jArray->parentAccount as $key => $parent) {
                $trainings[$record->Id]["hierarchy"][$key]["parent_account"]      = $parent->records[0]->Name;
                $trainings[$record->Id]["hierarchy"][$key]["parent_account_code"] = $parent->records[0]->Id;
            }
            $trainings[$record->Id]["account"]             = $record->Account__c;
            $trainings[$record->Id]["account_code"]        = $record->Contract2__r->Account->Id;
            $trainings[$record->Id]["contract"]            = $record->Contract2__r->ContractNumber;
            $trainings[$record->Id]["contract_code"]       = $record->Contract2__c;
            $trainings[$record->Id]["contract_approval"]   = $record->Approval_Status__c;
            $trainings[$record->Id]["owner_id"]            = $record->Owner->Id;
            $trainings[$record->Id]["name"]                = $record->Name;
            $trainings[$record->Id]["code"]                = $record->Product__c;
            $trainings[$record->Id]["training_ui_lang"]    = $record->Training_LMS_interface_language__c;
            if (isset($record->HR_Manager__c)) {
                $trainings[$record->Id]["hr_id"]        = $record->HR_Manager__c;
                $trainings[$record->Id]["hr_salute"]    = $record->HR_Manager__r->Salutation;
                $trainings[$record->Id]["hr_firstname"] = $record->HR_Manager__r->FirstName;
                $trainings[$record->Id]["hr_lastname"]  = $record->HR_Manager__r->LastName;
                $trainings[$record->Id]["hr_email"]     = $record->HR_Manager__r->email;
            } else {
                $trainings[$record->Id]["hr_id"]        = "";
                $trainings[$record->Id]["hr_salute"]    = "";
                $trainings[$record->Id]["hr_firstname"] = "";
                $trainings[$record->Id]["hr_lastname"]  = "";
                $trainings[$record->Id]["hr_email"]     = "";
            }
            $trainings[$record->Id]["learners"]         = array();
            if (isset($record->Learners_Trainings__r->records)) {
                foreach ($record->Learners_Trainings__r->records as $lt) {
                    $trainings[$record->Id]["learners"][$lt->Learner__r->Id] = "";
                }
            }
            // Get Division
            $res                                        = json_decode(
                $this->fetchBranchInfoForUserId($record->Owner->Id)
            );
            $trainings[$record->Id]["owner_firstname"]  = $res->records[0]->FirstName;
            $trainings[$record->Id]["owner_lastname"]   = $res->records[0]->LastName;
            $trainings[$record->Id]["owner_email"]      = $res->records[0]->Email;
            $trainings[$record->Id]["owner_phone"]      = $res->records[0]->Phone;
            $trainings[$record->Id]["owner_cell"]       = $res->records[0]->MobilePhone;
            $trainings[$record->Id]["owner_photo_url"]  = $res->records[0]->SmallPhotoUrl;
            $trainings[$record->Id]["owner_active"]     = $res->records[0]->IsActive;
            $trainings[$record->Id]["owner_street"]     = $res->records[0]->Street;
            $trainings[$record->Id]["owner_zipcode"]    = $res->records[0]->PostalCode;
            $trainings[$record->Id]["owner_city"]       = $res->records[0]->City;
            $trainings[$record->Id]["owner_country"]    = $res->records[0]->Country;
            $trainings[$record->Id]["owner_department"] = $res->records[0]->Department;
            $trainings[$record->Id]["owner_division"]   = $res->records[0]->Division;
        }
        return $trainings;
    }

    public function getParentAccountsRecursive($accountId, $accountName, &$hierarchy)
    {
        $hierarchy["id"] = $accountId;
        $hierarchy["name"] = $accountName;
        $this->log("Issue request with account = $accountId");
        $query = sprintf("SELECT Parent.Name, Parent.Id FROM Account WHERE Account.Id='%s'", $accountId);
        $res = $this->query($query);
        $oRes = json_decode($res);
        if (isset($oRes->records[0]) && isset($oRes->records[0]->Parent)) {
            $hierarchy["parent"] = array();
            $parent =& $hierarchy["parent"];
            $this->getParentAccountsRecursive($oRes->records[0]->Parent->Id, $oRes->records[0]->Parent->Name, $parent);
        }
        return $hierarchy;
    }

    public function fetchLearnersForTraining($training)
    {
        $this->log("Issue request with training=" . $training);
        $query = sprintf(
            "SELECT Contract2__r.Account.ParentId, Account__c, Training_LMS_interface_language__c, Contract2__r.Account.Id, Contract2__r.Account.Name, Contract2__c, Contract2__r.ContractNumber, Contract2__r.HR_Manager__r.Id, Contract2__r.HR_Manager__r.FirstName, Contract2__r.HR_Manager__r.LastName, Contract2__r.HR_Manager__r.Email, Contract2__r.HR_Manager__r.Salutation, Id, Name, Approval_Status__c, Owner.Id, Owner.Name, Product__c, Product__r.Name, (SELECT Learners_Training__c.Learner__r.Id, Learners_Training__c.Learner__r.Salutation__c, Learners_Training__c.Learner__r.First_name__c, Learners_Training__c.Learner__r.Name, Learners_Training__c.Learner__r.Email__c, Learners_Training__c.Learner__r.Email_address_2__c, Learners_Training__c.Learner__r.Date_of_birth__c, Learners_Training__c.Learner__r.Job_Title__c, Learners_Training__c.Learner__r.Learner_type__c, Learners_Training__c.Learner__r.Center__c, Learners_Training__c.Learner__r.LSAT_is_Valid__c, Learners_Training__c.Learner__r.Last_LSAT_Date__c, Learners_Training__c.Learner__r.Last_LSAT_Level__c, Learners_Training__c.Learner__r.Last_LSAT_Comment__c, Learners_Training__c.Learner__r.Level__c, Learners_Training__c.Learner__r.Recommended_Level__c, Learners_Training__c.Learner__r.Login__c, Learners_Training__c.Learner__r.Phone__c, Learners_Training__c.Learner__r.Mobile_phone__c, Learners_Training__c.Learner__r.Date_of_First_Training__c, Learners_Training__c.Learner__r.Date_of_Last_Training__c FROM Training__c.Learners_Trainings__r) FROM Training__c WHERE Training__c.Id='%s'",
            $training
        );
        return $this->query($query);
    }

    public function fetchAccountInfoFromId($accountId)
    {
        $this->log("Issue request with training=" . $accountId);
        $query = sprintf(
            "SELECT Id, Name from Account where Id='%s'",
            $accountId
        );
        return $this->query($query);
    }

    public function fetchAccountIdFromName($name)
    {
        $this->log("Issue request with account.name=" . $name);
        $query = sprintf(
            "SELECT Name, Id from Account where Account.Name = '%s'",
            $name
        );
        return $this->query($query);
    }

    public function log($msg)
    {
        file_put_contents(
            dirname(__DIR__) . DIRECTORY_SEPARATOR . self::LOG_FILE,
            sprintf("[%s] %s%s", date("Y-m-d H:i:s"), $msg, PHP_EOL),
            FILE_APPEND
        );
    }
}
