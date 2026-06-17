<?php

namespace UnityWebPortal\lib;

use UnityWebPortal\lib\exceptions\HTTPRedirect;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class AdminUserMgmtController extends UnitySlimController
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function get(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $USER = $this->container->get("USER");

        if (!$USER->getFlag(UserFlag::ADMIN)) {
            UnityHTTPD::forbidden("not an admin", "You are not an admin.");
        }

        $LDAP = $this->container->get("LDAP");

        $flags_to_display = array_filter(UserFlag::cases(), fn($x) => $x !== UserFlag::DISABLED);

        $UID2PIGIDs = $LDAP->getUID2PIGIDs();
        $user_attributes = $LDAP->getAllNativeUsersAttributes(
            ["uid", "gecos", "o", "mail"],
            default_values: [
                "gecos" => ["(not found)"],
                "o" => ["(not found)"],
                "mail" => ["(not found)"],
            ],
        );
        $users_with_flags = [];
        foreach (UserFlag::cases() as $flag) {
            $users_with_flags[$flag->value] = $LDAP->userFlagGroups[$flag->value]->getMemberUIDs();
        }

        usort($user_attributes, fn($a, $b) => strcmp($a["uid"][0], $b["uid"][0]));

        $users = [];
        foreach ($user_attributes as $attributes) {
            $uid = $attributes["uid"][0];
            if (in_array($uid, $users_with_flags[UserFlag::DISABLED->value])) {
                continue;
            }

            $user_flags = [];
            foreach ($flags_to_display as $flag) {
                $user_flags[$flag->value] = in_array($uid, $users_with_flags[$flag->value]);
            }

            $users[] = [
                "uid" => $uid,
                "name" => $attributes["gecos"][0],
                "org" => $attributes["o"][0],
                "mail" => $attributes["mail"][0],
                "groups" => $UID2PIGIDs[$uid] ?? [],
                "flags" => $user_flags,
            ];
        }

        return $view->render(
            $response,
            "admin/user-mgmt.html.twig",
            $this->setupTwigContext([
                "users" => $users,
                "flags_to_display" => array_map(fn($f) => $f->value, $flags_to_display),
            ]),
        );
    }

    public function post(Request $request, Response $response): Response
    {
        UnityHTTPD::validatePostCSRFToken();

        $USER = $this->container->get("USER");
        if (!$USER->getFlag(UserFlag::ADMIN)) {
            UnityHTTPD::forbidden("not an admin", "You are not an admin.");
        }

        switch ($_POST["form_type"] ?? null) {
            case "viewAsUser":
                $_SESSION["viewUser"] = $_POST["uid"];
                throw new HTTPRedirect("panel/account.php");
                break;
        }

        return $response;
    }
}
