<?php

require_once __DIR__ . "/../../../resources/autoload.php";  // Load required libs
use UnityWebPortal\lib\UnityHTTPD;
$CSRFTokenHiddenFormInput = UnityHTTPD::getCSRFTokenHiddenFormInput();
?>

<form
    id="newKeyform"
    enctype="multipart/form-data"
    method="POST"
    action="<?php echo getRelativeURL("panel/account.php"); ?>"
>
    <?php echo $CSRFTokenHiddenFormInput; ?>
    <input type='hidden' name='form_type' value='addKey'>

    <input type="radio" id="paste" name="add_type" value="paste" checked>
    <label for="paste">Paste Key</label>
    <br>
    <input type="radio" id="import" name="add_type" value="import">
    <label for="import">Local File</label>
    <br>
    <input type="radio" id="generate" name="add_type" value="generate">
    <label for="generate">Generate Key</label>
    <br>
    <input type="radio" id="github" name="add_type" value="github">
    <label for="github">Import from GitHub</label>

    <hr>

    <div id="key_paste">
        <textarea placeholder="ssh-rsa AAARs1..." form="newKeyform" name="key"></textarea>
        <input type="submit" value="Add Key" disabled />
        <br>
        <p id="key_invalid_explanation" style="margin-top: 10px;"></p>
    </div>

    <div style="display: none;" id="key_import">
        <label for="keyfile">Select local file:</label>
        <input type="file" name="keyfile" />
        <input type="submit" value="Import Key" disabled />
    </div>

    <div style="display: none;" id="key_generate">
        <input type="hidden" name="gen_key" />
        <table style="border-spacing: 5px;">
            <tr>
                <td>
                    <span id="generate_key_download_checkmark">&hellip;</span>
                </td>
                <td>Download Private Key</td>
                <td>
                    <div style="display: flex; gap: 5px;" id="key_generate_download_buttons">
                        <button type="button" class="btnLin">OpenSSH (recommended)</button>
                        <button type="button" class="btnWin">PuTTY</button>
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <span id="generate_key_upload_checkmark">&hellip;</span>
                </td>
                <td>Upload Public Key</td>
                <td>
                    <input id="key_generate_upload_button" type="submit" value="Upload" disabled />
                </td>
            </tr>
        </table>
        <p style="margin-top: 10px;">
            Once you download your private key, you must also upload your public key.
        </p>
    </div>

    <div style="display: none;" id="key_github">
        <div class='inline'>
            <input type="text" name="gh_user" placeholder="GitHub Username" />
            <input type="submit" value="Import Key(s)" disabled />
        </div>
    </div>
</form>

<script>
    $("input[type=radio]").change(function() {
        if ($(this).is(":checked")) {
            $("#newKeyform > div").hide()  // Hide existing divs
            $("div#key_" + $(this).attr('id')).show();  // show only one div
        }
    });

    function generateKey(type) {
        $.ajax({
            url: "<?php echo getRelativeURL("panel/ajax/ssh_generate.php"); ?>?type=" + type,
            dataType: "json",
            success: function(result) {
                $("input[type=hidden][name=gen_key]").val(result.public);
                downloadFile(result.private, "privkey." + type); // Force download of private key
                $("#generate_key_download_checkmark").text("✅");
                // now that private key is downloading, don't let them close the modal or
                // switch method until they upload the pubkey (which reloads the page)
                $("#modal").attr("closedby", "none");
                $("#modalCloseButton").prop("disabled", true);
                $("#newKeyform > input[type=radio]:not(:checked)").prop("disabled", true);
            },
            error: function (result) {
                $("#key_generate").append(result.responseText);
            },
        });
    }

    $("#key_generate_download_buttons > button").click(function() {
        // get type
        if ($(this).hasClass('btnWin')) {
            var type = "ppk";
        } else if ($(this).hasClass('btnLin')) {
            var type = "key";
        }

        generateKey(type);
        setTimeout(() => {
            $("#key_generate_upload_button").prop("disabled", false);
            $("#key_generate_download_buttons > button").prop("disabled", true);
        }, 300);
    });

    $("#key_paste > textarea").on("input", function() {
        var key = $(this).val();
        var submit = $(this).siblings("input[type=submit]")
        if (key == "") {
            submit.prop("disabled", true);
            $("#key_invalid_explanation").text("").hide();
            return;
        }
        $.ajax({
            url: "<?php echo getRelativeURL("panel/ajax/ssh_validate.php"); ?>",
            dataType: "json",
            type: "POST",
            data: {key: key},
            success: function(result) {
                if (result.is_valid) {
                    submit.prop("disabled", false);
                    $("#key_invalid_explanation").text("").hide();
                } else {
                    submit.prop("disabled", true);
                    $("#key_invalid_explanation").text(result.explanation).show();
                }
            },
            error: function(result) {
                submit.prop("disabled", true);
                $("#key_invalid_explanation").html(result.responseText).show();
            }
        });
    });

    $("input[name=keyfile]").on("change", function() {
        $(this).siblings("input[type=submit]").prop("disabled", (this.files.length === 0));
    });
    $("input[name=gh_user]").on("input", function() {
        $(this).siblings("input[type=submit]").prop("disabled", (this.value === ""));
    });
</script>
