<?php use UnityWebPortal\lib\UserFlag; ?>
<?php switch ($data["flag"]):
case UserFlag::QUALIFIED: ?>
<?php $this->Subject = "User Disqualified"; ?>
<p>Hello,</p>
<p>User "<?php echo $data["user"] ?>" has been disqualified. </p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::DISABLED: ?>
<?php $this->Subject = "User Re-Enabled"; ?>
<p>Hello,</p>
<p>User "<?php echo $data["user"] ?>" has been re-enabled. </p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::LOCKED: ?>
<?php $this->Subject = "User Unlocked"; ?>
<p>Hello,</p>
<p>User "<?php echo $data["user"] ?>" has been unlocked. </p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::IDLELOCKED: ?>
<?php $this->Subject = "User Idle Unlocked"; ?>
<p>Hello,</p>
<p>User "<?php echo $data["user"] ?>" has been idle unlocked. </p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::ADMIN: ?>
<?php $this->Subject = "User Demoted"; ?>
<p>Hello,</p>
<p>User "<?php echo $data["user"] ?>" has been demoted from admin. </p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::IMMORTAL: ?>
<?php $this->Subject = "User Mortalized"; ?>
<p>Hello,</p>
<p>User "<?php echo $data["user"] ?>" has been made mortal (no longer exempt from expiry). </p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php default: ?>
<?php throw new \Exception("unknown flag: " . $data["flag"]); ?>
<?php endswitch; ?>
