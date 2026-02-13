#!/usr/bin/env php
<?php
$_SERVER["HTTP_HOST"] = "course-creator"; // see deployment/overrides/course-creator
include __DIR__ . "/init.php";
use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UnityOrg;
use UnityWebPortal\lib\UserFlag;

function cn2org($cn)
{
    $matches = [];
    _preg_match("/.*_([^_]+_[^_]+)$/", $cn, $matches);
    ensure(count($matches) == 2, "failed to extract org from cn: '$cn'");
    return $matches[1];
}

// if array is length 1 then replace it with its one element
function flatten_attributes(array $attributes): array
{
    return array_map(fn($v) => count($v) === 1 ? $v[0] : $v, $attributes);
}

$givenName = trim(readline("Enter the course ID (example: CS123): "));
$sn = trim(readline("Enter the year and semester of the course (example: Fall 2025): "));
$cn = strtolower(
    trim(readline("Please enter the cn to be used for the course (example: cs123_umass_edu): ")),
);
$manager_uid = trim(
    readline("Enter the UID of the group manager (example: simonleary_umass_edu): "),
);
$org_gid = cn2org($cn);

$manager = new UnityUser($manager_uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
if (!$manager->exists()) {
    _die("no such user: '$manager_uid'", 1);
}

$course_user = new UnityUser($cn, $LDAP, $SQL, $MAILER, $WEBHOOK);
if ($course_user->exists()) {
    $course_user_dn = $LDAP->getUserEntry($cn)->getDN();
    _die("course user already exists: '$course_user_dn'", 1);
}
$org = new UnityOrg($org_gid, $LDAP);
if (!$org->exists()) {
    print "WARNING: creating new org '$org_gid'...\n";
}
$mail = ""; // temporary empty mail
$course_user->init($givenName, $sn, $mail, $org_gid);
$course_user->setFlag(UserFlag::IMMORTAL, true, false, true);

$course_pi_group = $course_user->getPIGroup();
$course_user->setMail($course_pi_group->addPlusAddressToMail($manager->getMail()));

if ($course_pi_group->exists()) {
    $course_pi_group_dn = $LDAP->getPIGroupEntry($course_pi_group->gid)->getDN();
    _die("course PI group already exists: '$course_pi_group_dn'", 1);
}
$course_pi_group->requestGroup(false, false);
$course_pi_group->approveGroup();

$course_pi_group->newUserRequest($manager, false);
$course_pi_group->approveUser($manager);
$course_pi_group->addManagerUID($manager_uid);

print "LDAP entries created:\n";
print _json_encode(
    [
        "course user" => flatten_attributes($LDAP->getUserEntry($cn)->getAttributes()),
        "course user group" => flatten_attributes($LDAP->getUserGroupEntry($cn)->getAttributes()),
        "course PI group" => flatten_attributes(
            $LDAP->getPIGroupEntry($course_pi_group->gid)->getAttributes(),
        ),
    ],
    JSON_PRETTY_PRINT,
);

