/**
* Remotelabz main JS file.
*
* Mainly used to customize plugins and loading them.
*
* @author Julien Hubert <julien.hubert@outlook.com>
*/

// require('jquery');

import '../css/style.scss';

import 'datatables.net-bs4/css/dataTables.bootstrap4.css';
import 'datatables.net-buttons-bs4/css/buttons.bootstrap4.css';
import 'datatables.net-select-bs4/css/select.bootstrap4.css';
import 'flag-icons/sass/flag-icons.scss';
import 'noty/src/noty.scss';
import 'noty/src/themes/mint.scss';
import 'simplemde/dist/simplemde.min.css';
import '@fortawesome/fontawesome-free/css/all.css';
import 'cropperjs/dist/cropper.min.css';

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
* Functions using jQuery goes here
*/
(function($) {
    'use strict';

    window.addEventListener("onwheel", { passive: false });

    // Switch themes
    //TODO: #665 On login page, this element is empty and generate error
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
    
})(jQuery);
