function hideClearAllMessagesButtonIfAllMessagesAlreadyCleared() {
    var visibleMessages = $('#messages .message:visible').length;
    if (visibleMessages === 0) {
        $('#clear_all_messages_button').hide();
    }
}

$(document).ready(function () {
    // #messages is added to the page as late as possible so that no messages are missed
    // it's currently at the bottom of the HTML, move it to the top
    $('#messages').prependTo($('main'));
    $('#messages').on('click', '.message button', function () {
        var button = $(this);
        var message = button.parent();
        message.hide();
        $.ajax({
            url: '/panel/ajax/delete_message.php',
            method: 'POST',
            data: {
                'level': button.data('level'),
                'title': button.data('title'),
                'body': button.data('body')
            },
            error: function (result) {
                $("#messages").append(result.responseText);
            }
        });
        hideClearAllMessagesButtonIfAllMessagesAlreadyCleared();
    });

    $('#clear_all_messages_button').on('click', function () {
        $('#messages .message:visible button').click();
    });
});
