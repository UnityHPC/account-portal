<?php

namespace UnityWebPortal\lib;

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
