<?php
use UnityWebPortal\lib\UserFlag;
use UnityWebPortal\lib\UnityHTTPD;
?>
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
<?php default: ?>
<?php
UnityHTTPD::errorLog("email cancelled", sprintf("user flag '%s' has no template", $data["flag"]));
$this->cancelled = true;
?>
<?php endswitch; ?>
