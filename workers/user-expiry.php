#!/usr/bin/env php
<?php
include __DIR__ . "/init.php";
use Garden\Cli\Cli;
use UnityWebPortal\lib\UnityUserExpirationWarningType;
use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UnityGroup;
use UnityWebPortal\lib\UserFlag;

$cli = new Cli();
$cli->description(
    "Send a warning email, idlelock, or disable users, depending on their last login date. " .
        "It is important that this script runs exactly once per day.",
)
    ->opt("dry-run", "Print actions without actually doing anything.", false, "boolean")
    ->opt("verbose", "Print which emails are sent.", false, "boolean");
$args = $cli->parse($argv, true);

$idlelock_warning_days = CONFIG["user_expiry"]["idlelock_warning_days"];
$idlelock_day = CONFIG["user_expiry"]["idlelock_day"];
$disable_warning_days = CONFIG["user_expiry"]["idlelock_warning_days"];
$disable_day = CONFIG["user_expiry"]["disable_day"];
$now = time();

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

$final_disable_warning_day = $disable_warning_days[array_key_last($disable_warning_days)];
$final_idlelock_warning_day = $idlelock_warning_days[array_key_last($idlelock_warning_days)];
if ($disable_day <= $final_disable_warning_day) {
    _die("disable day must be greater than the last disable warning day", 1);
}
if ($idlelock_day <= $final_idlelock_warning_day) {
    _die("idlelock day must be greater than the last idlelock warning day", 1);
}

$uid_to_last_login = [];
foreach ($SQL->getAllUserLastLogins() as $record) {
    $uid_to_last_login[$record["uid"]] = strtotime($record["last_login"]);
}

$uid_to_idle_days = [];
foreach ($uid_to_last_login as $uid => $last_login) {
    $seconds_since_last_login = $now - $uid_to_last_login[$uid];
    $uid_to_idle_days[$uid] = round($seconds_since_last_login / (60 * 60 * 24));
}

$uid_to_warnings_sent = [];
foreach ($SQL->getAllUsersExpirationWarningDaysSent() as $record) {
    $uids_to_warnings_sent[$record["uid"]] = [
        "idlelock" => $record["idlelock"],
        "disable" => $record["disable"],
    ];
}

$pi_group_members = [];
foreach ($LDAP->getAllPIGroupsAttributes(["cn", "memberuid"], ["memberuid" => []]) as $attributes) {
    $pi_group_members[$attributes["cn"][0]] = $attributes["memberuid"];
}
$pi_group_owners = array_map(UnityGroup::GID2OwnerUID(...), array_keys($pi_group_members));

$uids_to_send_idlelock_warning = [];
$uids_to_send_disable_warning = [];
$uids_to_idlelock = [];
$uids_to_disable = [];
foreach ($uid_to_idle_days as $uid => $day) {
    if (in_array($day, $idlelock_warning_days)) {
        array_push($uids_to_send_idlelock_warning, $uid);
    }
    if (in_array($day, $disable_warning_days)) {
        array_push($uids_to_send_disable_warning, $uid);
    }
    if ($day === $idlelock_day) {
        array_push($uids_to_idlelock, $uid);
    }
    if ($day === $disable_day) {
        array_push($uids_to_disable, $uid);
    }
}

$action_log = [];
foreach ($uids_to_send_disable_warning as $uid) {
    $idle_days = $uid_to_idle_days[$uid];
    $days_remaining = $disable_day - $idle_days;
    $warnings_sent = $uid_to_warnings_sent[$uid]["disable"];
    $warning_number = count($warnings_sent) + 1;
    $is_final_warning = $warning_number === $final_disable_warning_day;
    $pi_group_gid = UnityGroup::ownerUID2GID($uid);
    $pi_group_member_uids = $pi_group_members[$pi_group_gid] ?? [];
    $mail_template_data = [
        "idle_days" => $idle_days,
        "days_remaining" => $days_remaining,
        "warning_number" => $warning_number,
        "is_final_warning" => $is_final_warning,
    ];
    if (count($pi_group_member_uids) > 0) {
        $mail_template_data["pi_group_gid"] = $pi_group_gid;
        if ($args["verbose"]) {
            array_push(
                $action_log,
                sprintf(
                    "PI disable warning: data=%s members=%s",
                    jsonEncode($pi_group_member_uids),
                    jsonEncode($mail_template_data),
                ),
            );
        }
        if (!$args["dry-run"]) {
            $owner = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
            $MAILER->sendMail(
                $owner->getMail(),
                "user_expiry_disable_warning_pi.php",
                $mail_template_data,
            );
            $members = [];
            foreach ($pi_group_member_uids as $uid) {
                array_push($members, new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK));
            }
            $member_mails = array_map(fn($x) => $x->getMail(), $members);
            $MAILER->sendMail(
                $member_mails,
                "user_expiry_disable_warning_member.php",
                $mail_template_data,
            );
        }
    } else {
        if ($args["verbose"]) {
            array_push(
                $action_log,
                sprintf("disable warning: uid=%s data=%s", $uid, jsonEncode($mail_template_data)),
            );
        }
        if (!$args["dry-run"]) {
            $owner = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
            $MAILER->sendMail($owner->getMail(), "user_expiry_disable_warning_non_pi.php", [
                "idle_days" => $day,
                "days_remaining" => $days_remaining,
                "warning_number" => $warning_number,
            ]);
        }
    }
    if ($args["verbose"]) {
        $summary = "";
        array_push($action_log, sprintf("send disable warning email to user '%s': %s"));
    }
    $MAILER->sendMail($pi_user->getMail(), "group_user_request_owner", []);
}
foreach ($uids_to_idlelock as $uid) {
    array_push($action_log, "idlelock user '$uid'");
    if (!$args["dry-run"]) {
        $user = new UnityUser($uid, $LDAP, $SLQ, $MAILER, $WEBHOOK);
        $user->setFlag(UserFlag::IDLELOCKED, true);
    }
}
foreach ($uids_to_disable as $uid) {
    array_push($action_log, "disable user '$uid'");
    if (!$args["dry-run"]) {
        $user = new UnityUser($uid, $LDAP, $SLQ, $MAILER, $WEBHOOK);
        $user->disable();
    }
}

echo jsonEncode($action_log, JSON_PRETTY_PRINT) . "\n";
if ($args["dry-run"]) {
    echo "dry run, nothing doing.\n";
}

