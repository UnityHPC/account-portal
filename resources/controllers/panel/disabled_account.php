<?php

namespace UnityWebPortal\lib;

use UnityWebPortal\lib\exceptions\HTTPRedirect;
use UnityWebPortal\lib\exceptions\HTTPBadRequest;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class DisabledAccountController extends UnitySlimController
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function get(Request $request, Response $response): Response
    {
        $USER = $this->container->get("USER");
        if (!$USER->getFlag(UserFlag::DISABLED)) {
            throw new HTTPRedirect("panel/account.php");
        }

        $view = Twig::fromRequest($request);
        return $view->render(
            $response,
            "panel/disabled_account.html.twig",
            $this->setupTwigContext([
                "fullname" => $USER->getFullname(),
                "mail" => $USER->getMail(),
                "account_expiration_policy_url" => CONFIG["site"]["account_expiration_policy_url"],
                "support_mail" => CONFIG["mail"]["support"],
            ]),
        );
    }

    public function post(Request $request, Response $response): Response
    {
        $USER = $this->container->get("USER");

        switch (getPostData("form_type")) {
            case "reEnable":
                $USER->reEnable();
                UnityHTTPD::messageSuccess("Account Re-Enabled", "");
                throw new HTTPRedirect("panel/account.php");
            default:
                throw new HTTPBadRequest("invalid form_type");
        }

        return $response;
    }
}
