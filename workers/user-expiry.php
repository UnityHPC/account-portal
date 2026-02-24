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
        "To prevent a user from being expired, add them to the 'immortal' user flag group.",
)
    ->opt("dry-run", "Print actions without actually doing anything.", false, "boolean")
    ->opt("verbose", "Print which emails are sent.", false, "boolean")
    ->opt("timestamp", "Use this unix timestamp instead of right now", false, "int")
    ->opt("email-sleep-seconds", "Number of seconds to sleep after sending an email", false, "int");
$args = $cli->parse($argv, true);

$idlelock_warning_days = CONFIG["expiry"]["idlelock_warning_days"];
$idlelock_day = CONFIG["expiry"]["idlelock_day"];
$disable_warning_days = CONFIG["expiry"]["disable_warning_days"];
$disable_day = CONFIG["expiry"]["disable_day"];
$final_disable_warning_day = $disable_warning_days[array_key_last($disable_warning_days)];
$final_idlelock_warning_day = $idlelock_warning_days[array_key_last($idlelock_warning_days)];

if (isset($args["timestamp"])) {
    $now = $args["timestamp"];
} else {
    $now = time();
}

$uid_to_last_login = [];
foreach ($SQL->getAllUserLastLogins() as $record) {
    $uid_to_last_login[$record["operator"]] = strtotime($record["last_login"]);
}

$uid_to_idle_days = [];
foreach ($uid_to_last_login as $uid => $last_login) {
    $idle_seconds = $now - $last_login;
    // round down, err on the side of caution
    $uid_to_idle_days[$uid] = intdiv($idle_seconds, 60 * 60 * 24);
}

$pi_group_members = [];
foreach (
    $LDAP->getAllNonDisabledPIGroupsAttributes(["cn", "memberuid"], ["memberuid" => []])
    as $attributes
) {
    $pi_group_members[$attributes["cn"][0]] = $attributes["memberuid"];
}
$pi_group_owners = array_map(UnityGroup::GID2OwnerUID(...), array_keys($pi_group_members));

$initially_idlelocked_users = $LDAP->userFlagGroups["idlelocked"]->getMemberUIDs();
$initially_disabled_users = $LDAP->userFlagGroups["disabled"]->getMemberUIDs();
$immortal_users = $LDAP->userFlagGroups["immortal"]->getMemberUIDs();

function sendMail(array|string $recipients, string $template, ?array $data = null)
{
    global $MAILER, $args;
    if ($args["verbose"]) {
        printf(
            "sending %s email to %s with data %s\n",
            $template,
            _json_encode($recipients),
            _json_encode($data),
        );
    }
    if (!$args["dry-run"]) {
        $MAILER->sendMail($recipients, $template, $data);
        if (isset($args["email-sleep-seconds"])) {
            sleep($args["email-sleep-seconds"]);
        }
    }
}

function sendUserExpiryNoticeToPIGroupOwners(string $template, UnityUser $user)
{
    global $LDAP, $SQL, $MAILER, $WEBHOOK;
    foreach ($LDAP->getNonDisabledPIGroupGIDsWithMemberUID($user->uid) as $gid) {
        $group = new UnityGroup($gid, $LDAP, $SQL, $MAILER, $WEBHOOK);
        sendMail($group->getOwnerMailAndPlusAddressedManagerMails(), $template, [
            "group" => $gid,
            "user" => $user->uid,
            "org" => $user->getOrg(),
            "name" => $user->getFullname(),
            "email" => $user->getMail(),
        ]);
    }
}

function idleLockUser(UnityUser $user)
{
    global $args;
    echo "idle-locking user '$user->uid'\n";
    if (!$args["dry-run"]) {
        sendUserExpiryNoticeToPIGroupOwners("group_user_idlelocked_owner", $user);
        $user->setFlag(UserFlag::IDLELOCKED, true);
    }
}

function disableUser(UnityUser $user)
{
    global $args;
    echo "disabling user '$user->uid'\n";
    if (!$args["dry-run"]) {
        sendUserExpiryNoticeToPIGroupOwners("group_user_disabled_owner", $user);
        $user->disable(send_mail_pi_group_owner: false);
    }
}

function idleLockWarnUser(UnityUser $user, int $day)
{
    global $uid_to_idle_days, $uid_to_last_login, $idlelock_day, $final_idlelock_warning_day;
    $last_login = $uid_to_last_login[$user->uid];
    $idle_days = $uid_to_idle_days[$user->uid];
    $expiration_date = date("Y/m/d", $last_login + $idlelock_day * 24 * 60 * 60);
    $is_final_warning = $day === $final_idlelock_warning_day;
    sendMail($user->getMail(), "user_expiry_idlelock_warning", [
        "idle_days" => $idle_days,
        "expiration_date" => $expiration_date,
        "is_final_warning" => $is_final_warning,
    ]);
}

function disableWarnUser(UnityUser $user, int $day)
{
    global $uid_to_idle_days,
        $uid_to_last_login,
        $pi_group_owners,
        $pi_group_members,
        $disable_day,
        $final_disable_warning_day,
        $LDAP,
        $SQL,
        $MAILER,
        $WEBHOOK;
    $last_login = $uid_to_last_login[$user->uid];
    $idle_days = $uid_to_idle_days[$user->uid];
    $expiration_date = date("Y/m/d", $last_login + $disable_day * 24 * 60 * 60);
    $is_final_warning = $day === $final_disable_warning_day;
    $pi_group_gid = UnityGroup::ownerUID2GID($user->uid);
    $pi_group_member_uids = $pi_group_members[$pi_group_gid] ?? [];
    $mail_template_data = [
        "idle_days" => $idle_days,
        "expiration_date" => $expiration_date,
        "is_final_warning" => $is_final_warning,
    ];
    if (!in_array($user->uid, $pi_group_owners)) {
        sendMail($user->getMail(), "user_expiry_disable_warning_non_pi", $mail_template_data);
    } else {
        $mail_template_data["pi_group_gid"] = $pi_group_gid;
        $owner = $user;
        sendMail($owner->getMail(), "user_expiry_disable_warning_pi", $mail_template_data);
        if (count($pi_group_member_uids) > 1) {
            $members = [];
            foreach ($pi_group_member_uids as $member_uid) {
                $member = new UnityUser($member_uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
                if ($member != $owner) {
                    array_push($members, $member);
                }
            }
            $member_mails = array_map(fn($x) => $x->getMail(), $members);
            sendMail($member_mails, "user_expiry_disable_warning_member", $mail_template_data);
        }
    }
}

foreach ($uid_to_idle_days as $uid => $day) {
    if (in_array($uid, $immortal_users)) {
        continue;
    }
    $user = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
    if (!$user->exists()) {
        continue;
    }
    if (in_array($day, $idlelock_warning_days)) {
        idleLockWarnUser($user, $day);
    }
    if (in_array($day, $disable_warning_days)) {
        disableWarnUser($user, $day);
    }
    if ($day === $idlelock_day) {
        idleLockUser($user);
    }
    if ($day === $disable_day) {
        disableUser($user);
    }
    if (!in_array($uid, $initially_idlelocked_users) && $day > $idlelock_day) {
        echo "WARNING: user '$uid' should have already been idlelocked, but isn't!\n";
    }
    if (!in_array($uid, $initially_disabled_users) && $day > $disable_day) {
        echo "WARNING: user '$uid' should have already been disabled, but isn't!\n";
    }
}

if ($args["dry-run"]) {
    echo "[DRY RUN]\n";
}

