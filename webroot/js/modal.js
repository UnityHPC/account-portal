function openModal(title, link) {
    $("#modalTitle").text(title);
    $.ajax({
        url: link,
        success: function (result) {
            $("#modalBody").html(result);
            $("#modal")[0].showModal();
        },
        error: function (result) {
            $("#modalBody").html(result.responseText);
            $("#modal")[0].showModal();
        },
    });
}

$("button.btnClose").click(function () {
    $("#modal")[0].close();
});
