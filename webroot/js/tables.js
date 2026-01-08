$("tr.expandable").on("click", function (e) {
  var target = $(e.target);
  if (target.is("button") || target.is("a") || target.is("input")) {
    return;
  }

  var button = $(this).find("button.btnExpand");
  button.trigger("click");
});

$("button.btnExpand").click(function () {
  var pi_wrapper = $(this).parent(); // parent column (td)
  var piRow = pi_wrapper.parent(); // parent row (tr)
  var piTree = piRow.parent(); // parent table (table)
  var gid = pi_wrapper.next().html();

  if ($(this).hasClass("btnExpanded")) {
    // already expanded
    var currentNode = piRow.nextAll().first();

    while (!currentNode.hasClass("expandable") && currentNode.length != 0) {
      var nextNode = currentNode.nextAll().first();
      currentNode.remove();
      currentNode = nextNode;
    }

    $(this).removeClass("btnExpanded");
    piRow.removeClass("expanded");
    piRow.removeClass("first");
  } else {
    $("button.btnExpanded").trigger("click");
    // not expanded
    $.ajax({
      url: ajax_url + gid,
      success: function (result) {
        piRow.after(result);
      },
      error: function (result) {
        piRow.after(result.responseText);
      },
    });

    $(this).addClass("btnExpanded");
    piRow.addClass("expanded");
    piRow.addClass("first");
  }
});

$("#tableSearch").keyup(function () {
  var value = $(this).val().toLowerCase();

  $("table.searchable tr:not(:first-child)").filter(function () {
    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
  });
});

function setColumnVisibility(table_id, column_index, is_visible) {
  $(`#${table_id} tr > :nth-child(${column_index})`).toggle(is_visible);
}

$("table.column-toggle").each(function () {
  const table = $(this);
  const id = $(this).attr("id");
  if (typeof id === "undefined") {
    console.log("error: table does not have id attribute");
    return;
  }
  const checkboxTable = $(`<table style="margin-bottom: 10px;"></table>`);
  const checkboxTableRow = $("<tr></tr>");
  table.find('th').each((index, th) => {
    const checkboxTableCell = $("<td></td>");
    const checkboxLabel = $(`<label>${th.textContent.replace('⫧', '').trim()}</label>`);
    const checkbox = $('<input type="checkbox">');
    if (th.classList.contains("hidden-by-default")) {
      setColumnVisibility(id, index + 1, false);
      checkbox.prop("checked", false);
    } else {
      checkbox.prop("checked", true);
    }
    checkbox.on('change', function () {
      setColumnVisibility(id, index + 1, this.checked);
    });
    checkboxLabel.append(checkbox);
    checkboxTableCell.append(checkboxLabel);
    checkboxTableRow.append(checkboxTableCell);
  });
  checkboxTable.append(checkboxTableRow);
  table.before(checkboxTable);
});
