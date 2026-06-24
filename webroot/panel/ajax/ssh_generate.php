<?php

require_once __DIR__ . "/../../../resources/autoload.php";

use phpseclib3\Crypt\EC;
use UnityWebPortal\lib\UnityHTTPD;

$private = EC::createKey('Ed25519');
$public = $private->getPublicKey();
$public_str = $public->toString('OpenSSH');
if (UnityHTTPD::getQueryParameter("type", false) == "ppk") {
    $private_str = $private->toString('PuTTY');
} else {
    $private_str = $private->toString('OpenSSH');
}
header('Content-Type: application/json; charset=utf-8');
echo _json_encode(["public" => $public_str, "private" => $private_str]);
