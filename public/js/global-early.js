function downloadFile(text, filename) {
  var element = document.createElement("a");
  element.setAttribute(
    "href",
    "data:text/plain;charset=utf-8," + encodeURIComponent(text),
  );
  element.setAttribute("download", filename);

  element.style.display = "none";
  $("body").append(element);
  element.click();
  element.remove();
}

const dataTablesRenderMailtoLink = function (data, type, row, meta) {
  if (type === 'display' && data) {
    return '<a href="mailto:' + data + '">' + data + '</a>';
  }
  return data;
}
