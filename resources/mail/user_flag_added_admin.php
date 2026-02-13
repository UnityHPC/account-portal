<?php use UnityWebPortal\lib\UserFlag; ?>
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
<?php case UserFlag::ADMIN: ?>
<?php $this->Subject = "User Promoted"; ?>
<p>Hello,</p>
<p>User "<?php echo $data["user"] ?>" has been promoted to admin. </p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php case UserFlag::IMMORTAL: ?>
<?php $this->Subject = "User Immortalized"; ?>
<p>Hello,</p>
<p>User "<?php echo $data["user"] ?>" has been made immortal (exempt from expiry). </p>
<?php break; ?>

<?php /////////////////////////////////////////////////////////////////////////////////////////// ?>
<?php default: ?>
<?php throw new \Exception("unknown flag: " . $data["flag"]); ?>
<?php endswitch; ?>
