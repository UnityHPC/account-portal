<?php

require_once __DIR__ . "/../../resources/autoload.php";

use UnityWebPortal\lib\UnityGroup;
use UnityWebPortal\lib\UnityHTTPD;

$getPIGroupFromPost = function () {
    global $LDAP, $SQL, $MAILER, $WEBHOOK;
    $gid = UnityHTTPD::getPostData("pi");
    $pi_group = new UnityGroup($gid, $LDAP, $SQL, $MAILER, $WEBHOOK);
    if (!$pi_group->exists()) {
        UnityHTTPD::messageError("This PI Doesn't Exist", $gid);
        UnityHTTPD::redirect();
    }
    return $pi_group;
};

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    UnityHTTPD::validatePostCSRFToken();
    if (isset($_POST["form_type"])) {
        switch ($_POST["form_type"]) {
            case "addPIform":
                $pi_account = $getPIGroupFromPost();
                if ($_POST["tos"] != "agree") {
                    UnityHTTPD::badRequest("user did not agree to terms of service");
                }
                if ($pi_account->exists()) {
                    if ($pi_account->requestExists($USER)) {
                        UnityHTTPD::messageError(
                            "Invalid Group Membership Request",
                            "You've already requested this"
                        );
                        UnityHTTPD::redirect();
                    }
                    if ($pi_account->memberUIDExists($USER->uid)) {
                        UnityHTTPD::messageError(
                            "Invalid Group Membership Request",
                            "You're already in this PI group"
                        );
                        UnityHTTPD::redirect();
                    }
                }
                $pi_account->newUserRequest($USER);
                UnityHTTPD::redirect();
                break; /** @phpstan-ignore deadCode.unreachable */
            case "removePIForm":
                $pi_account = $getPIGroupFromPost();
                $pi_account->removeUser($USER);
                UnityHTTPD::redirect();
                break; /** @phpstan-ignore deadCode.unreachable */
            case "cancelPIForm":
                $pi_account = $getPIGroupFromPost();
                $pi_account->cancelGroupJoinRequest($USER);
                UnityHTTPD::redirect();
                break; /** @phpstan-ignore deadCode.unreachable */
        }
    }
}


require getTemplatePath("header.php");
$CSRFTokenHiddenFormInput = UnityHTTPD::getCSRFTokenHiddenFormInput();
?>

<h1>My Principal Investigators</h1>
<hr>

<?php
$PIGroupGIDs = [];
$PIGroupAttributes = $LDAP->getNonDisabledPIGroupAttributesWithMemberUID(
    $USER->uid,
    ["cn", "memberuid", "manageruid"],
    default_values: ["memberuid" => [], "manageruid" => []]
);
$PIGroupMembers = [];
$PIGroupManagers = [];
foreach ($PIGroupAttributes as $attributes) {
    $gid = $attributes["cn"][0];
    $PIGroupMembers[$gid] = $attributes["memberuid"];
    $PIGroupManagers[$gid] = $attributes["manageruid"];
    array_push($PIGroupGIDs, $gid);
}

$requests = $SQL->getRequestsByUser($USER->uid);

$req_filtered = array();
foreach ($requests as $request) {
    // FIXME "admin" -> UnitySQL::REQUEST_BECOME_PI
    if ($request["request_for"] != "admin") {  // put this in config later for gypsum
        array_push($req_filtered, $request);
    }
}

if (count($req_filtered) > 0) {
    echo "
        <h2>Pending Requests</h2>
        <table id='pi-request-table' class='stripe compact hover'>
            <thead>
                <tr>
                    <th>Group Owner Name</th>
                    <th>Group ID</th>
                    <th>Group Owner Mail</th>
                    <th>Requested On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
    ";
    foreach ($req_filtered as $request) {
        $requested_account = new UnityGroup(
            $request["request_for"],
            $LDAP,
            $SQL,
            $MAILER,
            $WEBHOOK
        );
        $requested_owner = $requested_account->getOwner();
        $full_name = $requested_owner->getFirstname() . " " . $requested_owner->getLastname();
        $mail = $requested_owner->getMail();
        echo "<tr class='pending_request'>";
        echo "<td>$full_name</td>";
        echo "<td>" . $requested_account->gid . "</td>";
        echo "<td>$mail</td>";
        echo "<td>" . date("jS F, Y", strtotime($request['timestamp'])) . "</td>";
        echo "<td>";
        echo "<form action='' method='POST' id='cancelPI'>
            $CSRFTokenHiddenFormInput
            <input type='hidden' name='pi' value='$requested_account->gid'>
            <input type='hidden' name='form_type' value='cancelPIForm'>
            <input name='cancel' style='margin-top: 10px;' type='submit' value='Cancel Request'/>
            </form>";
        echo "</td>";
        echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";

    if (count($PIGroupGIDs) > 0) {
        echo "<hr>";
    }
}

echo "<h2>Current Groups</h2>";

if ($USER->isPI() && count($PIGroupGIDs) == 1) {
    $url = getRelativeURL("panel/pi.php");
    echo "
        You are only a member of your own PI group.
        Navigate to the
        <a href='$url'>my users</a>
        page to see your group.
    ";
}

if (count($PIGroupGIDs) == 0) {
    $url = getRelativeURL("panel/groups.php");
    echo "You are not a member of any groups. Request to join a PI using the button below,
    or request your own PI account on the <a href='$url'>account settings</a> page";
}

echo "
    <table id='pi-table' class='stripe compact hover'>
        <thead>
            <tr>
                <th>Name</th>
                <th>GID</th>
                <th>PI Mail</th>
                <th>Members</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
";
foreach ($PIGroupGIDs as $gid) {
    $group = new UnityGroup($gid, $LDAP, $SQL, $MAILER, $WEBHOOK);
    $owner = $group->getOwner();
    $full_name = $owner->getFirstname() . " " . $owner->getLastname();
    if ($USER->uid == $owner->uid) {
        continue;
    }

    echo "<tr>";
    echo "<td>$full_name</td>";
    echo "<td>$gid</td>";
    echo "<td>" . $owner->getMail() . "</td>";
    echo "<td><ul>";
    foreach ($PIGroupMembers[$gid] as $memberuid) {
        echo "<li>$memberuid</li>";
    }
    echo "</ul></td><td>";
    echo "
        <form
            action=''
            method='POST'
            onsubmit='return confirm(\"Are you sure you want to leave the PI group $gid?\")'
        >
            $CSRFTokenHiddenFormInput
            <input type='hidden' name='form_type' value='removePIForm'>
            <input type='hidden' name='pi' value='$gid'>
            <input type='submit' value='Leave Group'>
        </form>
    ";
    if (in_array($USER->uid, $PIGroupManagers[$gid])) {
        $url = getRelativeURL("panel/pi.php");
        echo "
            <form action='$url' method='GET'>
                <input type='hidden' name='gid' value='$gid'>
                <input type='submit' value='Manage Group'>
            </form>
        ";
    }
    echo "</td></tr>";
}
echo "</tbody>";
echo "</table>";
?>

<button type='button' class='plusBtn btnAddPI'><span>&#43;</span></button>

<style>
    div.modalContent {
        max-width: 300px;
    }
</style>

<script>
    $("button.btnAddPI").click(function () {
        openModal("Add New PI", "<?php echo getRelativeURL("panel/modal/new_pi.php"); ?>");
    });
</script>

<script>
    $(document).ready(() => {
        $('#pi-table').DataTable({
            stateSave: true,
            responsive: true,
            columns: [
                {responsivePriority: 1}, // name
                {responsivePriority: 2}, // gid
                {responsivePriority: 2}, // pi_mail
                {responsivePriority: 3, visible: false, searchable: false}, // members
                {responsivePriority: 1, searchable: false}, // actions
            ],
            layout: {topStart: {buttons: ['colvis']}}
        });
        $('#pi-request-table').DataTable({
            stateSave: true,
            responsive: true,
            columns: [
                {responsivePriority: 1}, // owner_name
                {responsivePriority: 2}, // gid
                {responsivePriority: 2}, // pi_mail
                {responsivePriority: 2, searchable: false}, // requested_on
                {responsivePriority: 1, searchable: false}, // actions
            ],
        });
    });
</script>

<?php require getTemplatePath("footer.php"); ?>
