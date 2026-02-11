#!/usr/bin/env php
<?php
include __DIR__ . "/init.php";
use Garden\Cli\Cli;
use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UnityGroup;

$cli = new Cli();
$cli->description("Remove users from a group")
    ->arg("gid", "Group ID (example: pi_user1_org1_test)", true)
    ->arg("users-file-path", "Path to file containing one UID per line", true);
$args = $cli->parse($argv, true);

$gid = $args->getArg("gid");
$filename = $args->getArg("users-file-path");
$group = new UnityGroup($gid, $LDAP, $SQL, $MAILER, $WEBHOOK);
if (!$group->exists()) {
    _die("No such group '$gid'\n", 1);
}
$handle = _fopen($filename, "r");
try {
    while (($line = fgets($handle)) !== false) {
        $uid = trim($line);
        $user = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
        if (!$group->memberUIDExists($user->uid)) {
            print "Skipping '$uid' who doesn't appear to be in '$gid'\n";
            continue;
        }
        $group->removeUser($user);
    }
} finally {
    _fclose($handle);
}

