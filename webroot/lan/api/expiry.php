<?php

require_once __DIR__ . "/../../../resources/autoload.php";

use UnityWebPortal\lib\UnityHTTPD;

$uid = UnityHTTPD::getQueryParameter("uid");
$last_login = $SQL->getUserLastLogin($uid);
if ($last_login === null) {
    UnityHTTPD::badRequest("no last login timestamp known for user '$uid'");
}
$idlelock_timestamp = $last_login + CONFIG["expiry"]["idlelock_day"] * 60 * 60 * 24;
$disable_timestamp = $last_login + CONFIG["expiry"]["disable_day"] * 60 * 60 * 24;
$idlelock_date = date("Y/m/d", $idlelock_timestamp);
$disable_date = date("Y/m/d", $disable_timestamp);
echo _json_encode(["uid" => $uid, "idlelock_date" => $idlelock_timestamp, "disable_date" => $disable_timestamp]);
