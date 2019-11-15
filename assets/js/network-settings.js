/**
 * This file implements JavaScript for networkSettingss/
 */

import API from './api';

const api = new API('network_settings')
  
$(function () {
    var networkSettingsTable = $('#networkSettingsTable').DataTable({
        ajax: {
            url: '/network-settings',
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
                    if (row.ip !== undefined || row.prefix4 !== undefined)
                        return row.ip + '<strong>/' + row.prefix4 + '</strong>';
                    else
                        return '';
                },
                defaultContent: ''
            }, {
                data: 'ipv6',
                render: (data, type, row) => {
                    if (row.ipv6 !== undefined || row.prefix6 !== undefined)
                        return row.ipv6 + '<strong>/' + row.prefix6 + '</strong>';
                    else
                        return '';
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
  