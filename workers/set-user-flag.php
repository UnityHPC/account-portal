#!/usr/bin/env php
<?php
$_SERVER["HTTP_HOST"] = "worker"; // see deployment/overrides/worker
require_once __DIR__ . "/../resources/autoload.php";
use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UserFlag;

$flag_choices = UserFlag::cases();
if (sizeof($argv) != 4 or in_array($argv, ["-h", "--help"])) {
    die(
        sprintf(
            implode("\n", [
                "Usage: set-user-flag.php uid flag flag_value",
                "choices for flag: %s",
                "choices for value: [\"0\", \"1\"]",
                "",
            ]),
            jsonEncode($flag_choices),
        )
    );
}
[$_, $uid, $flag_str, $value_str] = $argv;
if (!in_array($flag, $flag_choices)) {
    echo sprintf("invalid flag: '%s'. valid choices: %s\n", $flag, jsonEncode($flag_choices));
    exit(1);
}
$flag = UserFlag::from($flag_str);
switch ($value_str) {
    case "0":
        $value = false;
        break;
    case "1":
        $value = true;
        break;
    default:
        print "invalid value: '$value_str'. valid values are 0 or 1\n";
        exit(1);
}
$user = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
if (!$user->exists()) {
    echo "no such user: '$uid'";
    exit(1);
}
$user->setFlag($flag, $value);

