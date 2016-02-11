<?php
require_once "../../lms/LmsActions.php";

$lms = new LmsActions(true);
$lms->authenticate();
