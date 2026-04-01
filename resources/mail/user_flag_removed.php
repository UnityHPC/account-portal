<?php
use UnityWebPortal\lib\UserFlag;
$uid = $data["user"];
switch ($data["flag"]):
////////////////////////////////////////////////////////////////////////////////////////////////////
case UserFlag::QUALIFIED: ?>
<?php $this->Subject = "User Disqualified"; ?>
<p>Hello,</p>
<p>
    Your account on the Unity HPC Platform, "<?php echo $uid ?>" has been disqualified.
    You should no longer be able to access Unity HPC Platform services.
</p>
<p>In order to be qualified, you must be a PI or be a member of at least one PI group.</p>
<p>If you believe this to be a mistake, please reply to this email as soon as possible.</p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::DISABLED: ?>
<?php $this->Subject = "User Re-Enabled"; ?>
<p>Hello,</p>
<p>
    Your account on the Unity HPC Platform, "<?php echo $uid ?>" has been re-enabled.
    You should now be able to access Unity HPC Platform services.
</p>
<p>If you believe this to be a mistake, please reply to this email as soon as possible.</p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::LOCKED: ?>
<?php $this->Subject = "User Unlocked"; ?>
<p>Hello,</p>
<p>
    Your account on the Unity HPC Platform, "<?php echo $uid ?>" has been unlocked.
    You should now be able to access Unity HPC Platform services.
</p>
<p>If you believe this to be a mistake, please reply to this email as soon as possible.</p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::IDLELOCKED: ?>
<?php $this->Subject = "User Unlocked"; ?>
<p>Hello,</p>
<p>
    Your account on the Unity HPC Platform, "<?php echo $uid ?>" has been unlocked.
    You should now be able to access Unity HPC Platform services.
</p>
<p>If you believe this to be a mistake, please reply to this email as soon as possible.</p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::ADMIN: ?>
<?php $this->Subject = "User Demoted"; ?>
<p>Hello,</p>
<p>Your account on the Unity HPC Platform, "<?php echo $uid ?>" has been demoted from admin.</p>
<p>If you believe this to be a mistake, please reply to this email as soon as possible.</p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::IMMORTAL: ?>
<?php $this->Subject = "User Mortalized"; ?>
<p>Hello,</p>
<p>Your account on the Unity HPC Platform, "<?php echo $uid ?>" has been made mortal (no longer exempt frome expiry).</p>
<p>If you believe this to be a mistake, please reply to this email as soon as possible.</p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php default: ?>
<?php throw new \Exception("unknown flag: " . $data["flag"]); ?>
<?php endswitch; ?>
