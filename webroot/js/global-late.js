(function () {
  $(document).ready(setNav);
  $(window).resize(setNav);

  function setNav() {
    if ($("button.hamburger").is(":visible")) {
      $("nav.mainNav").hide(); // Mobile View
      $("button.hamburger").attr("aria-expanded", "false");
    } else {
      $("nav.mainNav").show(); // Desktop View
      $("button.hamburger").attr("aria-expanded", "true");
    }
  }

  $("button.hamburger").on("click", function () {
    $("nav.mainNav").toggle();
    var expanded_before = $("button.hamburger").attr("aria-expanded");
    var expanded_after = expanded_before === "true" ? "false" : "true";
    $("button.hamburger").attr("aria-expanded", expanded_after);
  });

  $("main").click(function (e) {
    if (
      !$(e.target).parent().hasClass("hamburger") &&
      $("button.hamburger").is(":visible")
    ) {
      $("button.hamburger").attr("aria-expanded", "false");
      $("nav.mainNav").hide();
    }
  });

  // Functions to set nav links as active. Sub links can activate parents by naming files with same prefix, for example: documentation.php and documentation_view.php activate the same link
  var url = location.pathname;
  if (url.lastIndexOf(".") >= 0) {
    url = url.substring(0, url.lastIndexOf("."));
  }

  if (url.lastIndexOf("/") >= 0) {
    url = url.substring(url.lastIndexOf("/") + 1);
  }

  $("nav.mainNav a").each(function () {
    var href = $(this).attr("href");

    if (href.lastIndexOf(".") >= 0) {
      href = href.substring(0, href.lastIndexOf("."));
    }

    if (href.lastIndexOf("/") >= 0) {
      href = href.substring(href.lastIndexOf("/") + 1);
    }

    if (url.indexOf(href) === 0) {
      $(this).addClass("active");
    }
  });
})();
