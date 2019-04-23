/**
 * This file implements JavaScript for hypervisors/
 */

import API from './app';

const api = new API('hypervisor')
  
$(function () {
    var hypervisorTable = $('#hypervisorTable').DataTable({
        ajax: {
            url: Routing.generate('get_hypervisors'),
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
                data: 'command'
            }, {
                data: 'arguments'
        }]
    });
})
  