<?php
use UnityWebPortal\lib\UserFlag;
use UnityWebPortal\lib\UnityHTTPD;
?>
<?php switch ($data["flag"]):
case UserFlag::QUALIFIED: ?>
<?php $this->Subject = "User Qualified"; ?>
<p>Hello,</p>
<p>User "<?php echo $data["user"] ?>" has been qualified. </p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::DISABLED: ?>
<?php $this->Subject = "User Disabled"; ?>
<p>Hello,</p>
<p>User "<?php echo $data["user"] ?>" has been disabled. </p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::LOCKED: ?>
<?php $this->Subject = "User Locked"; ?>
<p>Hello,</p>
<p>User "<?php echo $data["user"] ?>" has been locked. </p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::IDLELOCKED: ?>
<?php $this->Subject = "User Idle Locked"; ?>
<p>Hello,</p>
<p>User "<?php echo $data["user"] ?>" has been idle locked. </p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php default: ?>
<?php
UnityHTTPD::errorLog("email cancelled", sprintf("user flag '%s' has no template", $data["flag"]));
$this->cancelled = true;
?>
<?php endswitch; ?>
