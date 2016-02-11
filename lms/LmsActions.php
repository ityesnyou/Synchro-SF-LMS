<?php
// require_once dirname(__DIR__) . "/oauth2-client/src/Provider/GenericProvider.php";

class LmsActions
{
    const LOG_FILE  = "hub.log";
    const CONF_FILE = "config.php";

    private $useLogging        = true;
    private $useProductionConf = false;

    protected $headers = array();

    public $rollback    = false;
    public $conf        = array();
    public $lpWithError = array();


    /**
     * @var array User fetch tracking
     */
    public $userFetches = array();
    /**
     * @var array User creation tracking
     */
    public $userCreations = array();
    /**
     * @var array Branch creation tracking
     */
    public $branchFetches = array();
    /**
     * @var array Branch creation tracking
     */
    public $branchCreations = array();
    /**
     * @var array Enrollment tracking
     */
    public $enrollments = array();

    /**
     * @param bool $useLogging
     * @param bool $useProd
     */
    public function __construct($useLogging = true, $useProd = false)
    {
        if (true === $useProd) {
            $this->useProductionConf = true;
        }
        $this->loadConf();
    }

    private function createSimplePassword()
    {
        $str = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        return substr(str_shuffle($str), 0, 8);
    }

    private function getLearningplanNameFromCodeAndLevel($code, $level)
    {
        $this->log(sprintf("Getting Learning plan ID from code %s and level %s", $code, $level));
        $lpCode = sprintf("%s|%s", substr($code, 0, -3), $level);
        $lpId   = $this->conf["LP"][$lpCode];
        $this->log(sprintf("  Got learning plan %s", print_r($lpId, 1)));
        return $lpId;
    }

    protected function loadConf()
    {
        $this->conf = require_once __DIR__ . DIRECTORY_SEPARATOR . self::CONF_FILE;
    }

    public function createUser($branchId, $userData, $raw = false) {
        $this->log("Create user");
        if (empty($branchId)) {
            $this->log("  Empty mandatory branch ID");
            return false;
        }
        if (empty($userData["email"])) {
            $this->log("  Empty email, which is also login, and thus required");
            return false;
        }
        $url        = "/user/create";
        $userPassword = $this->createSimplePassword();
        $postParams = array(
            "orgchart"             => $branchId,
            "userid"               => $userData["login"],
            "firstname"            => isset($userData["first_name"]) ? $userData["first_name"] : "",
            "lastname"             => isset($userData["last_name"]) ? $userData["last_name"] : "",
            "password"             => $userPassword,
            "email"                => $userData["email"],
            "valid"                => "true",
            "role"                 => "student",
            "language"             => $userData["ui_lang"],
            "timezone"             => "Europe/Paris",
            // Company Name
            "fields[1]"            => $userData["account_name"],
            // Learner type
            "fields[32]"           => isset($userData["learner_type"]) ? $userData["learner_type"] : "",
            // Date of Birth
            "fields[156]"          => isset($userData["birth_date"]) ? $userData["birth_date"] : "",
            // Job title
            "fields[187]"          => isset($userData["job_title"]) ? $userData["job_title"] : "",
            // Other email
            "fields[218]"          => isset($userData["email2"]) ? $userData["email2"] : "",
            // Cell phone
            "fields[249]"          => isset($userData["phone"]) ? $userData["phone"] : "",
            // Other phone
            "fields[280]"          => isset($userData["cell"]) ? $userData["cell"] : "",
            // Center (Corporate owned or Franchisees)
            "fields[311]"          => isset($userData["center"]) ? $userData["center"] : "",
        );
        //// Special cases because values between Salesforce and LMS do not match (all drop-down lists)
        // Gender
        if (isset($userData["salutation"]) && array_key_exists($userData["salutation"], $this->conf["GENDER"])) {
            $postParams["fields[125]"] = $this->conf["GENDER"][$userData["salutation"]];
        }
        // Acquired level
        if (!empty($userData["level"])) {
            $postParams["fields[590]"] = $userData["level"];
        }
        // Recommended level
        if (!empty($userData["rec_level"])) {
            $postParams["fields[621]"] = $userData["rec_level"];
        }
        // PM
//        if (!empty($userData["owner_firstname"])) {
//            if (array_key_exists(strtolower($userData["owner_firstname"] . " " . $userData["owner_lastname"]), $this->conf["PM"])) {
//                $postParams["fields[683]"] = $this->conf["PM"][strtolower($userData["owner_firstname"] . " " . $userData["owner_lastname"])];
//            }
//        }

        $res        = $this->call($url, $postParams);
        $oRes       = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf("  Error %d: %s. email=%d, level=%s", $oRes->error, $oRes->message, $userData["email"], "User")
            );
            return false;
        }
        // Need to get branch name for tracking purposes
        if (false !== $branch = $this->fetchBranchInfoById($branchId)) {
            $postParams["branchname"] = $branch->translation->english;
        }
        $postParams["idst"]               = $oRes->idst;
        $this->userCreations[$oRes->idst] = array_merge($userData, $postParams);
        if ($raw) {
            return $res;
        }
        return $oRes;
    }

    /**
     * @param string $email The email to check for
     * @return string JSON response in case the user was found. If not, the call returns HTTP code 201
     *
     * The response string is as follows:
     * CheckUsernameResponse {
     *    success (boolean): True if operation was successfully completed,
     *    idst (integer): Internal ID of the user,
     *    firstname (string): The user's firstname,
     *    lastname (string): The user's lastname,
     *    email (string): The user's email,
     *    ext_fields (array[ExtFieldsCheckUsernameModel], optional): User's external identification fields
     * }
     * ExtFieldsCheckUsernameModel {
     *    ext_type (string): Type of external identification (e.g. joomla, drupal),
     *    ext_user (string): Value assigned for this external identification type
     * }
     */
    public function fetchUserWithEmail($email, $raw = false)
    {
        $url        = "/user/checkUsername";
        $postParams = array(
            "userid"              => filter_var($email, FILTER_SANITIZE_STRING),
            "also_check_as_email" => "true"
        );
        $res        = $this->call($url, $postParams);
        $oRes       = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf("  Error %d: %s. email=%s", $oRes->error, $oRes->message, $email)
            );
            return false;
        }
        $this->userFetches[$oRes->idst] = array(
            "idst"      => $oRes->idst,
            "userid"    => $oRes->email,
            "firstname" => $oRes->firstname,
            "lastname"  => $oRes->lastname,
            "email"     => $oRes->email
        );
        if ($raw) {
            return $res;
        }
        return $oRes;
    }

    public function createBranch($code, $name, $parentNodeId = 0, $raw = false)
    {
        $this->log("Create branch");
        if (empty($code) || empty($name)) {
            $this->log(sprintf("  Empty code or name, abort: name=%s, code=%s", $name, $code));
            return false;
        }
        $url        = "/orgchart/createNode";
        $postParams = array(
            "code"                            => $code,
            "translation[arabic]"             => $name,
            "translation[bosnian]"            => $name,
            "translation[bulgarian]"          => $name,
            "translation[croatian]"           => $name,
            "translation[czech]"              => $name,
            "translation[danish]"             => $name,
            "translation[dutch]"              => $name,
            "translation[english]"            => $name,
            "translation[farsi]"              => $name,
            "translation[finnish]"            => $name,
            "translation[french]"             => $name,
            "translation[german]"             => $name,
            "translation[greek]"              => $name,
            "translation[hebrew]"             => $name,
            "translation[hungarian]"          => $name,
            "translation[indonesian]"         => $name,
            "translation[italian]"            => $name,
            "translation[japanese]"           => $name,
            "translation[korean]"             => $name,
            "translation[norwegian]"          => $name,
            "translation[polish]"             => $name,
            "translation[portuguese]"         => $name,
            "translation[portuguese-br]"      => $name,
            "translation[romanian]"           => $name,
            "translation[russian]"            => $name,
            "translation[spanish]"            => $name,
            "translation[swedish]"            => $name,
            "translation[turkish]"            => $name,
            "translation[ukrainian]"          => $name,
            "translation[simplified_chinese]" => $name,
            "translation[thai]"               => $name,
            "id_parent"                       => $parentNodeId
        );
        $res        = $this->call($url, $postParams);
        $oRes       = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf(
                    "  Error %d: %s. org parent ID=%d, node code=%s",
                    $oRes->error,
                    $oRes->message,
                    $parentNodeId,
                    $code
                )
            );
            return false;
        }
        // Need to get parent branch info for tracking
        if (false !== $parentBranch = $this->fetchBranchInfoById($parentNodeId)) {
            $postParams["parent_branch"] = $parentBranch;
        }
        $this->branchCreations[] = $postParams;
        if ($raw) {
            return $res;
        }
        return $oRes;
    }

    public function getParentBranchCode($country, $city)
    {
        $this->log(sprintf("Getting parent branch code from conf with country=%s, city=%s", $country, $city));
        if (empty($country) || empty($city)) {
            $this->log("  Abort, empty country or city");
            return false;
        }
        if (!isset($this->conf["BRANCHES"][strtoupper($country)][strtoupper($city)])) {
            $this->log("  Abort, country/city combination not known in conf");
            return false;
        }
        $this->log(sprintf("  Returning %s", $this->conf["BRANCHES"][strtoupper($country)][strtoupper($city)]));
        return $this->conf["BRANCHES"][strtoupper($country)][strtoupper($city)];
    }

    public function fetchBranchIdByCode($code, $raw = false)
    {
        if (empty($code)) {
            return false;
        }
        $url        = "/orgchart/findNodeByCode";
        $postParams = array(
            "code" => $code
        );
        $res        = $this->call($url, $postParams);
        $oRes       = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf("  Error %d: %s. org code=%s", $oRes->error, $oRes->message, $code)
            );
            return false;
        }
        if ($raw) {
            return $res;
        }
        $this->branchFetches[$code] = $oRes;
        return json_decode($res);
    }

    public function fetchBranchInfoById($id, $raw = false)
    {
        if (empty($id)) {
            return false;
        }
        $url        = "/orgchart/getNodeInfo";
        $postParams = array(
            "id_org" => $id
        );
        $res        = $this->call($url, $postParams);
        $oRes       = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf("  Error %d: %s. org ID=%d", $oRes->error, $oRes->message, $id)
            );
            return false;
        }
        if ($raw) {
            return $res;
        }
        // Replace existing fetched branch info because more complete
        if (array_key_exists($oRes->code, $this->branchFetches)) {
            $this->branchFetches[$oRes->code] = $oRes;
        }
        return $oRes;
    }

    public function assignUsersToBranch(array $userIds, $branchId, $raw = false)
    {
        $this->log("Assign users to branch");
        if (empty($userIds) || empty($branchId)) {
            $this->log(sprintf("  Empty user ID (%s) or empty branch ID (%s)", implode(", ", $userIds), $branchId));
            return false;
        }

        $url        = "/orgchart/assignUsersToNode";
        $postParams = array(
            "id_org"   => $branchId,
            "user_ids" => implode(",", $userIds)
        );
        $res        = $this->call($url, $postParams);
        $oRes       = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf(
                    "  Error %d: %s. org ID=%d, user IDs=%s",
                    $oRes->error,
                    $oRes->message,
                    $branchId,
                    implode(", ", $userIds)
                )
            );
            return false;
        }
        if ($raw) {
            return $res;
        }
        return $oRes;
    }

    public function enrollUserToLearningplan($userId, $learningplanCode, $level, $raw = false)
    {
        $this->log("Assign user to Learning Plan");
        if (empty($userId) || empty($learningplanCode)) {
            $this->log(sprintf("  Empty user ID (%s) or empty Learning Plan CODE (%s)", $userId, $learningplanCode));
            return false;
        }

        $lpCode           = sprintf("%s|%s", substr($learningplanCode, 0, -3), $level);
        $learningplanName = $this->getLearningplanNameFromCodeAndLevel($learningplanCode, $level);

        // Need to get user detail for tracking purposes
        if (array_key_exists($userId, $this->userCreations)) {
            $user = $this->userCreations[$userId];
            $this->log(sprintf("  Got user data from creation array: %s", print_r($user, 1)));
        } else {
            $user = $this->userFetches[$userId];
            $this->log(sprintf("  Got user data from fetch array: %s", print_r($user, 1)));
        }
        $this->enrollments[] = array(
            "level"   => $level,
            "lp_code" => $lpCode,
            "lp_name" => $learningplanName,
            "user"    => $user
        );

        $url        = "/learningplan/enroll";
        $postParams = array(
            "learningplan_code" => $lpCode,
            "id_user"           => $userId
        );
        $res        = $this->call($url, $postParams);
        $oRes       = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf(
                    "  Error %d: %s. user ID=%d, Learning Plan CODE=%s, level=%s",
                    $oRes->error,
                    $oRes->message,
                    $userId,
                    $learningplanCode,
                    $level
                )
            );
            if (203 != $oRes->error) { // Only rollback if real error, not if user already enrolled.
                $this->log("  Setting rollback flag");
                $this->rollback      = true;
                $this->lpWithError[] = $lpCode;
            } else {
                $this->enrollments[count($this->enrollments) - 1]["already_enrolled"] = 1; // Set flag
            }
            return false;
        }
        if ($raw) {
            return $res;
        }
        return $oRes;
    }

    public function unenrollUserFromLearningplan($userId, $learningplanCode, $raw = false)
    {
        $this->log("Unenroll user from Learning Plan");
        if (empty($userId) || empty($learningplanCode)) {
            $this->log(sprintf("  Empty user ID (%s) or empty Learning Plan CODE (%d)", $userId, $learningplanCode));
            return false;
        }

        $url        = "/learningplan/unenroll";
        $postParams = array(
            "learningplan_code" => $learningplanCode,
            "id_user"           => $userId
        );
        $res        = $this->call($url, $postParams);
        $oRes       = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf(
                    "  Error %d: %s. user ID=%d, Learning Plan CODE=%s",
                    $oRes->error,
                    $oRes->message,
                    $userId,
                    $learningplanCode
                )
            );
            $this->rollback = true;
            return false;
        }
        if ($raw) {
            return $res;
        }
        return $oRes;
    }

    public function getSubscribedUsersForCourse(array $branches, $sortByCourse = false, $raw = false)
    {
        $url = "/user/getstats";
        $postParams = array(
            "all_courses" => 1,
            "id_org_list" => implode(",", $branches),
            "status" => "ab-initio"
        );
        if ($sortByCourse) {
            $postParams["group_by_course"] = 1;
        }
        $res = $this->call($url, $postParams);
        $oRes = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf(
                    "  Error %d: %s. id_org_list=%s",
                    $oRes->error,
                    $oRes->message,
                    implode(",", $branches)
                )
            );
            $this->rollback = true;
            return false;
        }
        if ($raw) {
            return $res;
        }
        return $oRes;
    }

    public function updateBranch($branchId, $translation, $newParent = null, $newCode = null, $raw = false)
    {
        $url = "/orgchart/updateNode";
        $postParams = array(
            "id_org" => $branchId,
            "translation[arabic]"             => $translation,
            "translation[bosnian]"            => $translation,
            "translation[bulgarian]"          => $translation,
            "translation[croatian]"           => $translation,
            "translation[czech]"              => $translation,
            "translation[danish]"             => $translation,
            "translation[dutch]"              => $translation,
            "translation[english]"            => $translation,
            "translation[farsi]"              => $translation,
            "translation[finnish]"            => $translation,
            "translation[french]"             => $translation,
            "translation[german]"             => $translation,
            "translation[greek]"              => $translation,
            "translation[hebrew]"             => $translation,
            "translation[hungarian]"          => $translation,
            "translation[indonesian]"         => $translation,
            "translation[italian]"            => $translation,
            "translation[japanese]"           => $translation,
            "translation[korean]"             => $translation,
            "translation[norwegian]"          => $translation,
            "translation[polish]"             => $translation,
            "translation[portuguese]"         => $translation,
            "translation[portuguese-br]"      => $translation,
            "translation[romanian]"           => $translation,
            "translation[russian]"            => $translation,
            "translation[spanish]"            => $translation,
            "translation[swedish]"            => $translation,
            "translation[turkish]"            => $translation,
            "translation[ukrainian]"          => $translation,
            "translation[simplified_chinese]" => $translation,
            "translation[thai]"               => $translation,
        );
        if (null !== $newCode) {
            $postParams["code"] = $newCode;
        }
        if (null !== $newParent) {
            $postParams["new_parent"] = $newParent;
        }
        $res = $this->call($url, $postParams);
        $oRes = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf(
                    "  Error %d: %s. id_org=%s, translation=%s, new_parent=%s",
                    $oRes->error,
                    $oRes->message,
                    implode($branchId, $translation, $newParent)
                )
            );
            $this->rollback = true;
            return false;
        }
        if ($raw) {
            return $res;
        }
        return $oRes;
    }

    public function getChildNodes($branchId, $raw = false)
    {
        $url = "/orgchart/getChildren";
        $postParams = array(
            "id_org" => $branchId
        );
        $res = $this->call($url, $postParams);
        $oRes = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf(
                    "  Error %d: %s. id_org=%s",
                    $oRes->error,
                    $oRes->message,
                    implode(",", $branchId)
                )
            );
            $this->rollback = true;
            return false;
        }
        if ($raw) {
            return $res;
        }
        return $oRes;
    }

    public function getChildNodesRecursive($branchId, $depth = 0)
    {
        $childNodes = $this->getChildNodes($branchId);
        $childNodes->depth = $depth;
        if (0 < count($childNodes->children)) {
            $depth++;
            foreach ($childNodes->children as &$node) {
                $node->children = $this->getChildNodesRecursive($node->id_org, $depth);
            }
        }
        return $childNodes;
    }

    public function moveNode($branchId, $destId, $raw = false)
    {
        $url = "/orgchart/moveNode";
        $postParams = array(
            "id_org" => $branchId,
            "dst_node_id" => $destId
        );
        $res = $this->call($url, $postParams);
        $oRes = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf(
                    "  Error %d: %s. id_org=%s, dst_node_id=%s",
                    $oRes->error,
                    $oRes->message,
                    $branchId,
                    $destId
                )
            );
            $this->rollback = true;
            return false;
        }
        if ($raw) {
            return $res;
        }
        return $oRes;
    }

    public function getCourses($branchId, $raw = false)
    {
        $url = "/course/listCourses";
        $postParams = array(
            "category" => $branchId
        );
        $res = $this->call($url, $postParams);
        $oRes = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf(
                    "  Error %d: %s. id_org=%s",
                    $oRes->error,
                    $oRes->message,
                    implode(",", $branchId)
                )
            );
            $this->rollback = true;
            return false;
        }
        if ($raw) {
            return $res;
        }
        return $oRes;
    }

    public function listUsers($raw = false)
    {
        $url = "/user/listUsers";
        $res = $this->call($url, array());
        $oRes = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf(
                    "  Error %d: %s.",
                    $oRes->error,
                    $oRes->message
                )
            );
            $this->rollback = true;
            return false;
        }
        if ($raw) {
            return $res;
        }
        return $oRes;
    }

    public function listUserCourses($user, $raw = false)
    {
        $url = "/user/userCourses";
        $postParams = array(
            "id_user" => $user
        );
        $res = $this->call($url, $postParams);
        $oRes = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf(
                    "  Error %d: %s.",
                    $oRes->error,
                    $oRes->message
                )
            );
            $this->rollback = true;
            return false;
        }
        if ($raw) {
            return $res;
        }
        return $oRes;
    }

    public function listUserIltCourses($user, $raw = false)
    {
        $url = "/user/userCourses";
        $postParams = array(
            "id_user" => $user,
            "classroom" => 1
        );
        $res = $this->call($url, $postParams);
        $oRes = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf(
                    "  Error %d: %s.",
                    $oRes->error,
                    $oRes->message
                )
            );
            $this->rollback = true;
            return false;
        }
        if ($raw) {
            return $res;
        }
        return $oRes;
    }

    public function listCourseSessions($course, $raw = false)
    {
        $url = "/iltsessions/list";
        $postParams = array(
            "id_course" => $course
        );
        $res = $this->call($url, $postParams);
        $oRes = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf(
                    "  Error %d: %s.",
                    $oRes->error,
                    $oRes->message
                )
            );
            $this->rollback = true;
            return false;
        }
        if ($raw) {
            return $res;
        }
        return $oRes;
    }

    public function getUserGroups($user, $raw = false)
    {
        $url = "/user/group_associations";
        $postParams = array(
            "id_user" => $user
        );
        $res = $this->call($url, $postParams);
        $oRes = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf(
                    "  Error %d: %s.",
                    $oRes->error,
                    $oRes->message
                )
            );
            $this->rollback = true;
            return false;
        }
        if ($raw) {
            return $res;
        }
        return $oRes;
    }

    public function getCourseMaterials($courseId, $raw = false)
    {
        $url = "/organization/listObjects";
        $postParams = array(
            "id_course" => $courseId
        );
        $res = $this->call($url, $postParams);
        $oRes = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf(
                    "  Error %d: %s.",
                    $oRes->error,
                    $oRes->message
                )
            );
            $this->rollback = true;
            return false;
        }
        if ($raw) {
            return $res;
        }
        return $oRes;
    }

    public function getUserResponsesForCourse($user, $courseMaterial, $raw = false)
    {
        $url = "/organization/getUserInteractions";
        $postParams = array(
            "id_user"      => $user,
            "id_scormitem" => $courseMaterial
        );
        $res = $this->call($url, $postParams);
        $oRes = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf(
                    "  Error %d: %s.",
                    $oRes->error,
                    $oRes->message
                )
            );
            $this->rollback = true;
            return false;
        }
        if ($raw) {
            return $res;
        }
        return $oRes;
    }

    public function getAdditionalUserFields($raw = false)
    {
        $url = "/user/fields";
        $postParams = array(
            "language" => "english"
        );
        $res = $this->call($url, $postParams);
        $oRes = json_decode($res);
        if (false == $oRes->success) {
            $this->log(
                sprintf(
                    "  Error %d: %s.",
                    $oRes->error,
                    $oRes->message
                )
            );
            $this->rollback = true;
            return false;
        }
        if ($raw) {
            return $res;
        }
        return $oRes;
    }

    /**
     * Perform API call
     *
     * @param string $url       The URL address of the API method to call
     * @param array $postParams An array of parameters to POST to the API
     *
     * @return bool|mixed The resulting JSON string, or FALSE on error
     */
    public function call($url, $postParams)
    {
        if ($this->useProductionConf) {
            $this->log("Using PRODUCTION");
            $host   = $this->conf["PRODUCTION"]["LMS_URL"];
            $key    = $this->conf["PRODUCTION"]["LMS_KEY"];
            $secret = $this->conf["PRODUCTION"]["LMS_SECRET"];
        } else {
            $this->log("Using SANDBOX");
            $host   = $this->conf["SANDBOX"]["LMS_URL"];
            $key    = $this->conf["SANDBOX"]["LMS_KEY"];
            $secret = $this->conf["SANDBOX"]["LMS_SECRET"];
        }
        $paramsList = "";
        foreach ($postParams as $paramName => $paramValue) {
            $paramsList .= sprintf("%s=%s, ", $paramName, $paramValue);
        }
        $this->log(sprintf("Calling %s with parameters %s", $host . $url, $paramsList));
        $sha1       = sha1(implode(",", $postParams) . "," . $secret);
        $code       = base64_encode($key . ":" . $sha1);
        $theHeaders = &$this->headers;

        $ch = curl_init($host . $url);
        curl_setopt($ch, CURLOPT_POST, count($postParams));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt(
            $ch,
            CURLOPT_HEADERFUNCTION,
            function ($curl, $header) use (&$theHeaders) {
                // echo "adding header $header";
                $length       = strlen($header);
                $theHeaders[] = $header;
                return $length;
            }
        );
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postParams));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-Authorization: Docebo " . $code));

        $json = curl_exec($ch);
        if (false === $json) {
            exit("Oops! An error occurred (" . curl_errno($ch) . "): " . curl_error($ch));
        }
        $http = explode(" ", $this->headers[0]);
        if ((int)$http[1] >= 300) {
            $this->log(sprintf("An error occurred with this request: (%s) %s", $http[1], $http[2]));
            return false;
        };
        $this->log("  HTTP Status: " . $http[1]);
        $this->log(sprintf("  Received data: %s", $json));
        return $json;
    }

    public function authenticate()
    {
        if ($this->useProductionConf) {
            $this->log("Using PRODUCTION");
            $host   = $this->conf["PRODUCTION"]["LMS_HOST"];
            $key    = $this->conf["PRODUCTION"]["LMS_KEY"];
            $secret = $this->conf["PRODUCTION"]["LMS_SECRET"];
        } else {
            $this->log("Using SANDBOX");
            $host   = $this->conf["SANDBOX"]["LMS_HOST"];
            $key    = $this->conf["SANDBOX"]["LMS_KEY"];
            $secret = $this->conf["SANDBOX"]["LMS_SECRET"];
        }

        $provider = new League\OAuth2\Client\Provider\GenericProvider(array(
            "clientId" => $key,
            "clientSecret" => $secret,
            "redirectUri" => "https://lmshub.yesnyou.com/lms/oauth",
            "urlAuthorize" => "https://$host/oauth2/authorize",
            "urlAccessToken" => "https://$host/oauth2/token"
        ));
        // If we don't have an authorization code then get one
        if (!isset($_GET['code'])) {

            // Fetch the authorization URL from the provider; this returns the
            // urlAuthorize option and generates and applies any necessary parameters
            // (e.g. state).
            $authorizationUrl = $provider->getAuthorizationUrl();

            // Get the state generated for you and store it to the session.
            $_SESSION['oauth2state'] = $provider->getState();

            // Redirect the user to the authorization URL.
            header('Location: ' . $authorizationUrl);
            exit;

        // Check given state against previously stored one to mitigate CSRF attack
        } elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

            unset($_SESSION['oauth2state']);
            exit('Invalid state');

        } else {

            try {

                // Try to get an access token using the authorization code grant.
                $accessToken = $provider->getAccessToken(
                    'authorization_code',
                    array(
                        'code' => $_GET['code']
                    )
                );

                // We have an access token, which we may use in authenticated
                // requests against the service provider's API.
                echo $accessToken->getToken() . "\n";
                echo $accessToken->getRefreshToken() . "\n";
                echo $accessToken->getExpires() . "\n";
                echo ($accessToken->hasExpired() ? 'expired' : 'not expired') . "\n";

                // Using the access token, we may look up details about the
                // resource owner.
                $resourceOwner = $provider->getResourceOwner($accessToken);

                var_export($resourceOwner->toArray());

                // The provider provides a way to get an authenticated API request for
                // the service, using the access token; it returns an object conforming
                // to Psr\Http\Message\RequestInterface.
                $request = $provider->getAuthenticatedRequest(
                    "GET",
                    $this->conf["SANDBOX"]["LMS_URL"] . "/course/listCourses",
                    $accessToken
                );
                exit(print_r($request));
            } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

                // Failed to get the access token or user details.
                exit($e->getMessage());

            }
        }
    }

    public function log($msg)
    {
        if ($this->useLogging) {
            file_put_contents(
                dirname(__DIR__) . DIRECTORY_SEPARATOR . self::LOG_FILE,
                sprintf("[%s] %s%s", date("Y-m-d H:i:s"), $msg, PHP_EOL),
                FILE_APPEND
            );
        }
    }
}
