<?php

$this->Subject = "Group Member Disabled, Removed";
$policy_hyperlink = fmtHyperlink("account policy", CONFIG["site"]["account_expiration_policy_url"]);
?>

<p>Hello,</p>

<p>
A user from your PI group '<?php echo $data["group"]; ?>' has been disabled and removed.
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
    This user has expired according to our <?php echo $policy_hyperlink; ?>.
</p>
