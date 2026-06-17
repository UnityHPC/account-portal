<?php

namespace UnityWebPortal\lib;

use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class PanelModalController extends UnitySlimController
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function new_key(Request $request, Response $response)
    {
        $view = Twig::fromRequest($request);
        return $view->render($response, "panel/modal/new_key.html.twig");
    }

    public function new_pi(Request $request, Response $response)
    {
        $owner_uids = $GLOBALS["ldapconn"]->getPIGroupOwnerUIDs();
        $owner_attributes = $GLOBALS["ldapconn"]->getUsersAttributes(
            $owner_uids,
            ["uid", "gecos", "mail"],
            default_values: ["gecos" => [""], "mail" => [""]],
        );
        $pi_group_gid_to_owner_gecos_and_mail = [];
        foreach ($owner_attributes as $attributes) {
            $gid = UnityGroup::ownerUID2GID($attributes["uid"][0]);
            $pi_group_gid_to_owner_gecos_and_mail[$gid] = [
                $attributes["gecos"][0],
                $attributes["mail"][0],
            ];
        }
        $_SESSION["pi_group_gid_to_owner_gecos_and_mail"] = $pi_group_gid_to_owner_gecos_and_mail;

        $view = Twig::fromRequest($request);
        return $view->render($response, "panel/modal/new_pi.html.twig");
    }
}
