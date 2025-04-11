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

import { getInternetExplorerVersion } from './browsers.js';
import '../bootstrap/js/jquery-cookie-1.4.1';
import { logger, getUserInfo, postLogin, printPageAuthentication } from'./functions.js';
// Custom vars
export var DEBUG = 5;
export var TIMEOUT = 30000;
export var LONGTIMEOUT = 600000;
export var STATUSINTERVAL = 5000;

// Global vars
export var EMAIL;
export var FOLDER;
export var LAB;
export var LANG;
export var NAME;
export var ROLE;
export var TENANT;
export var USERNAME;
export var ATTACHMENTS;
export var UPDATEID;
export var LOCK = 0 ; 
export var EDITION;
export var isIE = getInternetExplorerVersion() > -1;
export var FOLLOW_WRAPPER_IMG_STATE = 'resized'
export var EVE_VERSION = "5.0.1-19";
export var AUTHOR;
export var TEMPLATE;
export var ISGROUPOWNER;
export var HASGROUPACCESS;
export var VIRTUALITY;

export function setFolder(value){
	FOLDER = value;
}
export function setLab(value){
	LAB = value;
}
export function setLang(value){
	LANG = value;
}
export function setEmail(value){
	EMAIL = value;
}
export function setName(value){
	NAME = value;
}
export function setUserName(value){
	USERNAME = value;
}
export function setRole(value){
	ROLE = value;
}
export function setTenant(value){
	TENANT = value;
}
export function setUpdateId(value){
	UPDATEID = value;
}
export function setLock(value){
	LOCK = value;
}

export function setAttachements(value) {
	ATTACHMENTS = value;
}

export function setEditon(value) {
	EDITION = value;
}

export function setAuthor(value) {
	AUTHOR = value;
}

export function setTemplate(value) {
	TEMPLATE = value;
}

export function setIsGroupOwner(value) {
	ISGROUPOWNER = value;
}

export function setHasGroupAccess(value) {
	HASGROUPACCESS = value;
}

export function setVirtuality(value) {
	VIRTUALITY = value;
}

$(document).ready(function() {
	if ($.cookie('privacy') != 'true') {
		// Cookie is not set, show a modal with privacy policy
		logger(1, 'DEBUG: need to accept privacy.');
		//addModal('Privacy Policy', '<p>We use cookies on this site for our own business purposes including collecting aggregated statistics to analyze how our site is used, integrating social networks and forums and to show you ads tailored to your interests. Find out our <a href="http://www.unetlab.com/about/privacy.html" title="Privacy Policy">privacy policy</a> for more information.</p><p>By continuing to browse the site, you are agreeing to our use of cookies.</p>', '<button id="privacy" type="button" class="btn btn-aqua" data-dismiss="modal">Accept</button>');
		$.cookie('privacy', 'true', {
                    expires: 90,
                    path: '/'
                });
                if ($.cookie('privacy') == 'true') {
                      window.location.reload();
                } 
	} else {
		// Privacy policy already been accepted, check if user is already authenticated
		$.when(getUserInfo()).done(function() {
			// User is authenticated
			logger(1, 'DEBUG: loading language.');
			/*$.getScript('./components/Editor2/themes/default/js/messages_' + LANG + '.js')
				.done(function() {*/
					postLogin();
				/*})
				.fail(function() {
					logger(1, 'DEBUG: error loading language.');
				});*/
		}).fail(function(data) {
			// User is not authenticated, or error on API
			logger(1, 'DEBUG: loading authentication page.');
			printPageAuthentication();
		});
	}
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
