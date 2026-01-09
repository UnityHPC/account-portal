<?php

use UnityWebPortal\lib\UnityHTTPD;

header('Content-type: text/plain');

require_once __DIR__ . "/../../../resources/autoload.php";

$CHAR_WRAP = digits2int(UnityHTTPD::getOptionalQueryParameter("line_wrap") ?? "80");
$content_name = UnityHTTPD::getQueryParameter("content_name");
echo $SQL->getPage($content_name)["content"];
