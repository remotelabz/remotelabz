/**
 * This file implements JavaScript for flavors/
 */

import API from './api';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { toast } from 'react-toastify';



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
                api.delete('/api/flavors/' + $('table tr.selected').data('id'))
                    .catch(error => {
                        if (error.response && error.response.status === 409) {
                            toast.error(error.response.data.error, {
                                autoClose: 10000,
                            });
                        }
                    });
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
  