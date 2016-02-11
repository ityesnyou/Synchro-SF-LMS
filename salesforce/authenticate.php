<?php
if (!array_key_exists("code", $_GET) || "" == $_GET["code"]) {
    echo "WARNING: Queries should be addressed to the url '/salesforce/query'";
    exit;
}
require_once __DIR__ . DIRECTORY_SEPARATOR . "SalesforceActions.php";
$c = new CommonStuff(true, true);
$authCode = $_GET["code"];
$query = isset($_GET["state"]) ? $_GET["state"] : "";

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
$c->query($query);
exit(0);
