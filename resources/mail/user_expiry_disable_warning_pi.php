<p>Hello,</p>
<p>
<?php
$this->Subject = "Account Expiration Warning";

$idle_days = $data["idle_days"];
$expiration_date = $data["expiration_date"];
$is_final_warning = $data["is_final_warning"];
$portal_hyperlink = getRelativeHyperlink("account portal");
$policy_hyperlink = getRelativeHyperlink("account policy", CONFIG["site"]["account_policy_url"]);
$pi_group_gid = $data["pi_group_gid"];

echo "
    Your account and PI group are scheduled to be disabled on $expiration_date because you have been idle for too long.
    Upon expiration, your files and your PI group's files will be permanently deleted,
    you will lose access to Unity HPC Platform services,
    and your group members also may lose access unless they are a member of some other group.
    If you don't wish for this to happen,
    reset the inactivity timer by simply logging in to the $portal_hyperlink.
    For more information, see our $policy_hyperlink.
";
if ($is_final_warning) {
    echo "This is the final warning.\n";
}
?>
</p>
