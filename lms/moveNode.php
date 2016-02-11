<?php
//header("Content-type: application/json; charset=UTF-8");
header("Content-type: text/plain; charset=UTF-8");
require_once __DIR__ . DIRECTORY_SEPARATOR . "LmsActions.php";

// Test header return
$c = new LmsActions(true, false);

echo $c->moveNode(1759, 1757, true);
