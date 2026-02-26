<p>Hello,</p>
<p>
<?php
$this->Subject = "Account Expiration Warning";

$idle_days = $data["idle_days"];
$expiration_date = $data["expiration_date"];
$is_final_warning = $data["is_final_warning"];
$portal_hyperlink = getRelativeHyperlink("account portal");
$policy_hyperlink = formatHyperlink("account policy", CONFIG["site"]["account_expiration_policy_url"]);

echo "
    Your account is scheduled to be locked on $expiration_date because you have been idle for too long.
    Upon expiration, you will lose access to Unity HPC Platform services
    until you reset the inactivity timer by logging in to the $portal_hyperlink.
    For more information, see our $policy_hyperlink.
";
if ($is_final_warning) {
    echo "This is the final warning.\n";
}
?>
</p>
