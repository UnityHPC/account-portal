<?php

require_once __DIR__ . "/../../resources/autoload.php";

use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UnityGroup;
use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UnitySQL;
use UnityWebPortal\lib\UserFlag;

if (!$USER->getFlag(UserFlag::ADMIN)) {
    UnityHTTPD::forbidden("not an admin", "You are not an admin.");
}

$getUserFromPost = function () {
    global $LDAP, $SQL, $MAILER, $WEBHOOK;
    return new UnityUser(UnityHTTPD::getPostData("uid"), $LDAP, $SQL, $MAILER, $WEBHOOK);
};

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    UnityHTTPD::validatePostCSRFToken();
    switch ($_POST["form_type"]) {
        case "req":
            $form_user = $getUserFromPost();
            if ($_POST["action"] == "Approve") {
                $group = $form_user->getPIGroup();
                $group->approveGroup();
            } elseif ($_POST["action"] == "Deny") {
                $group = $form_user->getPIGroup();
                $group->denyGroup();
            }
            break;
        case "reqChild":
            $form_user = $getUserFromPost();
            $parent_group = new UnityGroup($_POST["pi"], $LDAP, $SQL, $MAILER, $WEBHOOK);
            if ($_POST["action"] == "Approve") {
                $parent_group->approveUser($form_user);
            } elseif ($_POST["action"] == "Deny") {
                $parent_group->denyUser($form_user);
            }
            break;
        case "remUserChild":
            $form_user = $getUserFromPost();
            $parent = new UnityGroup($_POST["pi"], $LDAP, $SQL, $MAILER, $WEBHOOK);
            $parent->removeUser($form_user);
            break;
    }
}

require $LOC_HEADER;
?>

<h1>PI Management</h1>
<hr>

<!-- <input type="text" id="tableSearch" placeholder="Search..."> -->

<h2>Pending PI Requests</h2>
<table id="pi-request-table" class="stripe compact hover">
    <thead>
        <tr>
            <th>Name</th>
            <th>UID</th>
            <th>Mail</th>
            <th>Requested On</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $requests = $SQL->getRequests(UnitySQL::REQUEST_BECOME_PI);

    foreach ($requests as $request) {
        $uid = $request["uid"];
        $request_user = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
        $name = $request_user->getFullname();
        $email = $request_user->getMail();
        echo "<tr>";
        echo "<td>$name</td>";
        echo "<td>$uid</td>";
        echo "<td>$email</td>";
        echo "<td>" . $request['timestamp'] . "</td>";
        echo "<td>";
        $CSRFTokenHiddenFormInput = UnityHTTPD::getCSRFTokenHiddenFormInput();
        echo
            "<form action='' method='POST'>
        $CSRFTokenHiddenFormInput
        <input type='hidden' name='form_type' value='req'>
        <input type='hidden' name='uid' value='$uid'>
        <input type='submit' name='action' value='Approve'
        onclick='return confirm(\"Are you sure you want to approve $uid?\");'>
        <input type='submit' name='action' value='Deny'
        onclick='return confirm(\"Are you sure you want to deny $uid?\");'>
        </form>";
        echo "</td>";
        echo "</tr>";
    }
    ?>
    </tbody>
</table>

<h2>List of PIs</h2>

<table id="pi-table" class="stripe compact hover">
    <thead>
        <tr>
            <th>Name</th>
            <th>Unity ID</th>
            <th>Mail</th>
            <th>Members</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $pi_groups_attributes = $LDAP->getAllPIGroupsAttributes(["cn", "memberuid"]);
    $pi_group_gid_to_member_uids = [];
    $pi_group_gid_to_owner_uid = [];
    foreach ($pi_groups_attributes as $group_attributes) {
        $gid = $group_attributes["cn"][0];
        $pi_group_gid_to_owner_uid[$gid] = UnityGroup::GID2OwnerUID($gid);
        $pi_group_gid_to_member_uids[$gid] = $group_attributes["memberuid"];
    }
    $pi_group_owners_attributes = $LDAP->getUsersAttributes(
        array_values($pi_group_gid_to_owner_uid),
        ["uid", "gecos", "mail"],
        default_values: ["gecos" => ["(not found)"], "mail" => ["(not found)"]]
    );
    foreach ($pi_group_gid_to_owner_uid as $gid => $uid) {
        $owner_attributes = $pi_group_owners_attributes[$uid];
        $gecos = $owner_attributes["gecos"][0];
        $mail = $owner_attributes["mail"][0];
        $members = $pi_group_gid_to_member_uids[$gid];
        echo "<tr>";
        echo "<td>$gecos</td>";
        echo "<td>$uid</td>";
        echo "<td>$mail</td>";
        echo "<td><ul>";
        foreach ($members as $member_uid) {
            echo "<li>$member_uid</li>";
        }
        echo "</ul></td>";
        echo "</tr>";
    }
    ?>
    </tbody>
</table>

<script>
    $(document).ready(() => {
        $('#pi-request-table').DataTable({
            responsive: true,
            columns: [
                {responsivePriority: 2}, // name
                {responsivePriority: 1}, // uid
                {responsivePriority: 2}, // mail
                {responsivePriority: 2}, // requested on
                {responsivePriority: 1}, // actions
            ],
            layout: {topStart: {buttons: ['colvis']}}
        });
        $('#pi-table').DataTable({
            responsive: true,
            columns: [
                {responsivePriority: 1}, // name
                {responsivePriority: 1}, // uid
                {responsivePriority: 1}, // mail,
                {responsivePriority: 2, visible: false}, // members
            ],
            layout: {topStart: {buttons: ['colvis']}}
        });
    });
</script>

<?php require $LOC_FOOTER; ?>
