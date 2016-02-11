<?php
require_once "./../../SalesforceActions.php";
$c = new SalesforceActions(true, true);
$authCode = $_GET["code"];

$url = "https://login.salesforce.com/services/oauth2/token";
$postParams = array(
    "code"          => $authCode,
    "grant_type"    => "authorization_code",
    "client_id"     => $c->conf["SF_CLIENT_ID"],
    "client_secret" => $c->conf["SF_CLIENT_SECRET"],
    "redirect_uri"  => $c->conf["APP_REDIRECT_URI"],
    "format"        => "json"
);
$json = $c->getAccessToken($url, $postParams);
$c->updateLocalTokens($json);
if (!$c->writeToConf($json)) {
    exit("snort. An unexpected error occurred.");
}
header('Location: https://remote.yesnyou.com/salesforce/query/index.php');
exit(0);
