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
            url: '/courses',
            dataSrc: ''
        },
        buttons: [{
            extend: 'edit',
            action: function() {
                api.edit('/admin/courses/' + $('table tr.selected').data('id') + '/edit');
            }
        }, {
            extend: 'delete',
            action: function() {
                api.delete('/admin/courses/' + $('table tr.selected').data('id'));
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