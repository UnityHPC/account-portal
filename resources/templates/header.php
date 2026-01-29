<?php

use UnityWebPortal\lib\UnityHTTPD;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // another page should have already validated and we can't validate the same token twice
    // UnityHTTPD::validatePostCSRFToken();
    if (($_POST["form_type"] ?? null) == "clearView") {
        if (isset($_SESSION["viewUser"])) {
            unset($_SESSION["viewUser"]);
            UnityHTTPD::redirect(getURL("admin/user-mgmt.php"));
        } else {
            throw new Exception('Cannot clearView because $_SESSION["viewUser"] is not set!');
        }
    }
    // Webroot files need to handle their own POSTs before loading the header
    // so that they can do UnityHTTPD::badRequest before anything else has been printed.
    // They also must not redirect like standard PRG practice because this
    // header also needs to handle POST data. So this header does the PRG redirect
    // for all pages.
    unset($_POST); // unset ensures that header must not come before POST handling
    UnityHTTPD::redirect();
}

if (isset($USER) && !$USER->exists() && !str_ends_with($_SERVER['PHP_SELF'], "/new_account.php")) {
    UnityHTTPD::redirect(getURL("panel/new_account.php"));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php
    $scripts = [
      "jquery.min", "global-early", "dataTables.min", "dataTables.responsive.min",
      "dataTables.buttons.min", "dataTables.columnControl.min", "buttons.print.min",
      "buttons.colVis.min", "buttons.html5.min",
    ];
    foreach ($scripts as $x) {
        $url = getURL("js/$x.js?cache_bust_increment_me=" . CONFIG["upstream"]["version"]);
        echo "<script src='$url'></script>";
    }
    ?>
  <style>
    <?php
    // set global css variables from branding
    echo ":root {";
    foreach (CONFIG["colors"] as $var_name => $var_value) {
        echo "--$var_name: $var_value;";
    }
    echo "}";
    ?>
  </style>

  <?php
    $stylesheets = [
      "global", "navbar", "modal", "messages", "responsive.dataTables.min",
      "dataTables.dataTables.min", "columnControl.dataTables.min", "buttons.dataTables.min",
    ];
    foreach ($stylesheets as $x) {
        $url = getURL("css/$x.css?cache_bust_increment_me=" . CONFIG["upstream"]["version"]);
        echo "<link rel='stylesheet' type='text/css' href='$url' />";
    }
    ?>

  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?php echo CONFIG["site"]["description"] ?>">

  <title><?php echo CONFIG["site"]["name"]; ?></title>
</head>

<body>

   <header>
     <img id="imgLogo" draggable=false
     src="<?php echo getURL("assets", CONFIG["site"]["logo"]); ?>" alt="Unity Logo">
     <button class="hamburger vertical-align">
       <img
         draggable="false"
         src="<?php echo getURL("assets/menu.png") ?>"
         alt="Menu Button"
       >
     </button>
   </header>

  <nav class="mainNav">
    <?php
    echo getHyperlink("Home", "index.php") . "\n";

    $num_additional_items = count(CONFIG["menuitems"]["labels"]);
    for ($i = 0; $i < $num_additional_items; $i++) {
        echo "<a target='_blank' href='" . CONFIG["menuitems"]["links"][$i] . "'>" .
        CONFIG["menuitems"]["labels"][$i] . "</a>\n";
    }

    if ($_SESSION["navbar_show_logged_in_user_pages"] ?? false) {
        echo "<hr class='navHR'>\n";
        echo getHyperlink("Account Settings", "panel/account.php") . "\n";
        echo getHyperlink("My PIs", "panel/groups.php") . "\n";

        if ($_SESSION["navbar_show_pi_pages"] ?? false) {
            echo getHyperlink("My Users", "panel/pi.php") . "\n";
        }

        $num_additional_items = count(CONFIG["menuitems_secure"]["labels"]);
        for ($i = 0; $i < $num_additional_items; $i++) {
            echo "<a target='_blank' href='" . CONFIG["menuitems_secure"]["links"][$i] . "'>" .
            CONFIG["menuitems_secure"]["labels"][$i] . "</a>\n";
        }

        if ($_SESSION["navbar_show_admin_pages"] ?? false) {
            echo "<hr class='navHR'>\n";
            echo getHyperlink("User Management", "admin/user-mgmt.php") . "\n";
            echo getHyperlink("PI Management", "admin/pi-mgmt.php") . "\n";
        }
    } else {
        echo getHyperlink("Login / Request Account", "panel/account.php") . "\n";
    }
    ?>
  </nav>

  <div class="modalWrapper" style="display: none;">
    <div class="modalContent">
      <div class="modalTitleWrapper">
        <span class="modalTitle"></span>
        <button style="position: absolute; right: 10px; top: 10px;" class="btnClose"></button>
      </div>
      <div class="modalBody"></div>
    </div>
  </div>
  <script
    src="<?php echo getURL("js/modal.js?cache_bust_increment_me=" . CONFIG["upstream"]["version"]) ?>"
  ></script>
  <main>

  <?php
    echo "<div id='messages'>";
    $messages = UnityHTTPD::getMessages();
    if (count($messages) >= 3) {
        echo "<button id='clear_all_messages_button'>Clear All Messages</button>";
    }
    foreach (array_reverse($messages) as [$title, $body, $level]) {
        echo sprintf(
            "
              <div class='message %s'>
                <h3>%s</h3>
                <p>%s</p>
                <button
                  data-level='%s'
                  data-title='%s'
                  data-body='%s'
                >
                  ×
                </button>
              </div>
            ",
            htmlspecialchars($level->value),
            htmlspecialchars($title),
            htmlspecialchars($body),
            base64_encode($level->value),
            base64_encode($title),
            base64_encode($body),
        );
    }
    echo "</div>";
    if (isset($_SESSION["viewUser"])) {
        $viewUser = $_SESSION["viewUser"];
        $CSRFTokenHiddenFormInput = UnityHTTPD::getCSRFTokenHiddenFormInput();
        echo "
          <div id='viewAsBar'>
            <span>You are accessing the web portal as the user <strong>$viewUser</strong></span>
            <form method='POST' action=''>
              $CSRFTokenHiddenFormInput
              <input type='hidden' name='form_type' value='clearView'>
              <input type='hidden' name='uid' value='$viewUser'>
              <input type='submit' value='Return to My User'>
            </form>
          </div>
        ";
    }
    ?>
