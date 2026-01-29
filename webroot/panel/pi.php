<?php

require_once __DIR__ . "/../../resources/autoload.php";

use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UnityGroup;

if (($gid = $_GET["gid"] ?? null) !== null) {
    $group = new UnityGroup($gid, $LDAP, $SQL, $MAILER, $WEBHOOK);
    $user_is_owner = false;
    if (!$group->exists()) {
        UnityHTTPD::badRequest("no such group: '$gid'", "This group does not exist.");
    }
    if (!in_array($USER->uid, $group->getManagerUIDs())) {
        UnityHTTPD::forbidden("not a manager of group '$gid'", "You cannot manage this group.");
    }
} else {
    $group = $USER->getPIGroup();
    $user_is_owner = true;
    if (!$group->exists()) {
        UnityHTTPD::badRequest("not a PI", "You are not a PI.");
    }
}

if ($group->getIsDisabled()) {
    UnityHTTPD::forbidden("group '$gid' is disabled", "This group is disabled.");
}

$getUserFromPost = function () {
    global $LDAP, $SQL, $MAILER, $WEBHOOK;
    return new UnityUser(UnityHTTPD::getPostData("uid"), $LDAP, $SQL, $MAILER, $WEBHOOK);
};

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    UnityHTTPD::validatePostCSRFToken();
    switch ($_POST["form_type"]) {
        case "userReq":
            $form_user = $getUserFromPost();
            if ($_POST["action"] == "Approve") {
                $group->approveUser($form_user);
                UnityHTTPD::messageSuccess("User Approved", "");
                UnityHTTPD::redirect();
            } elseif ($_POST["action"] == "Deny") {
                $group->denyUser($form_user);
                UnityHTTPD::messageSuccess("User Denied", "");
                UnityHTTPD::redirect();
            } else {
                UnityHTTPD::badRequest(sprintf("unrecognized action: '%s'", $_POST["action"]), "");
            }
            break; /** @phpstan-ignore deadCode.unreachable */
        case "remUser":
            $form_user = $getUserFromPost();
            // remove user button clicked
            $group->removeUser($form_user);
            UnityHTTPD::messageSuccess("User Removed", "");
            // group manager removed themself
            if ($USER->uid === $form_user->uid) {
                UnityHTTPD::redirect("/panel/groups.php");
            } else {
                UnityHTTPD::redirect();
            }
            break; /** @phpstan-ignore deadCode.unreachable */
        case "disable":
            if (!$user_is_owner) {
                UnityHTTPD::forbidden("Manager cannot disable", "Only the group owner can disable");
            }
            if (count($group->getMemberUIDs()) > 1) {
                UnityHTTPD::messageError("Cannot Disable PI Group", "Group still has members");
                UnityHTTPD::redirect();
            }
            if ($group->getIsDisabled()) {
                UnityHTTPD::messageError("Cannot Disable PI Group", "Group is already disabled");
                UnityHTTPD::redirect();
            }
            $group->disable();
            UnityHTTPD::messageSuccess("Group Disabled", "");
            UnityHTTPD::redirect(getURL("panel/account.php"));
            break; /** @phpstan-ignore deadCode.unreachable */
    }
}

require getTemplatePath("header.php");
$CSRFTokenHiddenFormInput = UnityHTTPD::getCSRFTokenHiddenFormInput();

if ($user_is_owner) {
    echo "<h1>My Users</h1>";
} else {
    echo sprintf("<h1>PI Group '$group->gid'</h1>");
}
?>
<hr>

<?php
$requests = $group->getRequests();
$assocs = $group->getGroupMembers();


echo "<h2>Pending Requests</h2>";
if (count($requests) === 0) {
    echo "<p>You do not have any pending requests.</p>";
}
echo "<table>";

foreach ($requests as [$user, $timestamp]) {
    $uid = $user->uid;
    $name = $user->getFullName();
    $email = $user->getMail();
    $date = date("jS F, Y", strtotime($timestamp));
    echo "<tr>";
    echo "<td>$name</td>";
    echo "<td>$uid</td>";
    echo "<td>$email</td>";
    echo "<td>$date</td>";
    echo "<td>";
    echo
        "<form action='' method='POST'>
    $CSRFTokenHiddenFormInput
    <input type='hidden' name='form_type' value='userReq'>
    <input type='hidden' name='uid' value='$uid'>
    <input type='submit' name='action' value='Approve'
    onclick='return confirm(\"Are you sure you want to approve $uid?\")'>
    <input type='submit' name='action' value='Deny'
    onclick='return confirm(\"Are you sure you want to deny $uid?\")'>
    </form>";
    echo "</td>";
    echo "</tr>";
}
echo "</table>";

if (count($assocs) > 0) {
    echo "<hr>";
}

echo "<h2>Users in Group</h2>";

if (count($assocs) === 1) {
    $hyperlink = getHyperlink("My PIs", "panel/groups.php");
    echo "
        <p>
            You do not have any users in your group.
            Ask your users to request to join your account on the $hyperlink page.
        </p>
    ";
}

echo "
    <table id='users-table' class='stripe compact hover'>
        <thead>
            <tr>
                <th>Name</th>
                <th>Username</th>
                <th>Mail</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
";

$owner_uid = $group->getOwner()->uid;
foreach ($assocs as $assoc) {
    echo "<tr>";
    echo "<td>" . $assoc->getFirstname() . " " . $assoc->getLastname() . "</td>";
    echo "<td>" . $assoc->uid . "</td>";
    echo "<td>" . $assoc->getMail() . "</td>";
    echo "<td>";
    $disabled = $assoc->uid === $owner_uid ? "disabled" : "";
    echo
        "<form action='' method='POST'>
    $CSRFTokenHiddenFormInput
    <input type='hidden' name='form_type' value='remUser'>
    <input type='hidden' name='uid' value='" . $assoc->uid . "'>
    <input
        type='submit'
        value='Remove'
        onclick='
            return confirm(\"Are you sure you want to remove $assoc->uid from your PI group?\")
        '
        $disabled
    >
    </form>";
    echo "</td>";
    echo "</tr>";
}

echo "
    </tbody>
    </table>
    <hr>
    <h2>Danger Zone</h2>
    <div style='display: flex; flex-direction: row; align-items: center;'>
        <p>
            <strong>Disable PI Group</strong>
            <br>
            Your group's files will be permanently deleted,
            and you will lose access to UnityHPC Platform services
            unless you are a member of some other group.
        </p>
        <form
            action=''
            method='POST'
            onsubmit='return confirm(\"🚨 Are you sure you want to DISABLE your PI group? 🚨\")'
        >
            $CSRFTokenHiddenFormInput
            <input type='hidden' name='form_type' value='disable'>
";
if (!$user_is_owner) {
    echo "
        <input type='submit' value='Disable PI Group' class='danger' disabled>
        <p>Only the group owner can disable the group.</p>
    ";
} elseif (count($assocs) > 1) {
    echo "
        <input type='submit' value='Disable PI Group' class='danger' disabled>
        <p>You must first remove all members before you can disable.</p>
    ";
} else {
    echo "
        <input type='submit' value='Disable PI Group' class='danger'>
    ";
}
echo "</div></form>";

?>

<script>
    $(document).ready(() => {
        $('#users-table').DataTable({
            stateSave: true,
            responsive: true,
            columns: [
                {responsivePriority: 2}, // name
                {responsivePriority: 1}, // uid
                {responsivePriority: 2, render: dataTablesRenderMailtoLink}, // mail
                {responsivePriority: 1, searchable: false}, // actions
            ],
        });
    });
</script>

<?php require getTemplatePath("footer.php"); ?>
