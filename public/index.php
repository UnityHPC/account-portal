<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Twig\TwigFunction;
use UnityWebPortal\lib\UnitySlimErrorHandler;
use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UnityDeployment;
use UnityWebPortal\lib\UnityLDAP;
use UnityWebPortal\lib\UnityMailer;
use UnityWebPortal\lib\UnitySlimMiddleware;
use UnityWebPortal\lib\UnitySQL;
use UnityWebPortal\lib\UnityGithub;
use UnityWebPortal\lib\AccountController;
use UnityWebPortal\lib\GroupsController;
use UnityWebPortal\lib\NewAccountController;
use UnityWebPortal\lib\DisabledAccountController;
use UnityWebPortal\lib\PiController;
use UnityWebPortal\lib\LanApiController;
use UnityWebPortal\lib\AdminPiMgmtController;
use UnityWebPortal\lib\AdminUserMgmtController;
use UnityWebPortal\lib\PanelModalController;
use UnityWebPortal\lib\PanelAjaxController;
use UnityWebPortal\lib\AdminAjaxController;
use UnityWebPortal\lib\CSRFToken;

require_once __DIR__ . "/../resources/autoload.php";
require_once __DIR__ . "/../resources/config.php";

if (isset($GLOBALS["ldapconn"])) {
    $LDAP = $GLOBALS["ldapconn"];
} else {
    $LDAP = new UnityLDAP();
    $GLOBALS["ldapconn"] = $LDAP;
}
$SQL = new UnitySQL();
$MAILER = new UnityMailer();
$GITHUB = new UnityGithub();

$app = AppFactory::create();

$middleware = new UnitySlimMiddleware();
$app->add($middleware);

$error_middleware = $app->addErrorMiddleware(
    displayErrorDetails: CONFIG["site"]["debug"],
    logErrors: true,
    logErrorDetails: true,
);
$error_middleware->setDefaultErrorHandler(
    new UnitySlimErrorHandler($app->getCallableResolver(), $app->getResponseFactory()),
);

$twig = Twig::create(UnityDeployment::getTemplateDirs(), [
    "cache" => false,
    "strict_variables" => true,
    "autoescape" => "name",
]);
$twig_env = $twig->getEnvironment();
$twig_env->addFunction(new TwigFunction("getRelativeURL", getRelativeURL(...)));
$twig_env->addFunction(new TwigFunction("getRelativeHyperlink", getRelativeHyperlink(...)));
$twig_env->addFunction(new TwigFunction("formatHyperlink", formatHyperlink(...)));
$twig_env->addFunction(new TwigFunction("errorLog", _error_log(...)));
$twig_env->addFunction(new TwigFunction("generateCSRFToken", CSRFToken::generate(...)));
$twig_env->addFunction(
    new TwigFunction("formatCSRFTokenHiddenFormInput", formatCSRFTokenHiddenFormInput(...)),
);
$twig_env->addFunction(new TwigFunction("base64_encode", base64_encode(...)));
$twig_env->addFunction(new TwigFunction("sound_it_out", sound_it_out(...)));
$twig_env->addGlobal("CONFIG", CONFIG);
$app->add(TwigMiddleware::create($app, $twig));

// TODO make HomeController
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

$routes_get = [
    "/panel/groups" => GroupsController::class . ":get",
    "/panel/pi" => PiController::class . ":get",
    "/lan/api/expiry" => LanApiController::class . ":expiry",
    "/admin/pi-mgmt" => AdminPiMgmtController::class . ":get",
    "/admin/user-mgmt" => AdminUserMgmtController::class . ":get",
    "/panel/modal/new_key" => PanelModalController::class . ":new_key",
    "/panel/modal/new_pi" => PanelModalController::class . ":new_pi",
    "/panel/account" => AccountController::class . ":get",
    "/panel/new_account" => NewAccountController::class . ":get",
    "/panel/disabled_account" => DisabledAccountController::class . ":get",
    "/panel/ajax/pi_search" => PanelAjaxController::class . ":pi_search",
    "/admin/ajax/get_group_members" => AdminAjaxController::class . ":get_group_members",
];

$routes_post = [
    "/panel/account" => AccountController::class . ":post",
    "/panel/new_account" => NewAccountController::class . ":post",
    "/panel/disabled_account" => DisabledAccountController::class . ":post",
    "/panel/groups" => GroupsController::class . ":post",
    "/panel/pi" => PiController::class . ":post",
    "/lan/api/bump-last-login" => LanApiController::class . ":bumpLastLogin",
    "/admin/pi-mgmt" => AdminPiMgmtController::class . ":post",
    "/admin/user-mgmt" => AdminUserMgmtController::class . ":post",
    "/panel/ajax/ssh_validate" => PanelAjaxController::class . ":ssh_validate",
    "/panel/ajax/ssh_generate" => PanelAjaxController::class . ":ssh_generate",
    "/panel/ajax/delete_message" => PanelAjaxController::class . ":delete_message",
];

foreach ($routes_get as $src => $dest) {
    $app->get($src, $dest);
}
foreach ($routes_post as $src => $dest) {
    $app->post($src, $dest);
}

$redirects = [
    "/panel/index.php" => "/",
];
foreach (array_keys($routes_get) as $route) {
    $redirects["$route.php"] = $route;
}
foreach (array_keys($routes_post) as $route) {
    $redirects["$route.php"] = $route;
}

foreach ($redirects as $src => $dest) {
    $app->any(
        $src,
        fn($request, $response) => $response->withHeader("Location", $dest)->withStatus(302),
    );
}

$app->run();
