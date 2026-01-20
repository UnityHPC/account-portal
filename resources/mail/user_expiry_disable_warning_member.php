<p>Hello,</p>
<p>
<?php

$this->Subject = "PI Group Expiration Warning";

$idle_days = $data["idle_days"];
$expiration_date = $data["expiration_date"];
$warning_number = $data["warning_number"];
$is_final_warning = $data["is_final_warning"];
$pi_group_gid = $data["pi_group_gid"];

echo "The PI group $pi_group_gid is set to be disabled on $expiration_date because the group owner has been idle for too long.\n";
if ($is_final_warning) {
    echo "This is the final warning.\n";
} else {
    echo "This is warning number $warning_number.\n";
}
?>
<p>
Upon expiration, this group's files will be permanently deleted,
and you will lose access to UnityHPC Platform services unless you are a member of any other PI group.
If you don't wish for this to happen,
remind your PI to reset the inactivity timer by simply logging in to the <?php echo getHyperlink("Unity account portal") ?>.
</p>
