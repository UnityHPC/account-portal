#!/usr/bin/env php
<?php
$_SERVER["HTTP_HOST"] = "course-creator"; // see deployment/overrides/course-creator
include __DIR__ . "/init.php";
use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UnityGroup;

$course_id = trim(readline("Enter the course ID (example: CS123): "));
$course_notes = trim(readline("Enter the year and semester of the course (example: Fall 2025): "));
$gid = strtolower(
    trim(readline("Please enter the cn to be used for the course (example: cs123_umass_edu): ")),
);
$owner_uid = trim(
    readline(
        "Enter the UID of the Unity admin responsible for the group (example: simonleary_umass_edu): ",
    ),
);

$owner = new UnityUser($owner_uid, $LDAP, $SQL, $MAILER);
if (!$owner->exists()) {
    _die("no such user: '$owner_uid'", 1);
}

$course_pi_group = new UnityGroup($gid, $LDAP, $SQL, $MAILER);
if ($course_pi_group->exists()) {
    $course_pi_group_dn = $LDAP->getPIGroupEntry($course_pi_group->gid)->getDN();
    _die("course PI group already exists: '$course_pi_group_dn'", 1);
}

$course_pi_group->requestGroup($owner_uid, false, false);
$course_pi_group->approveGroup($owner_uid);

$entry = $LDAP->getPIGroupEntry($course_pi_group->gid);
$entry->setAttribute("description", "$course_id $course_notes");

print "PI group created:\n";
print _json_encode($entry->getAttributes(), JSON_PRETTY_PRINT);

