<?php
// header("Content-type: text/plain; charset=utf-8");
require_once __DIR__ . DIRECTORY_SEPARATOR . "LmsActions.php";

//Function to fix up PHP's messing up POST input containing dots, etc.
function getRealPOST() {
    parse_str(urldecode(file_get_contents("php://input")), $parsed);
    return $parsed;
}

// Test header return
/* Expected format.
The "items" array lists the items that were selected for import.
In other words, if it's not in the "items" array, ignore it.

Array
(
    [items] => Array
    (
        [0] => a0Pg0000002UXSQEA4.a0Jg0000002RXuMEAW
        [1] => a0Pg0000002UXSQEA4.a0Jg0000002RXuREAW
    )
    [item] => Array
    (
        [a0Pg0000002UXSQEA4.a0Jg0000002RXuMEAW] => Array
        (
            [parent_account_name] => YES N YOU FR
            [parent_account_code] => 001h340000J7TzDAAV
            [account_name] => Synefo1
            [account_code] => 001g000000S5BpKAAV
            [contract] => 2015-5613
            [contract_code] => 800g0000000Qk3sAAC
            [training_name] => - BizSem 3 days -
            [training_code] => 01t200000038avr
            [level] => A1.3
            [ui_lang] => english
            [salutation] => Female
            [first_name] => Wild
            [last_name] => Flower
            [email] => wild.flower@yesnyou.com
            [phone] => 0800 008 771
            [cell] => 06 09 00 90 90
            [login] => wild.flower@yesnyou.com
            [job_title] =>
            [rec_level] =>
            [owner_firstname] => Anaïs;
            [owner_lastname] => Tourlourat;
            [owner_email] => anais.tourlourat@yesnyou.com;
            [owner_phone] => 01 75 77 48 08;
            [owner_cell] => 06 67 78 89 91;
            [owner_photo_url] => https://c.eu0.content.force.com/profilephoto/005/T;
            [owner_active] => true;
            [owner_street] => 40 ter, Avenue de Suffren;
            [owner_zipcode] => 75015;
            [owner_city] => Paris;
            [owner_country] => France;
            [owner_department] => Sales;
            [owner_division] => HQ
        )
        [a0Pg0000002UXSQEA4.a0Jg0000002RXuREAW] => Array
        (
            [parent_account_name] => YES N YOU FR
            [parent_account_code] => 001h340000J7TzDAAV
            [account_name] => Synefo1
            [account_code] => 001g000000S5BpKAAV
            [contract] => 2015-5613
            [contract_code] => 800g0000000Qk3sAAC
            [training_name] => - BizSem 3 days -
            [training_code] => 01t200000038avr
            [level] => A1.3
            [ui_lang] => french
            [salutation] => Male
            [first_name] => Geronimo
            [last_name] => Apache
            [email] => geronimo@yesnyou.com
            [phone] => 0800 008 771
            [cell] => 06 07 08 09 01
            [login] => geronimo@yesnyou.com
            [job_title] => Grand manitou
            [rec_level] =>
            [owner_firstname] => Anaïs;
            [owner_lastname] => Tourlourat;
            [owner_email] => anais.tourlourat@yesnyou.com;
            [owner_phone] => 01 75 77 48 08;
            [owner_cell] => 06 67 78 89 91;
            [owner_photo_url] => https://c.eu0.content.force.com/profilephoto/005/T;
            [owner_active] => true;
            [owner_street] => 40 ter, Avenue de Suffren;
            [owner_zipcode] => 75015;
            [owner_city] => Paris;
            [owner_country] => France;
            [owner_department] => Sales;
            [owner_division] => HQ
        )
    )
)
*/
$lms = new LmsActions(true, true);

$_POST = getRealPOST();

if (!isset($_POST["items"])) {
    include __DIR__ . DIRECTORY_SEPARATOR . 'result_sync.phtml';
    exit;
}

// First off, find the PM branch, accounts, contracts and users
foreach ($_POST["items"] as &$item) {
    // We'll be handling timeout
    $start = time();

    // Find PM branch.
    // If it does not exist, create it right under the PM's country/city branch
    // If it exists, this is the starting point for the account hierarchy creation
    $lms->log(sprintf("Find PM branch for PM %s", ucwords($_POST["item"][$item]["owner_firstname"] . " " . $_POST["item"][$item]["owner_lastname"])));
    $itemParentBranch = $lms->fetchBranchIdByCode(strtolower($_POST["item"][$item]["owner_firstname"] . "_" . $_POST["item"][$item]["owner_lastname"]));
    if (empty($itemParentBranch->id_org)) {
        $lms->log("PM branch not found");
        $lms->log("Parent account not found, fetch top parent account (ie. Paris)");
        $parentBranch = $lms->fetchBranchIdByCode($lms->getParentBranchCode($_POST["item"][$item]["owner_country"],$_POST["item"][$item]["owner_city"]));
        $lms->log(sprintf("Create PM branch for PM for PM %s",ucwords($_POST["item"][$item]["owner_firstname"] . " " . $_POST["item"][$item]["owner_lastname"])));
        if (false === $lms->createBranch(
                strtolower($_POST["item"][$item]["owner_firstname"] . "_" . $_POST["item"][$item]["owner_lastname"]),
                ucwords($_POST["item"][$item]["owner_firstname"] . " " . $_POST["item"][$item]["owner_lastname"]),
                $parentBranch->id_org
            )) {
            exit(sprintf("  Doom's upon us! Unable to create branch '%s'!", $hierarchy["name"]));
        }
        $lms->log("Find ID of newly created node " . $hierarchy["name"]);
        $itemParentBranch = $lms->fetchBranchIdByCode(strtolower($_POST["item"][$item]["owner_firstname"] . "_" . $_POST["item"][$item]["owner_lastname"]));
    }
    $_POST["item"][$item]["account_parent_branch_id"] = $itemParentBranch->id_org;

    // Find account
    $lms->log("Find account");
    $jAccount = $lms->fetchBranchIdByCode($_POST["item"][$item]["account_code"]);
    if (!empty($jAccount->id_org)) {
        $lms->log("Account found, id=" . $jAccount->id_org);
        $_POST["item"][$item]["account_parent_branch_id"] = $jAccount->id_org;
    } else {
        $lms->log("Account not found");
        foreach ($_POST["item"][$item]["hierarchy"] as $key => $hierarchy) {
            $lms->log("Find parent account with code " . $hierarchy["code"]);
            $itemParentBranch = $lms->fetchBranchIdByCode($hierarchy["code"]);
            if (!empty($itemParentBranch->id_org)) {
                $lms->log("Parent account found, id=" . $itemParentBranch->id_org);
                $parentBranch                                     = $itemParentBranch->id_org;
                $_POST["item"][$item]["account_parent_branch_id"] = $itemParentBranch->id_org;
            } else {
                if (!isset($_POST["item"][$item]["account_parent_branch_id"])) {
                    $lms->log(
                        "Create parent account " . $hierarchy["name"] . " with code " . $hierarchy["code"] . " under node id " . $parentBranch->id_org
                    );
                    if (false === $lms->createBranch(
                            $hierarchy["code"],
                            $hierarchy["name"],
                            $parentBranch->id_org
                        )
                    ) {
                        exit(sprintf("  Doom's upon us! Unable to create branch '%s'!", $hierarchy["name"]));
                    }
                    $lms->log("Find ID of newly created node " . $hierarchy["name"]);
                    $parentBranch = $lms->fetchBranchIdByCode(
                        $hierarchy["code"]
                    );
                    $_POST["item"][$item]["account_parent_branch_id"] = $parentBranch->id_org;
                } else {
                    $lms->log(
                        "Create parent account " . $hierarchy["name"] . " with code " . $hierarchy["code"] . " under node id " . $_POST["item"][$item]["account_parent_branch_id"]
                    );
                    if (false === $lms->createBranch(
                            $hierarchy["code"],
                            $hierarchy["name"],
                            $_POST["item"][$item]["account_parent_branch_id"]
                        )
                    ) {
                        exit(sprintf("  Doom's upon us, again! Unable to create branch '%s'!", $hierarchy["name"]));
                    }
                    $lms->log("Find ID of newly created node " . $hierarchy["name"]);
                    $parentBranch                                     = $lms->fetchBranchIdByCode(
                        $hierarchy["code"]
                    );
                    $_POST["item"][$item]["account_parent_branch_id"] = $parentBranch->id_org;
                }
            }
        }
    }

    // Find contract
    $jContract = $lms->fetchBranchIdByCode($_POST["item"][$item]["contract_code"]);
    if (!empty($jContract->id_org)) {
        $_POST["item"][$item]["contract_branch_id"] = $jContract->id_org;
    } else {
        // The parent branch is the account_branch_id, so we already have that
        $lms->createBranch($_POST["item"][$item]["contract_code"], $_POST["item"][$item]["contract"], $_POST["item"][$item]["account_parent_branch_id"]);
        // Get id of newly created branch
        $jContract = $lms->fetchBranchIdByCode($_POST["item"][$item]["contract_code"]);
    }

    // Find user
    $jUser = $lms->fetchUserWithEmail($_POST["item"][$item]["email"]);
    if (empty($jUser->idst)) {
        $userData = $_POST["item"][$item]; // Simplify next call a bit
        if (false === $jUser = $lms->createUser($jContract->id_org, $userData)) {
            exit(sprintf("  Blimey! Error creating user %s %s with email %s", $userData["first_name"], $userData["last_name"], print_r($userData, 1)));
        }
    }
    // Associate user with branch
    if (false !== $json = $lms->assignUsersToBranch(array($jUser->idst), $jContract->id_org)) {
        $lpJson = $lms->enrollUserToLearningplan($jUser->idst, $_POST["item"][$item]["training_code"], $_POST["item"][$item]["level"]);
    }
    $time = time() - $start;
    // Add spent time to script timeout
    set_time_limit($time);
}
//--- Rollback enrollments in case of error ----
if ($lms->rollback) {
    foreach ($lms->enrollments as $lpUserPair) {
        $lms->unenrollUserFromLearningplan($lpUserPair["user"]["idst"], $lpUserPair["lp_code"]);
    }
}
include __DIR__ . DIRECTORY_SEPARATOR . 'result_sync.phtml';
