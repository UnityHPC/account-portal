<?php

/**
 * init.php - Initialization script that is run on every page of Unity
 */

use UnityWebPortal\lib\UnityLDAP;
use UnityWebPortal\lib\UnityMailer;
use UnityWebPortal\lib\UnitySQL;
use UnityWebPortal\lib\UnitySSO;
use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UnityWebhook;
use UnityWebPortal\lib\UnityGithub;
use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UserFlag;

if (CONFIG["site"]["enable_exception_handler"]) {
    set_exception_handler(["UnityWebPortal\lib\UnityHTTPD", "exceptionHandler"]);
}

if (CONFIG["site"]["enable_error_handler"]) {
    set_error_handler(["UnityWebPortal\lib\UnityHTTPD", "errorHandler"]);
}

if (isset($GLOBALS["ldapconn"])) {
    $LDAP = $GLOBALS["ldapconn"];
} else {
    $LDAP = new UnityLDAP();
    $GLOBALS["ldapconn"] = $LDAP;
}
$SQL = new UnitySQL();
$MAILER = new UnityMailer();
$WEBHOOK = new UnityWebhook();
$GITHUB = new UnityGithub();

session_start();
// https://stackoverflow.com/a/1270960/18696276
if (time() - ($_SESSION["LAST_ACTIVITY"] ?? 0) > CONFIG["site"]["session_cleanup_idle_seconds"]) {
    $_SESSION["csrf_tokens"] = [];
    $_SESSION["messages"] = [];
    session_write_close();
    session_start();
}
$_SESSION["LAST_ACTIVITY"] = time();

if (!array_key_exists("messages", $_SESSION)) {
    $_SESSION["messages"] = [];
}

if (!array_key_exists("csrf_tokens", $_SESSION)) {
    $_SESSION["csrf_tokens"] = [];
}

if (isset($_SERVER["REMOTE_USER"])) {
    // Check if SSO is enabled on this page
    $SSO = UnitySSO::getSSO();
    $_SESSION["SSO"] = $SSO;

    $OPERATOR = new UnityUser($SSO["user"], $LDAP, $SQL, $MAILER, $WEBHOOK);

    $_SESSION["is_admin"] = $OPERATOR->getFlag(UserFlag::ADMIN);

    $_SESSION["OPERATOR"] = $SSO["user"];
    $_SESSION["OPERATOR_IP"] = $_SERVER["REMOTE_ADDR"];

    if (isset($_SESSION["viewUser"]) && $_SESSION["is_admin"]) {
        $USER = new UnityUser($_SESSION["viewUser"], $LDAP, $SQL, $MAILER, $WEBHOOK);
    } else {
        $USER = $OPERATOR;
    }

    $_SESSION["user_exists"] = $USER->exists();
    $_SESSION["is_pi"] = $USER->isPI();

    $SQL->addLog("user_login", $OPERATOR->uid);

    if ($OPERATOR->getFlag(UserFlag::LOCKED)) {
        UnityHTTPD::die("Your account is locked.");
    }

    if ($OPERATOR->setFlag(UserFlag::IDLELOCKED, false)) {
        UnityHTTPD::messageSuccess(
            "Account Unlocked",
            "Your account was previously locked due to inactivity.",
        );
    }
}

$LOC_HEADER = __DIR__ . "/templates/header.php";
$LOC_FOOTER = __DIR__ . "/templates/footer.php";
