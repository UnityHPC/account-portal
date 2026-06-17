<?php

namespace UnityWebPortal\lib;

use UnityWebPortal\lib\exceptions\HTTPForbidden;
use UnityWebPortal\lib\exceptions\HTTPRedirect;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class AdminPiMgmtController extends UnitySlimController
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
            throw new HTTPForbidden("not an admin", user_msg_body: "You are not an admin.");
        }

        $LDAP = $this->container->get("LDAP");
        $SQL = $this->container->get("SQL");

        $requests = [];
        foreach ($SQL->getRequests(UnitySQL::REQUEST_BECOME_PI) as $request_row) {
            $uid = $request_row["uid"];
            $request_user = new UnityUser($uid, $LDAP, $SQL, $this->container->get("MAILER"));
            $requests[] = [
                "uid" => $uid,
                "name" => $request_user->getFullname(),
                "mail" => $request_user->getMail(),
                "requested_on" => $request_row["timestamp"],
            ];
        }

        $pi_groups = [];
        $owner_uids = $LDAP->getPIGroupOwnerUIDs();
        $owner_attributes = $LDAP->getUsersAttributes(
            $owner_uids,
            ["uid", "gecos", "mail"],
            default_values: ["gecos" => ["(not found)"], "mail" => ["(not found)"]],
        );
        usort($owner_attributes, fn($a, $b) => strcmp($a["uid"][0], $b["uid"][0]));
        foreach ($owner_attributes as $attributes) {
            $gid = UnityGroup::OwnerUID2GID($attributes["uid"][0]);
            $pi_groups[] = [
                "gid" => $gid,
                "name" => $attributes["gecos"][0],
                "mail" => $attributes["mail"][0],
            ];
        }

        return $view->render(
            $response,
            "admin/pi-mgmt.html.twig",
            $this->setupTwigContext([
                "requests" => $requests,
                "pi_groups" => $pi_groups,
            ]),
        );
    }

    public function post(Request $request, Response $response): Response
    {
        UnityHTTPD::validatePostCSRFToken();

        $USER = $this->container->get("USER");
        if (!$USER->getFlag(UserFlag::ADMIN)) {
            throw new HTTPForbidden("not an admin", user_msg_body: "You are not an admin.");
        }

        $LDAP = $this->container->get("LDAP");
        $SQL = $this->container->get("SQL");
        $MAILER = $this->container->get("MAILER");

        $getUserFromPost = function () use ($LDAP, $SQL, $MAILER) {
            return new UnityUser(getPostData("uid"), $LDAP, $SQL, $MAILER);
        };

        switch ($_POST["form_type"] ?? null) {
            case "req":
                $form_user = $getUserFromPost();
                if ($_POST["action"] === "Approve") {
                    $group = $form_user->getPIGroup();
                    $group->approveGroup();
                } elseif ($_POST["action"] === "Deny") {
                    $group = $form_user->getPIGroup();
                    $group->denyGroup();
                }
                break;
            case "reqChild":
                $form_user = $getUserFromPost();
                $parent_group = new UnityGroup($_POST["pi"], $LDAP, $SQL, $MAILER);
                if ($_POST["action"] === "Approve") {
                    $parent_group->approveUser($form_user);
                } elseif ($_POST["action"] === "Deny") {
                    $parent_group->denyUser($form_user);
                }
                break;
            case "remUserChild":
                $form_user = $getUserFromPost();
                $parent = new UnityGroup($_POST["pi"], $LDAP, $SQL, $MAILER);
                $parent->removeUser($form_user, UnityGroupUserRemovedReason::RemovedByAdmin);
                break;
            case "disable":
                $group = new UnityGroup(getPostData("pi"), $LDAP, $SQL, $MAILER);
                if ($group->getIsDisabled()) {
                    UnityHTTPD::messageError(
                        "Cannot Disable PI Group",
                        "Group is already disabled",
                    );
                    throw new HTTPRedirect();
                }
                $group->disable();
                UnityHTTPD::messageSuccess("Group Disabled", $group->gid);
                break;
        }
        throw new HTTPRedirect();
        return $response;
    }
}
