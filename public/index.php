<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Twig\TwigFunction;
use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UnityDeployment;
use UnityWebPortal\lib\UnityLDAP;
use UnityWebPortal\lib\UnityMailer;
use UnityWebPortal\lib\UnitySQL;
use UnityWebPortal\lib\UnitySSO;
use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UnityGithub;
use UnityWebPortal\lib\UserFlag;

require_once __DIR__ . "/../resources/autoload.php";
require_once __DIR__ . "/../resources/config.php";

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
            $USER->setFlag(UserFlag::IDLELOCKED, false, doSendMailAdmin: false);
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

// TODO middleware
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

$app = AppFactory::create();
$twig = Twig::create(UnityDeployment::getTemplateDirs(), [
    "cache" => false,
    "strict_variables" => true,
]);
$twig_env = $twig->getEnvironment();
$twig_env->addFunction(new TwigFunction("getRelativeURL", getRelativeURL(...)));
$twig_env->addFunction(new TwigFunction("getRelativeHyperlink", getRelativeHyperlink(...)));
$twig_env->addFunction(new TwigFunction("formatHyperlink", formatHyperlink(...)));
$twig_env->addFunction(new TwigFunction("errorLog", UnityHTTPD::errorLog(...)));
$twig_env->addFunction(
    new TwigFunction("getCSRFTokenHiddenFormInput", UnityHTTPD::getCSRFTokenHiddenFormInput(...)),
);
$twig_env->addFunction(new TwigFunction("base64_encode", base64_encode(...)));
$twig_env->addGlobal("CONFIG", CONFIG);
$app->add(TwigMiddleware::create($app, $twig));

$render = function (Response $response, string $scriptPath): Response {
    ob_start();
    include $scriptPath;
    $output = _ob_get_clean();
    $response->getBody()->write($output);
    return $response;
};

$app->any("/", function (Request $_request, Response $response): Response {
    $view = Twig::fromRequest($_request);
    return $view->render($response, "home.html.twig", [
        "messages" => UnityHTTPD::getMessages(),
        "viewUser" => $_SESSION["viewUser"] ?? null,
        "user_exists" => $_SESSION["user_exists"] ?? false,
        "is_pi" => $_SESSION["is_pi"] ?? false,
        "is_admin" => $_SESSION["is_admin"] ?? false,
    ]);
});

$legacyRoutes = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__ . "/../webroot", FilesystemIterator::SKIP_DOTS),
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }
    $absolute = $fileInfo->getPathname();
    if (pathinfo($absolute, PATHINFO_EXTENSION) !== "php") {
        continue;
    }
    if (realpath($absolute) === __FILE__) {
        continue;
    }
    $relative = str_replace(__DIR__ . "/../webroot" . DIRECTORY_SEPARATOR, "", $absolute);
    $relative = str_replace(DIRECTORY_SEPARATOR, "/", $relative);
    $withExtension = "/" . ltrim($relative, "/");
    $legacyRoutes[$withExtension] = $absolute;
    $withoutExtension = _preg_replace('/\.php$/', "", $withExtension);
    if (is_string($withoutExtension) && $withoutExtension !== $withExtension) {
        $legacyRoutes[$withoutExtension] = $absolute;
    }
}

ksort($legacyRoutes);

foreach ($legacyRoutes as $route => $scriptPath) {
    $app->any($route, function (
        Request $_request,
        Response $response
    ) use (
        $render,
        $scriptPath,
    ): Response {
        return $render($response, $scriptPath);
    });
}

$app->map(["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"], "/{routes:.+}", function (
    Request $_request,
    Response $response,
): Response {
    $response->getBody()->write("Not Found");
    return $response->withStatus(404);
});

$app->run();
