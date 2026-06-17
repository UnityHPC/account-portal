<?php

namespace UnityWebPortal\lib;

class CSRFToken
{
    private static function ensureSessionCSRFTokensSanity(): void
    {
        if (!isset($_SESSION)) {
            throw new \RuntimeException("Session is not started. Call session_start() first.");
        }
        if (!array_key_exists("csrf_tokens", $_SESSION)) {
            _error_log(
                "invalid session",
                '$_SESSION has no array key "csrf_tokens"',
                data: ['$_SESSION' => $_SESSION],
            );
            $_SESSION["csrf_tokens"] = [];
        }
        if (!is_array($_SESSION["csrf_tokens"])) {
            _error_log(
                "invalid session",
                '$_SESSION["csrf_tokens"] is not an array',
                data: ['$_SESSION' => $_SESSION],
            );
            $_SESSION["csrf_tokens"] = [];
        }
    }

    public static function generate(): string
    {
        self::ensureSessionCSRFTokensSanity();
        $token = bin2hex(random_bytes(32));
        $_SESSION["csrf_tokens"][$token] = false;
        return $token;
    }

    public static function validate(string $token): bool
    {
        self::ensureSessionCSRFTokensSanity();
        if ($token === "") {
            _error_log("empty CSRF token", "");
            return false;
        }
        if (!array_key_exists($token, $_SESSION["csrf_tokens"])) {
            _error_log("unknown CSRF token", $token);
            return false;
        }
        $entry = $_SESSION["csrf_tokens"][$token];
        if ($entry === true) {
            _error_log("reused CSRF token", $token);
            return false;
        }
        $_SESSION["csrf_tokens"][$token] = true;
        return true;
    }

    public static function clear(): void
    {
        if (!isset($_SESSION)) {
            return;
        }
        if (array_key_exists("csrf_tokens", $_SESSION)) {
            unset($_SESSION["csrf_tokens"]);
        }
        $_SESSION["csrf_tokens"] = [];
    }
}
