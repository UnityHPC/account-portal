<?php

require_once __DIR__ . "/../../resources/autoload.php";

use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UserFlag;
use UnityWebPortal\lib\UnityUser;

if (!$USER->getFlag(UserFlag::ADMIN)) {
    UnityHTTPD::forbidden("not an admin", "You are not an admin.");
}

$pi_uids = $LDAP->getAllNonDisabledPIGroupOwnerUIDs();
$users_with_flags = [];
foreach (UserFlag::cases() as $flag) {
    $users_with_flags[$flag->value] = $LDAP->userFlagGroups[$flag->value]->getMemberUIDs();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    UnityHTTPD::validatePostCSRFToken();
    switch ($_POST["form_type"]) {
        case "viewAsUser":
            $_SESSION["viewUser"] = $_POST["uid"];
            UnityHTTPD::redirect(getRelativeURL("panel/account.php"));
            break; /** @phpstan-ignore deadCode.unreachable */
        case "lockUser":
            $uid = UnityHTTPD::getPostData("uid");
            $user = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
            if (in_array($uid, $users_with_flags[UserFlag::LOCKED->value])) {
                UnityHTTPD::messageError("Cannot lock user, already locked", $uid);
                UnityHTTPD::redirect();
            }
            $user->setFlag(UserFlag::LOCKED, true);
            UnityHTTPD::messageSuccess("User Locked", $uid);
            UnityHTTPD::redirect();
            break; /** @phpstan-ignore deadCode.unreachable */
        case "unlockUser":
            $uid = UnityHTTPD::getPostData("uid");
            $user = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
            if (!in_array($uid, $users_with_flags[UserFlag::LOCKED->value])) {
                UnityHTTPD::messageError("Cannot unlock user, not locked", $uid);
                UnityHTTPD::redirect();
            }
            $user->setFlag(UserFlag::LOCKED, false);
            UnityHTTPD::messageSuccess("User Unlocked", $uid);
            UnityHTTPD::redirect();
            break; /** @phpstan-ignore deadCode.unreachable */
        case "disableUser":
            $uid = UnityHTTPD::getPostData("uid");
            $user = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
            if (in_array($uid, $users_with_flags[UserFlag::DISABLED->value])) {
                UnityHTTPD::messageError("Cannot disable user, already disabled", $uid);
                UnityHTTPD::redirect();
            }
            if ($user->isPI()) {
                UnityHTTPD::messageError("Cannot disable user, user is PI", $uid);
                UnityHTTPD::redirect();
            }
            $user->disable();
            UnityHTTPD::messageSuccess("User Disabled", $uid);
            UnityHTTPD::redirect();
            break; /** @phpstan-ignore deadCode.unreachable */
    }
}

require getTemplatePath("header.php");
$CSRFTokenHiddenFormInput = UnityHTTPD::getCSRFTokenHiddenFormInput();
$flags_to_display = array_filter(UserFlag::cases(), fn($x) => $x !== UserFlag::DISABLED);
?>

<h1>User Management</h1>
<hr>

<table
    id="user-table"
    class="stripe compact hover"
>
    <thead>
        <tr>
            <th>Name</th>
            <th>UID</th>
            <th>Org</th>
            <th>Mail</th>
            <th>Groups</th>
            <th>Actions</th>
<?php foreach ($flags_to_display as $flag) : ?>
            <th><?php echo $flag->value; ?></th>
<?php endforeach ?>
        </tr>
    </thead>
    <tbody>
    <?php
    $UID2PIGIDs = $LDAP->getUID2PIGIDs();
    $user_attributes = $LDAP->getAllNativeUsersAttributes(
        ["uid", "gecos", "o", "mail"],
        default_values: [
            "gecos" => ["(not found)"],
            "o" => ["(not found)"],
            "mail" => ["(not found)"]
        ]
    );
    usort($user_attributes, fn ($a, $b) => strcmp($a["uid"][0], $b["uid"][0]));
    foreach ($user_attributes as $attributes) {
        $uid = $attributes["uid"][0];
        if (in_array($uid, $users_with_flags[UserFlag::DISABLED->value])) {
            continue;
        }
        echo "<tr>";
        echo "<td>" . $attributes["gecos"][0] . "</td>";
        echo "<td>" . $uid . "</td>";
        echo "<td>" . $attributes["o"][0] . "</td>";
        echo "<td>" . $attributes["mail"][0] . "</td>";
        echo "<td>";
        echo "<ul style='padding-left: 2ch; margin: 0;'>";
        if (count($UID2PIGIDs[$uid] ?? []) > 0) {
            foreach ($UID2PIGIDs[$uid] as $gid) {
                echo "<li>$gid</li>";
            }
        }
        echo "</ul>";
        echo "</td>";
        echo "<td>";
        if (in_array($uid, $users_with_flags[UserFlag::LOCKED->value])) {
            $access_button_disabled = "disabled";
            [$action, $action_lowercase, $form_type] = ["Unlock", "unlock", "unlockUser"];
        } else {
            $access_button_disabled = "";
            [$action, $action_lowercase, $form_type] = ["Lock", "lock", "lockUser"];
        }
        if (in_array($uid, $pi_uids)) {
            $disable_button_disabled = "disabled";
            $disable_button_title = "PI group owners cannot be disabled.";
        } else {
            $disable_button_disabled = "";
            $disable_button_title = "";
        }
        echo "
            <div style='display: flex; gap: 5px;'>
                <form action='' method='POST'>
                    $CSRFTokenHiddenFormInput
                    <input type='hidden' name='form_type' value='viewAsUser'>
                    <input type='hidden' name='uid' value='$uid'>
                    <input type='submit' name='action' value='Access' $access_button_disabled>
                </form>
                <form
                    action=''
                    method='POST'
                    onsubmit='return confirm(\"Are you sure you want to $action_lowercase user $uid?\");'
                >
                    $CSRFTokenHiddenFormInput
                    <input type='hidden' name='form_type' value='$form_type'>
                    <input type='hidden' name='uid' value='$uid'>
                    <input type='submit' name='action' value='$action'>
                </form>
                <form
                    action=''
                    method='POST'
                    onsubmit='return confirm(\"🚨 Are you sure you want to DISABLE user $uid? 🚨\");'
                >
                    $CSRFTokenHiddenFormInput
                    <input type='hidden' name='form_type' value='disableUser'>
                    <input type='hidden' name='uid' value='$uid'>
                    <input
                        type='submit'
                        name='action'
                        value='Disable'
                        class='danger'
                        title='$disable_button_title'
                        $disable_button_disabled
                    >
                </form>
            </div>
        ";
        echo "</td>";
        foreach ($flags_to_display as $flag) {
            echo sprintf(
                "<td>%s</td>",
                in_array($uid, $users_with_flags[$flag->value]) ? $flag->value : ""
            );
        }
        echo "</tr>";
    }
    ?>
    </tbody>
</table>

<script>
$(document).ready(() => {
    $('#user-table').DataTable({
        stateSave: true,
        responsive: true,
        columns: [
            {responsivePriority: 2}, // name
            {responsivePriority: 1}, // uid
            {responsivePriority: 2}, // org
            {responsivePriority: 2, render: dataTablesRenderMailtoLink}, // mail
            {responsivePriority: 3, searchable: false}, // groups
            {responsivePriority: 1, searchable: false}, // actions
<?php foreach ($flags_to_display as $flag) : ?>
            {visible: false}, // <?php echo $flag->value . "\n"?>
<?php endforeach ?>
        ],
        layout: {topStart: {buttons: ['colvis']}}
    });
});
</script>
<?php require getTemplatePath("footer.php"); ?>
