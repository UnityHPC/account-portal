<?php

namespace UnityWebPortal\lib;

use Slim\Exception\HttpNotFoundException;
use UnityWebPortal\lib\exceptions\HTTPRedirect;
use UnityWebPortal\lib\exceptions\HTTPError;
use UnityWebPortal\lib\exceptions\HTTPForbidden;
use UnityWebPortal\lib\exceptions\HTTPInternalServerError;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Handlers\ErrorHandler;

class UnitySlimController
{
    /**
     * @param mixed[] $base
     * @return mixed[]
     */
    public function setupTwigContext(array $base = []): array
    {
        global $_SESSION;
        return array_merge($base, [
            "messages" => UnityHTTPD::getMessages(),
            "viewUser" => $_SESSION["viewUser"] ?? null,
            "user_exists" => $_SESSION["user_exists"] ?? false,
            "is_pi" => $_SESSION["is_pi"] ?? false,
            "is_admin" => $_SESSION["is_admin"] ?? false,
        ]);
    }
}

class UnitySlimErrorHandler extends ErrorHandler
{
    private function respondRedirect(?string $relative_path = null): Response
    {
        $response = $this->responseFactory->createResponse();
        if ($relative_path === "" || $relative_path === null) {
            $relative_path = $_SERVER["REQUEST_URI"];
        }
        // TODO check $_SERVER["REDIRECT_STATUS"]?
        $dest = getRelativeURL($relative_path);
        $msg =
            "If you're reading this message, then your browser has failed to redirect you " .
            "to the proper destination. click <a href='$dest'>here</a> to continue.";
        $response->getBody()->write($msg);
        return $response->withStatus(302)->withHeader("Location", $dest);
    }

    public function respond(): Response
    {
        $e = $this->exception;
        $response = $this->responseFactory->createResponse();
        $body = $response->getBody();
        if ($e instanceof HTTPRedirect) {
            return $this->respondRedirect($e->getMessage());
        }
        // use Slim\Exception\HttpException as SlimHTTPException;
        // use Slim\Error\Renderers\HtmlErrorRenderer;
        // if ($e instanceof SlimHTTPException) {
        //     $builtin_renderer = new HtmlErrorRenderer();
        //     $output = $builtin_renderer->__invoke($e, $this->displayErrorDetails);
        //     $body->write($output);
        //     return $response->withStatus($e->getCode());
        // }
        if ($e instanceof HttpNotFoundException) {
            $body->write("404: Not Found");
            return $response->withStatus(404);
        }
        if ($e instanceof HTTPError) {
            $status = $e->getCode();
            $internal_msg_title = $e->internal_msg_title;
            $internal_msg_body = $e->internal_msg_body;
            $user_msg_title = $e->user_msg_title;
            $user_msg_body = $e->user_msg_body;
            $data = $e->data;
        } else {
            $dummy_exc = new HTTPInternalServerError("");
            $status = $dummy_exc->getCode();
            $internal_msg_title = $dummy_exc->internal_msg_title;
            $internal_msg_body = $e->getMessage();
            $user_msg_title = $dummy_exc->user_msg_title;
            $user_msg_body = "";
            $data = null;
        }
        $errorid = uniqid();
        $title = trim($user_msg_title);
        if (trim($user_msg_body) !== "") {
            $body_paragraphs = [htmlspecialchars(trim($user_msg_body))];
        } else {
            $body_paragraphs = [];
        }
        $support = CONFIG["mail"]["support"];
        array_push($body_paragraphs, "For assistance, contact a Unity admin at $support.");
        array_push($body_paragraphs, "Error ID: $errorid");
        _error_log(
            $internal_msg_title,
            $internal_msg_body,
            data: $data,
            error: $e,
            errorid: $errorid,
        );
        if (
            ($_SERVER["REQUEST_METHOD"] ?? "") == "POST" &&
            !str_starts_with($_SERVER["REQUEST_URI"], "/lan/api/") &&
            !str_starts_with($_SERVER["REQUEST_URI"], "/panel/modal/") &&
            !str_starts_with($_SERVER["REQUEST_URI"], "/panel/ajax/") &&
            !str_starts_with($_SERVER["REQUEST_URI"], "/admin/modal/") &&
            !str_starts_with($_SERVER["REQUEST_URI"], "/admin/ajax/")
        ) {
            UnityHTTPD::messageError($title, implode("\n", $body_paragraphs));
            return $this->respondRedirect();
        }
        // text may not be shown in the webpage in an obvious way, so make a popup
        $body->write(alert(implode(" -- ", [$title, ...$body_paragraphs])));
        $body->write(
            sprintf(
                "<h1>%s</h1>\n%s\n",
                htmlspecialchars($title),
                implode("\n<br>\n", $body_paragraphs),
            ),
        );
        if ($this->displayErrorDetails) {
            if ($data !== null) {
                $body->write(
                    sprintf(
                        "data relevant to error:<br><pre>%s</pre>",
                        _json_encode($data, JSON_PRETTY_PRINT),
                    ),
                );
            }
            if (property_exists($e, "xdebug_message")) {
                $body->write("<table>$e->xdebug_message</table>");
            } else {
                $body->write(
                    sprintf("<pre>%s</pre>", _json_encode(throwableToArray($e), JSON_PRETTY_PRINT)),
                );
            }
        }
        return $response->withStatus($status);
    }
}

class UnitySlimMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        global $LDAP, $SQL, $MAILER, $SSO, $USER, $OPERATOR;
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        // https://stackoverflow.com/a/1270960/18696276
        if (
            time() - ($_SESSION["LAST_ACTIVITY"] ?? 0) >
            CONFIG["site"]["session_cleanup_idle_seconds"]
        ) {
            $_SESSION["csrf_tokens"] = [];
            $_SESSION["messages"] = [];
            if (array_key_exists("pi_group_gid_to_owner_gecos_and_mail", $_SESSION)) {
                unset($_SESSION["pi_group_gid_to_owner_gecos_and_mail"]);
            }
            session_write_close();
            session_start();
        }
        $_SESSION["LAST_ACTIVITY"] = time();

        if (!array_key_exists("messages", $_SESSION)) {
            $_SESSION["messages"] = [];
        }

        if (!array_key_exists("csrf_tokens", $_SESSION)) {
            $_SESSION["csrf_tokens"] = [];
        }

        // $_SERVER["REMOTE_USER"] is only defined for pages where httpd requies authentication
        // the home page does not require authentication, so
        // if the user goes to a secure page and then back to home, they've effectively logged out
        // it would be bad UX to show the user that they are effectively logging in and out,
        // so we use session cache to remember if they have logged in recently and then pretend
        // they're logged in even if they aren't
        if (isset($_SERVER["REMOTE_USER"])) {
            // Check if SSO is enabled on this page
            $SSO = UnitySSO::getSSO();
            $_SESSION["SSO"] = $SSO;

            $OPERATOR = new UnityUser($SSO["user"], $LDAP, $SQL, $MAILER);
            $_SESSION["is_admin"] = $OPERATOR->getFlag(UserFlag::ADMIN);

            $_SESSION["OPERATOR"] = $SSO["user"];
            $_SESSION["OPERATOR_IP"] = $_SERVER["REMOTE_ADDR"];

            if (isset($_SESSION["viewUser"]) && $_SESSION["is_admin"]) {
                $USER = new UnityUser($_SESSION["viewUser"], $LDAP, $SQL, $MAILER);
            } else {
                $USER = $OPERATOR;
            }

            $_SESSION["user_exists"] = $USER->exists() && !$USER->getFlag(UserFlag::DISABLED);
            $_SESSION["is_pi"] = $USER->isPI();

            $days_idle = $SQL->convertLastLoginToDaysIdle($SQL->getUserLastLogin($USER->uid));
            $SQL->addLog("user_login", $OPERATOR->uid);
            $SQL->updateUserLastLogin($OPERATOR->uid);

            $USER->updateIsQualified(); // in case manual changes have been made to PI groups

            // $OPERATOR can be != $USER if an admin is logged in as another user
            if ($USER->exists() && $OPERATOR == $USER && !$USER->getFlag(UserFlag::DISABLED)) {
                // check if contact info sent by home institution has changed
                $USER->setFirstname($SSO["firstname"]);
                $USER->setLastname($SSO["lastname"]);
                $USER->setMail($SSO["mail"]);
                // remove idle-lock if exists
                if ($USER->getFlag(UserFlag::IDLELOCKED)) {
                    $USER->setFlag(UserFlag::IDLELOCKED, false, doSendMailAdmin: false);
                    UnityHTTPD::messageSuccess(
                        "Account Unlocked",
                        "Your account was previously locked due to inactivity.",
                    );
                } elseif ($days_idle >= CONFIG["expiry"]["idlelock_warning_days"][0]) {
                    UnityHTTPD::messageSuccess(
                        "Inactivity Timer Reset",
                        "Your account's scheduled locking is now cancelled.",
                    );
                }
            }
        }

        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            UnityHTTPD::validatePostCSRFToken();
            if (
                ($_SESSION["is_admin"] ?? false) == true &&
                ($_POST["form_type"] ?? null) == "clearView"
            ) {
                unset($_SESSION["viewUser"]);
                throw new HTTPRedirect("admin/user-mgmt");
            }
        }

        if (isset($SSO)) {
            if (
                !$USER->exists() &&
                !str_ends_with($_SERVER["PHP_SELF"], "/panel/new_account.php")
            ) {
                throw new HTTPRedirect(getRelativeURL("panel/new_account.php"));
            }
            if (
                $USER->getFlag(UserFlag::DISABLED) &&
                !str_ends_with($_SERVER["PHP_SELF"], "/panel/disabled_account.php")
            ) {
                throw new HTTPRedirect("panel/disabled_account");
            }
            if ($USER->getFlag(UserFlag::LOCKED)) {
                throw new HTTPForbidden("locked", user_msg_body: "Your account is locked.");
            }
        }

        return $handler->handle($request);
    }
}
