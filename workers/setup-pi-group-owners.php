#!/usr/bin/env php
<?php
include __DIR__ . "/init.php";

use UnityWebPortal\lib\UnityGroup;
use UnityWebPortal\lib\UnityLDAP;

foreach ($LDAP->getPIGroupsAttributes(["cn"], filter: UnityLDAP::INCLUDE_DISABLED) as $attributes) {
    $gid = $attributes["cn"][0];
    $entry = $LDAP->getPIGroupEntry($gid);
    $entry->setAttribute("ownerUid", UnityGroup::GID2OwnerUID($gid));
}

