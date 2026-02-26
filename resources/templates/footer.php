</main>

<footer>
  <div id="footerLogos">
    <?php

    $footer_logos = CONFIG["footer"]["logos"];
    $footer_links = CONFIG["footer"]["links"];
    $footer_titles = CONFIG["footer"]["title"];
    for ($i = 0; $i < count($footer_logos); $i++) {
        echo
        "<a target='_blank' href='" . $footer_links[$i] . "'>
        <img src='" . getRelativeURL("assets", $footer_logos[$i]) . "'
        draggable='false' title='" . $footer_titles[$i] . "'></a>";
    }
    ?>

  </div>
  <div class="footerBlock">
    <span>
        Unity Web Portal Version
        <a target="_blank" href="<?php echo CONFIG["upstream"]["repo"]; ?>">
            <?php echo CONFIG["upstream"]["version"]; ?>
        </a>
    </span>
    &nbsp;|
    <a href="<?php echo CONFIG["site"]["terms_of_service_url"]; ?>">Terms of Service</a>
  </div>
</footer>

</body>
<?php
foreach (["global-late", "messages"] as $x) {
    $url = getRelativeURL("js/$x.js?cache_bust_increment_me=" . CONFIG["upstream"]["version"]);
    echo "<script src='$url'></script>";
}
?>
</html>
