<?php

require_once __DIR__ . "/../../resources/autoload.php";

use UnityWebPortal\lib\UserFlag;
use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\exceptions\EncodingUnknownException;
use UnityWebPortal\lib\exceptions\EncodingConversionException;
use UnityWebPortal\lib\exceptions\ArrayKeyException;
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
            try {
                $USER->removeSSHKey($key);
            } catch (ArrayKeyException) {
                UnityHTTPD::messageError("Cannot Remove SSH Key", "Key not found");
                UnityHTTPD::redirect();
            }
            $keyShort = shortenString($key, 10, 30);
            UnityHTTPD::messageSuccess("SSH Key Removed", $keyShort);
            UnityHTTPD::redirect();
            break; /** @phpstan-ignore deadCode.unreachable */
        case "loginshell":
            $shell = UnityHTTPD::getPostData("shellSelect");
            $USER->setLoginShell($shell);
            UnityHTTPD::messageSuccess("Login Shell Changed", "");
            UnityHTTPD::redirect();
            break; /** @phpstan-ignore deadCode.unreachable */
        case "pi_request":
            if ($USER->isPI()) {
                UnityHTTPD::messageError("Cannot Submit PI Request", "Already a PI");
                UnityHTTPD::redirect();
            }
            if ($SQL->requestExists($USER->uid, UnitySQL::REQUEST_BECOME_PI)) {
                UnityHTTPD::messageError("Cannot Submit PI Request", "This request already exists");
                UnityHTTPD::redirect();
            }
            if ($_POST["tos"] != "agree") {
                UnityHTTPD::badRequest("user did not agree to terms of service");
            }
            $USER->getPIGroup()->requestGroup();
            UnityHTTPD::messageSuccess("PI Group Requested", "");
            UnityHTTPD::redirect();
            break; /** @phpstan-ignore deadCode.unreachable */
        case "cancel_pi_request":
            if (!$SQL->requestExists($USER->uid, UnitySQL::REQUEST_BECOME_PI)) {
                UnityHTTPD::messageError("Cannot Cancel PI Request", "No PI request found");
                UnityHTTPD::redirect();
            }
            $USER->getPIGroup()->cancelGroupRequest();
            UnityHTTPD::messageSuccess("PI Request Cancelled", "");
            UnityHTTPD::redirect();
            break; /** @phpstan-ignore deadCode.unreachable */
        case "disable":
            if ($hasGroups) {
                UnityHTTPD::messageError(
                    "Cannot Disable",
                    "You are a PI or you are a member of at least one PI group"
                );
                UnityHTTPD::redirect();
            }
            if ($USER->getFlag(UserFlag::DISABLED)) {
                UnityHTTPD::badRequest("user is already disabled", "");
            }
            $USER->disable();
            UnityHTTPD::messageSuccess("Account Disabled", "");
            UnityHTTPD::redirect();
            break; /** @phpstan-ignore deadCode.unreachable */
        case "update_name_email":
            $USER->setFirstname($SSO["firstname"]);
            $USER->setLastname($SSO["lastname"]);
            $USER->setFullname($SSO["name"]);
            $USER->setMail($SSO["mail"]);
    }
}

require getTemplatePath("header.php");
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
";

if (
    $SSO["firstname"] !== $USER->getFirstname()
    || $SSO["lastname"] !== $USER->getLastname()
    || $SSO["gecos"] !== $USER->getFullname()
    || $SSO["mail"] !== $USER->getMail()
) {
    echo "
        <p>
            The name/email from your SSO login does not match the name/email we have on file.
            Would you like to update your name/email?
        <p>
        <form action='' method='POST' aria-label='Update Name/Email'>
            $CSRFTokenHiddenFormInput
            <input type='hidden' name='form_type' value='update_name_email' />
            <input type='submit' value='Update Name/Email' />
        </form>
    ";
}

echo "<hr><h2>Account Status</h2>";
$isPI = $USER->isPI();
if ($isPI) {
    echo "
        <p>You are currently a <strong>principal investigator</strong> on the UnityHPC Platform.</p>
    ";
} else {
    if ($USER->getPIGroup()->exists() && $USER->getPIGroup()->getIsDisabled()) {
        echo "<p>You are no longer a PI because your PI group is disabled.</p>";
    }
    if ($USER->getFlag(UserFlag::QUALIFIED)) {
        echo "<p>You are currently a <strong>qualified user</strong> on the UnityHPC Platform.</p>";
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
        if ($USER->getPIGroup()->exists() && $USER->getPIGroup()->getIsDisabled()) {
            $button_msg = "Request to Re-Enable PI Group";
            $onclick = "return confirm(\"Are you sure you want to re-enable your old PI group?\")";
        } else {
            $button_msg = "Request PI Group";
            $onclick = "return confirm(\"Are you sure you want to request a PI group?\")";
        }
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
            <input type='submit' value='$button_msg' onclick='$onclick'/>
        ";
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
    <h2>Danger Zone</h2>
    <div style='display: flex; flex-direction: row; align-items: center;'>
        <p>
            <strong>Disable Account</strong>
            <br>
            You will lose access to UnityHPC Platform services
            and your home directory will be permanently deleted.
            Your account can later be re-enabled.
        </p>
        <form
            action=''
            method='POST'
            onsubmit='return confirm(\"🚨 Are you sure you want to DISABLE your account? 🚨\")'
        >
            $CSRFTokenHiddenFormInput
            <input type='hidden' name='form_type' value='disable'>
";
if ($isPI) {
    echo "
        <input type='submit' value='Disable Account' class='danger' disabled>
        <p>You must first disable your PI group before you can disable your account.</p>
    ";
} elseif ($hasGroups) {
    echo "
        <input type='submit' value='Disable Account' class='danger' disabled>
        <p>You cannot disable your account while you are in a PI group.</p>
    ";
} else {
    echo "
        <input type='submit' value='Disable Account' class='danger'>
    ";
}
echo "</form></div>";
// $support = CONFIG["mail"]["support"];
// echo "
//         </form>
//     </div>
//     <p>
//         <strong>Request Account Deletion</strong>
//         <br>
//         If you wish for all non-essential personal information to be redacted,
//         send us an email at <a href='mailto:$support'>$support</a>.
//         This cannot be undone.
//     </p>
// ";
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

<?php require getTemplatePath("footer.php"); ?>
