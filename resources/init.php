<?php

/**
 * init.php - Initialization script that is run on every page of Unity
 */

declare(strict_types=1);

use UnityWebPortal\lib\UnityLDAP;
use UnityWebPortal\lib\UnityMailer;
use UnityWebPortal\lib\UnitySQL;
use UnityWebPortal\lib\UnitySSO;
use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UnityWebhook;
use UnityWebPortal\lib\UnityGithub;
use UnityWebPortal\lib\UserFlag;
use UnityWebPortal\lib\UnityHTTPD;

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
    if (array_key_exists("pi_group_gid_to_owner_gecos_and_mail", $_SESSION)) {
        unset($_SESSION["pi_group_gid_to_owner_gecos_and_mail"]);
    }
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

// $_SERVER["REMOTE_USER"] is only defined for pages where httpd requies authentication
// the home page does not require authentication,
// so if the user goes to a secure page and then back to home, they've effectively logged out
// it would be bad UX to show the user that they are effectively logging in and out,
// so we use session cache to remember if they have logged in recently and then pretend
// they're logged in even if they aren't
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

    $_SESSION["user_exists"] = $USER->exists() && !$USER->getFlag(UserFlag::DISABLED);
    $_SESSION["is_pi"] = $USER->isPI();

    $last_login = $SQL->getUserLastLogin($USER->uid);
    $SQL->addLog("user_login", $OPERATOR->uid);

    $USER->updateIsQualified(); // in case manual changes have been made to PI groups

    if ($USER->getFlag(UserFlag::LOCKED)) {
        UnityHTTPD::forbidden("locked", "Your account is locked.");
    }

    if ($OPERATOR == $USER && $USER->getFlag(UserFlag::IDLELOCKED)) {
        $USER->setFlag(UserFlag::IDLELOCKED, false);
        UnityHTTPD::messageSuccess(
            "Account Unlocked",
            "Your account was previously locked due to inactivity.",
        );
    }

    if ($last_login !== null) {
        $first_idlelock_warning_day = CONFIG["expiry"]["idlelock_warning_days"][0];
        $idlelock_averted_day = CONFIG["expiry"]["idlelock_day"];
        $days = fn($seconds) => $seconds * 60 * 60 * 24;
        $first_idlelock_warning_timestamp = $last_login + $days($first_idlelock_warning_day);
        $idlelock_averted_timestamp = $last_login + $days($idlelock_averted_day);
        // if this current login is after the 1st warning and before the idlelock
        if ($first_idlelock_warning_timestamp <= time() && time() < $idlelock_averted_timestamp) {
            $idlelock_averted_date = date("Y/m/d", $idlelock_averted_timestamp);
            UnityHTTPD::messageSuccess(
                "Inactivity Timer Reset",
                "Your account's scheduled locking on $idlelock_averted_date is now cancelled.",
            );
        }
    }
}
