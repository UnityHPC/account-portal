#!/usr/bin/env php
<?php
include __DIR__ . "/init.php";
use Garden\Cli\Cli;
use UnityWebPortal\lib\UnityUserExpirationWarningType;

$cli = new Cli();
$cli->description(
    "Send a warning email, idlelock, or disable users, depending on their last login date. " .
        "It is important that this script runs exactly once per day.",
)->opt("dry-run", "Print changes without actually changing anything.", false, "boolean");
$args = $cli->parse($argv, true);

$idlelock_warning_emails = [];
$disable_warning_emails = [];
$uids_to_idlelock = [];
$uids_to_disable = [];

$idlelock_warning_days = CONFIG["user_expiry"]["idlelock_warning_days"];
$idlelock_day = CONFIG["user_expiry"]["idlelock_day"];
$disable_warning_days = CONFIG["user_expiry"]["idlelock_warning_days"];
$disable_day = CONFIG["user_expiry"]["disable_day"];
$now = time();
$all_users_last_logins = $SQL->getAllUserLastLogins();
$all_users_warning_emails_sent = $SQL->getAllUsersExpirationWarningDaysSent();

function doesArrayHaveOnlyIntegerValues(array $x): bool
{
    foreach ($x as $value) {
        if (!is_int($value)) {
            return false;
        }
    }
    return true;
}

function isArrayMonotonicallyIncreasing(array $x): bool
{
    if (count($x) <= 1) {
        return true;
    }
    $remaining_values = $x;
    $last_value = array_shift($remaining_values);
    while (count($remaining_values)) {
        $this_value = array_shift($remaining_values);
        if ($this_value < $last_value) {
            return false;
        }
        $last_value = $this_value;
    }
    return true;
}

if (!doesArrayHaveOnlyIntegerValues($idlelock_warning_days)) {
    _die('$CONFIG["user_expiry"]["idlelock_warning_days"] must be a list of integers!', 1);
}
if (!doesArrayHaveOnlyIntegerValues($disable_warning_days)) {
    _die('$CONFIG["user_expiry"]["disable_warning_days"] must be a list of integers!', 1);
}
if (!isArrayMonotonicallyIncreasing($idlelock_warning_days)) {
    _die('$CONFIG["user_expiry"]["idlelock_warning_days"] must be monotonically increasing!', 1);
}
if (!isArrayMonotonicallyIncreasing($disable_warning_days)) {
    _die('$CONFIG["user_expiry"]["disable_warning_days"] must be monotonically increasing!', 1);
}

$last_disable_warning_day = $disable_warning_days[array_key_last($disable_warning_days)];
$last_idlelock_warning_day = $idlelock_warning_days[array_key_last($idlelock_warning_days)];
if ($disable_day <= $last_disable_warning_day) {
    _die("disable day must be greater than the last disable warning day", 1);
}
if ($idlelock_day <= $last_idlelock_warning_day) {
    _die("idlelock day must be greater than the last idlelock warning day", 1);
}

$all_users_idle_days = [];
foreach ($all_users_last_logins as $sql_record) {
    $uid = $sql_record["uid"];
    $last_login_str = $sql_record["last_login"];
    $last_login = strtotime($last_login_str);
    $seconds_since_last_login = $now - $last_login;
    $days_since_last_login = round($seconds_since_last_login / (60 * 60 * 24));
    $all_users_idle_days[$uid] = $days_since_last_login;
}

