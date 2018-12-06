/**
 * This file implements JavaScript for flavors/
 */

import API from './app';

const api = new API('flavor')
  
$(function () {
    var flavorTable = $('#flavorTable').DataTable({
        ajax: {
            url: Routing.generate('get_flavors'),
            dataSrc: ''
        },
        buttons: [{
            extend: 'edit'
        }, {
            extend: 'delete',
            action: function() {
                api.delete($('table tr.selected').data('id'));
            }
        }],
        columns: [{
                data: 'name'
            }, {
                data: 'memory'
            }, {
                data: 'disk'
        }]
    });
})
  