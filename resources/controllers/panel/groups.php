<?php

namespace UnityWebPortal\lib;

use UnityWebPortal\lib\exceptions\HTTPBadRequest;
use UnityWebPortal\lib\exceptions\HTTPRedirect;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class GroupsController extends UnitySlimController
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
        $LDAP = $this->container->get("LDAP");
        $SQL = $this->container->get("SQL");
        $MAILER = $this->container->get("MAILER");

        $pi_group_attributes = $LDAP->getPIGroupAttributesWithMemberUID(
            $USER->uid,
            ["cn", "memberuid", "manageruid"],
            default_values: ["memberuid" => [], "manageruid" => []],
        );

        $pi_group_gids = [];
        $pi_groups = [];
        $pi_group_members = [];
        $pi_group_managers = [];
        foreach ($pi_group_attributes as $attributes) {
            $gid = $attributes["cn"][0];
            $pi_group_gids[] = $gid;
            $pi_group_members[$gid] = $attributes["memberuid"];
            $pi_group_managers[$gid] = $attributes["manageruid"];

            $group = new UnityGroup($gid, $LDAP, $SQL, $MAILER);
            $owner = $group->getOwner();
            if ($USER->uid === $owner->uid) {
                continue;
            }

            $pi_groups[] = [
                "gid" => $gid,
                "owner_name" => $owner->getFirstname() . " " . $owner->getLastname(),
                "owner_mail" => $owner->getMail(),
                "members" => $attributes["memberuid"],
                "can_manage" => in_array($USER->uid, $attributes["manageruid"], true),
            ];
        }

        $pending_requests = [];
        foreach ($SQL->getRequestsByUser($USER->uid) as $request_row) {
            if ($request_row["request_for"] === "admin") {
                continue;
            }

            $requested_account = new UnityGroup($request_row["request_for"], $LDAP, $SQL, $MAILER);
            $requested_owner = $requested_account->getOwner();
            $pending_requests[] = [
                "gid" => $requested_account->gid,
                "owner_name" =>
                    $requested_owner->getFirstname() . " " . $requested_owner->getLastname(),
                "owner_mail" => $requested_owner->getMail(),
                "requested_on" => date("jS F, Y", strtotime($request_row["timestamp"])),
            ];
        }

        $owner_uids = $LDAP->getPIGroupOwnerUIDs();
        $owner_attributes = $LDAP->getUsersAttributes(
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

        return $view->render(
            $response,
            "panel/groups.html.twig",
            $this->setupTwigContext([
                "pi_group_gids" => $pi_group_gids,
                "pi_groups" => $pi_groups,
                "pi_group_members" => $pi_group_members,
                "pi_group_managers" => $pi_group_managers,
                "pending_requests" => $pending_requests,
            ]),
        );
    }

    public function post(Request $request, Response $response): Response
    {
        UnityHTTPD::validatePostCSRFToken();

        $USER = $this->container->get("USER");
        $LDAP = $this->container->get("LDAP");
        $SQL = $this->container->get("SQL");
        $MAILER = $this->container->get("MAILER");

        $getPIGroupFromPost = function () use ($LDAP, $SQL, $MAILER) {
            $gid = getPostData("pi");
            $pi_group = new UnityGroup($gid, $LDAP, $SQL, $MAILER);
            if (!$pi_group->exists()) {
                UnityHTTPD::messageError("This PI Doesn't Exist", $gid);
                throw new HTTPRedirect();
            }
            return $pi_group;
        };

        if (!isset($_POST["form_type"])) {
            return $response;
        }

        switch ($_POST["form_type"]) {
            case "addPIform":
                $pi_account = $getPIGroupFromPost();
                if ($_POST["tos"] != "agree") {
                    throw new HTTPBadRequest("user did not agree to terms of service");
                }
                if ($pi_account->requestExists($USER)) {
                    UnityHTTPD::messageError(
                        "Invalid Group Membership Request",
                        "You've already requested this",
                    );
                    throw new HTTPRedirect();
                }
                if ($pi_account->memberUIDExists($USER->uid)) {
                    UnityHTTPD::messageError(
                        "Invalid Group Membership Request",
                        "You're already in this PI group",
                    );
                    throw new HTTPRedirect();
                }
                $pi_account->newUserRequest($USER);
                throw new HTTPRedirect();
                break; /** @phpstan-ignore deadCode.unreachable */
            case "removePIForm":
                $pi_account = $getPIGroupFromPost();
                $pi_account->removeUser($USER, UnityGroupUserRemovedReason::RemovedSelf);
                throw new HTTPRedirect();
                break; /** @phpstan-ignore deadCode.unreachable */
            case "cancelPIForm":
                $pi_account = $getPIGroupFromPost();
                $pi_account->cancelGroupJoinRequest($USER);
                throw new HTTPRedirect();
                break; /** @phpstan-ignore deadCode.unreachable */
        }

        return $response;
    }
}
