/**
* Remotelabz main JS file.
*
* Mainly used to customize plugins and loading them.
*
* @author Julien Hubert
* @author Florent Nolot
*/

// require('jquery');

import '../css/style.scss';

import 'datatables.net-bs4/css/dataTables.bootstrap4.css';
import 'datatables.net-buttons-bs4/css/buttons.bootstrap4.css';
import 'datatables.net-select-bs4/css/select.bootstrap4.css';
import 'flag-icons/sass/flag-icons.scss';
//import 'noty/src/noty.scss';
//import 'noty/src/themes/mint.scss';
import 'simplemde/dist/simplemde.min.css';
import '@fortawesome/fontawesome-free/css/all.css';
import 'cropperjs/dist/cropper.min.css';
import 'react-toastify/dist/ReactToastify.css';


import $ from 'jquery';
global.$ = global.jQuery = $;
import 'popper.js';
import 'bootstrap';
import 'datatables.net-bs4';
import 'datatables.net-buttons-bs4';
import 'datatables.net-select-bs4';
import '@novnc/novnc/lib/rfb.js';
import 'select2';
import 'select2/dist/css/select2.css';
import 'select2-bootstrap-5-theme/dist/select2-bootstrap-5-theme.min.css';

import './components/registration';
import 'react-toastify';
import './components/gauges';


import React from 'react';
import { createRoot } from 'react-dom/client';

import { ToastContainer } from 'react-toastify';

import notificationService from './notification-service';


const Cookies = require('js-cookie');

let theme = Cookies.get('theme');

if (theme !== undefined) {
    document.documentElement.setAttribute('theme', theme);
} else { // first visit
    if (window.matchMedia('(prefers-color-scheme: dark)')) {
        theme = 'dark';
    } else {
        theme = 'light';
    }

    document.documentElement.setAttribute('theme', theme);
    Cookies.set('theme', theme, {
        expires: 3650
    });
}

/**
 * Initialize ToastContainer
 * This needs to be mounted once in the DOM
 */
$(function() {
    // Create a div for the toast container if it doesn't exist
    if (!document.getElementById('toast-container-root')) {
        const toastRoot = document.createElement('div');
        toastRoot.id = 'toast-container-root';
        document.body.appendChild(toastRoot);
    }
    
    // Mount the ToastContainer
    const root = createRoot(document.getElementById('toast-container-root'));
    root.render(
        React.createElement(ToastContainer, {
            position: "top-right",
            autoClose: 5000,
            hideProgressBar: false,
            newestOnTop: true,
            closeOnClick: true,
            rtl: false,
            pauseOnFocusLoss: true,
            draggable: true,
            pauseOnHover: true,
            theme: theme === 'dark' ? 'dark' : 'light'
        })
    );
    window.toastRoot = root;
});


/**
* Functions using jQuery goes here
*/
(function($) {
    'use strict';

    window.addEventListener("onwheel", { passive: false });

    // Switch themes
    
    if (document.getElementById("themeSwitcher")) {
        document.getElementById("themeSwitcher").addEventListener('change', () => {
            if (document.getElementById("themeSwitcher").checked) {
                Cookies.set('theme', 'dark', {
                    expires: 3650
                });
            } else {
                Cookies.set('theme', 'light', {
                    expires: 3650
                });
            }
        });
    
        document.getElementById("themeSwitcherDiv").addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            document.getElementById("themeSwitcher").checked = !document.getElementById("themeSwitcher").checked;

            if (document.getElementById("themeSwitcher").checked) {
                document.documentElement.setAttribute('theme', 'dark');
            } else {
                document.documentElement.setAttribute('theme', 'light');
            }

            document.getElementById("themeSwitcher").dispatchEvent(new Event('change'));

            // Update toast theme when theme changes
            const newTheme = document.getElementById("themeSwitcher").checked ? 'dark' : 'light';
            if (window.toastRoot) {
                window.toastRoot.render(
                    React.createElement(ToastContainer, {
                        position: "top-right",
                        autoClose: 5000,
                        hideProgressBar: false,
                        newestOnTop: true,
                        closeOnClick: true,
                        rtl: false,
                        pauseOnFocusLoss: true,
                        draggable: true,
                        pauseOnHover: true,
                        theme: newTheme
                    })
                );
            }

        });
    };

    /**
    * Customize dataTables
    */
    $.fn.dataTable.ext.feature.push( {
        fnInit: function ( settings ){
            var api = new $.fn.dataTable.Api( settings );
            var table = api.table();
            var id = table.node().id
            var $input = $(`<div id="`+id+`_filter" class="dataTables_filter"><div class="input-group"><div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-search"></i></span></div><input type="search" class="form-control input-sm" placeholder="Search all columns" aria-controls="`+id+`" style="margin-left: 0;"></div></div>`)

            $input.on('keyup change','input',function(){
                if(table.search() !== this.value)
                table.search(this.value).draw()
            })
            return $input;
        },
        cFeature: 'F'
    } );

    $.extend( true, $.fn.dataTable.Buttons.defaults, {
        dom: {
            button: {
                className: 'btn'
            }
        }
    } );

    $.fn.dataTable.ext.buttons.edit = {
        extend: 'selectedSingle',
        text: '<i class="fa fa-edit"></i> Edit',
        className: 'btn-secondary'
    };

    $.fn.dataTable.ext.buttons.toggle = {
        extend: 'selected',
        text: '<i class="fa fa-lock"></i> (Un)lock',
        className: 'btn-warning'
    };

    $.fn.dataTable.ext.buttons.delete = {
        extend: 'selected',
        text: '<i class="fa fa-times"></i> Delete',
        className: 'btn-danger'
    };

    $.extend( true, $.fn.dataTable.defaults, {
        dom: "<'row'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'F>>" +
        "<'row'<'col-sm-12'tr>>" +
        "<'row'<'col-sm-12 col-md-5 small'i><'col-sm-12 col-md-7'p>>",
        order: [
            [1, "asc"]
        ],
        select: 'single',
        buttons: {
            dom: {
                button: {
                    tag: 'button',
                    className: 'btn'
                }
            },
            buttons: [
                'edit',
                'toggle',
                'delete'
            ]
        },
        createdRow: function (row, data) {
            $(row).data('id', data['id']);
        },
        drawCallback: function () {
            $('.dataTables_paginate > .pagination').addClass('rounded-separated pagination-info');
        }
    } );
    /**
    * End customize dataTables
    */

    /**
    * Dynamically attributes the active link in sidebar
    */
    $(function() {
        let sidebar = $('.sidebar');
        let sidebarElement = sidebar[0];
        let sidebarCollapseButton = document.getElementsByClassName('toggle-sidebar');

        //Close other submenu in sidebar on opening any

        sidebar.on('show.bs.collapse', '.collapse', function() {
            sidebar.find('.collapse.show').collapse('hide');
        });

        let sidebarCollapsed = Cookies.get('sidebar_collapsed');

        if (sidebarCollapsed === undefined) {
            Cookies.set('sidebar_collapsed', false, { expires: 3650 });
        }

        // if (sidebarCollapsed == "true") {
        //     sidebarElement.classList.add('sidebar-collapsed');
        // }

        for (let index = 0; index < sidebarCollapseButton.length; index++) {
            const element = sidebarCollapseButton[index];

            element.addEventListener("click", () => {
                sidebarElement.classList.toggle('sidebar-collapsed');
                let isCollapsed = sidebarElement.classList.contains('sidebar-collapsed');
                Cookies.set('sidebar_collapsed', isCollapsed, { expires: 3650 });
            });
        }
    });

    /**
    * File upload label displaying
    */
    $(function() {
        $('.file-upload-browse').on('click', function(){
            let file = $(this).parent().parent().parent().find('.file-upload-default');
            file.trigger('click');
        });
        $('.file-upload-default').on('change', function(){
            $(this).parent().find('.form-control').val($(this).val().replace(/C:\\fakepath\\/i, ''));
        });
    });

    /* Enable tooltips */
    $(function () {
        $('[data-toggle="tooltip"]').tooltip()
    })

    $('.custom-file input').change(function (e) {
        if (e.target.files.length) {
            $(this).next('.custom-file-label').html(e.target.files[0].name);
        }
    });

    /**
     * Initialize modern gauges if on resources page
     */
    if (document.querySelector('[data-page="resources"]')) {
        document.addEventListener('DOMContentLoaded', function() {
            drawWorkerGauges();
            
            // Redraw gauges on theme change
            if (document.getElementById("themeSwitcher")) {
                document.getElementById("themeSwitcher").addEventListener('change', () => {
                    setTimeout(function() {
                        drawWorkerGauges();
                    }, 100);
                });
            }
        });
    }

    /**
     * Display flash messages as notifications on page load
     */
    $(function() {
        // Display traditional flash messages
        $('.flash-notice').each(function() {
            const $flashElement = $(this);
            const message = $flashElement.text().trim();
            const type = $flashElement.hasClass('alert-danger') ? 'error' :
                        $flashElement.hasClass('alert-warning') ? 'warning' :
                        $flashElement.hasClass('alert-success') ? 'success' : 'info';
            
            // Show notification
            if (message) {
                notificationService[type](message);
            }
            
            // Hide the flash message element (we're showing it as a notification instead)
            $flashElement.hide();
        });
    });

    /**
     * Notification Polling System
     * Check for new notifications from backend every 3 seconds
     */
    
    $(function() {
        let isPolling = false;
        let shownNotificationIds = new Set();
        let pollingInterval = null;

        function checkNotifications() {
            if (isPolling) return;
            isPolling = true;

            $.ajax({
                url: '/api/notifications/unread',
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    if (response.notifications && Array.isArray(response.notifications)) {
                        response.notifications.forEach(notification => {
                            // Only show notifications we haven't shown yet
                            if (!shownNotificationIds.has(notification.id)) {
                                const type = notification.type || 'info';
                                const message = notification.message || 'Notification';
                                
                                // Display notification with appropriate timeout
                                const timeout = type === 'error' ? 0 : 5000;
                                notificationService[type](message, timeout);
                                
                                // Track that we've shown this notification
                                shownNotificationIds.add(notification.id);
                                
                                // Mark as read after showing
                                $.ajax({
                                    url: '/notifications/mark-read/' + notification.id,
                                    method: 'GET', // Changed to GET to match existing route
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest'
                                    },
                                    success: function() {
                                        console.log('Notification ' + notification.id + ' marked as read');
                                    },
                                    error: function(xhr, status, error) {
                                        console.error('Failed to mark notification as read:', error);
                                    }
                                });
                            }
                        });
                    }
                },
                error: function(xhr, status, error) {
                    // Silently fail if user is not authenticated or other errors
                    if (xhr.status !== 401) {
                        console.error('Failed to fetch notifications:', error);
                    }
                },
                complete: function() {
                    isPolling = false;
                }
            });
        }

        // Initial check on page load
        checkNotifications();

        // Poll every 3 seconds
        pollingInterval = setInterval(checkNotifications, 3000);

        // Clean up on page unload
        $(window).on('beforeunload', function() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
        });
    });
    
    
    
})(jQuery);
