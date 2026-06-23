<?php

namespace UnityWebPortal\lib;

use UnityWebPortal\lib\exceptions\HTTPBadRequest;
use UnityWebPortal\lib\exceptions\HTTPRedirect;
use UnityWebPortal\lib\exceptions\HTTPForbidden;
use UnityWebPortal\lib\exceptions\UnityHTTPDMessageNotFoundException;
use RuntimeException;

enum UnityHTTPDMessageLevel: string
{
    case DEBUG = "debug";
    case INFO = "info";
    case SUCCESS = "success";
    case WARNING = "warning";
    case ERROR = "error";
}

/**
 * @phpstan-type message array{0: string, 1: string, 2: UnityHTTPDMessageLevel}
 */
class UnityHTTPD
{
    private static function ensureSessionMessagesSanity(): void
    {
        if (!isset($_SESSION)) {
            throw new RuntimeException('$_SESSION is unset');
        }
        if (!array_key_exists("messages", $_SESSION)) {
            _error_log(
                "invalid session messages",
                'array key "messages" does not exist for $_SESSION',
                data: ['$_SESSION' => $_SESSION],
            );
            $_SESSION["messages"] = [];
        }
        if (!is_array($_SESSION["messages"])) {
            $type = gettype($_SESSION["messages"]);
            _error_log(
                "invalid session messages",
                "\$_SESSION['messages'] is type '$type', not an array",
                data: ['$_SESSION' => $_SESSION],
            );
            $_SESSION["messages"] = [];
        }
    }

    public static function message(string $title, string $body, UnityHTTPDMessageLevel $level): void
    {
        self::ensureSessionMessagesSanity();
        array_push($_SESSION["messages"], [$title, $body, $level]);
    }

    public static function messageDebug(string $title, string $body): void
    {
        self::message($title, $body, UnityHTTPDMessageLevel::DEBUG);
    }
    public static function messageInfo(string $title, string $body): void
    {
        self::message($title, $body, UnityHTTPDMessageLevel::INFO);
    }
    public static function messageSuccess(string $title, string $body): void
    {
        self::message($title, $body, UnityHTTPDMessageLevel::SUCCESS);
    }
    public static function messageWarning(string $title, string $body): void
    {
        self::message($title, $body, UnityHTTPDMessageLevel::WARNING);
    }
    public static function messageError(string $title, string $body): void
    {
        self::message($title, $body, UnityHTTPDMessageLevel::ERROR);
    }

    /** @return message[] */
    public static function getMessages(): array
    {
        self::ensureSessionMessagesSanity();
        return $_SESSION["messages"];
    }

    public static function clearMessages(): void
    {
        self::ensureSessionMessagesSanity();
        $_SESSION["messages"] = [];
    }

    private static function getMessageIndex(
        UnityHTTPDMessageLevel $level,
        string $title,
        string $body,
    ): int {
        $messages = self::getMessages();
        $error_msg = sprintf(
            "message(level='%s' title='%s' body='%s'), not found. found messages: %s",
            $level->value,
            $title,
            $body,
            _json_encode($messages),
        );
        foreach ($messages as $i => $message) {
            if ($title == $message[0] && $body == $message[1] && $level == $message[2]) {
                return $i;
            }
        }
        throw new UnityHTTPDMessageNotFoundException($error_msg);
    }

    /**
     * returns the 1st message that matches or throws UnityHTTPDMessageNotFoundException
     * @return message
     */
    public static function getMessage(
        UnityHTTPDMessageLevel $level,
        string $title,
        string $body,
    ): array {
        $index = self::getMessageIndex($level, $title, $body);
        return $_SESSION["messages"][$index];
    }

    /* deletes the 1st message that matches or throws UnityHTTPDMessageNotFoundException */
    public static function deleteMessage(
        UnityHTTPDMessageLevel $level,
        string $title,
        string $body,
    ): void {
        $index = self::getMessageIndex($level, $title, $body);
        unset($_SESSION["messages"][$index]);
        $_SESSION["messages"] = array_values($_SESSION["messages"]);
    }

    public static function validatePostCSRFToken(): void
    {
        $token = getPostData("csrf_token");
        if (!CSRFToken::validate($token)) {
            $errorid = uniqid();
            _error_log("csrf failed to validate", "", errorid: $errorid);
            self::messageError(
                "Invalid Session Token",
                "This can happen if you leave your browser open for too long. Error ID: $errorid",
            );
            throw new HTTPRedirect();
        }
    }

    public static function validateAPIKey(): void
    {
        $authorization = $_SERVER["HTTP_AUTHORIZATION"] ?? "";
        if (!str_starts_with($authorization, "Bearer ")) {
            // this can happen when you don't enable apache CGIPassAuth
            throw new HTTPBadRequest(
                "HTTP_AUTHORIZATION is not Bearer",
                user_msg_body: "invalid HTTP_AUTHORIZATION",
            );
        }
        $key = trim(substr($authorization, strlen("Bearer ")));
        if ($key === "") {
            throw new HTTPForbidden("empty API key");
        }
        if (!in_array($key, CONFIG["api"]["keys"])) {
            throw new HTTPForbidden("API key not found in config");
        }
    }
}
