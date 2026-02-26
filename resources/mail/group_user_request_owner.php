<?php

$this->Subject = "Group Member Request"; ?>

<p>Hello,</p>

<p>
A user has requested to join your PI group,
'<?php echo $data["group"]; ?>'.
The details of the user are below:
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

<p>You can approve or deny this user on the
    <?php echo getRelativeHyperlink("my users", "panel/pi.php?gid=$data[group]"); ?> page</p>
