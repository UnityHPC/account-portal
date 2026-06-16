<?php

namespace UnityWebPortal\lib;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

class NewAccountController
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function get(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $USER = $this->container->get("USER");
        $SSO = $this->container->get("SSO");
        if ($USER->exists()) {
            UnityHTTPD::redirect(getRelativeURL("panel/account.php"));
        }

        $view = Twig::fromRequest($request);
        return $view->render($response, "panel/new_account.html.twig", [
            "messages" => UnityHTTPD::getMessages(),
            "viewUser" => $_SESSION["viewUser"] ?? null,
            "user_exists" => $_SESSION["user_exists"] ?? false,
            "is_pi" => $_SESSION["is_pi"] ?? false,
            "is_admin" => $_SESSION["is_admin"] ?? false,
            "firstname" => $SSO["firstname"],
            "lastname" => $SSO["lastname"],
            "mail" => $SSO["mail"],
            "username" => $SSO["user"],
        ]);
    }

    public function post(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $USER = $this->container->get("USER");
        $SSO = $this->container->get("SSO");

        if (UnityHTTPD::getPostData("form_type") === "register") {
            UnityHTTPD::validatePostCSRFToken();
            $USER->init($SSO["firstname"], $SSO["lastname"], $SSO["mail"], $SSO["org"]);
            UnityHTTPD::redirect(getRelativeURL("panel/account.php"));
        }

        return $response;
    }
}
