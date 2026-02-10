#!/usr/bin/env php
<?php
include __DIR__ . "/init.php";

use Garden\Cli\Cli;

$cli = new Cli();
$cli->description("idlelock all users with a last login timestamp before a given date")
    ->opt("dry-run", "Print changes without actually changing anything.", false, "boolean")
    ->opt("date", "YYYY/MM/DD", true);
$args = $cli->parse($argv, true);

$idlelocked_users = $LDAP->userFlagGroups["idlelocked"]->getMemberUIDs();
$threshold = strtotime($args["date"]);

$changed = false;
foreach ($SQL->getAllUserLastLogins() as $last_login) {
    $uid = $last_login["operator"];
    $timestamp = strtotime($last_login["last_login"]);
    if ($timestamp < $threshold) {
        echo "idlelocking user '$uid'\n";
        $changed = true;
        array_push($idlelocked_users, $uid);
    }
}

if ($changed) {
    if ($args["dry-run"]) {
        echo "[DRY RUN]\n";
    } else {
        $LDAP->userFlagGroups["idlelocked"]->overwriteMemberUIDs($idlelocked_users);
    }
}

