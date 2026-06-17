<?php

namespace UnityWebPortal\lib;

use UnityWebPortal\lib\exceptions\HTTPBadRequest;
use UnityWebPortal\lib\exceptions\HTTPRedirect;
use UnityWebPortal\lib\exceptions\HTTPForbidden;
use UnityWebPortal\lib\exceptions\HTTPError;
use UnityWebPortal\lib\exceptions\HTTPInternalServerError;
use UnityWebPortal\lib\exceptions\UnityHTTPDMessageNotFoundException;
use Psr\Http\Message\ResponseInterface as Response;
use RuntimeException;
use Slim\Handlers\ErrorHandler;

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
                $output["data"] = var_export($data, true);
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
        if (!is_null($errorid)) {
            $output["errorid"] = $errorid;
        }
        error_log("$title: " . \_json_encode($output));
    }

    /**
     * recursive on $t->getPrevious()
     * @return array<string, mixed>
     */
    public static function throwableToArray(\Throwable $t): array
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

    public static function getPostData(string $key): string
    {
        if (!array_key_exists($key, $_POST)) {
            throw new HTTPBadRequest("\$_POST has no array key '$key'");
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
                throw new HTTPBadRequest("\$_GET has no array key '$key'");
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
            throw new HTTPBadRequest(
                "\$_FILES has no array key '$filename'",
                data: ['$_FILES' => $_FILES],
            );
        }
        if (!array_key_exists("tmp_name", $_FILES[$filename])) {
            throw new HTTPBadRequest(
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
        echo sprintf(
            "<script type='text/javascript'>\nalert('%s');\n</script>\n",
            htmlspecialchars($message),
        );
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
            throw new HTTPRedirect();
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

class SlimErrorHandler extends ErrorHandler
{
    public function respond(): Response
    {
        $e = $this->exception;
        $response = $this->responseFactory->createResponse($e->getCode());
        if ($e instanceof HTTPRedirect) {
            // TODO check $_SERVER["REDIRECT_STATUS"]?
            $relative_path = $e->getMessage();
            if ($relative_path == "") {
                $relative_path = $_SERVER["REQUEST_URI"];
            }
            $dest = getRelativeURL($relative_path);
            $msg =
                "If you're reading this message, then your browser has failed to redirect you " .
                "to the proper destination. click <a href='$dest'>here</a> to continue.";
            $response->getBody()->write($msg);
            return $response->withStatus(302)->withHeader("Location", $dest);
        }
        if (!($e instanceof HTTPError)) {
            $e = new HTTPInternalServerError($e->getMessage(), previous: $e);
        }
        $errorid = uniqid();
        $title = trim($e->user_msg_title);
        if (trim($e->user_msg_body) !== "") {
            $body_paragraphs = [htmlspecialchars(trim($e->user_msg_body))];
        } else {
            $body_paragraphs = [];
        }
        $support = CONFIG["mail"]["support"];
        array_push($body_paragraphs, "For assistance, contact a Unity admin at $support.");
        array_push($body_paragraphs, "Error ID: $errorid");
        UnityHTTPD::errorLog(
            $e->internal_msg_title,
            $e->internal_msg_body,
            data: $e->data,
            error: $e,
            errorid: $errorid,
        );
        if (
            ($_SERVER["REQUEST_METHOD"] ?? "") == "POST" &&
            !str_starts_with($_SERVER["REQUEST_URI"], "/lan/api/")
        ) {
            UnityHTTPD::messageError($title, implode("\n", $body_paragraphs));
            $new_handler = new SlimErrorHandler($this->callableResolver, $this->responseFactory);
            $new_handler->exception = new HTTPRedirect();
            return $new_handler->respond();
        } else {
            $body = $response->getBody();
            // text may not be shown in the webpage in an obvious way, so make a popup
            $body->write(UnityHTTPD::alert(implode(" -- ", [$title, ...$body_paragraphs])));
            $body->write(
                sprintf(
                    "<h1>%s</h1>\n%s\n",
                    htmlspecialchars($title),
                    implode("\n<br>\n", $body_paragraphs),
                ),
            );
            if ($this->displayErrorDetails) {
                if (property_exists($e, "xdebug_message")) {
                    $body->write("<table>$e->xdebug_message</table>");
                } else {
                    $body->write(
                        sprintf(
                            "<pre>%s</pre>",
                            _json_encode(UnityHTTPD::throwableToArray($e), JSON_PRETTY_PRINT),
                        ),
                    );
                }
            }
            return $response->withStatus($e->getCode());
        }
    }
}
