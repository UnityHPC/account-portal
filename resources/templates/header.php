<?php

use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UserFlag;

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
echo $TWIG->render("header.html.twig", [
    "user_exists" => $_SESSION["user_exists"] ?? false,
    "is_pi" => $_SESSION["is_pi"] ?? false,
    "is_admin" => $_SESSION["is_admin"] ?? false
]);
