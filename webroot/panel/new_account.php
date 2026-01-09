<?php

require_once __DIR__ . "/../../resources/autoload.php";

use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UserFlag;

if ($USER->exists() && (!$USER->getFlag(UserFlag::GHOST))) {
    UnityHTTPD::redirect(getURL("panel/account.php"));
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    UnityHTTPD::validatePostCSRFToken();
    if ($USER->getFlag(UserFlag::GHOST)) {
        $USER->setFlag(UserFlag::GHOST, false);
        UnityHTTPD::messageInfo(
            "Welcome Back!",
            "Your previously deleted account has been resurrected."
        );
    } else {
        $USER->init($SSO["firstname"], $SSO["lastname"], $SSO["mail"], $SSO["org"]);
    }
    // header.php will redirect to this same page again and then this page will redirect to account
}
require $LOC_HEADER;
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
<?php require $LOC_FOOTER; ?>
