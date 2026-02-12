<?php use UnityWebPortal\lib\UserFlag; ?>
<?php switch ($data["flag"]):
case UserFlag::QUALIFIED: ?>
<?php $this->Subject = "User Qualified"; ?>
<p>Hello,</p>
<p>
    Your account on the UnityHPC Platform has been qualified.
    You should now be able to access UnityHPC Platform services.
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
<p>Your account on the UnityHPC Platform has been disabled.</p>
<p>If you believe this to be a mistake, please reply to this email as soon as possible.</p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::LOCKED: ?>
<?php $this->Subject = "User Locked"; ?>
<p>Hello,</p>
<p>Your account on the UnityHPC Platform has been locked.</p>
<p>If you believe this to be a mistake, please reply to this email as soon as possible.</p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::IDLELOCKED: ?>
<?php $this->Subject = "User Locked"; ?>
<p>Hello,</p>
<p>Your account on the UnityHPC Platform has been locked due to inactivity.</p>
<p>If you believe this to be a mistake, please reply to this email as soon as possible.</p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::ADMIN: ?>
<?php $this->Subject = "User Promoted"; ?>
<p>Hello,</p>
<p>Your account on the UnityHPC Platform has been promoted to admin.</p>
<p>If you believe this to be a mistake, please reply to this email as soon as possible.</p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::IMMORTAL: ?>
<?php $this->Subject = "User Immortalized"; ?>
<p>Hello,</p>
<p>Your account on the UnityHPC Platform has been made immortal (exempt from expiry).</p>
<p>If you believe this to be a mistake, please reply to this email as soon as possible.</p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php default: ?>
<?php throw new \Exception("unknown flag: " . $data["flag"]); ?>
<?php endswitch; ?>
