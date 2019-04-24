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
                data: 'type'
                // render: (data) => {
                //     switch (data) {
                //         case 'INTERFACE_TYPE_TAP':
                //             return 'Linux Bridge';
                //         case 'INTERFACE_TYPE_OVS':
                //             return 'OpenVSwitch';
                //     }
                // }
            }, {
                data: 'settings',
                defaultContent: 'None',
                render: (data, type) => {
                    if (type !== 'None') {
                        var render = '<a href="' + 
                        Routing.generate('edit_network_settings', {
                            id: data.id
                        }) +
                        '">' +
                        data.name + 
                        '</a>';

                        return render;
                    }
                }
            }, {
                data: 'device',
                defaultContent: 'None',
                render: (data, type) => {
                    if (type !== 'None') {
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
  