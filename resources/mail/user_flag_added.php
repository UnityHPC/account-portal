<?php
use UnityWebPortal\lib\UserFlag;
$portal_hyperlink = getRelativeHyperlink("account portal");
$policy_hyperlink = formatHyperlink("account expiration policy", CONFIG["site"]["account_expiration_policy_url"]);
switch ($data["flag"]):
////////////////////////////////////////////////////////////////////////////////////////////////////
case UserFlag::QUALIFIED: ?>
<?php $this->Subject = "User Qualified"; ?>
<p>Hello,</p>
<p>
    Your account on the Unity HPC Platform has been qualified.
    You should now be able to access Unity HPC Platform services.
    Your account details are below:
</p>
<p>
<strong>Username</strong> <?php echo $data["user"]; ?>
<br>
<strong>Organization</strong> <?php echo $data["org"]; ?>
</p>
<p>
See the
<a href="<?php echo CONFIG["site"]["getting_started_url"]; ?>">Getting Started</a>
page in our documentation for next steps.
</p>
<p>If you believe this to be a mistake, please reply to this email as soon as possible.</p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::DISABLED: ?>
<?php $this->Subject = "User Disabled"; ?>
<p>Hello,</p>
<p>
    Your account on the Unity HPC Platform has been disabled.
    You should no longer be able to access Unity HPC Platform services.
    This can happen as a result of the <?php echo $policy_hyperlink ?>.
</p>
<p>If you believe this to be a mistake, please reply to this email as soon as possible.</p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::LOCKED: ?>
<?php $this->Subject = "User Locked"; ?>
<p>Hello,</p>
<p>
    Your account on the Unity HPC Platform has been locked.
    You should no longer be able to access Unity HPC Platform services.
</p>
<p>If you believe this to be a mistake, please reply to this email as soon as possible.</p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::IDLELOCKED: ?>
<?php $this->Subject = "User Locked"; ?>
<p>Hello,</p>
<p>
    Your account on the Unity HPC Platform has been locked due to inactivity.
    You should no longer be able to access Unity HPC Platform services.
    To unlock your account, simply log in to the <?php echo $portal_hyperlink ?>.
    See the <?php echo $policy_hyperlink ?> for more information.
</p>
<p>If you believe this to be a mistake, please reply to this email as soon as possible.</p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::ADMIN: ?>
<?php $this->Subject = "User Promoted"; ?>
<p>Hello,</p>
<p>Your account on the Unity HPC Platform has been promoted to admin.</p>
<p>If you believe this to be a mistake, please reply to this email as soon as possible.</p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::IMMORTAL: ?>
<?php $this->Subject = "User Immortalized"; ?>
<p>Hello,</p>
<p>Your account on the Unity HPC Platform has been made immortal (exempt from expiry).</p>
<p>If you believe this to be a mistake, please reply to this email as soon as possible.</p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php default: ?>
<?php throw new \Exception("unknown flag: " . $data["flag"]); ?>
<?php endswitch; ?>
