/**
 * This file implements JavaScript for network-interfaces/
 */

import API from './app';

const api = new API('network_interface')
  
$(function () {
    var networkInterfaceTable = $('#networkInterfaceTable').DataTable({
        ajax: {
            url: Routing.generate('get_network_interfaces'),
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
                        console.log(data);
                        var render = data.protocol;

                        return render;
                    }
                }
            }, {
                data: 'mac_address',
                defaultContent: 'None',
                render: (data, type) => {
                    if (type !== 'None' && data !== undefined) {
                        console.log(data);
                        var render = data;

                        return render;
                    }
                }
            }, {
                data: 'device',
                defaultContent: 'None',
                render: (data, type) => {
                    if (type !== 'None' && data !== undefined) {
                        var render = '<a href="' + 
                        Routing.generate('edit_device', {
                            id: data.id
                        }) +
                        '">' +
                        data.name + 
                        '</a>';

                        return render;
                    }
                }
        }]
    });
})
  