<p>Hello,</p>
<p>
<?php
$this->Subject = "Account Expiration Warning";

$idle_days = $data["idle_days"];
$expiration_date = $data["expiration_date"];
$is_final_warning = $data["is_final_warning"];

echo "Your account is set to be disabled on $expiration_date because you have been idle for too long.\n";
if ($is_final_warning) {
    echo "This is the final warning.\n";
}
?>
</p>
<p>
Upon expiration, you will lose access to UnityHPC Platform services.
If you don't wish for this to happen,
reset the inactivity timer by simply logging in to the <?php echo getHyperlink(
    "Unity account portal",
); ?>.
</p>
