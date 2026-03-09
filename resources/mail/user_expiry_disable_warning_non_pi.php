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
    Your Unity account, {$data['user']}, is scheduled to be disabled on $expiration_date due to lapsed credential verification.
    To maintain an active Unity account, you must verify your institutional credentials on a biannual basis
    due to security requirements. To keep your account active, please log in to the $portal_hyperlink before $expiration_date.
    Upon expiration, you will lose access to Unity HPC Platform services until you log in to the $portal_hyperlink.
    For more information, see our $policy_hyperlink.

    Currently, the Unity account portal is the only way to verify credentials.
    To maintain an active account, all that's needed is logging in to the portal, you don't need to run jobs or otherwise interact with Unity.
    You can check your status by running unity-account-expiry-status from the Unity command line, or by logging in to the $portal_hyperlink.

    If you are a PI on Unity, please use this opportunity to verify all users under your PI group are current students or collaborators by
    visiting the \"My Users\" tab in the $portal_hyperlink.
";
if ($is_final_warning) {
    echo "This is the final warning.\n";
}
?>
</p>
