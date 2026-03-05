<?php

require_once __DIR__ . "/../../../resources/autoload.php";

use UnityWebPortal\lib\UnityHTTPD;

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    UnityHTTPD::badRequest("invalid request method {$_SERVER['REQUEST_METHOD']}");
}
UnityHTTPD::validateAPIKey();
$uid = UnityHTTPD::getQueryParameter("uid");
$SQL->updateUserLastLogin($uid);
UnityHTTPD::die();
