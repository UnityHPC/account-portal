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
use UnityWebPortal\lib\UnityGithub;
use UnityWebPortal\lib\UserFlag;
use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UnityDeployment;
use Twig\TwigFunction;

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

    $OPERATOR = new UnityUser($SSO["user"], $LDAP, $SQL, $MAILER);
    $_SESSION["is_admin"] = $OPERATOR->getFlag(UserFlag::ADMIN);

    $_SESSION["OPERATOR"] = $SSO["user"];
    $_SESSION["OPERATOR_IP"] = $_SERVER["REMOTE_ADDR"];

    if (isset($_SESSION["viewUser"]) && $_SESSION["is_admin"]) {
        $USER = new UnityUser($_SESSION["viewUser"], $LDAP, $SQL, $MAILER);
    } else {
        $USER = $OPERATOR;
    }

    $_SESSION["user_exists"] = $USER->exists() && !$USER->getFlag(UserFlag::DISABLED);
    $_SESSION["is_pi"] = $USER->isPI();

    $days_idle = $SQL->convertLastLoginToDaysIdle($SQL->getUserLastLogin($USER->uid));
    $SQL->addLog("user_login", $OPERATOR->uid);
    $SQL->updateUserLastLogin($OPERATOR->uid);

    $USER->updateIsQualified(); // in case manual changes have been made to PI groups

    // $OPERATOR can be != $USER if an admin is logged in as another user
    if ($USER->exists() && $OPERATOR == $USER && !$USER->getFlag(UserFlag::DISABLED)) {
        // check if contact info sent by home institution has changed
        $USER->setFirstname($SSO["firstname"]);
        $USER->setLastname($SSO["lastname"]);
        $USER->setMail($SSO["mail"]);
        // remove idle-lock if exists
        if ($USER->getFlag(UserFlag::IDLELOCKED)) {
            $USER->setFlag(UserFlag::IDLELOCKED, false);
            UnityHTTPD::messageSuccess(
                "Account Unlocked",
                "Your account was previously locked due to inactivity.",
            );
        } elseif ($days_idle >= CONFIG["expiry"]["idlelock_warning_days"][0]) {
            UnityHTTPD::messageSuccess(
                "Inactivity Timer Reset",
                "Your account's scheduled locking is now cancelled.",
            );
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // another page should have already validated and we can't validate the same token twice
    // UnityHTTPD::validatePostCSRFToken();
    if (($_SESSION["is_admin"] ?? false) == true && ($_POST["form_type"] ?? null) == "clearView") {
        unset($_SESSION["viewUser"]);
        UnityHTTPD::redirect(getRelativeURL("admin/user-mgmt.php"));
    }
    // Webroot files need to handle their own POSTs before loading the header
    // so that they can do UnityHTTPD::badRequest before anything else has been printed.
    // They also must not redirect like standard PRG practice because this
    // header also needs to handle POST data. So this header does the PRG redirect
    // for all pages.
    unset($_POST); // unset ensures that header must not come before POST handling
    UnityHTTPD::redirect();
}

if (isset($SSO)) {
    if (!$USER->exists() && !str_ends_with($_SERVER["PHP_SELF"], "/panel/new_account.php")) {
        UnityHTTPD::redirect(getRelativeURL("panel/new_account.php"));
    }
    if (
        $USER->getFlag(UserFlag::DISABLED) &&
        !str_ends_with($_SERVER["PHP_SELF"], "/panel/disabled_account.php")
    ) {
        UnityHTTPD::redirect(getRelativeURL("panel/disabled_account.php"));
    }
    if ($USER->getFlag(UserFlag::LOCKED)) {
        UnityHTTPD::forbidden("locked", "Your account is locked.");
    }
}

$TWIG = new \Twig\Environment(
    new \Twig\Loader\FilesystemLoader(UnityDeployment::getTemplateDirs()),
    ["strict_variables" => true],
);
$TWIG->addFunction(new TwigFunction("getRelativeURL", getRelativeURL(...)));
$TWIG->addFunction(new TwigFunction("getRelativeHyperlink", getRelativeHyperlink(...)));
$TWIG->addFunction(new TwigFunction("formatHyperlink", formatHyperlink(...)));
$TWIG->addFunction(new TwigFunction("errorLog", UnityHTTPD::errorLog(...)));
$TWIG->addGlobal("CONFIG", CONFIG);
