<?php
header("Content-type: application/json; charset=UTF-8");
require_once __DIR__ . DIRECTORY_SEPARATOR . "LmsActions.php";
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . "salesforce/SalesforceActions.php";

// Test header return
$lms = new LmsActions(true, true);
$sf  = new SalesforceActions(true, true);

//if (!file_exists(__DIR__ . "/nodeList.php")) {
    $nodeList = $lms->getChildNodesRecursive(12);
//    file_put_contents(__DIR__ . "/nodeList.php", json_encode($nodeList));
//} else {
//    $nodeList = json_decode(file_get_contents(__DIR__ . "/nodeList.php"));
//}
foreach ($nodeList->children as $parentNode) {
    printf("Processing %4d - %s\n", $parentNode->id_org, $parentNode->translation->english);
    foreach ($parentNode->children->children as $node) {
        printf("%7d - %-50s... ", $node->id_org, str_replace("ï", "i", $node->translation->english));
        // Check if the node has a code
        if ("" != $node->code) {
            echo " has code ", $node->code, "\n";
            continue;
        }
        echo " fetching code... ";
        $tmp = $sf->fetchAccountIdFromName(strtolower(str_replace("ï", "i", $node->translation->english)));
        $code = json_decode($tmp);
        if (1 < $code->totalSize) {
            echo "more than 1 found, must disambiguate\n";
            continue;
        }
        if (1 > $code->totalSize) {
            echo "not found\n";
            continue;
        }
        echo "attributing ", $code->records[0]->Id, "... ";
            if (false == $lms->updateBranch($node->id_org, $node->translation->english, null, $code->records[0]->Id)) {
                echo "error";
            } else {
                echo "ok";
            }
        echo "\n";
//        exit; // try just one
    }
}
