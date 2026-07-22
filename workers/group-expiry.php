#!/usr/bin/env php
<?php
include __DIR__ . "/init.php";
use Garden\Cli\Cli;
use UnityWebPortal\lib\UnityGroup;

$cli = new Cli();
$cli->description("Disable any PI groups which are past their expiration date.")
    ->opt("dry-run", "Print actions without actually doing anything.", type: "boolean")
    ->opt("timestamp", "Use this unix timestamp instead of right now", type: "int");
$args = $cli->parse($argv, true);
$dry_run = $args->getOpt("dry-run", false);
$now = $args->getOpt("timestamp", time());

foreach ($SQL->getAllPIGroupExpirationDates() as $record) {
    $expiration_date = $record["expiration_date"];
    if ($expiration_date <= $now) {
        $group = new UnityGroup($record["gid"], $LDAP, $SQL, $MAILER);
        if (!$group->getIsDisabled()) {
            printf(
                "group '%s' expired on %s, disabling group and removing members %s\n",
                $group->gid,
                date("Y/m/d", $expiration_date),
                _json_encode($group->getMemberUIDs()),
            );
            if (!$dry_run) {
                $group->disable();
            }
        }
    }
}

if ($dry_run) {
    echo "[DRY RUN]\n";
}

