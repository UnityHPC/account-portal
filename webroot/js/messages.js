$(document).ready(function () {
    $('#messages').on('click', '.message button', function () {
        var button = $(this);
        var message = button.parent();
        $.ajax({
            url: '/panel/ajax/delete_message.php',
            method: 'POST',
            data: {
                'level': button.data('level'),
                'title': button.data('title'),
                'body': button.data('body')
            },
            success: function () {
                message.hide();
                let visibleMessages = $('#messages .message:visible').length;
                if (visibleMessages === 0) {
                    $('#clear_all_messages_button').hide();
                }
            },
            error: function (result) {
                $("#messages").append(result.responseText);
            }
        });
    });

    $('#clear_all_messages_button').on('click', function () {
        $.ajax({
            url: '/panel/ajax/clear_messages.php',
            method: 'POST',
            success: function () {
                $('#messages .message').hide();
                $('#clear_all_messages_button').hide();
            },
            error: function (result) {
                $("#messages").append(result.responseText);
            }
        });
    });
});
