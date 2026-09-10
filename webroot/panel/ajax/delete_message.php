<?php

require_once __DIR__ . "/../../../resources/autoload.php";

use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UnityHTTPDMessageLevel;
use UnityWebPortal\lib\exceptions\UnityHTTPDMessageNotFoundException;

$level_str = _base64_decode(UnityHTTPD::getPostData("level"));
$level = UnityHTTPDMessageLevel::from($level_str);
$title = _base64_decode(UnityHTTPD::getPostData("title"));
$body = _base64_decode(UnityHTTPD::getPostData("body"));
try {
    UnityHTTPD::deleteMessage($level, $title, $body);
} catch (UnityHTTPDMessageNotFoundException $e) {
    UnityHTTPD::errorLog("warning", "deleted nonexistent message", error: $e);
}
UnityHTTPD::die();
