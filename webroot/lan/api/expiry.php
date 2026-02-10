<?php

require_once __DIR__ . "/../../../resources/autoload.php";

use UnityWebPortal\lib\UnityHTTPD;

$uid = UnityHTTPD::getQueryParameter("uid");
$last_login = $SQL->getUserLastLogin($uid);
if ($last_login === null) {
    UnityHTTPD::badRequest("no last login timestamp known for user '$uid'");
}
$idlelock_day = $last_login + CONFIG["expiry"]["idlelock_day"] * 60 * 60 * 24;
$disable_day = $last_login + CONFIG["expiry"]["disable_day"] * 60 * 60 * 24;
$idlelock_day_str = date("Y/m/d", $idlelock_day);
$disable_day_str = date("Y/m/d", $disable_day);
echo _json_encode(["uid" => $uid, "idlelock_day" => $idlelock_day_str, "disable_day" => $disable_day_str]);
