/**
 * This file implements JavaScript for flavors/
 */

import API from './api';

const api = new API('flavor')
  
$(function () {
    var flavorTable = $('#flavorTable').DataTable({
        ajax: {
            url: "/api/flavors",
            dataSrc: ''
        },
        buttons: [{
            extend: 'edit',
            action: function() {
                api.edit('/admin/flavors/' + $('table tr.selected').data('id') + '/edit');
            }
        }, {
            extend: 'delete',
            action: function() {
                api.delete('/api/flavors/' + $('table tr.selected').data('id'));
            }
        }],
        columns: [{
                data: 'name'
            }, {
                data: 'memory'
            },// {
              //  data: 'disk'
        //}
    ]
    });
})
  