<?php

namespace UnityWebPortal\lib;

use UnityWebPortal\lib\exceptions\HTTPRedirect;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class NewAccountController extends UnitySlimController
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function get(Request $request, Response $response): Response
    {
        $USER = $this->container->get("USER");
        $SSO = $this->container->get("SSO");
        if ($USER->exists()) {
            throw new HTTPRedirect("panel/account.php");
        }
        $view = Twig::fromRequest($request);
        return $view->render(
            $response,
            "panel/new_account.html.twig",
            $this->setupTwigContext([
                "is_admin" => $_SESSION["is_admin"] ?? false,
                "firstname" => $SSO["firstname"],
                "lastname" => $SSO["lastname"],
                "mail" => $SSO["mail"],
                "username" => $SSO["user"],
            ]),
        );
    }

    public function post(Request $request, Response $response): Response
    {
        $USER = $this->container->get("USER");
        $SSO = $this->container->get("SSO");

        if (UnityHTTPD::getPostData("form_type") === "register") {
            UnityHTTPD::validatePostCSRFToken();
            $USER->init($SSO["firstname"], $SSO["lastname"], $SSO["mail"], $SSO["org"]);
            throw new HTTPRedirect("panel/account.php");
        }

        return $response;
    }
}
