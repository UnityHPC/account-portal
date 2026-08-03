#!/usr/bin/env php
<?php
$_SERVER["HTTP_HOST"] = "worker"; // see deployment/overrides/worker
require_once __DIR__ . "/../resources/autoload.php";
use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UserFlag;

if (sizeof($argv) != 2 or in_array($argv, ["-h", "--help"])) {
    die("Usage: get-user-flags.php uid\n");
}
$uid = $argv[1];
$user = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
if (!$user->exists()) {
    echo "no such user: '$uid'\n";
    exit(1);
}
$max_flag_strlen = max(array_map(fn($x) => strlen($x->value), UserFlag::cases()));
foreach (UserFlag::cases() as $flag) {
    if ($user->getFlag($flag)) {
        echo str_pad($flag->value, $max_flag_strlen, " ", STR_PAD_RIGHT) . " 1\n";
    } else {
        echo str_pad($flag->value, $max_flag_strlen, " ", STR_PAD_RIGHT) . " 0\n";
    }
}

