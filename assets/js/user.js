/**
* This file implements JavaScript for users/
*/

import Noty from 'noty';
import Routing from 'fos-jsrouting';

$(function () {
    $('#addUserFromFileForm').parent('form').submit(function (event) {
        event.preventDefault();

        var formData = new FormData();
        $.each($(this).find('input, button'), function(i, e) {
            if (e.getAttribute('type') === 'file') {
                console.log(e.files[0] instanceof Blob);
                formData.append(e.getAttribute('name'), e.files[0], e.files[0].name);
            }
            else {
                formData.append(e.getAttribute('name'), e.getAttribute('value'));
            }
        });
        var url = Routing.generate('users');

        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: "multipart/form-data"
        })
        .done(function (data) {
            // userTable.ajax.reload();

            new Noty({
                type: 'success',
                text: data.message
            }).show();
        })
        .fail(function (data) {
            new Noty({
                type: 'error',
                text: data.message
            }).show();
        });
    });
})
