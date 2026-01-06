<?php

require_once __DIR__ . "/../../resources/autoload.php";

use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UnityHTTPD;

$group = $USER->getPIGroup();

if (!$USER->isPI()) {
    UnityHTTPD::forbidden("not a PI", "You are not a PI.");
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
            } elseif ($_POST["action"] == "Deny") {
                $group->denyUser($form_user);
            }
            break;
        case "remUser":
            $form_user = $getUserFromPost();
            // remove user button clicked
            $group->removeUser($form_user);

            break;
    }
}

require $LOC_HEADER;
?>

<h1>My Users</h1>
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
    $CSRFTokenHiddenFormInput = UnityHTTPD::getCSRFTokenHiddenFormInput();
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

foreach ($assocs as $assoc) {
    if ($assoc->uid == $USER->uid) {
        continue;
    }

    echo "<tr>";
    echo "<td>" . $assoc->getFirstname() . " " . $assoc->getLastname() . "</td>";
    echo "<td>" . $assoc->uid . "</td>";
    echo "<td>" . $assoc->getMail() . "</td>";
    echo "<td>";
    $CSRFTokenHiddenFormInput = UnityHTTPD::getCSRFTokenHiddenFormInput();
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
    >
    </form>";
    echo "</td>";
    echo "</tr>";
}
echo "</tbody>";
echo "</table>";
?>

<script>
    $(document).ready(() => {
        $('#users-table').DataTable({
            responsive: true,
            columns: [
                {data: "name", responsivePriority: 2},
                {data: "uid", responsivePriority: 1},
                {data: "mail", responsivePriority: 2},
                {data: "actions", responsivePriority: 1},
            ],
        });
    });
</script>

<?php require $LOC_FOOTER; ?>
