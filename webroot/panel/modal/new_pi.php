<?php

require_once __DIR__ . "/../../../resources/autoload.php";
use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UnityGroup;
$CSRFTokenHiddenFormInput = UnityHTTPD::getCSRFTokenHiddenFormInput();

// cache PI group info in $_SESSION for ajax pi_search.php
// cache persists only until the user loads this page again
$owner_uids = $LDAP->getAllNonDisabledPIGroupOwnerUIDs();
$owner_attributes = $LDAP->getUsersAttributes(
    $owner_uids,
    ["uid", "gecos", "mail"],
    default_values: ["gecos" => [""], "mail" => [""]]
);
$pi_group_gid_to_owner_gecos_and_mail = [];
foreach ($owner_attributes as $attributes) {
    $gid = UnityGroup::ownerUID2GID($attributes["uid"][0]);
    $pi_group_gid_to_owner_gecos_and_mail[$gid] = [$attributes["gecos"][0], $attributes["mail"][0]];
}
$_SESSION["pi_group_gid_to_owner_gecos_and_mail"] = $pi_group_gid_to_owner_gecos_and_mail;
?>

<form
    id="newPIform"
    method="POST"
    action="<?php echo getURL("panel/groups.php"); ?>"
>
    <?php echo $CSRFTokenHiddenFormInput; ?>
    <input type="hidden" name="form_type" value="addPIform">
    <div style="position: relative;">
        <input
            type="text"
            id="pi_search"
            name="pi"
            placeholder="Search by GID, Name, or Email"
            required
        >
        <div class="searchWrapper" style="display: none;"></div>
    </div>
    <label>
        <input type='checkbox' name='tos' value='agree' required>
        I have read and accept the
        <a href='<?php echo CONFIG["site"]["terms_of_service_url"]; ?>' target='_blank'>
            Terms of Service
        </a>.
    </label>
    <input
        type="submit"
        value="Send Request"
        title="Please enter a GID, owner name, or owner email"
        disabled
    >
</form>

<script>
    (function () {
        const input = $("input[name=pi]");
        const wrapper = $("div.searchWrapper");
        const submit = $("#newPIform > input[type=submit]");
        function updateSearch() {
            const query = input.val();
            $.ajax({
                url: '<?php echo getURL("panel/ajax/pi_search.php") ?>',
                data: {"search": query},
                success: function(data) {
                    const results = JSON.parse(data);
                    if (results.length === 0) {
                        wrapper.html("<span>No Results</span>").show();
                        submit.prop("disabled", true).prop("title", "no groups found");
                    } else if (results.includes(query)) {
                        // search query exactly matches a PI group GID
                        wrapper.html("").hide();
                        submit.prop("disabled", false).prop("title", "");
                    } else {
                        const html = results.map(gid => `<span>${gid}</span>`).join('');
                        wrapper.html(html).show();
                        submit.prop("disabled", true).prop("title", "no group found with this GID");
                    }
                },
                error: function(result) {
                    submit.after($("<div></div>").html(result.responseText));
                    wrapper.html("").hide();
                    submit.prop("disabled", true).prop("title", "something went wrong");
                }
            });
        };
        input.on("keyup", () => updateSearch());
        wrapper.on("click", "span", function() {
            input.val($(this).text());
            updateSearch();
        });
        $(document).on("click", () => wrapper.hide());
    })();
</script>
