/**
 * This file implements JavaScript for flavors/
 */

import API from './api';
import { ToastContainer, toast } from 'react-toastify';
import { createRoot } from 'react-dom/client';
import React from 'react';

const api = new API('flavor')

const toastDiv = document.createElement('div');
toastDiv.id = 'toast-root';
document.body.appendChild(toastDiv);
const root = createRoot(toastDiv);
root.render(
    <ToastContainer
        position="top-right"
        autoClose={5000}
        hideProgressBar={false}
        closeOnClick
        pauseOnHover
        draggable
        pauseOnFocusLoss={false}
    />
);

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
  