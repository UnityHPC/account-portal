<?php
if (!array_key_exists("SERVER_NAME", $_SERVER)) {
    if (getenv("SERVER_NAME")) {
        $_SERVER["SERVER_NAME"] = getenv("SERVER_NAME");
    } else {
        $_SERVER["SERVER_NAME"] = "worker"; // see deployment/overrides/worker
    }
}
if (!array_key_exists("REMOTE_ADDR", $_SERVER)) {
    if (getenv("REMOTE_ADDR")) {
        $_SERVER["REMOTE_ADDR"] = getenv("REMOTE_ADDR");
    } else {
        $_SERVER["REMOTE_ADDR"] = "127.0.0.1"; // needed for audit log
    }
}

require_once __DIR__ . "/../resources/autoload.php";

// UnityHTTPD::die() makes no output by default
// builtin die() makes a return code of 0, we may want nonzero
function _die(string $msg, int $exit_code)
{
    print $msg;
    exit($exit_code);
}
