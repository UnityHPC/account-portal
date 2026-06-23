<?php

declare(strict_types=1);

use UnityWebPortal\lib\UnityLDAP;
use UnityWebPortal\lib\UnityMailer;
use UnityWebPortal\lib\UnitySQL;
use UnityWebPortal\lib\UnityGithub;

require_once __DIR__ . "/../resources/autoload.php";
require_once __DIR__ . "/../resources/config.php";
require_once __DIR__ . "/../resources/framework.php";

if (isset($GLOBALS["ldapconn"])) {
    $LDAP = $GLOBALS["ldapconn"];
} else {
    $LDAP = new UnityLDAP();
    $GLOBALS["ldapconn"] = $LDAP;
}
$SQL = new UnitySQL();
$MAILER = new UnityMailer();
$GITHUB = new UnityGithub();

$app = get_app();
$app->run();
