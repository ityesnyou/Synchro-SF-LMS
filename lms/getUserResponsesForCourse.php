<?php
header("Content-type: application/json; charset=UTF-8");
require_once __DIR__ . DIRECTORY_SEPARATOR . "LmsActions.php";

// Test header return
$c = new LmsActions(true, true);

$courses = $c->getCourseMaterials(6352);

$res = array();
foreach ($courses->objects as $scoItem) {
    $res[] = $c->getUserResponsesForCourse(12306, $scoItem->id_scormitem);
}
echo json_encode($res);
