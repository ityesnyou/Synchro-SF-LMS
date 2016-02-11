<?php
function addAccountInfo($c, &$jArray, $hierarchy) {
    if (isset($hierarchy["parent"])) {
        addAccountInfo($c, $jArray, $hierarchy["parent"]);
    }
    $jArray->parentAccount[] = json_decode($c->fetchAccountInfoFromId($hierarchy["id"]));
}
// header('Content-type: application/json; charset=utf-8');
// We expect the parameter contract. If this is missing that's an error.
if (1 > count($_GET) || empty($_GET["training"])) {
    $o = new stdClass;
    $o->errorCode = "MISSING PARAMETER";
    $o->errorMessage = "The necessary parameter to build the query is missing: training.";
    exit(json_encode(array($o)));
}
// Sanitize parameters
$training = filter_var($_GET["training"], FILTER_SANITIZE_STRING);
require_once __DIR__ . DIRECTORY_SEPARATOR . "SalesforceActions.php";
$c = new SalesforceActions(true, true);
$c->log("SalesforceActions class loaded");
$c->checkBasics();
$json = $c->fetchLearnersForTraining($training);
$out = $json;
if (is_array($jArray = json_decode($json))) {
    $out = $jArray[0];
    exit($out);
}
// Get account hierarchy
$h = array();
$hierarchy = $c->getParentAccountsRecursive($jArray->records[0]->Contract2__r->Account->Id, $jArray->records[0]->Contract2__r->Account->Name, $h);
addAccountInfo($c, $jArray, $hierarchy);
//if (null != $jArray->records[0]->Contract2__r->Account->Id) {
//    $jArray->parentAccount = json_decode($c->fetchAccountInfoFromId($jArray->records[0]->Contract2__r->Account->ParentId));
//}
$trainings = $c->getLearnerIdsPerTraining($jArray);
$trainingsLearners = $c->fetchLearnerInfoPerTraining($trainings);
include __DIR__ . DIRECTORY_SEPARATOR . 'confirm_sync.phtml';
