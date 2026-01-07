<?php

require_once __DIR__ . "/../../resources/autoload.php";

use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UserFlag;
use UnityWebPortal\lib\CSRFToken;

if (!$USER->getFlag(UserFlag::ADMIN)) {
    UnityHTTPD::forbidden("not an admin", "You are not an admin.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    UnityHTTPD::validatePostCSRFToken();
    switch ($_POST["form_type"]) {
        case "viewAsUser":
            $_SESSION["viewUser"] = $_POST["uid"];
            UnityHTTPD::redirect(getURL("panel/account.php"));
            break; /** @phpstan-ignore deadCode.unreachable */
    }
}

require $LOC_HEADER;
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
    $csrf_token = htmlspecialchars(CSRFToken::generate());
    usort($user_attributes, fn ($a, $b) => strcmp($a["uid"][0], $b["uid"][0]));
    foreach ($user_attributes as $attributes) {
        $uid = $attributes["uid"][0];
        if ($SQL->accDeletionRequestExists($uid)) {
            echo "<tr style='color:#555555; font-style: italic'>";
        } else {
            echo "<tr>";
        }
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
        echo "<form class='viewAsUserForm' action='' method='POST'
        onsubmit='return confirm(\"Are you sure you want to switch to the user $uid?\");'>
        <input type='hidden' name='csrf_token' value='$csrf_token'>
        <input type='hidden' name='form_type' value='viewAsUser'>
        <input type='hidden' name='uid' value='$uid'>
        <input type='submit' name='action' value='Access'>
        </form>";
        echo "</td>";
        echo "</tr>";
    }
    ?>
    </tbody>
</table>

<script>
$(document).ready(() => {
    $('#user-table').DataTable({
        responsive: true,
        columns: [
            {responsivePriority: 2}, // name
            {responsivePriority: 1}, // uid
            {responsivePriority: 2}, // org
            {responsivePriority: 2}, // mail
            {responsivePriority: 3}, // groups
            {responsivePriority: 1}, // actions
        ],
        layout: {topStart: {buttons: ['colvis']}}
    });
});
</script>
<?php require $LOC_FOOTER; ?>
