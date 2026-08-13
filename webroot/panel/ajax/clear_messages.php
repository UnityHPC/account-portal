<?php

require_once __DIR__ . "/../../../resources/autoload.php";

use UnityWebPortal\lib\UnityHTTPD;

UnityHTTPD::assertRequestMethod("POST");
UnityHTTPD::clearMessages();
UnityHTTPD::die();
