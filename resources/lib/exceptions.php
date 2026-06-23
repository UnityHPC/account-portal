<?php

namespace UnityWebPortal\lib\exceptions;

class ArrayKeyException extends \Exception {}
class CurlException extends \Exception {}
class EncodingConversionException extends \Exception {}
class EncodingUnknownException extends \Exception {}
class EntryNotFoundException extends \Exception {}
class InvalidConfigurationException extends \Exception {}
class SSOException extends \Exception {}
class UnityHTTPDMessageNotFoundException extends \Exception {}
class HTTPRedirect extends \Exception {}

class HTTPError extends \Exception
{
    public $internal_msg_title;
    public $internal_msg_body;
    public $user_msg_title;
    public $user_msg_body;
    public $data;
    public function __construct(
        string $internal_msg_body,
        int $code = 0,
        ?\Throwable $previous = null,
        string $user_msg_body = "",
        mixed $data = null,
    ) {
        parent::__construct($internal_msg_body, $code, $previous);
        $this->internal_msg_body = $internal_msg_body;
        $this->user_msg_body = $user_msg_body;
        $this->data = $data;
    }
}

class HTTPBadRequest extends HTTPError
{
    public $internal_msg_title = "bad request";
    public $user_msg_title = "Invalid requested action or submitted data.";
    public $code = 400;
}
class HTTPForbidden extends HTTPError
{
    public $internal_msg_title = "forbidden";
    public $user_msg_title = "Permission denied.";
    public $code = 403;
}
class HTTPInternalServerError extends HTTPError
{
    public $internal_msg_title = "internal server error";
    public $user_msg_title = "An internal server error has occurred.";
    public $code = 400;
}
