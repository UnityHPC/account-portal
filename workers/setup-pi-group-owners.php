#!/usr/bin/env php
<?php
include __DIR__ . "/init.php";

use UnityWebPortal\lib\UnityGroup;
use UnityWebPortal\lib\UnityLDAP;
use Garden\Cli\Cli;

$cli = new Cli();
$cli->description("Set the ownerUid attribute for all PI groups.")->opt(
    "dry-run",
    "Print changes without actually changing anything.",
    required: false,
    type: "boolean",
);
$args = $cli->parse($argv, exit: true);
$dry_run = $args->getOpt("dry-run", false);

foreach ($LDAP->getPIGroupsAttributes(["cn"], filter: UnityLDAP::INCLUDE_DISABLED) as $attributes) {
    $gid = $attributes["cn"][0];
    $entry = $LDAP->getPIGroupEntry($gid);
    $owner_uid = UnityGroup::GID2OwnerUID($gid);
    $before = $entry->getAttribute("ownerUid")[0] ?? null;
    if ($before === null) {
        echo "set the ownerUid of group '$gid'\n";
    } elseif ($before !== $owner_uid) {
        echo "WARNING: changed ownerUid of group '$gid' from '$before' to '$owner_uid'\n";
    }
    if (!$dry_run) {
        $entry->setAttribute("ownerUid", $owner_uid);
    }
}

if ($dry_run) {
    echo "[DRY RUN]\n";
}

