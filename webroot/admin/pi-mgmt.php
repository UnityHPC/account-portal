<?php

require_once __DIR__ . "/../../resources/autoload.php";

use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UnityGroup;
use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UnitySQL;
use UnityWebPortal\lib\UserFlag;
use UnityWebPortal\lib\UnityDeployment;

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
        case "disable":
            $group = new UnityGroup(UnityHTTPD::getPostData("pi"), $LDAP, $SQL, $MAILER, $WEBHOOK);
            if ($group->getIsDisabled()) {
                UnityHTTPD::messageError("Cannot Disable PI Group", "Group is already disabled");
                UnityHTTPD::redirect();
            }
            $group->disable();
            UnityHTTPD::messageSuccess("Group Disabled", "");
            break;
    }
}

require UnityDeployment::getTemplatePath("header.php");
$CSRFTokenHiddenFormInput = UnityHTTPD::getCSRFTokenHiddenFormInput();
?>

<h1>PI Management</h1>
<hr>

<!-- <input type="text" id="tableSearch" placeholder="Search..."> -->

<h2>PI Group Requests</h2>
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

<br>
<h2>PI Groups</h2>

<table id="pi-table" class="stripe compact hover">
    <thead>
        <tr>
            <th>Name</th>
            <th>GID</th>
            <th>Mail</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $owner_uids = $LDAP->getAllNonDisabledPIGroupOwnerUIDs();
    $owner_attributes = $LDAP->getUsersAttributes(
        $owner_uids,
        ["uid", "gecos", "mail"],
        default_values: ["gecos" => ["(not found)"], "mail" => ["(not found)"]]
    );
    usort($owner_attributes, fn($a, $b) => strcmp($a["uid"][0], $b["uid"][0]));
    foreach ($owner_attributes as $attributes) {
        $gecos = $attributes["gecos"][0];
        $gid = UnityGroup::OwnerUID2GID($attributes["uid"][0]);
        $mail = $attributes["mail"][0];
        echo "
            <tr>
                <td>$gecos</td>
                <td>$gid</td>
                <td>$mail</td>
                <td>
                    <form
                        action=''
                        method='POST'
                        onsubmit='return confirm(
                            \"🚨 Are you sure you want to REMOVE ALL MEMBERS and DISABLE group $gid? 🚨\"
                        )'
                    >
                        $CSRFTokenHiddenFormInput
                        <input type='hidden' name='form_type' value='disable'>
                        <input type='hidden' name='pi' value='$gid'>
                        <input class='danger' type='submit' value='Disable'>
                    </form>
                </td>
            </tr>
        ";
    }
    ?>
    </tbody>
</table>

<script>
    $(document).ready(() => {
        let pi_request_datatable = $('#pi-request-table').DataTable({
            responsive: true,
            stateSave: true,
            columns: [
                {responsivePriority: 2}, // name
                {responsivePriority: 1}, // uid
                {responsivePriority: 2, render: dataTablesRenderMailtoLink}, // mail
                {responsivePriority: 2, searchable: false}, // requested_on
                {responsivePriority: 1, searchable: false}, // actions
            ],
        });
        let pi_datatable = $('#pi-table').DataTable({
            stateSave: true,
            columns: [
                {className: 'details-control'}, // name
                {}, // gid
                {render: dataTablesRenderMailtoLink}, // mail
                {searchable: false}, // actions
            ]
        });
        // https://datatables.net/blog/2017/ajax-loaded-row-details
        // https://datatables.net/forums/discussion/42045/nested-tables
        $('#pi-table tbody').on('click', 'td.details-control', function() {
            var tr = $(this).closest('tr');
            var row = pi_datatable.row(tr);
            if (row.child.isShown()) {
                row.child.hide();
                tr.removeClass('shown');
            }
            else {
                const gid = tr.children('td').eq(1).text().trim();
                $.ajax({
                    url: `/admin/ajax/get_group_members.php?gid=${encodeURIComponent(gid)}`,
                    success: function(responseText) {
                        const responseElements = $(responseText).toArray();
                        row.child(responseElements).show();
                    },
                    error: function(x) {
                        row.child($(`<span>${x.responseText}</span>`)).show();
                    },
                });
                tr.addClass('shown');
            }
        });
    });
</script>
<style>
.details-control::before {
    content: "▶ ";
}
tr.shown td.details-control::before {
    content: "▼ ";
}
th.details-control::before {
    content: "";
}
</style>
<?php require UnityDeployment::getTemplatePath("footer.php"); ?>
