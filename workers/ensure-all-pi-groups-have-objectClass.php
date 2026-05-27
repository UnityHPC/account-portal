#!/usr/bin/env php
<?php
include __DIR__ . "/init.php";

use Garden\Cli\Cli;
use UnityWebPortal\lib\UnityLDAP;

$cli = new Cli();
$cli->description("Ensure that all PI groups have the 'piGroup' objectClass.")->opt(
    "dry-run",
    "Print changes without actually changing anything.",
    false,
    "boolean",
);
$args = $cli->parse($argv, true);

foreach (
    $LDAP->getPIGroupsAttributes(["cn", "objectclass"], filter: UnityLDAP::INCLUDE_DISABLED)
    as $attributes
) {
    if (!in_array("piGroup", $attributes["objectclass"])) {
        $gid = $attributes["cn"][0];
        echo "adding value to objectClass of group '$gid'\n";
        if (!$args["dry-run"]) {
            $entry = $LDAP->getPIGroupEntry($gid);
            $entry->appendAttribute("objectclass", "piGroup");
        }
    }
}

