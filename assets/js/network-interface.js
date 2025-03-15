/**
 * This file implements JavaScript for network-interfaces/
 */

import API from './api';

const api = new API('network_interface')

$(function () {
    var networkInterfaceTable = $('#networkInterfaceTable').DataTable({
        ajax: {
            url: '/network-interfaces',
            dataSrc: ''
        },
        buttons: [{
            extend: 'edit',
            action: function() {
                let id = $('table tr.selected').data('id');
                api.edit('/admin/network-interfaces/' + id + '/edit');
            }
        }, {
            extend: 'delete',
            action: function() {
                let id = $('table tr.selected').data('id');
                api.delete('/api/network-interfaces/' + id);
            }
        }],
        columns: [{
                data: 'name'
            }, {
                data: 'type'
                // render: (data) => {
                //     switch (data) {
                //         case 'INTERFACE_TYPE_TAP':
                //             return 'Linux Bridge';
                //         case 'INTERFACE_TYPE_OVS':
                //             return 'OpenVSwitch';
                //     }
                // }
            // }, {
            //     data: 'settings',
            //     defaultContent: 'None',
            //     render: (data, type) => {
            //         if (type !== 'None' && data !== undefined) {
            //             var render = '<a href="' + 
            //             Routing.generate('edit_network_settings', {
            //                 id: data.id
            //             }) +
            //             '">' +
            //             data.name + 
            //             '</a>';

            //             return render;
            //         }
            //     }
            }, {
                data: 'settings',
                defaultContent: 'Local only',
                render: (data, type) => {
                    if (type !== 'None' && data !== undefined) {
                        //console.log(data);
                        var render = data.protocol;

                        return render;
                    }
                }
            }, {
                data: 'macAddress',
                defaultContent: 'None',
                render: (data, type) => {
                    if (type !== 'None' && data !== undefined) {
                        //(data);
                        var render = data;

                        return render;
                    }
                }
            }, {
                data: 'device',
                defaultContent: 'None',
                render: (data, type) => {
                    if (type !== 'None' && data !== undefined) {
                        var render = '<a href="/admin/devices/' + 
                        data.id +
                        '/edit">' +
                        data.name +
                        '</a>';

                        return render;
                    }
                }
        }]
    });
})
  