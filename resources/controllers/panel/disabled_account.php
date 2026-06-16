<?php

namespace UnityWebPortal\lib;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

class DisabledAccountController extends UnitySlimController
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

    public function post(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
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
