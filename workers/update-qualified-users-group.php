#!/usr/bin/env php
<?php
include __DIR__ . "/init.php";
use Garden\Cli\Cli;

$cli = new Cli();
$cli->description("Add and remove users from the qualified user group.")->opt(
    "dry-run",
    "Print changes without actually changing anything.",
    false,
    "boolean",
);
$args = $cli->parse($argv, true);

$qualified_list_before = $LDAP->userFlagGroups["qualified"]->getMemberUIDs();
$pi_groups_attributes = $LDAP->getPIGroupsAttributes(["memberuid"], ["memberuid" => []]);
$users_with_at_least_one_group = array_merge(
    ...array_map(fn($x) => $x["memberuid"], $pi_groups_attributes),
);
$users_with_at_least_one_group = array_values(array_unique($users_with_at_least_one_group));
$native_users_attributes = $LDAP->getAllNativeUsersAttributes(["uid"]);
$native_users = array_map(fn($x) => $x["uid"][0], $native_users_attributes);
$non_native_users = array_diff($qualified_list_before, $native_users);
$non_native_users = array_values(array_unique($non_native_users));
$qualified_list_after = array_merge($users_with_at_least_one_group, $non_native_users);
sort($qualified_list_after);
$users_added = array_values(array_diff($qualified_list_after, $qualified_list_before));
$users_removed = array_values(array_diff($qualified_list_before, $qualified_list_after));
echo _json_encode(
    [
        "added" => $users_added,
        "removed" => $users_removed,
        "not removed (non-native)" => $non_native_users,
    ],
    JSON_PRETTY_PRINT,
) . "\n";

if ($args["dry-run"]) {
    echo "dry run, nothing doing.\n";
} else {
    if (count($users_added) + count($users_removed) > 0) {
        $LDAP->userFlagGroups["qualified"]->overwriteMemberUIDs($qualified_list_after);
    }
}

