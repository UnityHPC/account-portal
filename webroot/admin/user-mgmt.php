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

<!-- <input type="text" id="tableSearch" placeholder="Search..."> -->

<table class="searchable longTable sortable filterable column-toggle" id="user-table">
    <tr>
        <input
            type="text"
            style="margin-right:5px;"
            placeholder="Filter by..."
            id="common-filter"
            class="filterSearch"
        >
        <th id="name"><span class="filter">⫧ </span>Name</th>
        <th id="uid"><span class="filter">⫧ </span>UID</th>
        <th id="org" class="hidden-by-default"><span class="filter">⫧ </span>Org</th>
        <th id="mail"><span class="filter">⫧ </span>Mail</th>
        <th id="groups"><span class="filter">⫧ </span>Groups</th>
        <th>Actions</th>
        <?php
        foreach (UserFlag::cases() as $flag) {
            $val = $flag->value;
            echo "<th id='$val' class='hidden-by-default'><span class='filter'>⫧ </span>$val</th>";
        }
        ?>
    </tr>

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
    $users_with_flags = [];
    foreach (UserFlag::cases() as $flag) {
        $users_with_flags[$flag->value] = $LDAP->userFlagGroups[$flag->value]->getMemberUIDs();
    }
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
        echo "
            <td>
                <a href='mailto:" . $attributes["mail"][0] . "'>" . $attributes["mail"][0] . "</a>
            </td>
        ";
        echo "<td>";
        if (array_key_exists($uid, $UID2PIGIDs) && count($UID2PIGIDs[$uid] ?? []) > 0) {
            echo "<table>";
            foreach ($UID2PIGIDs[$uid] as $gid) {
                echo "<tr><td>$gid</td></tr>";
            }
            echo "</table>";
        }
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
        foreach (UserFlag::cases() as $flag) {
            echo "<td>";
            if (in_array($uid, $users_with_flags[$flag->value])) {
                echo $flag->value;
            }
            echo "</td>";
        }
        echo "</tr>";
    }
    ?>
</table>

<?php require $LOC_FOOTER; ?>
