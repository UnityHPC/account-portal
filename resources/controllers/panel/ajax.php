<?php

namespace UnityWebPortal\lib;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use phpseclib3\Crypt\EC;

class PanelAjaxController extends UnitySlimController
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function ssh_generate(ServerRequestInterface $request, ResponseInterface $response)
    {
        $private = EC::createKey("Ed25519");
        $public = $private->getPublicKey();
        $public_str = $public->toString("OpenSSH");
        if (($request->getQueryParams()["type"] ?? null) === "ppk") {
            $private_str = $private->toString("PuTTY");
        } else {
            $private_str = $private->toString("OpenSSH");
        }
        $response
            ->getBody()
            ->write(_json_encode(["public" => $public_str, "private" => $private_str]));
        return $response->withHeader("Content-Type", "application/json; charset=utf-8");
    }

    public function ssh_validate(ServerRequestInterface $request, ResponseInterface $response)
    {
        $post_data = (array) $request->getParsedBody();
        [$is_valid, $explanation] = testValidSSHKey($post_data["key"]);
        $response
            ->getBody()
            ->write(_json_encode(["is_valid" => $is_valid, "explanation" => $explanation]));
        return $response->withHeader("Content-Type", "application/json; charset=utf-8");
    }

    public function pi_search(ServerRequestInterface $request, ResponseInterface $response)
    {
        $search_query = strtolower((string) ($request->getQueryParams()["search"] ?? ""));
        if ($search_query === "") {
            $response->getBody()->write("[]");
            return $response->withHeader("Content-Type", "application/json; charset=utf-8");
        }
        if (!array_key_exists("pi_group_gid_to_owner_gecos_and_mail", $_SESSION)) {
            UnityHTTPD::internalServerError(
                '$_SESSION["pi_group_gid_to_owner_gecos_and_mail"] does not exist!',
                "Session cache not found. Try reloading the page.",
            );
        }
        $pi_group_gid_to_owner_gecos_and_mail = $_SESSION["pi_group_gid_to_owner_gecos_and_mail"];
        $output = [];
        foreach ($pi_group_gid_to_owner_gecos_and_mail as $gid => [$gecos, $mail]) {
            $gid = strtolower($gid);
            $gecos = strtolower($gecos);
            $mail = strtolower($mail);
            if (
                str_contains($gid, $search_query) ||
                str_contains($gecos, $search_query) ||
                str_contains($mail, $search_query)
            ) {
                $output[] = $gid;
                if (count($output) >= 10) {
                    break;
                }
            }
        }
        $response->getBody()->write(_json_encode($output));
        return $response->withHeader("Content-Type", "application/json; charset=utf-8");
    }

    public function delete_message(ServerRequestInterface $request, ResponseInterface $response)
    {
        $post_data = (array) $request->getParsedBody();
        $level_str = _base64_decode($post_data["level"]);
        $level = UnityHTTPDMessageLevel::from($level_str);
        $title = _base64_decode($post_data["title"]);
        $body = _base64_decode($post_data["body"]);
        UnityHTTPD::deleteMessage($level, $title, $body);
        return $response;
    }
}
