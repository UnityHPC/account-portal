<?php

namespace UnityWebPortal\lib;

use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class PiController extends UnitySlimController
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

        if (($gid = $request->getQueryParams()["gid"] ?? null) !== null) {
            $group = new UnityGroup(
                $gid,
                $this->container->get("LDAP"),
                $this->container->get("SQL"),
                $this->container->get("MAILER"),
            );
            $user_is_owner = false;
            if (!$group->exists()) {
                UnityHTTPD::badRequest("no such group: '$gid'", "This group does not exist.");
            }
            if (
                !in_array($USER->uid, $group->getManagerUIDs()) &&
                $USER->uid != $group->getOwner()->uid
            ) {
                UnityHTTPD::forbidden(
                    "not a manager of group '$gid'",
                    "You cannot manage this group.",
                );
            }
        } else {
            $group = $USER->getPIGroup();
            $user_is_owner = true;
            if (!$group->exists()) {
                UnityHTTPD::badRequest("not a PI", "You are not a PI.");
            }
        }

        if ($group->getIsDisabled()) {
            $group_id = $gid ?? $group->gid;
            UnityHTTPD::forbidden("group '$group_id' is disabled", "This group is disabled.");
        }

        $requests = $group->getRequests();
        $assocs = $group->getGroupMembers();

        $pending_requests = [];
        foreach ($requests as [$user, $timestamp]) {
            $pending_requests[] = [
                "uid" => $user->uid,
                "name" => $user->getFullName(),
                "email" => $user->getMail(),
                "requested_on" => date("jS F, Y", strtotime($timestamp)),
            ];
        }

        $users = [];
        $owner_uid = $group->getOwner()->uid;
        foreach ($assocs as $assoc) {
            $users[] = [
                "uid" => $assoc->uid,
                "name" => $assoc->getFirstname() . " " . $assoc->getLastname(),
                "mail" => $assoc->getMail(),
                "is_owner" => $assoc->uid === $owner_uid,
            ];
        }

        return $view->render(
            $response,
            "panel/pi.html.twig",
            $this->setupTwigContext([
                "group_gid" => $group->gid,
                "user_is_owner" => $user_is_owner,
                "pending_requests" => $pending_requests,
                "users" => $users,
                "users_count" => count($assocs),
                "group_disabled" => $group->getIsDisabled(),
                "account_page_url" => getRelativeURL("panel/groups.php"),
            ]),
        );
    }

    public function post(Request $request, Response $response): Response
    {
        UnityHTTPD::validatePostCSRFToken();

        $USER = $this->container->get("USER");

        if (($gid = $request->getQueryParams()["gid"] ?? null) !== null) {
            $group = new UnityGroup(
                $gid,
                $this->container->get("LDAP"),
                $this->container->get("SQL"),
                $this->container->get("MAILER"),
            );
            $user_is_owner = false;
            if (!$group->exists()) {
                UnityHTTPD::badRequest("no such group: '$gid'", "This group does not exist.");
            }
            if (
                !in_array($USER->uid, $group->getManagerUIDs()) &&
                $USER->uid != $group->getOwner()->uid
            ) {
                UnityHTTPD::forbidden(
                    "not a manager of group '$gid'",
                    "You cannot manage this group.",
                );
            }
        } else {
            $group = $USER->getPIGroup();
            $user_is_owner = true;
            if (!$group->exists()) {
                UnityHTTPD::badRequest("not a PI", "You are not a PI.");
            }
        }

        if ($group->getIsDisabled()) {
            $group_id = $gid ?? $group->gid;
            UnityHTTPD::forbidden("group '$group_id' is disabled", "This group is disabled.");
        }

        $getUserFromPost = function () {
            $LDAP = $this->container->get("LDAP");
            $SQL = $this->container->get("SQL");
            $MAILER = $this->container->get("MAILER");
            return new UnityUser(UnityHTTPD::getPostData("uid"), $LDAP, $SQL, $MAILER);
        };

        switch ($_POST["form_type"] ?? null) {
            case "userReq":
                $form_user = $getUserFromPost();
                if ($_POST["action"] === "Approve") {
                    $group->approveUser($form_user);
                    UnityHTTPD::messageSuccess("User Approved", "");
                    UnityHTTPD::redirect();
                } elseif ($_POST["action"] === "Deny") {
                    $group->denyUser($form_user);
                    UnityHTTPD::messageSuccess("User Denied", "");
                    UnityHTTPD::redirect();
                } else {
                    UnityHTTPD::badRequest(
                        sprintf("unrecognized action: '%s'", $_POST["action"]),
                        "",
                    );
                }
                break; /** @phpstan-ignore deadCode.unreachable */
            case "remUser":
                $form_user = $getUserFromPost();
                $group->removeUser($form_user, UnityGroupUserRemovedReason::RemovedByOwner);
                UnityHTTPD::messageSuccess("User Removed", "");
                if ($USER->uid === $form_user->uid) {
                    UnityHTTPD::redirect("/panel/groups.php");
                } else {
                    UnityHTTPD::redirect();
                }
                break; /** @phpstan-ignore deadCode.unreachable */
            case "disable":
                if (!$user_is_owner) {
                    UnityHTTPD::forbidden(
                        "Manager cannot disable",
                        "Only the group owner can disable",
                    );
                }
                if (count($group->getMemberUIDs()) > 1) {
                    UnityHTTPD::messageError("Cannot Disable PI Group", "Group still has members");
                    UnityHTTPD::redirect();
                }
                if ($group->getIsDisabled()) {
                    UnityHTTPD::messageError(
                        "Cannot Disable PI Group",
                        "Group is already disabled",
                    );
                    UnityHTTPD::redirect();
                }
                $group->disable();
                UnityHTTPD::messageSuccess("Group Disabled", "");
                UnityHTTPD::redirect(getRelativeURL("panel/account.php"));
                break; /** @phpstan-ignore deadCode.unreachable */
        }

        return $response;
    }
}
