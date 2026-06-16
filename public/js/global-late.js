(function () {
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

    if (url === href) {
      $(this).addClass("active");
    }
  });
})();
