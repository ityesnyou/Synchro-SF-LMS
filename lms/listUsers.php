<?php
//header("Content-type: application/json; charset=UTF-8");
header("Content-type: text/plain; charset=UTF-8");
require_once __DIR__ . DIRECTORY_SEPARATOR . "LmsActions.php";

// Test header return
$c = new LmsActions(true, true);

$users = $c->listUsers();
foreach ($users->users as $key => &$user) {
    if (!is_object($user)) continue;
    $isWaiting = false;
    $courses = $c->listUserIltCourses($user->id_user);
//    print_r($courses); exit("DOOOOOOOOOOOOOOOOOOOOOOOOOOONE");
    foreach ($courses as $course) {
        if (!is_object($course)) continue;
        if (isset($course->can_enter) && isset($course->can_enter->can) && false == $course->can_enter->can) {
            $isWaiting = true;
            $user->isWaiting = "yes";
            $user->courses = $courses;
        }
    }
//    if (!$isWaiting) {
//        unset($users[$key]);
//    }
};

echo json_encode($users);
