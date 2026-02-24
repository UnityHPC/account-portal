<?php

$this->Subject = "Login Shell Updated"; ?>

<p>Hello,</p>

<p>You have updated your login shell on the Unity HPC Platform to <?php echo $data["new_shell"]; ?>.
You can view the login shell settings on the
<?php echo getHyperlink("account settings", "panel/account.php"); ?> page</p>

<p>If you believe this to be a mistake, please reply to this email as soon as possible.</p>
