#!/usr/bin/env php
<?php
include __DIR__ . "/init.php";

use Garden\Cli\Cli;

$cli = new Cli();
$cli->description("clamp all last login dates to a minimum timestamp")
    ->opt("dry-run", "Print changes without actually changing anything.", false, "boolean")
    ->opt("date", "YYYY/MM/DD", true);
$args = $cli->parse($argv, true);

$threshold = strtotime($args["date"]);

foreach ($SQL->getAllUserLastLogins() as $last_login) {
    $uid = $last_login["operator"];
    $timestamp = $last_login["last_login"];
    if ($timestamp < $threshold) {
        echo "bumping last login date of user '$uid'\n";
        if (!$args["dry-run"]) {
            $SQL->setUserLastLogin($uid, $timestamp);
        }
    }
}

