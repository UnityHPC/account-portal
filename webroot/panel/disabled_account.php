<?php

require_once __DIR__ . "/../../resources/autoload.php";

use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UserFlag;

if (!$USER->getFlag(UserFlag::DISABLED)) {
    UnityHTTPD::redirect(getURL("panel/account.php"));
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    UnityHTTPD::validatePostCSRFToken();
    $USER->reEnable();
    UnityHTTPD::messageSuccess("Account Re-Enabled", "");
    UnityHTTPD::redirect(getURL("panel/account.php"));
}
require getTemplatePath("header.php");
$CSRFTokenHiddenFormInput = UnityHTTPD::getCSRFTokenHiddenFormInput();
$account_policy_url = CONFIG["site"]["account_policy_url"];
$support_mail = CONFIG["mail"]["support"];
?>
<h1>Disabled Account</h1>
<hr>
<p style="text-wrap: balance;">
    Your account has been disabled, but you can re-enable it.
    Accounts are disabled automatically according to our <a href="<?php echo $account_policy_url; ?>">account policy</a>.
    If this is unexpected, <a href="mailto:<?php echo $support_mail; ?>">send us an email</a>.
</p>
<br>
<p>Please verify that the information below is correct before continuing:</p>
<div>
    <strong>Name&nbsp;&nbsp;</strong>
    <?php echo $SSO["firstname"] . " " . $SSO["lastname"]; ?>
    <br>
    <strong>Email&nbsp;&nbsp;</strong>
    <?php echo $SSO["mail"]; ?>
</div>
<p>Your Unity HPC username will be <strong><?php echo $SSO["user"]; ?></strong>.</p>
<br>
<form action="" method="POST">
    <?php echo $CSRFTokenHiddenFormInput; ?>
    <input type='submit' value='Re-Enable Account'>
</form>
<?php require getTemplatePath("footer.php"); ?>
