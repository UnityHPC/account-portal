<?php

require_once __DIR__ . "/../../resources/autoload.php";

use UnityWebPortal\lib\UserFlag;
use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\exceptions\EncodingUnknownException;
use UnityWebPortal\lib\exceptions\EncodingConversionException;
use UnityWebPortal\lib\UnitySQL;

$hasGroups = count($USER->getPIGroupGIDs()) > 0;

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    UnityHTTPD::validatePostCSRFToken();
    switch (UnityHTTPD::getPostData("form_type")) {
        case "addKey":
            switch (UnityHTTPD::getPostData("add_type")) {
                case "paste":
                    $keys = [UnityHTTPD::getPostData("key")];
                    break;
                case "import":
                    try {
                        $keys = [UnityHTTPD::getUploadedFileContents("keyfile")];
                    } catch (EncodingUnknownException | EncodingConversionException $e) {
                        UnityHTTPD::errorLog("uploaded key has bad encoding", "", error: $e);
                        UnityHTTPD::messageError("SSH Key Not Added: Invalid Encoding", "");
                        UnityHTTPD::redirect();
                    }
                    break;
                case "generate":
                    $keys = [UnityHTTPD::getPostData("gen_key")];
                    break;
                case "github":
                    $githubUsername = UnityHTTPD::getPostData("gh_user");
                    $keys = $GITHUB->getSshPublicKeys($githubUsername);
                    if (count($keys) == 0) {
                        UnityHTTPD::messageWarning(
                            "No Keys Added",
                            "No keys found associated with GitHub account."
                        );
                        UnityHTTPD::redirect();
                    }
                    break;
                default:
                    UnityHTTPD::badRequest("invalid add_type");
            }
            $keys = array_map("trim", $keys);
            foreach ($keys as $key) {
                $keyShort = shortenString($key, 10, 30);
                [$is_valid, $explanation] = testValidSSHKey($key);
                if (!$is_valid) {
                    UnityHTTPD::messageError("SSH Key Not Added: $explanation", $keyShort);
                    continue;
                }
                $keyWasAdded = $USER->addSSHKey($key);
                if ($keyWasAdded) {
                    UnityHTTPD::messageSuccess("SSH Key Added", $keyShort);
                } else {
                    UnityHTTPD::messageInfo("SSH Key Not Added: Already Exists", $keyShort);
                }
            }
            UnityHTTPD::redirect();
            break; /** @phpstan-ignore deadCode.unreachable */
        case "delKey":
            $key = UnityHTTPD::getPostData("delKey");
            $USER->removeSSHKey($key);
            $keyShort = shortenString($key, 10, 30);
            UnityHTTPD::messageSuccess("SSH Key Removed", $keyShort);
            UnityHTTPD::redirect();
            break; /** @phpstan-ignore deadCode.unreachable */
        case "loginshell":
            $USER->setLoginShell(UnityHTTPD::getPostData("shellSelect"));
            break;
        case "pi_request":
            if ($USER->isPI()) {
                UnityHTTPD::badRequest("already a PI");
            }
            if ($SQL->requestExists($USER->uid, UnitySQL::REQUEST_BECOME_PI)) {
                UnityHTTPD::badRequest("already requested to be PI");
            }
            if (UnityHTTPD::getPostData("tos") != "agree") {
                UnityHTTPD::badRequest("user did not agree to terms of service");
            }
            $USER->getPIGroup()->requestGroup();
            break;
        case "cancel_pi_request":
            $USER->getPIGroup()->cancelGroupRequest();
            break;
        case "account_deletion_request":
            if ($hasGroups) {
                break;
            }
            // FIXME send an error message if already exists
            if (!$SQL->accDeletionRequestExists($USER->uid)) {
                $USER->requestAccountDeletion();
            }
            break;
        case "cancel_account_deletion_request":
            // FIXME send an error message if doesn't exist
            if ($SQL->accDeletionRequestExists($USER->uid)) {
                $USER->cancelRequestAccountDeletion();
            }
            break;
    }
}

require $LOC_HEADER;
$CSRFTokenHiddenFormInput = UnityHTTPD::getCSRFTokenHiddenFormInput();

$uid = $USER->uid;
$org = $USER->getOrg();
$mail = $USER->getMail();
echo "
    <h1>Account Settings</h1>
    <hr>
    <h2>Account Details</h2>
    <table>
        <tr>
            <td>Username</th>
            <td><code>$uid</code></td>
        </tr>
        <tr>
            <td>Organization</th>
            <td><code>$org</code></td>
        </tr>
        <tr>
            <td>Email</th>
            <td><code>$mail</code></td>
        </tr>
    </table>
    <hr>
    <h2>Account Status</h2>
";

$isPI = $USER->isPI();

if ($isPI) {
    echo "
        <p>You are curently a <strong>principal investigator</strong> on the UnityHPC Platform</p>
    ";
} elseif ($USER->getFlag(UserFlag::QUALIFIED)) {
    echo "<p>You are curently a <strong>qualified user</strong> on the UnityHPC Platform</p>";
} else {
    $tos_url = CONFIG["site"]["terms_of_service_url"];
    $form_url = getURL("panel/groups.php");
    echo "
        <p>
            You are currently an <strong>unqualified user</strong>, and will be
            <strong>unable to access UnityHPC Platform services</strong>.
            To become qualified, request to join a PI group, or if you are a PI, request a PI group.
            Do not request a PI group if you are a student.
        </p>
        <br>
        <form action='$form_url' method='GET'>
            <label>
                <input type='checkbox' name='tos' value='agree' required />
                I have read and accept the
                <a target='_blank' href='$tos_url'>Terms of Service</a>.
            </label>
            <br>
            <input type='submit' value='Request to Join a PI Group' />
        </form>
        <br>
    ";
}

if (!$isPI) {
    echo "
        <form
            action=''
            method='POST'
            id='piReq'
        >
    ";
    echo $CSRFTokenHiddenFormInput;
    if ($SQL->accDeletionRequestExists($USER->uid)) {
        echo "<input type='submit' value='Request PI Account' disabled />";
        echo "
            <label style='margin-left: 10px'>
                You cannot request PI Account while you have requested account deletion.
            </label>
        ";
    } else {
        if ($SQL->requestExists($USER->uid, UnitySQL::REQUEST_BECOME_PI)) {
            $onclick = "return confirm(\"Are you sure you want to cancel this request?\")";
            echo "<input type='submit' value='Cancel PI Account Request' onclick='$onclick'/>";
            echo "
                <label style='margin-left: 10px'>
                    Your request has been submitted and is currently pending
                </label>
               <input type='hidden' name='form_type' value='cancel_pi_request'/>
            ";
        } else {
            $onclick = "return confirm(\"Are you sure you want to request a PI account?\")";
            $tos_url = CONFIG["site"]["terms_of_service_url"];
            $account_policy_url = CONFIG["site"]["account_policy_url"];
            echo "
                <label>
                    <input type='checkbox' name='confirm_pi' value='agree' required>
                    I have read the PI
                    <a target='_blank' href='$account_policy_url'> account policy</a> guidelines.
                </label>
                <br>
                <label><input type='checkbox' name='tos' value='agree' required>
                    I have read and accept the
                    <a target='_blank' href='$tos_url'>Terms of Service</a>.
                </label>
                <br>
                <input type='hidden' name='form_type' value='pi_request'/>
                <input type='submit' value='Request a PI Group' onclick='$onclick'/>
            ";
        }
    }
    echo "</form>";
}
echo "<hr><h2>SSH Keys</h2>";

$sshPubKeys = $USER->getSSHKeys();

if (count($sshPubKeys) == 0) {
    echo "<p>You do not have any SSH public keys, press the button below to add one.</p>";
}

foreach ($sshPubKeys as $key) {
    echo
    "<div class='key-box'>
        <textarea spellcheck='false' readonly aria-label='key box'>$key</textarea>
        <form
            action=''
            onsubmit='return confirm(\"Are you sure you want to delete this SSH key?\");'
            method='POST'
            aria-label='delete key'
        >
            $CSRFTokenHiddenFormInput
            <input type='hidden' name='delKey' value='$key' />
            <input type='hidden' name='form_type' value='delKey' />
            <input type='submit' value='&times;' />
        </form>
    </div>";
}

echo "
    <button type='button' class='plusBtn btnAddKey'><span>&#43;</span></button>
    <hr>
    <h2>Login Shell</h2>
    <form action='' method='POST'>
      $CSRFTokenHiddenFormInput
      <input type='hidden' name='form_type' value='loginshell' />
      <select id='loginSelector' class='code' name='shellSelect' aria-label='Login Shell'>
";
foreach (CONFIG["loginshell"]["shell"] as $shell) {
    echo "<option>$shell</option>";
}
echo "
      </select>
      <br>
      <input id='submitLoginShell' type='submit' value='Set Login Shell' />
    </form>
    <hr>
    <h2>Account Deletion</h2>
";

if ($hasGroups) {
    echo "<p>You cannot request to delete your account while you are in a PI group.</p>";
} else {
    if ($SQL->accDeletionRequestExists($USER->uid)) {
        echo "
            <p>Your request has been submitted and is currently pending.</p>
            <form
                action=''
                method='POST'
                onsubmit='
                    return confirm(
                        \"Are you sure you want to cancel your request for account deletion?\"
                    )
                '
            >
                $CSRFTokenHiddenFormInput
                <input type='hidden' name='form_type' value='cancel_account_deletion_request' />
                <input type='submit' value='Cancel Account Deletion Request' />
            </form>
        ";
    } else {
        echo "
            <form
                action=''
                method='POST'
                onsubmit='return confirm(\"Are you sure you want to request an account deletion?\")'
            >
                $CSRFTokenHiddenFormInput
                <input type='hidden' name='form_type' value='account_deletion_request' />
                <input type='submit' value='Request Account Deletion' />
            </form>
        ";
    }
}

?>

<script>
    const url = '<?php echo getURL("panel/modal/new_key.php")?>';
    const ldapLoginShell = '<?php echo $USER->getLoginShell(); ?>';

    $("button.btnAddKey").click(function() {
        openModal("Add New Key", url);
    });

    $("#loginSelector option").each(function(i, e) {
        if ($(this).val() == ldapLoginShell) {
            $(this).prop("selected", true);
        }
    });

    function enableOrDisableSubmitLoginShell() {
        if ($("#loginSelector").val() == ldapLoginShell) {
            $("#submitLoginShell").prop("disabled", true);
        } else {
            $("#submitLoginShell").prop("disabled", false);
        }
    }
    $("#loginSelector").change(enableOrDisableSubmitLoginShell);
    enableOrDisableSubmitLoginShell()
</script>

<style>
    .key-box {
        position: relative;
        width: auto;
        height: auto;
        max-width: 700px;
    }

    .key-box input[type=submit] {
        position: absolute;
        right: 0;
        top: 0;
        bottom: 0;
        padding: 5px;
        width: 32px;
        border-radius: 0 3px 3px 0;
        font-size: 20pt;
        margin: 0;
    }

    .key-box textarea {
        word-wrap: break-word;
        word-break: break-all;
        width: calc(100% - 44px);
        border-radius: 3px 0 0 3px;
        font-family: monospace;
    }
</style>

<?php require $LOC_FOOTER; ?>
