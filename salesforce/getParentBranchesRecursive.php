<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . "SalesforceActions.php";
$sf = new SalesforceActions(true, true);
$in = array();
$hierarchy = $sf->getParentAccountsRecursive("0012000000Y4W04", "KENZO PARFUMS", $in); // 0012000000abS6Y SEPIC, child account of AIR LIQUIDE

function displayHierarchy($hierarchy) {
    if (isset($hierarchy["parent"])) {
        displayHierarchy($hierarchy["parent"]);
        echo " &raquo; ";
    }
    echo $hierarchy["name"];
}
displayHierarchy($hierarchy);
