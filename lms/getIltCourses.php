<?php
//header("Content-type: application/json; charset=UTF-8");
header("Content-type: text/plain; charset=UTF-8");
require_once __DIR__ . DIRECTORY_SEPARATOR . "LmsActions.php";

// Test header return
$c = new LmsActions(true, true);

$tmp = $c->getCourses(6);
//print_r($tmp);
$iltCourses = array_filter((array)$tmp, function($val) {
    return is_object($val) && $val->course_info->course_type != "elearning";
});
echo json_encode($iltCourses);
