<?php

namespace UnityWebPortal\lib\exceptions;

class ArrayKeyException extends \Exception {}
class CurlException extends \Exception {}
class EncodingConversionException extends \Exception {}
class EncodingUnknownException extends \Exception {}
class EntryNotFoundException extends \Exception {}
class InvalidConfigurationException extends \Exception {}
class NoDieException extends \Exception {}
class SSOException extends \Exception {}
class UnityHTTPDMessageNotFoundException extends \Exception {}
class HTTPRedirect extends \Exception {}

class HTTPError extends \Exception
{
    public $internal_msg;
    public $user_msg;
    public $data;
    public function __construct(
        string $internal_msg,
        int $code = 0,
        ?\Throwable $previous = null,
        string $user_msg = "",
        mixed $data = null,
    ) {
        parent::__construct($internal_msg, $code, $previous);
        $this->internal_msg = $internal_msg;
        $this->user_msg = $user_msg;
        $this->data = $data;
    }
}

class HTTPBadRequest extends HTTPError {}
class HTTPForbidden extends HTTPError {}
class HTTPInternalServerError extends HTTPError {}
