/**
* Remotelabz main JS file.
* 
* Mainly used to customize plugins and loading them.
* 
* @author Julien Hubert <julien.hubert@outlook.com>
*/

require('jquery');

require('../css/style.scss');

require('datatables.net-bs4/css/dataTables.bootstrap4.css');
require('datatables.net-buttons-bs4/css/buttons.bootstrap4.css');
require('datatables.net-select-bs4/css/select.bootstrap4.css');
require('flag-icon-css/sass/flag-icon.scss');
require('noty/src/noty.scss')
require('noty/src/themes/mint.scss')
require('simplemde/dist/simplemde.min.css')
require('font-awesome/scss/font-awesome.scss')
require('cropperjs/dist/cropper.min.css')
require('vis-network/dist/vis-network.min.css')

require('popper.js');
require('bootstrap');
require('datatables.net-bs4');
require('datatables.net-buttons-bs4');
require('datatables.net-select-bs4');
require('icheck');
require('selectize');
require('select2');
require('@novnc/novnc/core/rfb');
require("jsplumb");

/**
* Functions using jQuery goes here
*/
(function($) {
    'use strict';
    
    window.addEventListener("onwheel", { passive: false });
    
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
    * Dynamically attrubutes the active link in sidebar
    */
    $(function() {
        var sidebar = $('.sidebar');
        
        //Add active class to nav-link based on url dynamically
        //Active class can be hard coded directly in html file also as required
        var current = location.pathname.split("/").slice(-1)[0].replace(/^\/|\/$/g, '');
        $('.nav li a', sidebar).each(function() {
            var $this = $(this);
            if (current === "") {
                //for root url
                if ($this.attr('href').indexOf("index.html") !== -1) {
                    $(this).parents('.nav-item').last().addClass('active');
                    if ($(this).parents('.sub-menu').length) {
                        $(this).closest('.collapse').addClass('show');
                        $(this).addClass('active');
                    }
                }
            } else {
                //for other url
                if ($this.attr('href').indexOf(current) !== -1) {
                    $(this).parents('.nav-item').last().addClass('active');
                    if ($(this).parents('.sub-menu').length) {
                        $(this).closest('.collapse').addClass('show');
                        $(this).addClass('active');
                    }
                }
            }
        })
        
        //Close other submenu in sidebar on opening any
        
        sidebar.on('show.bs.collapse', '.collapse', function() {
            sidebar.find('.collapse.show').collapse('hide');
        });
        
        
        //Change sidebar and content-wrapper height
        applyStyles();
        
        function applyStyles() {
            //Applying perfect scrollbar
            // if ($('.scroll-container').length) {
            //     const ScrollContainer = new PerfectScrollbar('.scroll-container');
            // }
        }
        
        //checkbox and radios
        // $(".form-check label,.form-radio label").append('<i class="input-helper"></i>');
        
        
        $(".purchace-popup .popup-dismiss").on("click",function(){
            $(".purchace-popup").slideToggle();
        });
    });
    
    /**
    * File upload label displaying
    */
    $(function() {
        $('.file-upload-browse').on('click', function(){
            var file = $(this).parent().parent().parent().find('.file-upload-default');
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
})(jQuery);
