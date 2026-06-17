<?php

namespace UnityWebPortal\lib;

use UnityWebPortal\lib\exceptions\HTTPRedirect;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use UnityWebPortal\lib\exceptions\EncodingUnknownException;
use UnityWebPortal\lib\exceptions\EncodingConversionException;
use Slim\Views\Twig;

class AccountController extends UnitySlimController
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
        $ssh_public_keys = [];
        foreach ($USER->getSSHKeys() as $key) {
            try {
                [$type, $_, $comment] = tokenizeSSHKey($key);
                [$length, $sha256_fingerprint] = getSSHKeyInfo($key);
                $stub_fingprint = substr($sha256_fingerprint, 0, 6);
                $ssh_public_keys[$key] = [$type, $comment, $length, $stub_fingprint];
            } catch (\Throwable $e) {
                $errid = uniqid();
                UnityHTTPD::errorLog(
                    "error",
                    "failed to analyze SSH key!",
                    errorid: $errid,
                    error: $e,
                    data: $key,
                );
                $comment = "ERROR: Something went wrong while fetching your key. error ID: $errid";
                $ssh_public_keys[$key] = [null, $comment, null, null];
            }
        }
        return $view->render(
            $response,
            "panel/account.html.twig",
            $this->setupTwigContext([
                "uid" => $USER->uid,
                "org" => $USER->getOrg(),
                "mail" => $USER->getMail(),
                "ssh_public_keys" => $ssh_public_keys,
                "login_shell" => $USER->getLoginShell(),
                "pi_group_exists" => $USER->getPIGroup()->exists(),
                "pi_group_is_disabled" => $USER->getPIGroup()->getIsDisabled(),
            ]),
        );
    }

    public function post(Request $request, Response $response): Response
    {
        $USER = $this->container->get("USER");
        $GITHUB = $this->container->get("GITHUB");
        $LDAP = $this->container->get("LDAP");
        $SQL = $this->container->get("SQL");
        $hasGroups = count($USER->getPIGroupGIDs()) > 0;
        switch (UnityHTTPD::getPostData("form_type")) {
            case "addKey":
                switch (UnityHTTPD::getPostData("add_type")) {
                    case "paste":
                        $keys = [UnityHTTPD::getPostData("key")];
                        break;
                    case "import":
                        try {
                            $keys = [UnityHTTPD::getUploadedFileContents("keyfile")];
                        } catch (EncodingUnknownException | EncodingConversionException $e) {
                            UnityHTTPD::errorLog("uploaded key has bad encoding", "", error: $e);
                            UnityHTTPD::messageError("SSH Key Not Added: Invalid Encoding", "");
                            throw new HTTPRedirect();
                        }
                        break;
                    case "generate":
                        $keys = [UnityHTTPD::getPostData("gen_key")];
                        break;
                    case "github":
                        $githubUsername = UnityHTTPD::getPostData("gh_user");
                        $keys = $GITHUB->getSshPublicKeys($githubUsername);
                        if (count($keys) == 0) {
                            UnityHTTPD::messageWarning(
                                "No Keys Added",
                                "No keys found associated with GitHub account.",
                            );
                            throw new HTTPRedirect();
                        }
                        break;
                    default:
                        UnityHTTPD::badRequest("invalid add_type");
                }
                $keys = array_map("trim", $keys);
                foreach ($keys as $key) {
                    $key_short = shortenString($key, 10, 30);
                    [$is_valid, $explanation] = testValidSSHKey($key);
                    if (!$is_valid) {
                        UnityHTTPD::messageError("SSH Key Not Added: $explanation", $key_short);
                        continue;
                    }
                    $already_using_this_key = $LDAP->whoIsUsingKey($key);
                    if (count($already_using_this_key) > 0) {
                        if ($already_using_this_key === [$USER->uid]) {
                            UnityHTTPD::messageWarning(
                                "SSH Key Not Added: Key Already Added",
                                $key_short,
                            );
                            continue;
                        } else {
                            UnityHTTPD::errorLog(
                                "security warning",
                                "attempted SSH public key sharing between users",
                                data: ["already using this key" => $already_using_this_key],
                            );
                            UnityHTTPD::messageWarning(
                                "SSH Key Not Added: Another User Is Already Using This Key",
                                "Sharing SSH keys with other users is against terms of service." .
                                    "This incident has been reported.",
                            );
                            continue;
                        }
                    }
                    $USER->addSSHKey($key);
                    $sha256_fingerprint = getSSHKeyInfo($key)[1];
                    $stub_fingprint = substr($sha256_fingerprint, 0, 6);
                    UnityHTTPD::messageSuccess("SSH Key Added", $stub_fingprint);
                }
                throw new HTTPRedirect();
                break; /** @phpstan-ignore deadCode.unreachable */
            case "delKey":
                $key = _base64_decode(UnityHTTPD::getPostData("delKey"));
                $key_short = shortenString($key, 10, 30);
                try {
                    $USER->removeSSHKey($key);
                } catch (ArrayKeyException) {
                    UnityHTTPD::messageError("Cannot Remove SSH Key", "Key not found");
                    throw new HTTPRedirect();
                }
                UnityHTTPD::messageSuccess("SSH Key Removed", "$key_short");
                throw new HTTPRedirect();
                break; /** @phpstan-ignore deadCode.unreachable */
            case "loginshell":
                $shell = UnityHTTPD::getPostData("shellSelect");
                if (!in_array($shell, CONFIG["loginshell"]["shell"])) {
                    UnityHTTPD::badRequest("invalid login shell", "invalid login shell");
                }
                $USER->setLoginShell($shell);
                UnityHTTPD::messageSuccess("Login Shell Changed", "");
                throw new HTTPRedirect();
                break; /** @phpstan-ignore deadCode.unreachable */
            case "pi_request":
                if ($USER->isPI()) {
                    UnityHTTPD::messageError("Cannot Submit PI Request", "Already a PI");
                    throw new HTTPRedirect();
                }
                if ($SQL->requestExists($USER->uid, UnitySQL::REQUEST_BECOME_PI)) {
                    UnityHTTPD::messageError(
                        "Cannot Submit PI Request",
                        "This request already exists",
                    );
                    throw new HTTPRedirect();
                }
                if ($_POST["tos"] != "agree") {
                    UnityHTTPD::badRequest("user did not agree to terms of service");
                }
                $USER->getPIGroup()->requestGroup();
                UnityHTTPD::messageSuccess("PI Group Requested", "");
                throw new HTTPRedirect();
                break; /** @phpstan-ignore deadCode.unreachable */
            case "cancel_pi_request":
                if (!$SQL->requestExists($USER->uid, UnitySQL::REQUEST_BECOME_PI)) {
                    UnityHTTPD::messageError("Cannot Cancel PI Request", "No PI request found");
                    throw new HTTPRedirect();
                }
                $USER->getPIGroup()->cancelGroupRequest();
                UnityHTTPD::messageSuccess("PI Request Cancelled", "");
                throw new HTTPRedirect();
                break; /** @phpstan-ignore deadCode.unreachable */
            case "disable":
                if ($hasGroups) {
                    UnityHTTPD::messageError(
                        "Cannot Disable",
                        "You are a PI or you are a member of at least one PI group",
                    );
                    throw new HTTPRedirect();
                }
                if ($USER->getFlag(UserFlag::DISABLED)) {
                    UnityHTTPD::badRequest("user is already disabled", "");
                }
                $USER->disable(UnityUserDisabledReason::DisabledSelf);
                UnityHTTPD::messageSuccess("Account Disabled", "");
                throw new HTTPRedirect();
                break; /** @phpstan-ignore deadCode.unreachable */
        }
        return $response;
    }
}
