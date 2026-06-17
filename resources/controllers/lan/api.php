<?php

namespace UnityWebPortal\lib;

use UnityWebPortal\lib\exceptions\HTTPBadRequest;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LanApiController extends UnitySlimController
{
    public function expiry(Request $request, Response $response): Response
    {
        global $SQL;
        $uid = getQueryParameter("uid");
        $last_login = $SQL->getUserLastLogin($uid);
        if ($last_login === null) {
            throw new HTTPBadRequest("no last login timestamp known for user '$uid'");
        }

        $idlelock_timestamp = $last_login + CONFIG["expiry"]["idlelock_day"] * 60 * 60 * 24;
        $disable_timestamp = $last_login + CONFIG["expiry"]["disable_day"] * 60 * 60 * 24;
        $response->getBody()->write(
            _json_encode([
                "uid" => $uid,
                "idlelock_date" => date("Y/m/d", $idlelock_timestamp),
                "disable_date" => date("Y/m/d", $disable_timestamp),
            ]),
        );
        return $response->withHeader("Content-Type", "application/json; charset=utf-8");
    }

    public function bumpLastLogin(Request $request, Response $response): Response
    {
        global $SQL;
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            throw new HTTPBadRequest("invalid request method {$_SERVER["REQUEST_METHOD"]}");
        }
        UnityHTTPD::validateAPIKey();
        $uid = getQueryParameter("uid");
        $SQL->updateUserLastLogin($uid);
        return $response->withStatus(200);
    }
}
