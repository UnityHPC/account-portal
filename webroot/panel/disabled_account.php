<?php

require_once __DIR__ . "/../../resources/autoload.php";

use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UserFlag;

if (!$USER->getFlag(UserFlag::DISABLED)) {
    UnityHTTPD::badRequest("user is not disabled", "");
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    UnityHTTPD::validatePostCSRFToken();
    $USER->reEnable();
    UnityHTTPD::messageSuccess("Account Re-Enabled", "");
    UnityHTTPD::redirect("/panel/account.php");
}
require getTemplatePath("header.php");
$CSRFTokenHiddenFormInput = UnityHTTPD::getCSRFTokenHiddenFormInput();
?>
<h1>Disabled Account</h1>
<hr>
<p>Please verify that the information below is correct before continuing:</p>
<div>
    <strong>Name&nbsp;&nbsp;</strong>
    <?php echo $SSO["firstname"] . " " . $SSO["lastname"]; ?>
    <br>
    <strong>Email&nbsp;&nbsp;</strong>
    <?php echo $SSO["mail"]; ?>
</div>
<p>Your UnityHPC username will be <strong><?php echo $SSO["user"]; ?></strong></p>
<br>
<form action="" method="POST">
    <?php echo $CSRFTokenHiddenFormInput; ?>
    <input type='submit' value='Re-Enable Account'>
</form>
<?php require getTemplatePath("footer.php"); ?>
