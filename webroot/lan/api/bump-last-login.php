<?php

require_once __DIR__ . "/../../../resources/autoload.php";

use UnityWebPortal\lib\UnityHTTPD;

UnityHTTPD::assertRequestMethod("POST");
UnityHTTPD::validateAPIKey();
$uid = UnityHTTPD::getQueryParameter("uid");
$SQL->updateUserLastLogin($uid);
UnityHTTPD::die();
