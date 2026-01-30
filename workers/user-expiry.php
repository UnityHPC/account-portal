#!/usr/bin/env php
<?php
include __DIR__ . "/init.php";
use Garden\Cli\Cli;
use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UnityGroup;
use UnityWebPortal\lib\UserFlag;

$cli = new Cli();
$cli->description(
    "Send a warning email, idlelock, or disable users, depending on their last login date. " .
        "It is important that this script runs exactly once per day." .
        "To prevent a user from being expired, remove them from the `user_last_logins` SQL table.",
)
    ->opt("dry-run", "Print actions without actually doing anything.", false, "boolean")
    ->opt("verbose", "Print which emails are sent.", false, "boolean");
$args = $cli->parse($argv, true);

$idlelock_warning_days = CONFIG["expiry"]["idlelock_warning_days"];
$idlelock_day = CONFIG["expiry"]["idlelock_day"];
$disable_warning_days = CONFIG["expiry"]["disable_warning_days"];
$disable_day = CONFIG["expiry"]["disable_day"];
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
    _die('$CONFIG["expiry"]["idlelock_warning_days"] must be a list of integers!', 1);
}
if (!doesArrayHaveOnlyIntegerValues($disable_warning_days)) {
    _die('$CONFIG["expiry"]["disable_warning_days"] must be a list of integers!', 1);
}
if (!isArrayMonotonicallyIncreasing($idlelock_warning_days)) {
    _die('$CONFIG["expiry"]["idlelock_warning_days"] must be monotonically increasing!', 1);
}
if (!isArrayMonotonicallyIncreasing($disable_warning_days)) {
    _die('$CONFIG["expiry"]["disable_warning_days"] must be monotonically increasing!', 1);
}

$final_disable_warning_day = $disable_warning_days[array_key_last($disable_warning_days)];
$final_idlelock_warning_day = $idlelock_warning_days[array_key_last($idlelock_warning_days)];
if ($disable_day <= $final_disable_warning_day) {
    _die("disable day must be greater than the last disable warning day", 1);
}
if ($idlelock_day <= $final_idlelock_warning_day) {
    _die("idlelock day must be greater than the last idlelock warning day", 1);
}
if ($disable_day <= $idlelock_day) {
    _die("disable day day must be greater than idlelock day", 1);
}

$uid_to_last_login = [];
foreach ($SQL->getAllUserLastLogins() as $record) {
    $uid_to_last_login[$record["operator"]] = strtotime($record["last_login"]);
}

$uid_to_idle_days = [];
foreach ($uid_to_last_login as $uid => $last_login) {
    $seconds_since_last_login = $now - $uid_to_last_login[$uid];
    // round down, err on the side of caution
    $uid_to_idle_days[$uid] = intdiv($seconds_since_last_login, 60 * 60 * 24);
}

$pi_group_members = [];
foreach (
    $LDAP->getAllNonDisabledPIGroupsAttributes(["cn", "memberuid"], ["memberuid" => []])
    as $attributes
) {
    $pi_group_members[$attributes["cn"][0]] = $attributes["memberuid"];
}
$pi_group_owners = array_map(UnityGroup::GID2OwnerUID(...), array_keys($pi_group_members));

function sendMail(string $type, array|string $recipients, string $template, ?array $data = null)
{
    global $MAILER, $args;
    if ($args["verbose"]) {
        printf(
            "sending %s email to %s with data %s\n",
            $type,
            _json_encode($recipients),
            _json_encode($data),
        );
    }
    if (!$args["dry-run"]) {
        $MAILER->sendMail($recipients, $template, $data);
    }
}

function idleLockUser($uid)
{
    global $args, $LDAP, $SQL, $MAILER, $WEBHOOK;
    echo "idle-locking user '$uid'";
    if (!$args["dry-run"]) {
        $user = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
        $user->setFlag(UserFlag::IDLELOCKED, true);
    }
}

function disableUser($uid)
{
    global $args, $LDAP, $SQL, $MAILER, $WEBHOOK;
    echo "disabling user '$uid'";
    if (!$args["dry-run"]) {
        $user = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
        $user->disable();
    }
}

foreach ($uid_to_idle_days as $uid => $day) {
    $last_login = $uid_to_last_login[$uid];
    if (in_array($day, $idlelock_warning_days)) {
        $idle_days = $uid_to_idle_days[$uid];
        $expiration_date = date("Y/m/d", $last_login + $idlelock_day * 24 * 60 * 60);
        $is_final_warning = $day === $final_idlelock_warning_day;
        $user = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
        sendMail("idlelock", $user->getMail(), "user_expiry_idlelock_warning", [
            "idle_days" => $idle_days,
            "expiration_date" => $expiration_date,
            "is_final_warning" => $is_final_warning,
        ]);
    }
    if (in_array($day, $disable_warning_days)) {
        $idle_days = $uid_to_idle_days[$uid];
        $expiration_date = date("Y/m/d", $last_login + $disable_day * 24 * 60 * 60);
        $is_final_warning = $day === $final_disable_warning_day;
        $pi_group_gid = UnityGroup::ownerUID2GID($uid);
        $pi_group_member_uids = $pi_group_members[$pi_group_gid] ?? [];
        $mail_template_data = [
            "idle_days" => $idle_days,
            "expiration_date" => $expiration_date,
            "is_final_warning" => $is_final_warning,
        ];
        if (!in_array($uid, $pi_group_owners)) {
            $user = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
            sendMail(
                "disable",
                $user->getMail(),
                "user_expiry_disable_warning_non_pi",
                $mail_template_data,
            );
        } else {
            $mail_template_data["pi_group_gid"] = $pi_group_gid;
            $owner_uid = $uid;
            $owner = new UnityUser($owner_uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
            sendMail(
                "disable",
                $owner->getMail(),
                "user_expiry_disable_warning_pi",
                $mail_template_data,
            );
            if (count($pi_group_member_uids) > 1) {
                $members = [];
                foreach ($pi_group_member_uids as $member_uid) {
                    $member = new UnityUser($member_uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
                    if ($member != $owner) {
                        array_push($members, $member);
                    }
                }
                $member_mails = array_map(fn($x) => $x->getMail(), $members);
                sendMail(
                    "disable (to PI group members)",
                    $member_mails,
                    "user_expiry_disable_warning_member",
                    $mail_template_data,
                );
            }
        }
    }
    if ($day === $idlelock_day) {
        idleLockUser($uid);
    }
    if ($day === $disable_day) {
        disableUser($uid);
    }
}

if ($args["dry-run"]) {
    echo "[DRY RUN]\n";
}

