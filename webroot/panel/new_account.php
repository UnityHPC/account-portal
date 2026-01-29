<?php

require_once __DIR__ . "/../../resources/autoload.php";

use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UserFlag;

if ($USER->exists() && (!$USER->getFlag(UserFlag::DISABLED))) {
    UnityHTTPD::redirect(getURL("panel/account.php"));
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    UnityHTTPD::validatePostCSRFToken();
    if ($USER->getFlag(UserFlag::DISABLED)) {
        $USER->reEnable();
        UnityHTTPD::messageInfo(
            "Welcome Back!",
            "Your previously disabled account has been re-enabled."
        );
    } else {
        $USER->init($SSO["firstname"], $SSO["lastname"], $SSO["mail"], $SSO["org"]);
    }
    // header.php will redirect to this same page again and then this page will redirect to account
}
require getTemplatePath("header.php");
$CSRFTokenHiddenFormInput = UnityHTTPD::getCSRFTokenHiddenFormInput();
?>

<h1>Register New Account</h1>
<hr>
<p>Please verify that the information below is correct before continuing</p>
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
    <input type='submit' value='Register'>
</form>
<?php require getTemplatePath("footer.php"); ?>
