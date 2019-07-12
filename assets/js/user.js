/**
* This file implements JavaScript for users/
*/

import Noty from 'noty';
import API from './app';
import { id } from 'postcss-selector-parser';

const api = new API('user')

$(function () {
    var userTable = $('#userTable').DataTable({
        ajax: {
            url: Routing.generate('get_users'),
            dataSrc: ''
        },
        buttons: [{
            extend: 'edit',
            action: function() {
                api.edit($('table tr.selected').data('id'));
            }
        }, {
            extend: 'toggle',
            action: function() {
                api.toggle($('table tr.selected').data('id'));
            }
        }, {
            extend: 'delete',
            action: function() {
                api.delete($('table tr.selected').data('id'));
            }
        }],
        columns: [{
            data: 'enabled',
            render: function(data) {
                return data === true ? '<label class="badge badge-success">Active</label>' : '<label class="badge badge-danger">Inactive</label>'
            }
        }, {
            data: 'last_name'
        }, {
            data: 'first_name'
        }, {
            data: 'email'
        }, {
            data: 'courses[, ].name',
            defaultContent: ''
        }]
    });
    
    $('#addUserFromFileForm').parent('form').submit(function (event) {
        event.preventDefault();
        
        var formData = new FormData();
        $.each($(this).find('input, button'), function(i, e) {
            var value = e.getAttribute('value');
            
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
            userTable.ajax.reload();
            
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
