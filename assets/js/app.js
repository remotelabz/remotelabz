/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you require will output into a single css file (app.css in this case)
// require('../css/app.css');
require('../css/style.scss')

require('datatables.net-bs4/css/dataTables.bootstrap4.css');
require('datatables.net-buttons-bs4/css/buttons.bootstrap4.css');
require('datatables.net-select-bs4/css/select.bootstrap4.css');

// Need jQuery? Install it with "yarn add jquery", then uncomment to require it.
var $ = require('jquery');

require('bootstrap');
require('@fortawesome/fontawesome-free/js/all');
require('datatables.net-bs4');
require('datatables.net-buttons-bs4');
require('datatables.net-select-bs4');
require('icheck');

(function($) {
  'use strict';
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
      if ($('.scroll-container').length) {
        const ScrollContainer = new PerfectScrollbar('.scroll-container');
      }
    }

    //checkbox and radios
    $(".form-check label,.form-radio label").append('<i class="input-helper"></i>');


    $(".purchace-popup .popup-dismiss").on("click",function(){
      $(".purchace-popup").slideToggle();
    });
  });
})(jQuery);

$(function() {
  /**
   * Creates a Bootstrap 3 customized alert.
   * @param {string} type 
   * @param {string} message 
   */
  function Notify(type, message) {
    var html = '<div class="flash-notice alert alert-' + type + ' alert-dismissible fade in">' +
      '	<button aria-label="Close" class="close" data-dismiss="alert" type="dismiss">' +
      '		<span aria-hidden="true">x</span>' +
      '	</button>' +
        message +
    '</div>'

    $('.flashbag-container').append(html);
  }
})