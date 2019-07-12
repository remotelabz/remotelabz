/**
 * This file implements JavaScript for networkSettingss/
 */

import API from './app';

const api = new API('network_settings')
  
$(function () {
    var networkSettingsTable = $('#networkSettingsTable').DataTable({
        ajax: {
            url: Routing.generate('get_network_settings'),
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
                data: 'ip',
                render: (data, type, row) => {
                    return row.ip + '<strong>/' + row.prefix4 + '</strong>';
                },
                defaultContent: ''
            }, {
                data: 'ipv6',
                render: (data, type, row) => {
                    return row.ipv6 + '<strong>/' + row.prefix6 + '</strong>';
                },
                defaultContent: ''
            }, {
                data: 'gateway',
                defaultContent: ''
            }, {
                data: 'protocol',
                defaultContent: ''
            }, {
                data: 'port',
                defaultContent: ''
        }]
    });
})
  