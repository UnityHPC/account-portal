<h1>Welcome</h1>
<p style="text-wrap: balance;">
    Welcome to the UnityHPC Platform Account Portal.
    Here you can manage your SSH keys, join and leave PI groups, manage your own PI group, and more.
    <?php
    if (!($_SESSION["navbar_show_logged_in_user_pages"] ?? false)) {
        $hyperlink = getHyperlink("Log In", "panel/account.php");
        echo "Please $hyperlink for more information.";
    }
    ?>
</p>
