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

$getPIGroupFromPost = function () {
    global $LDAP, $SQL, $MAILER, $WEBHOOK;
    return new UnityGroup(UnityHTTPD::getPostData("pi"), $LDAP, $SQL, $MAILER, $WEBHOOK);
};

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    UnityHTTPD::validatePostCSRFToken();
    switch (UnityHTTPD::getPostData("form_type")) {
        case "req":
            $form_user = $getUserFromPost();
            if (UnityHTTPD::getPostData("action") == "Approve") {
                $group = $form_user->getPIGroup();
                $group->approveGroup();
            } elseif (UnityHTTPD::getPostData("action") == "Deny") {
                $group = $form_user->getPIGroup();
                $group->denyGroup();
            }
            break;
        case "reqChild":
            $form_user = $getUserFromPost();
            $parent_group = $getPIGroupFromPost();
            if (UnityHTTPD::getPostData("action") == "Approve") {
                $parent_group->approveUser($form_user);
            } elseif (UnityHTTPD::getPostData("action") == "Deny") {
                $parent_group->denyUser($form_user);
            }
            break;
        case "remUserChild":
            $form_user = $getUserFromPost();
            $parent = $getPIGroupFromPost();
            $parent->removeUser($form_user);
            break;
    }
}

require $LOC_HEADER;
$CSRFTokenHiddenFormInput = UnityHTTPD::getCSRFTokenHiddenFormInput();
?>

<h1>PI Management</h1>
<hr>

<!-- <input type="text" id="tableSearch" placeholder="Search..."> -->

<h2>Pending PI Requests</h2>
<table class="searchable">
    <tr>
        <th>Name</th>
        <th>Unity ID</th>
        <th>Mail</th>
        <th>Requested On</th>
        <th>Actions</th>
    </tr>

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
        echo "<td><a href='mailto:$email'>$email</a></td>";
        echo "<td>" . date("jS F, Y", strtotime($request['timestamp'])) . "</td>";
        echo "<td>";
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

</table>

<h2>List of PIs</h2>

<table class="searchable longTable sortable filterable">
    <tr>
        <input
            type="text"
            style="margin-right:5px;"
            placeholder="Filter by..."
            id="common-filter"
            class="filterSearch"
        >
        <th id="name"><span class="filter">⫧ </span>Name</th>
        <th id="unityID"><span class="filter">⫧ </span>Unity ID</th>
        <th id="mail"><span class="filter">⫧ </span>Mail</th>
        <th>Actions</th>
    </tr>

    <?php
    $owner_uids = $LDAP->getAllPIGroupOwnerUIDs();
    $owner_attributes = $LDAP->getUsersAttributes(
        $owner_uids,
        ["uid", "gecos", "mail"],
        default_values: ["gecos" => ["(not found)"], "mail" => ["(not found)"]]
    );
    usort($owner_attributes, fn($a, $b) => strcmp($a["uid"][0], $b["uid"][0]));
    foreach ($owner_attributes as $attributes) {
        $mail = $attributes["mail"][0];
        echo "<tr class='expandable'>";
        echo "<td><button class='btnExpand'>&#9654;</button>" . $attributes["gecos"][0] . "</td>";
        echo "<td>" . UnityGroup::OwnerUID2GID($attributes["uid"][0]) . "</td>";
        echo "<td><a href='mailto:$mail'>$mail</a></td>";
        echo "</tr>";
    }
    ?>
</table>

<script>
    $("table tr.tr-pichild").hide(); // Hide the children first (and then the women)

    $("table tr").click(function () {
        if (!$(this).hasClass("tr-pichild")) {
            var current = $(this).next();
            while (current.hasClass("tr-pichild")) {
                if (current.is(":visible")) {
                    current.hide();
                } else {
                    current.show();
                }
                current = current.next();
            }
        }
    });

    var ajax_url = "<?php echo getURL("admin/ajax/get_group_members.php"); ?>?gid=";
</script>

<?php require $LOC_FOOTER; ?>
