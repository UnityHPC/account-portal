<p>Hello,</p>
<p>
<?php
$this->Subject = "PI Group Expiration Warning";

$idle_days = $data["idle_days"];
$expiration_date = $data["expiration_date"];
$is_final_warning = $data["is_final_warning"];
$portal_hyperlink = getRelativeHyperlink("account portal");
$policy_hyperlink = fmtHyperlink("account policy", CONFIG["site"]["account_expiration_policy_url"]);
$pi_group_gid = $data["pi_group_gid"];

echo "
    The PI group $pi_group_gid is scheduled to be disabled on $expiration_date because the group owner has been idle for too long.
    Upon expiration, this group's files will be permanently deleted,
    and you will lose access to Unity HPC Platform services unless you are a member of any other PI group.
    If you don't wish for this to happen,
    remind your PI to reset the inactivity timer by simply logging in to the $portal_hyperlink.
    For more information, see our $policy_hyperlink.
";
if ($is_final_warning) {
    echo "This is the final warning.\n";
}
?>
</p>
