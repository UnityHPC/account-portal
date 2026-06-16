<?php

namespace UnityWebPortal\lib;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LanApiController
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function expiry(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $SQL = $this->container->get("SQL");
        $uid = UnityHTTPD::getQueryParameter("uid");
        $last_login = $SQL->getUserLastLogin($uid);
        if ($last_login === null) {
            UnityHTTPD::badRequest("no last login timestamp known for user '$uid'");
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

    public function bumpLastLogin(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            UnityHTTPD::badRequest("invalid request method {$_SERVER["REQUEST_METHOD"]}");
        }
        UnityHTTPD::validateAPIKey();

        $SQL = $this->container->get("SQL");
        $uid = UnityHTTPD::getQueryParameter("uid");
        $SQL->updateUserLastLogin($uid);
        return $response;
    }
}
