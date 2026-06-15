#!/usr/bin/env php
<?php
include __DIR__ . "/init.php";

use UnityWebPortal\lib\UnitySSO;

if (stream_isatty(STDIN)) {
    _die("ERROR: use spoof-user-registration.bash instead\n", 1);
}

$first_name = trim(readline());
$last_name = trim(readline());
$eppn = strtolower(trim(readline()));
$mail = strtolower(trim(readline()));

$uid = UnitySSO::eppnToUID($eppn);
if ($LDAP->getUserEntry($uid)->exists()) {
    _die(sprintf("ERROR: user '%s' exists! You can login as them in user-mgmt.php", $uid), 1);
}
$org = UnitySSO::eppnToOrg($eppn);

$_SERVER = array_merge($_SERVER, [
    "givenName" => $first_name,
    "sn" => $last_name,
    "REMOTE_USER" => $eppn,
    "mail" => $mail,
]);
session_write_close();
include __DIR__ . "/../resources/init.php";

$expected_sso = [
    "firstname" => $first_name,
    "lastname" => $last_name,
    "name" => "$first_name $last_name",
    "mail" => $mail,
    "user" => $uid,
    "org" => $org,
];
if ($SSO != $expected_sso) {
    _die(
        sprintf(
            "ERROR: unexpected spoof!\n expected: %s\nfound: %s\n",
            _json_encode($expected_sso),
            _json_encode($SSO),
        ),
        1,
    );
}

$USER->init($first_name, $last_name, $mail, $org);

echo "user '$uid' registered successfully.\n";
