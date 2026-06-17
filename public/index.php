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

$app->get("/panel/account.php", AccountController::class . ":get");
$app->post("/panel/account.php", AccountController::class . ":post");
$app->get("/panel/new_account.php", NewAccountController::class . ":get");
$app->post("/panel/new_account.php", NewAccountController::class . ":post");
$app->get("/panel/disabled_account.php", DisabledAccountController::class . ":get");
$app->post("/panel/disabled_account.php", DisabledAccountController::class . ":post");
$app->get("/panel/groups.php", GroupsController::class . ":get");
$app->post("/panel/groups.php", GroupsController::class . ":post");
$app->get("/panel/pi.php", PiController::class . ":get");
$app->post("/panel/pi.php", PiController::class . ":post");
$app->get("/lan/api/expiry.php", LanApiController::class . ":expiry");
$app->post("/lan/api/bump-last-login.php", LanApiController::class . ":bumpLastLogin");
$app->get("/admin/pi-mgmt.php", AdminPiMgmtController::class . ":get");
$app->post("/admin/pi-mgmt.php", AdminPiMgmtController::class . ":post");
$app->get("/admin/user-mgmt.php", AdminUserMgmtController::class . ":get");
$app->post("/admin/user-mgmt.php", AdminUserMgmtController::class . ":post");
$app->get("/panel/modal/new_key.php", PanelModalController::class . ":new_key");
$app->get("/panel/modal/new_pi.php", PanelModalController::class . ":new_pi");
$app->post("/panel/ajax/ssh_validate.php", PanelAjaxController::class . ":ssh_validate");
$app->post("/panel/ajax/ssh_generate.php", PanelAjaxController::class . ":ssh_generate");
$app->get("/panel/ajax/pi_search.php", PanelAjaxController::class . ":pi_search");
$app->post("/panel/ajax/delete_message.php", PanelAjaxController::class . ":delete_message");
$app->get("/admin/ajax/get_group_members.php", AdminAjaxController::class . ":get_group_members");

$redirects = [
    "/panel/index.php" => "/",
];
foreach ($redirects as $src => $dest) {
    $app->any(
        $src,
        fn($request, $response) => $response->withHeader("Location", $dest)->withStatus(302),
    );
}

$app->run();
