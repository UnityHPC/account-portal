<?php

namespace UnityWebPortal\lib;

use UnityWebPortal\lib\exceptions\HTTPForbidden;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class AdminAjaxController extends UnitySlimController
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function get_group_members(Request $request, Response $response): Response
    {
        $USER = $this->container->get("USER");
        $LDAP = $this->container->get("LDAP");
        $SQL = $this->container->get("SQL");
        $MAILER = $this->container->get("MAILER");
        if (!$USER->getFlag(UserFlag::ADMIN)) {
            throw new HTTPForbidden("not an admin", user_msg_body: "You are not an admin.");
        }

        $view = Twig::fromRequest($request);
        $gid = UnityHTTPD::getQueryParameter("gid");
        $group = new UnityGroup($gid, $LDAP, $SQL, $MAILER);
        $requests = [];
        foreach ($group->getRequests() as [$user, $timestamp]) {
            $requests[] = [
                "uid" => $user->uid,
                "name" => $user->getFullName(),
                "mail" => $user->getMail(),
                "requested_on" => $timestamp,
            ];
        }

        $members = [];
        foreach ($group->getGroupMembersAttributes(["gecos", "mail"]) as $uid => $attributes) {
            if ($uid == $group->getOwner()->uid) {
                continue;
            }
            $members[] = [
                "uid" => $uid,
                "name" => $attributes["gecos"][0],
                "mail" => $attributes["mail"][0],
            ];
        }

        return $view->render($response, "admin/ajax/get_group_members.html.twig", [
            "group_gid" => $group->gid,
            "requests" => $requests,
            "members" => $members,
        ]);
    }
}
