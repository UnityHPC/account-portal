<?php

namespace UnityWebPortal\lib;

use UnityWebPortal\lib\exceptions\HTTPRedirect;
use UnityWebPortal\lib\exceptions\HTTPBadRequest;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class NewAccountController extends UnitySlimController
{
    public function get(Request $request, Response $response): Response
    {
        global $USER, $SSO;
        if ($USER->exists()) {
            throw new HTTPRedirect("panel/account");
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
        global $USER, $SSO;
        switch (getPostData("form_type")) {
            case "register":
                $USER->init($SSO["firstname"], $SSO["lastname"], $SSO["mail"], $SSO["org"]);
                throw new HTTPRedirect("panel/account");
            default:
                throw new HTTPBadRequest("invalid form_type");
        }
    }
}
