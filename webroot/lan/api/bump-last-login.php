<?php

require_once __DIR__ . "/../../../resources/autoload.php";

use UnityWebPortal\lib\UnityHTTPD;

UnityHTTPD::validateAPIKey();
$uid = UnityHTTPD::getQueryParameter("uid");
// please remove this ugly hack https://github.com/UnityHPC/account-portal/pull/593
$SQL->addLog("user_login", $uid);
