#!/usr/bin/env php
<?php
include __DIR__ . "/init.php";

use UnityWebPortal\lib\UnitySSO;
use Garden\Cli\Cli;

$cli = new Cli();
$cli->description(
    "Spoof a user registration." .
        " Name and email can be imprecise and they will be updated when the real user logs in." .
        " EPPN must be the exact value from the home institution. This determines their username and cannot be changed later.",
)
    ->arg("first_name", "first name", required: true)
    ->arg("last_name", "last name", required: true)
    ->arg("eppn", "EPPN", required: true)
    ->arg("mail", "mail", required: true);
$args = $cli->parse($argv, true);

$first_name = $args->getArg("first_name");
$last_name = $args->getArg("last_name");
$eppn = $args->getArg("eppn");
$mail = $args->getArg("mail");

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

