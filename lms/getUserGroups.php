<?php
header("Content-type: application/json; charset=UTF-8");
require_once __DIR__ . DIRECTORY_SEPARATOR . "LmsActions.php";

// Test header return
$c = new LmsActions(true, true);

echo $c->getUserGroups(12397, true);
