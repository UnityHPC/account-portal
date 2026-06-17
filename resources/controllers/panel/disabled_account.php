<?php

namespace UnityWebPortal\lib;

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
            UnityHTTPD::redirect(getRelativeURL("panel/account.php"));
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

        if (UnityHTTPD::getPostData("form_type") === "reEnable") {
            UnityHTTPD::validatePostCSRFToken();
            $USER->reEnable();
            UnityHTTPD::messageSuccess("Account Re-Enabled", "");
            UnityHTTPD::redirect(getRelativeURL("panel/account.php"));
        }

        return $response;
    }
}
