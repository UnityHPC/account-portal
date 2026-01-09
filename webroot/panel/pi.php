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
        case "disband":
            $group->disband();
            UnityHTTPD::redirect(getURL("panel/account.php"));
            break; /** @phpstan-ignore deadCode.unreachable */
    }
}

require $LOC_HEADER;
$CSRFTokenHiddenFormInput = UnityHTTPD::getCSRFTokenHiddenFormInput();
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
    echo "<td><a href='mailto:$email'>$email</a></td>";
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

echo "<table>";

foreach ($assocs as $assoc) {
    if ($assoc->uid == $USER->uid) {
        continue;
    }

    echo "<tr>";
    echo "<td>";
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
    echo "<td>" . $assoc->getFirstname() . " " . $assoc->getLastname() . "</td>";
    echo "<td>" . $assoc->uid . "</td>";
    echo "<td><a href='mailto:" . $assoc->getMail() . "'>" . $assoc->getMail() . "</a></td>";
    echo "</tr>";
}

echo "
    </table>
    <hr>
    <h2>Danger Zone</h2>
    <form
        action=''
        method='POST'
        onsubmit='return confirm(\"Are you sure you want to disband your PI group?\")'
    >
        $CSRFTokenHiddenFormInput
        <input type='hidden' name='form_type' value='disband'>
        <input type='submit' value='Disband PI Account'>
    </form>
";

?>

<?php require $LOC_FOOTER; ?>
