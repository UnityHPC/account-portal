function openModal(title, link) {
    $("#modalTitle").text(title);
    $("#modal")[0].showModal();
    $("#modalBody").text("Loading...");
    $.ajax({
        url: link,
        success: function (result) {
            $("#modalBody").html(result);
        },
        error: function (result) {
            $("#modalBody").html(result);
        },
    });
}

$("button.btnClose").click(function () {
    $("#modal")[0].close();
});
