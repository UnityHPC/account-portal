<p>Hello,</p>
<p>
<?php
$this->Subject = "Account Expiration Warning";

$idle_days = $data["idle_days"];
$expiration_date = $data["expiration_date"];
$is_final_warning = $data["is_final_warning"];
$hyperlink = getHyperlink("account portal");

echo "
    Your account is scheduled to be locked on $expiration_date because you have been idle for too long.
    Upon expiration, you will lose access to UnityHPC Platform services
    until you reset the inactivity timer by logging in to the $hyperlink.
";
if ($is_final_warning) {
    echo "This is the final warning.\n";
}
?>
</p>
