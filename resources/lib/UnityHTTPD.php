<?php

namespace UnityWebPortal\lib;

use UnityWebPortal\lib\exceptions\NoDieException;
use UnityWebPortal\lib\exceptions\ArrayKeyException;
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
    public static function die(?string $x = null): never
    {
        if ($x !== null) {
            echo $x;
        }
        if (CONFIG["site"]["allow_die"]) {
            die();
        } else {
            throw new NoDieException();
        }
    }

    /*
    send HTTP header, set HTTP response code,
    print a message just in case the browser fails to redirect if PHP is not being run from the CLI,
    and then die
    */
    public static function redirect(?string $dest = null): never
    {
        $dest ??= getURL($_SERVER["REQUEST_URI"]);
        header("Location: $dest");
        http_response_code(302);
        if (CONFIG["site"]["enable_redirect_message"]) {
            echo "If you're reading this message, then your browser has failed to redirect you " .
                "to the proper destination. click <a href='$dest'>here</a> to continue.";
        }
        self::die();
    }

    /*
    generates a unique error ID, writes to error log, and then:
        if the user is doing an HTTP POST:
            registers a message in the user's session and issues a redirect to display that message
        else:
            prints an HTML message to stdout, sets an HTTP response code, and dies
    we can't always do a redirect or else we could risk an infinite loop.
    */
    public static function gracefulDie(
        string $log_title,
        string $log_message,
        string $user_message_title,
        string $user_message_body,
        ?\Throwable $error = null,
        int $http_response_code = 200,
        mixed $data = null,
    ): never {
        $errorid = uniqid();
        $suffix = sprintf(
            "For assistance, contact a Unity admin at %s. Error ID: %s.",
            CONFIG["mail"]["support"],
            $errorid,
        );
        $user_message_title = htmlspecialchars($user_message_title);
        $user_message_body = htmlspecialchars($user_message_body);
        if (strlen($user_message_body) === 0) {
            $user_message_body = $suffix;
        } else {
            $user_message_body .= " $suffix";
        }
        self::errorLog($log_title, $log_message, data: $data, error: $error, errorid: $errorid);
        if (($_SERVER["REQUEST_METHOD"] ?? "") == "POST") {
            self::messageError($user_message_title, $user_message_body);
            self::redirect();
        } else {
            if (!headers_sent()) {
                http_response_code($http_response_code);
            }
            // text may not be shown in the webpage in an obvious way, so make a popup
            self::alert("$user_message_title -- $user_message_body");
            echo "<h1>$user_message_title</h1><p>$user_message_body</p>";
            // display_errors should not be enabled in production
            if (
                !is_null($error) &&
                ini_get("display_errors") === "1" &&
                property_exists($error, "xdebug_message")
            ) {
                echo "<table>";
                echo $error->xdebug_message;
                echo "</table>";
            }
            self::die();
        }
    }

    // $data must be JSON serializable
    public static function errorLog(
        string $title,
        string $message,
        ?string $errorid = null,
        ?\Throwable $error = null,
        mixed $data = null,
    ): void {
        if (!CONFIG["site"]["enable_verbose_error_log"]) {
            error_log("$title: $message");
            return;
        }
        $output = ["message" => $message];
        if (!is_null($data)) {
            try {
                \_json_encode($data);
                $output["data"] = $data;
            } catch (\JsonException $e) {
                $output["data"] = "data could not be JSON encoded: " . $e->getMessage();
            }
        }
        if (!is_null($error)) {
            $output["error"] = self::throwableToArray($error);
        } else {
            // newlines are bad for error log, but getTrace() is too verbose
            $output["trace"] = explode("\n", (new \Exception())->getTraceAsString());
        }
        $output["REMOTE_USER"] = $_SERVER["REMOTE_USER"] ?? null;
        $output["REMOTE_ADDR"] = $_SERVER["REMOTE_ADDR"] ?? null;
        $output["_REQUEST"] = $_REQUEST;
        if (!is_null($errorid)) {
            $output["errorid"] = $errorid;
        }
        error_log("$title: " . \_json_encode($output));
    }

    /**
     * recursive on $t->getPrevious()
     * @return array<string, mixed>
     */
    private static function throwableToArray(\Throwable $t): array
    {
        $output = [
            "class" => get_class($t),
            "msg" => $t->getMessage(),
            // newlines are bad for error log, but getTrace() is too verbose
            "trace" => explode("\n", $t->getTraceAsString()),
        ];
        $previous = $t->getPrevious();
        if (!is_null($previous)) {
            $output["previous"] = self::throwableToArray($previous);
        }
        return $output;
    }

    /** @param null|mixed[] $data */
    public static function badRequest(
        string $log_message,
        string $user_message = "",
        ?\Throwable $error = null,
        ?array $data = null,
    ): never {
        self::gracefulDie(
            "bad request",
            $log_message,
            "Invalid requested action or submitted data.",
            $user_message,
            error: $error,
            http_response_code: 400,
            data: $data,
        );
    }

    /** @param null|mixed[] $data */
    public static function forbidden(
        string $log_message,
        string $user_message = "",
        ?\Throwable $error = null,
        ?array $data = null,
    ): never {
        self::gracefulDie(
            "forbidden",
            $log_message,
            "Permission denied.",
            $user_message,
            error: $error,
            http_response_code: 403,
            data: $data,
        );
    }

    /** @param null|mixed[] $data */
    public static function internalServerError(
        string $log_message,
        string $user_message = "",
        ?\Throwable $error = null,
        ?array $data = null,
    ): never {
        self::gracefulDie(
            "internal server error",
            $log_message,
            "An internal server error has occurred.",
            $user_message,
            error: $error,
            http_response_code: 500,
            data: $data,
        );
    }

    // https://www.php.net/manual/en/function.set-exception-handler.php
    public static function exceptionHandler(\Throwable $e): void
    {
        // we disable log_errors before we enable this exception handler to avoid duplicate logging
        // if this exception handler itself fails, information will be lost unless we re-enable it
        ini_set("log_errors", true);
        self::internalServerError("", error: $e);
    }

    public static function errorHandler(
        int $severity,
        string $message,
        string $file,
        int $line,
    ): bool {
        if (str_contains($message, "Undefined array key")) {
            throw new ArrayKeyException($message);
        }
        return false;
    }

    public static function getPostData(string $key): string
    {
        if (!array_key_exists($key, $_POST)) {
            self::badRequest("\$_POST has no array key '$key'");
        }
        return $_POST[$key];
    }

    /**
     * @return ($die_if_not_found is true ? string : string|null)
     */
    public static function getQueryParameter(string $key, bool $die_if_not_found = true): ?string
    {
        if (!array_key_exists($key, $_GET)) {
            if ($die_if_not_found) {
                self::badRequest("\$_GET has no array key '$key'");
            } else {
                return null;
            }
        }
        return $_GET[$key];
    }

    public static function getUploadedFileContents(
        string $filename,
        bool $do_delete_tmpfile_after_read = true,
        string $encoding = "UTF-8",
    ): string {
        if (!array_key_exists($filename, $_FILES)) {
            self::badRequest("\$_FILES has no array key '$filename'", data: ['$_FILES' => $_FILES]);
        }
        if (!array_key_exists("tmp_name", $_FILES[$filename])) {
            self::badRequest(
                "\$_FILES[$filename] has no array key 'tmp_name'",
                data: ['$_FILES' => $_FILES],
            );
        }
        $tmpfile_path = $_FILES[$filename]["tmp_name"];
        $contents = file_get_contents($tmpfile_path);
        if ($contents === false) {
            throw new \Exception("Failed to read file: " . $tmpfile_path);
        }
        if ($do_delete_tmpfile_after_read) {
            unlink($tmpfile_path);
        }
        $old_encoding = _mb_detect_encoding($contents);
        return _mb_convert_encoding($contents, $encoding, $old_encoding);
    }

    // in firefox, the user can disable alert/confirm/prompt after the 2nd or 3rd popup
    // after I disable alerts, if I quit and reopen my browser, the alerts come back
    public static function alert(string $message): void
    {
        // jsonEncode escapes quotes
        echo "<script type='text/javascript'>alert(" . \_json_encode($message) . ");</script>";
    }

    private static function ensureSessionMessagesSanity(): void
    {
        if (!isset($_SESSION)) {
            throw new RuntimeException('$_SESSION is unset');
        }
        if (!array_key_exists("messages", $_SESSION)) {
            self::errorLog(
                "invalid session messages",
                'array key "messages" does not exist for $_SESSION',
                data: ['$_SESSION' => $_SESSION],
            );
            $_SESSION["messages"] = [];
        }
        if (!is_array($_SESSION["messages"])) {
            $type = gettype($_SESSION["messages"]);
            self::errorLog(
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
        $token = self::getPostData("csrf_token");
        if (!CSRFToken::validate($token)) {
            $errorid = uniqid();
            self::errorLog("csrf failed to validate", "", errorid: $errorid);
            self::messageError(
                "Invalid Session Token",
                "This can happen if you leave your browser open for too long. Error ID: $errorid",
            );
            self::redirect();
        }
    }

    public static function getCSRFTokenHiddenFormInput(): string
    {
        $token = htmlspecialchars(CSRFToken::generate());
        return "<input type='hidden' name='csrf_token' value='$token'>";
    }

    public static function validateAPIKey(): void
    {
        $authorization = $_SERVER["HTTP_AUTHORIZATION"] ?? "";
        if (!str_starts_with($authorization, "Bearer ")) {
            self::badRequest("HTTP_AUTHORIZATION is not Bearer", "invalid HTTP_AUTHORIZATION");
        }
        $key = substr($authorization, strlen("Bearer "));
        if (!in_array($key, CONFIG["api"]["keys"])) {
            self::forbidden("API key not found in config", "forbidden");
        }
    }
}
