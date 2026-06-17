<?php

namespace UnityWebPortal\lib;

use UnityWebPortal\lib\exceptions\HTTPBadRequest;
use UnityWebPortal\lib\exceptions\HTTPRedirect;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use UnityWebPortal\lib\exceptions\EncodingUnknownException;
use UnityWebPortal\lib\exceptions\EncodingConversionException;
use Slim\Views\Twig;

class AccountController extends UnitySlimController
{
    public function get(Request $request, Response $response): Response
    {
        global $USER;
        $view = Twig::fromRequest($request);
        $ssh_public_keys = [];
        foreach ($USER->getSSHKeys() as $key) {
            try {
                [$type, $_, $comment] = tokenizeSSHKey($key);
                [$length, $sha256_fingerprint] = getSSHKeyInfo($key);
                $stub_fingprint = substr($sha256_fingerprint, 0, 6);
                $ssh_public_keys[$key] = [$type, $comment, $length, $stub_fingprint];
            } catch (\Throwable $e) {
                $errid = uniqid();
                _error_log(
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
        global $USER, $GITHUB, $LDAP, $SQL;
        $hasGroups = count($USER->getPIGroupGIDs()) > 0;
        switch (getPostData("form_type")) {
            case "addKey":
                switch (getPostData("add_type")) {
                    case "paste":
                        $keys = [getPostData("key")];
                        break;
                    case "import":
                        try {
                            $keys = [getUploadedFileContents("keyfile")];
                        } catch (EncodingUnknownException | EncodingConversionException $e) {
                            _error_log("uploaded key has bad encoding", "", error: $e);
                            UnityHTTPD::messageError("SSH Key Not Added: Invalid Encoding", "");
                            throw new HTTPRedirect();
                        }
                        break;
                    case "generate":
                        $keys = [getPostData("gen_key")];
                        break;
                    case "github":
                        $githubUsername = getPostData("gh_user");
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
                        throw new HTTPBadRequest("invalid add_type");
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
                            _error_log(
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
            case "delKey":
                $key = _base64_decode(getPostData("delKey"));
                $key_short = shortenString($key, 10, 30);
                try {
                    $USER->removeSSHKey($key);
                } catch (ArrayKeyException) {
                    UnityHTTPD::messageError("Cannot Remove SSH Key", "Key not found");
                    throw new HTTPRedirect();
                }
                UnityHTTPD::messageSuccess("SSH Key Removed", "$key_short");
                throw new HTTPRedirect();
            case "loginshell":
                $shell = getPostData("shellSelect");
                if (!in_array($shell, CONFIG["loginshell"]["shell"])) {
                    throw new HTTPBadRequest(
                        "invalid login shell",
                        user_msg_body: "invalid login shell",
                    );
                }
                $USER->setLoginShell($shell);
                UnityHTTPD::messageSuccess("Login Shell Changed", "");
                throw new HTTPRedirect();
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
                    throw new HTTPBadRequest("user did not agree to terms of service");
                }
                $USER->getPIGroup()->requestGroup();
                UnityHTTPD::messageSuccess("PI Group Requested", "");
                throw new HTTPRedirect();
            case "cancel_pi_request":
                if (!$SQL->requestExists($USER->uid, UnitySQL::REQUEST_BECOME_PI)) {
                    UnityHTTPD::messageError("Cannot Cancel PI Request", "No PI request found");
                    throw new HTTPRedirect();
                }
                $USER->getPIGroup()->cancelGroupRequest();
                UnityHTTPD::messageSuccess("PI Request Cancelled", "");
                throw new HTTPRedirect();
            case "disable":
                if ($hasGroups) {
                    UnityHTTPD::messageError(
                        "Cannot Disable",
                        "You are a PI or you are a member of at least one PI group",
                    );
                    throw new HTTPRedirect();
                }
                if ($USER->getFlag(UserFlag::DISABLED)) {
                    throw new HTTPBadRequest("user is already disabled");
                }
                $USER->disable(UnityUserDisabledReason::DisabledSelf);
                UnityHTTPD::messageSuccess("Account Disabled", "");
                throw new HTTPRedirect();
            default:
                throw new HTTPBadRequest("invalid form_type");
        }
    }
}
