// vim: syntax=javascript tabstop=4 softtabstop=0 noexpandtab laststatus=1 ruler

/**
 * html/themes/default/js/javascript.js
 *
 * Startup scripts
 *
 * @author Andrea Dainese <andrea.dainese@gmail.com>
 * @copyright 2014-2016 Andrea Dainese
 * @license BSD-3-Clause https://github.com/dainok/unetlab/blob/master/LICENSE
 * @link http://www.unetlab.com/
 * @version 20160719
 */

// Custom vars
var DEBUG = 5;
var TIMEOUT = 30000;
var LONGTIMEOUT = 600000;
var STATUSINTERVAL = 5000;

// Global vars
var EMAIL;
var FOLDER;
var LAB;
var LANG;
var NAME;
var ROLE;
var TENANT;
var USERNAME;
var ATTACHMENTS;
var UPDATEID;
var LOCK = 0 ; 
var isIE = getInternetExplorerVersion() > -1;
var FOLLOW_WRAPPER_IMG_STATE = 'resized'
var EVE_VERSION = "2.0.3-86";

$(document).ready(function() {
		// getUserInfo();
		printPageLabOpen("/test.unl")

       var timer;

       $(document).on('click', '#alert_container', function(e){
           if(timer){
	       clearTimeout(timer);
           }
	   
	   var container = $(this).next().first();
           container.slideToggle(300);
	   setTimeout(function(){
		container.slideUp(300);
           }, 2700);

       });
});
