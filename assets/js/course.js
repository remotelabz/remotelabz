/**
 * This file implements JavaScript for courses/
 */

import API from './api';

const api = new API('course')

$(function () {
    $('#course_users').selectize({
        plugins: ['remove_button'],
        delimiter: ',',
        persist: false,
        create: false
    });

    var courseTable = $('#courseTable').DataTable({
        ajax: {
            url: Routing.generate('get_courses'),
            dataSrc: ''
        },
        buttons: [{
            extend: 'edit',
            action: function() {
                api.edit($('table tr.selected').data('id'));
            }
        }, {
            extend: 'delete',
            action: function() {
                api.delete($('table tr.selected').data('id'));
            }
        }],
        columns: [{
                data: 'name'
            }, {
                data: 'users[, ].name',
                defaultContent: ''
        }]
    });
})