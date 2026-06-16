<?php

require_once __DIR__ . "/../../../resources/autoload.php";

use UnityWebPortal\lib\UnityHTTPD;

[$is_valid, $explanation] = testValidSSHKey(UnityHTTPD::getPostData("key"));
header('Content-Type: application/json; charset=utf-8');
echo _json_encode(["is_valid" => $is_valid, "explanation" => $explanation]);
UnityHTTPD::die();
