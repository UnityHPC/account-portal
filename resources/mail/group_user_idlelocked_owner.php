<?php

$this->Subject = "Group Member Idle-Locked";
$portal_hyperlink = getHyperlink("account portal");
$policy_hyperlink = getHyperlink("account policy", CONFIG["site"]["account_policy_url"]);
$pi_hyperlink = getHyperlink("group management page", "panel/pi.php");
?>

<p>Hello,</p>

<p>
A user from your PI group '<?php echo $data["group"]; ?>' has been idle-locked.
Their details are below:
</p>

<p>
<strong>Username</strong> <?php echo $data["user"]; ?>
<br>
<strong>Organization</strong> <?php echo $data["org"]; ?>
<br>
<strong>Name</strong> <?php echo $data["name"]; ?>
<br>
<strong>Email</strong> <?php echo $data["email"]; ?>
</p>

<p>
    This user is expiring according to our <?php echo $policy_hyperlink; ?>.
    They have lost acces to UnityHPC Platform services until they reset the inactivity timer by logging in to the <?php echo $portal_hyperlink; ?>.
</p>

<p>
    If this user is no longer actively participating in your group using UnityHPC Platform services,
    you might consider removing them from your group in the <?php echo $pi_hyperlink; ?>.
</p>
