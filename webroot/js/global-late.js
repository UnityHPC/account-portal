(function () {
  $(document).ready(setNav);
  $(window).resize(setNav);

  function setNav() {
    if ($("button.hamburger").is(":visible")) {
      $("nav.mainNav").hide(); // Mobile View
    } else {
      $("nav.mainNav").show(); // Desktop View
    }
  }

  $("button.hamburger").on("keydown", function (e) {
    if (e.key === 'Enter' || e.keyCode === 13) {
      e.preventDefault();
      var mainNav = $("nav.mainNav");
      mainNav.toggle();
      if (mainNav.is(":visible")) {
        $("button.hamburger").attr("aria-expanded", "true");
        setTimeout(() => {
          mainNav.find('a').first().focus();
        });
      }
      else {
        $("button.hamburger").attr("aria-expanded", "false");
        $("button.hamburger").focus();
      }
    }
  });

  $("button.hamburger").on("click", function () {
    var mainNav = $("nav.mainNav");
    if (mainNav.is(":visible")) {
      mainNav.fadeOut(100);
    } else {
      mainNav.fadeIn(100);
    }
  });

  $(window).click(function (e) {
    if (
      !$(e.target).parent().hasClass("hamburger") &&
      $("button.hamburger").is(":visible")
    ) {
      $("nav.mainNav").fadeOut(100);
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

  /**
   * btnDropdown Click Events
   */
  $("div.btnDropdown > button").click(function () {
    $("div.btnDropdown > div").toggle();
  });

  $(window).click(function (e) {
    if (!e.target.matches("div.btnDropdown > button")) {
      $("div.btnDropdown > div").hide();
    }
  });
})();
