<?php

/* pi-mgmt.php uses ajax to get this output and then uses it to insert new rows to the PI table */

require_once __DIR__ . "/../../../resources/autoload.php";

use UnityWebPortal\lib\UnityGroup;
use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UserFlag;

if (!$USER->getFlag(UserFlag::ADMIN)) {
    UnityHTTPD::forbidden("not an admin", "You are not an admin.");
}

$gid = UnityHTTPD::getQueryParameter("gid");
$group = new UnityGroup($gid, $LDAP, $SQL, $MAILER, $WEBHOOK);
$members = $group->getGroupMembersAttributes(["gecos", "mail"]);
$requests = $group->getRequests();

$count = count($members) + count($requests);
foreach ($members as $uid => $attributes) {
    if ($uid == $group->getOwner()->uid) {
        continue;
    }
    echo "<tr style='background: var(--light_panel_background);'>";
    $fullname = $attributes["gecos"][0];
    $mail = $attributes["mail"][0];
    echo "<td>$fullname</td>";
    echo "<td>$uid</td>";
    echo "<td>$mail</td>";
    echo "<td>";
    $CSRFTokenHiddenFormInput = UnityHTTPD::getCSRFTokenHiddenFormInput();
    echo "
        <form
            action=''
            method='POST'
            onsubmit='
                return confirm(\"Are you sure you want to remove $uid from this group?\");
            '
        >
        $CSRFTokenHiddenFormInput
        <input type='hidden' name='form_type' value='remUserChild'>
        <input type='hidden' name='uid' value='$uid'>
        <input type='hidden' name='pi' value='$group->gid'>
        <input type='submit' value='Remove'>
        </form>
    ";
    echo "</td>";
    echo "</tr>";
}

foreach ($requests as [$user, $timestamp]) {
    echo "<tr style='background: var(--light_panel_background);'>";
    $name = $user->getFullName();
    $email = $user->getMail();
    echo "<td>$name</td>";
    echo "<td>$user->uid</td>";
    echo "<td>$email</td>";
    echo "<td>";
    $CSRFTokenHiddenFormInput = UnityHTTPD::getCSRFTokenHiddenFormInput();
    echo
        "<form action='' method='POST'
    onsubmit='return confirm(\"Are you sure you want to approve $user->uid ?\");'>
    $CSRFTokenHiddenFormInput
    <input type='hidden' name='form_type' value='reqChild'>
    <input type='hidden' name='uid' value='$user->uid'>
    <input type='hidden' name='pi' value='$group->gid'>
    <input type='submit' name='action' value='Approve'>
    <input type='submit' name='action' value='Deny'></form>";
    echo "</td>";
    echo "</tr>";
}
