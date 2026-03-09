<p>Hello,</p>
<p>
<?php
$this->Subject = "PI Group Expiration Warning";

$idle_days = $data["idle_days"];
$expiration_date = $data["expiration_date"];
$is_final_warning = $data["is_final_warning"];
$portal_hyperlink = getRelativeHyperlink("account portal");
$policy_hyperlink = formatHyperlink("account policy", CONFIG["site"]["account_expiration_policy_url"]);
$pi_group_gid = $data["pi_group_gid"];

echo "
    The PI group $pi_group_gid is scheduled to be disabled on $expiration_date because the group owner's credential verification has lapsed.
    To maintain an active Unity account, all users must verify their institutional credentials on a biannual basis
    due to security requirements.
    Upon expiration, you will lose access to Unity HPC Platform services unless you are a member of another PI group. Additionally,
    you won't be able to access files under directories owned by $pi_group_gid.

    Currently, the Unity account portal is the only way to verify credentials.
    To maintain an active account, all that's needed is logging in to the portal. A PI doesn't need to run jobs or otherwise interact with Unity.
    If you believe $pi_group_gid, should remain active, please remind your PI to verify their credentials at $portal_hyperlink.
    For more information, see our $policy_hyperlink.
";
if ($is_final_warning) {
    echo "This is the final warning.\n";
}
?>
</p>
