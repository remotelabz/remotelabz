// vim: syntax=javascript tabstop=4 softtabstop=0 noexpandtab laststatus=1 ruler

/**
 * html/themes/default/js/functions.js
 *
 * Functions
 *
 * @author Andrea Dainese <andrea.dainese@gmail.com>
 * @copyright 2014-2016 Andrea Dainese
 * @license BSD-3-Clause https://github.com/dainok/unetlab/blob/master/LICENSE
 * @link http://www.unetlab.com/
 * @version 20160719
 */

import {DEBUG, TIMEOUT, LAB, NAME, ROLE, AUTHOR, UPDATEID, LOCK, EDITION, TEMPLATE, ISGROUPOWNER, HASGROUPACCESS, VIRTUALITY,
       setLab, setLang, setLock, setUserName, setEmail, setRole, setTenant, setUpdateId, setTemplate, setIsGroupOwner, setHasGroupAccess,
       STATUSINTERVAL, ATTACHMENTS, isIE, setEditon, setAuthor,
      setVirtuality} from './javascript';
import {MESSAGES} from './messages_en';
import '../bootstrap/js/jquery-3.2.1.min';
import '../bootstrap/js/tinytools.toggleswitch.min';
import '../bootstrap/js/jquery-ui-1.12.1.min';
import '../bootstrap/js/jquery-cookie-1.4.1';
import '../bootstrap/js/jquery.validate-1.14.0.min';
import '../bootstrap/js/jquery.hotkey';
import '../bootstrap/js/jsPlumb-2.4.min';
import '../bootstrap/js/bootstrap.min';
import '../bootstrap/js/bootstrap-select.min';
import '../bootstrap/js/imageMapResizer.min';
import {validateLabInfo, validateLabPicture, validateNode} from './validate'
import './ejs';
import {fromByteArray,toByteArray,TextEncoderLite, TextDecoderLite} from './b64encoder';
var contextMenuOpen = false;
import { adjustZoom, readCookie, initTextarea, initEditor } from './ebs/functions';
import {ObjectPosUpdate} from './actions';
//import * as ace from 'ace-builds/src-noconflict/ace';
import { node } from 'prop-types';
import EasyMDE from 'easymde';
import 'easymde/dist/easymde.min.css';
import Dropzone from 'dropzone';


// Basename: given /a/b/c return c
export function basename(path) {
    return path.replace(/\\/g, '/').replace(/.*\//, '');
}

// Dirname: given /a/b/c return /a/b
export function dirname(path) {
    var dir = path.replace(/\\/g, '/').replace(/\/[^\/]*$/, '');
    if (dir == '') {
        return '/';
    } else {
        return dir;
    }
}

// Alert management
export function addMessage(severity, message, notFromLabviewport) {
    // Severity can be success (green), info (blue), warning (yellow) and danger (red)
    // Param 'notFromLabviewport' is used to filter notification
    $('#alert_container').show();
    var timeout = 10000;        // by default close messages after 10 seconds
    if (severity == 'danger') timeout = 5000;
    if (severity == 'alert') timeout = 10000;
    if (severity == 'warning') timeout = 10000;

    // Add notifications to #alert_container only when labview is open
    if ($("#lab-viewport").length) {
        if (!$('#alert_container').length) {
            // Add the frame container if not exists
            $('body').append('<div id="alert_container"><b><i class="fa fa-bell-o"></i> Notifications<i id="alert_container_close" class="pull-right fa fa-times" style="color: red; margin: 5px;cursor:pointer;"></b><div class="inner"></div></div></div>');
        }
        var msgalert = $('<div class="alert alert-' + severity.toLowerCase() + ' fade in">').append($('<button type="button" class="close" data-dismiss="alert">').append("&times;")).append(message);

        // Add the alert div to top (prepend()) or to bottom (append())
        $('#alert_container .inner').prepend(msgalert);

    }

    if ($("#lab-viewport").length || (!$("#lab-viewport").length && notFromLabviewport)) {
        if (!$('#notification_container').length) {
            $('body').append('<div id="notification_container"></div>');
        }

        //if (severity == "danger" )
        if (severity != "") {
            var notification_alert = $('<div class="alert alert-' + severity.toLowerCase() + ' fade in">').append($('<button type="button" class="close" data-dismiss="alert">').append("&times;")).append(message);

            $('#notification_container').prepend(notification_alert);
            if (timeout) {
                window.setTimeout(function () {
                    notification_alert.alert("close");
                }, timeout);
            }
        }
    }
    $('#alert_container').next().first().slideDown();
}

/* Add Modal
@param prop - helping classes. E.g prop = "red-text capitalize-title"
*/
export function addModal(title, body, footer, prop) {
    var html = '<div aria-hidden="false" style="display: block;z-index: 10000;" class="modal ' + ' ' + prop + ' fade in" tabindex="-1" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button><h4 class="modal-title">' + title + '</h4></div><div class="modal-body">' + body + '</div><div class="modal-footer">' + footer + '</div></div></div></div>';
    $('body').append(html);
    $('body > .modal').modal('show');
    $('.modal-dialog').draggable({handle: ".modal-header"});
}

// Add Modal
export function addModalError(message) {
    var html = '<div aria-hidden="false" style="display: block; z-index: 99999" class="modal fade in" tabindex="-1" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button><h4 class="modal-title">' + MESSAGES[15] + '</h4></div><div class="modal-body">' + message + '</div><div class="modal-footer"></div></div></div></div>';
    $('body').append(html);
    $('body > .modal').modal('show');
}

// Add Modal
export function addModalWide(title, body, footer, property) {
    // avoid open wide modal twice
    if ( $('.modal.fade.in').length > 0 && property.match('/second-win/') != null ) return ;
    var prop = property || "";
    var addittionalHeaderBtns = "";
    if (title.toUpperCase() == "STARTUP-CONFIGS" || title.toUpperCase() == "CONFIGURED NODES" ||
        title.toUpperCase() == "CONFIGURED TEXT OBJECTS" ||
        title.toUpperCase() == "CONFIGURED NETWORKS" || title.toUpperCase() == "CONFIGURED NODES" ||
        title.toUpperCase() == "STATUS") {
        addittionalHeaderBtns = '<i title="Make transparent" class="glyphicon glyphicon-certificate pull-right action-changeopacity"></i>'
    }
    var html = '<div aria-hidden="false" style="display: block;" class="modal modal-wide ' + prop + ' fade in" tabindex="-1" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"></i><button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>' + addittionalHeaderBtns + '<h4 class="modal-title">' + title + '</h4></div><div class="modal-body">' + body + '</div><div class="modal-footer">' + footer + '</div></div></div></div>';
    $('body').append(html);
    $('body > .modal').modal('show');
}

// Export node(s) config
/*export function cfg_export(node_id) {
    var deferred = $.Deferred();
    var lab_filename = $('#lab-viewport').attr('data-path');
    var url = '/api/labs' + lab_filename + '/nodes/' + node_id + '/export';
    var type = 'PUT';
    $.ajax({
        cache: false,
        timeout: TIMEOUT * 10,  // Takes a lot of time
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: config exported.');
                deferred.resolve(data['data']);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    return deferred.promise();
}*/

// // Export node(s) config recursive
/*export function recursive_cfg_export(nodes, i) {
    i = i - 1
    addMessage('info', nodes[Object.keys(nodes)[i]]['name'] + ': ' + MESSAGES[138])
    var deferred = $.Deferred();
    var lab_filename = $('#lab-viewport').attr('data-path');
    if (typeof nodes[Object.keys(nodes)[i]]['path'] === 'undefined') {
        var url = '/api/labs' + lab_filename + '/nodes/' + Object.keys(nodes)[i] + '/export';
    } else {
        var url = '/api/labs' + lab_filename + '/nodes/' + nodes[Object.keys(nodes)[i]]['path'] + '/export';
    }
    logger(1, 'DEBUG: ' + url);
    var type = 'PUT';
    $.ajax({
        cache: false,
        timeout: TIMEOUT * 10 * i,  // Takes a lot of time
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: config exported.');
                addMessage('success', nodes[Object.keys(nodes)[i]]['name'] + ': ' + MESSAGES[79])
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                addMessage('danger', nodes[Object.keys(nodes)[i]]['name'] + ': ' + data['message']);
            }
            if (i > 0) {
                recursive_cfg_export(nodes, i);
            } else {
                addMessage('info', 'Export All: done');
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            addMessage('danger', nodes[Object.keys(nodes)[i]]['name'] + ': ' + message);
            if (i > 0) {
                recursive_cfg_export(nodes, i);
            } else {
                addMessage('info', 'Export All: done');
            }
        }
    });
    return deferred.promise();
}*/

// Close lab
export function closeLab() {
    var deferred = $.Deferred();
    $.when(getNodes()).done(function (values) {
        var running_nodes = false;
        $.each(values, function (node_id, node) {
            if (node['status'] > 1) {
                running_nodes = true;
            }
        });

        var url = '/api/labs/close';
        var type = 'DELETE';
        $.ajax({
            cache: false,
            timeout: TIMEOUT,
            type: type,
            url: encodeURI(url),
            dataType: 'json',
            success: function (data) {
                if (data['status'] == 'success') {
                    logger(1, 'DEBUG: lab closed.');
                    setLab(null);
                    deferred.resolve();
                } else {
                    // Application error
                    logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                    deferred.reject(data['message']);
                }
            },
            error: function (data) {
                // Server error
                var message = getJsonMessage(data['responseText']);
                logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
                logger(1, 'DEBUG: ' + message);
                deferred.reject(message);
            }
        });
    }).fail(function (message) {
        // Lab maybe does not exist, closing
        var url = '/api/labs/close';
        var type = 'DELETE';
        $.ajax({
            cache: false,
            timeout: TIMEOUT,
            type: type,
            url: encodeURI(url),
            dataType: 'json',
            success: function (data) {
                if (data['status'] == 'success') {
                    logger(1, 'DEBUG: lab closed.');
                    LAB = null;
                    deferred.resolve();
                } else {
                    // Application error
                    logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                    deferred.reject(data['message']);
                }
            },
            error: function (data) {
                // Server error
                var message = getJsonMessage(data['responseText']);
                logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
                logger(1, 'DEBUG: ' + message);
                deferred.reject(message);
            }
        });
    });
    return deferred.promise();
}

// Delete node
export function deleteNode(id) {
    var deferred = $.Deferred();
    var type = 'DELETE';
    var lab_filename = $('#lab-viewport').attr('data-path');
    var url = '/api/labs/' + lab_filename + '/nodes/' + id;
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: node deleted.');
                deferred.resolve();
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    })
    return deferred.promise();
}

// HTML Form to array
export function form2Array(form_name) {
    var form_array = {};
    $('form :input[name^="' + form_name + '["]').each(function (id, object) {
        // INPUT name is in the form of "form_name[value]", get value only
        form_array[$(this).attr('name').substr(form_name.length + 1, $(this).attr('name').length - form_name.length - 2)] = $(this).val();
    });
    return form_array;
}

// HTML Form to array by row
function form2ArrayByRow(form_name, id) {
    var form_array = {};

    $('form :input[name^="' + form_name + '["][data-path="' + id +'"]').each(function (id, object) {
        // INPUT name is in the form of "form_name[value]", get value only
        form_array[$(this).attr('name').substr(form_name.length + 1, $(this).attr('name').length - form_name.length - 2)] = $(this).val();
    });
    return form_array;
}

// Get JSon message from HTTP response
export function getJsonMessage(response) {
    var message = '';
    try {
        message = JSON.parse(response)['message'];
        code = JSON.parse(response)['code'];
        if (code == 412) {
            // if 412 should redirect (user timed out)
            window.setTimeout(function () {
                location.reload();
            }, 2000);
        }
    } catch (e) {
        if (response != '') {
            message = response;
        } else {
            message = 'Undefined message, check if the UNetLab VM is powered on. If it is, see <a href="/Logs" target="_blank">logs</a>.';
        }
    }
    return message;
}

// Get lab info
export function getLabInfo(labId) { 
    var deferred = $.Deferred();
    var url = '/api/labs/info/' + labId;
    var type = 'GET';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: lab "' + labId + '" found.');
                deferred.resolve(data['data']);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
        }
    })
    return deferred.promise();
}

// Get lab body
export function getLabBody() {
    var deferred = $.Deferred();
    var lab_filename = $('#lab-viewport').attr('data-path');
    var url = '/api/labs/' + lab_filename + '/html';
    var type = 'GET';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: lab "' + lab_filename + '" body found.');
                deferred.resolve(data['data']);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    return deferred.promise();
}

// Get lab nodes
export function getNodes(node_id) {
    var deferred = $.Deferred();
    var lab_filename = $('#lab-viewport').attr('data-path');    
    var labInstance;
    var edition;
    var pathname = window.location.pathname;
    if(EDITION == 1) {
        labInstance = null;
        edition = EDITION;
    }
    if(EDITION == 0) {
       labInstance = pathname.split(/(\d+)/)[3];
       edition = EDITION;
    }
    var node_data = {};
    node_data['edition'] = edition;
    node_data['labInstance'] = labInstance;
    if (node_id != null) {
        var url = '/api/labs/' + lab_filename + '/nodes/' + node_id;
    } else {
        var url = '/api/labs/' + lab_filename + '/nodes';
    }
    var type = 'POST';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        data: JSON.stringify(node_data),
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: got node(s) from lab "' + lab_filename + '".');
                deferred.resolve(data['data']);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    return deferred.promise();
}

// Get node startup-config
/*export function getNodeConfigs(node_id) {
    var deferred = $.Deferred();
    var lab_filename = $('#lab-viewport').attr('data-path');
    if (node_id != null) {
        //configs = configList[id];
        var url = '/api/labs/' + lab_filename + '/configs/' + node_id;
    } else {
        //configs = configList;
        var url = '/api/labs/' + lab_filename + '/configs';
    }
    var type = 'GET';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: got sartup-config(s) from lab "' + lab_filename + '".');
                deferred.resolve(data['data']);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    return deferred.promise();
}*/

// Get lab node interfaces
export function getNodeInterfaces(node_id) {
    var deferred = $.Deferred();
    var lab_filename = $('#lab-viewport').attr('data-path');
    var url = '/api/labs/' + lab_filename + '/nodes/' + node_id + '/interfaces';
    var type = 'GET';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        success: function (data) {
            if (data['status'] == 'success') {
                deferred.resolve(data['data']);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    return deferred.promise();
}


// Get lab topology
export function getTopology() {
    var deferred = $.Deferred();
    var lab_filename = $('#lab-viewport').attr('data-path');
    var url = '/api/labs/' + lab_filename + '/topology';
    var type = 'GET';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: got topology from lab "' + lab_filename + '".');
                deferred.resolve(data['data']);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    return deferred.promise();
}

// Get templates
function getTemplates(template) {
    var deferred = $.Deferred();
    var templateData;
    var url = (template == null) ? '/api/list/templates' : '/api/list/templates/' + template;
    var type = 'POST';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        data: JSON.stringify({'virtuality': VIRTUALITY}),
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: got template(s).');
                deferred.resolve(data['data']);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    return deferred.promise();
}

// Get user info
export function getUserInfo() {
    var pathname = window.location.pathname;
    var lab = pathname.split(/(\d+)/)[1];
    var labInstance;
    if(pathname.split(/(\d+)/)[3] != null) {
        labInstance = pathname.split(/(\d+)/)[3];
    }
    else {
        labInstance = null;
    }
    var deferred = $.Deferred();
   var url = '/api/user/rights/lab/' + lab;
    var type = 'POST';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        data: JSON.stringify({"labInstance": labInstance}),
        url: encodeURI(url),
        dataType: 'json',
        beforeSend: function (jqXHR) {
            if (window.BASE_URL) {
                jqXHR.crossDomain = true;
            }
        },
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: user is authenticated.');
                setEmail(data['data']['email']);
                setUserName(data['data']['username']);
                setLang("en");
                setLab(lab);
                setTenant("0");
                setIsGroupOwner(data['data']['isGroupOwner']);
                setHasGroupAccess(data['data']['hasGroupAccess']);
                setRole(data['data']['role']);
                setVirtuality(data['data']['virtuality']);
                if(pathname == '/admin/labs/' + LAB + '/edit' || pathname == '/admin/labs_template/' + LAB + '/edit') {
                    setEditon(1);
                }
                else if(pathname == '/labs/' + LAB + '/see/' + labInstance || pathname == '/labs/guest/' + LAB + '/see/' + labInstance) {
                    setEditon(0);
                }
                if (pathname == '/admin/labs_template/' + LAB + '/edit') {
                    setTemplate(1);
                }
                else {
                    setTemplate(0);
                }
                setAuthor(data['data']['author']);
                deferred.resolve(data['data']);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    return deferred.promise();
}


// Logging
export function logger(severity, message) {
    if (DEBUG >= severity) {
        console.log(message);
    }
    $('#alert_container').next().first().slideDown();
}


// Post login
export function postLogin(param) {
    if (UPDATEID != null) {
        // Stop updating node_status
        clearInterval(UPDATEID);
    }
    $('body').removeClass('login');
    if (LAB == null) {
        setLab(param);
    }
    logger(1, 'DEBUG: loading lab "' + LAB + '".');


    printPageLabOpen(LAB);
    // Update node status
    setUpdateId(setInterval(function () {printLabStatus(LAB)}, STATUSINTERVAL));

}
// Post login
export function newUIreturn(param) {
    var lab_filename = $('#lab-viewport').attr('data-path');
    if (UPDATEID != null) {
        // Stop updating node_status
        clearInterval(UPDATEID);
    }
    if (TEMPLATE == 1) {
        $('body').removeClass('login');
        window.location.href = "/admin/sandbox";
    }
    else {
        $('body').removeClass('login');
        window.location.href = "/labs/"+ lab_filename ;
    }
    
}

// Set multiple node position
export function setNodesPosition(nodes) {
    var deferred = $.Deferred();
    if ( nodes.length == 0 ) { deferred.resolve(); return deferred.promise(); }
    var lab_filename = $('#lab-viewport').attr('data-path');
    var form_data = [];
    form_data=nodes;
    var url = '/api/labs/' + lab_filename + '/editordata' ;
    var type = 'PUT';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        data: JSON.stringify(form_data),
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: node position updated.');
                deferred.resolve();
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    return deferred.promise();
}

// Update node data from node list
export function setNodeData(id){
    var lab_filename = $('#lab-viewport').attr('data-path');
    var form_data = form2ArrayByRow('node', id);
    var promises = [];
    logger(1, 'DEBUG: posting form-node-edit form.');
    var url = '/api/labs/' + lab_filename + '/node/' + id;
    var type = 'PUT';
    form_data['id'] = id;
    form_data['count'] = 1;
    form_data['postfix'] = 0;
    for (var i = 0; i < form_data['count']; i++) {
        form_data['left'] = parseInt(form_data['left']) + i * 10;
        form_data['top'] = parseInt(form_data['top']) + i * 10;
        var request = $.ajax({
        cache: false,
            timeout: TIMEOUT,
            type: type,
            url: encodeURI(url),
            dataType: 'json',
            data: JSON.stringify(form_data),
            success: function (data) {
                if (data['status'] == 'success') {
                    logger(1, 'DEBUG: node "' + form_data['name'] + '" saved.');
                    // Close the modal
                    $("#node" + id + " .node_name").html('<i class="node' + id + '_status glyphicon glyphicon-stop"></i>' + form_data['name'])
                    $("#node" + id + " a img").attr("src", "/build/editor/images/icons/" + form_data['icon'])
                    addMessage(data['status'], data['message']);
                } else {
                    // Application error
                    logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                    addModal('ERROR', '<p>' + data['message'] + '</p>', '<button type="button" class="btn btn-flat" data-dismiss="modal">Close</button>');
                }
            },
            error: function (data) {
                // Server error
                var message = getJsonMessage(data['responseText']);
                logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
                logger(1, 'DEBUG: ' + message);
                addModal('ERROR', '<p>' + message + '</p>', '<button type="button" class="btn btn-flat" data-dismiss="modal">Close</button>');
            }
        });
    }
    logger(1,"data is sent");
    return false;
}

//set note interface
export function setNodeInterface(node_id,interface_id,vlan, connection, connector, connector_label){

    var deferred = $.Deferred();
    var lab_filename = $('#lab-viewport').attr('data-path');
    var form_data = {};
    form_data["interface id"] = interface_id;
    form_data["vlan"] = vlan;
    form_data["connection"] = connection;
    form_data["connector"] = connector;
    form_data["connector_label"] = connector_label;

    var url = '/api/labs/' + lab_filename + '/nodes/' + node_id +'/interfaces';
    var type = 'PUT';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        data: JSON.stringify(form_data),
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: node interface updated.');
                deferred.resolve(data);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    return deferred.promise();

}

//edit node interface
export function editConnection(connection, connector, connector_label){

    var deferred = $.Deferred();
    var lab_filename = $('#lab-viewport').attr('data-path');
    var url = '/api/labs/' + lab_filename + '/interfaces/' + connection + '/edit';
    var type = 'PUT';
    var form_data = {"connector": connector, "connector_label": connector_label}
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        data: JSON.stringify(form_data),
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: connection edited.');
                deferred.resolve(data);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    return deferred.promise();

}

//set note interface
export function removeConnection(connection){

    var deferred = $.Deferred();
    var lab_filename = $('#lab-viewport').attr('data-path');
    var url = '/api/labs/' + lab_filename + '/interfaces/' + connection;
    var type = 'PUT';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: connection removed.');
                deferred.resolve(data);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    return deferred.promise();

}

//get vlan
export function getVlan(){

    var deferred = $.Deferred();
    var lab_filename = $('#lab-viewport').attr('data-path');

    var url = '/api/labs/' + lab_filename + '/vlans';
    var type = 'Get';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: vlan listed.');
                deferred.resolve(data);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    return deferred.promise();

}

//get connection
export function getConnection(){

    var deferred = $.Deferred();
    var lab_filename = $('#lab-viewport').attr('data-path');

    var url = '/api/labs/' + lab_filename + '/connections';
    var type = 'Get';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: connection listed.');
                deferred.resolve(data);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    return deferred.promise();

}

// Start node(s)
export function start(node_id) {
    var deferred = $.Deferred();
    var lab_filename = $('#lab-viewport').attr('data-path');
    var labInstance;
    var edition;
    var pathname = window.location.pathname;
    if(EDITION == 1) {
        labInstance = null;
        edition = EDITION;
    }
    if(EDITION == 0) {
       labInstance = pathname.split(/(\d+)/)[3];
       edition = EDITION;
    }
    var node_data = {};
    node_data['edition'] = edition;
    node_data['labInstance'] = labInstance;
    var url = '/api/labs/' + lab_filename + '/nodes/' + node_id + '/start';
    var type = 'POST';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        data: JSON.stringify(node_data),
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: node(s) started.');
                deferred.resolve(data['data']);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    return deferred.promise();
}

// Start nodes recursive
export function recursive_start(nodes, i) {
    i = i - 1;
    var deferred = $.Deferred();
    var lab_filename = $('#lab-viewport').attr('data-path');
    var labInstance;
    var edition;
    var pathname = window.location.pathname;
    if(EDITION == 1) {
        labInstance = null;
        edition = EDITION;
    }
    if(EDITION == 0) {
       labInstance = pathname.split(/(\d+)/)[3];
       edition = EDITION;
    }
    var node_data = {};
    node_data['edition'] = edition;
    node_data['labInstance'] = labInstance;

    if (nodes[Object.keys(nodes)[i]].type != "switch") {
    if (typeof nodes[Object.keys(nodes)[i]]['path'] === 'undefined') {
        var url = '/api/labs/' + lab_filename + '/nodes/' + Object.keys(nodes)[i] + '/start';
    } else {
        var url = '/api/labs/' + lab_filename + '/nodes/' + nodes[Object.keys(nodes)[i]]['path'] + '/start';
    }
    var type = 'POST';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        data: JSON.stringify(node_data),
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: node(s) started.');
                addMessage('success', nodes[Object.keys(nodes)[i]]['name'] + ': ' + MESSAGES[76]);

                //set start status
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                addMessage('danger', nodes[Object.keys(nodes)[i]]['name'] + ': ' + MESSAGES[76] + 'failed');
            }
            if (i > 0) {
                recursive_start(nodes, i);
            } else {
                addMessage('info', 'Start All: done');
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            addMessage('danger', message);
            if (i > 0) {
                recursive_start(nodes, i);
            } else {
                addMessage('info', 'Start All: done');
            }

        }
    });
    return deferred.promise();
    }
    else {
        if (i > 0) {
            recursive_start(nodes, i);
        } else {
            addMessage('info', 'Start All: done');
        }
    }

}

// Stop node(s)
export function stop(node_id) {
    var deferred = $.Deferred();

    var lab_filename = $('#lab-viewport').attr('data-path');
    var labInstance;
    var edition;
    var pathname = window.location.pathname;
    if(EDITION == 1) {
        labInstance = null;
        edition = EDITION;
    }
    if(EDITION == 0) {
       labInstance = pathname.split(/(\d+)/)[3];
       edition = EDITION;
    }
    var node_data = {};
    node_data['edition'] = edition;
    node_data['labInstance'] = labInstance;

    var url = '/api/labs/' + lab_filename + '/nodes/' + node_id + '/stop';
    var type = 'POST';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        data: JSON.stringify(node_data),
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: node(s) stopped.');
                $('#node' + node_id).removeClass('jsplumb-connected');
                deferred.resolve(data['data']);

            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });

    return deferred.promise();
}

/***************************************************************************
 * Print forms and pages
 **************************************************************************/

// Context menu
export function printContextMenu(title, body, pageX, pageY, addToBody, role, hideTitle) {
    var zoomvalue = 100
    if ( role == "menu" ) zoomvalue=$('#zoomslide').slider("value")
    pageX=pageX*100/zoomvalue
    pageY=pageY*100/zoomvalue
    $("#context-menu").remove()
    var titleLine = '';

    if(!hideTitle){
        titleLine = '<li role="presentation" class="dropdown-header">' + title + '</li>'
    }

    var menu = '<div id="context-menu" class="collapse clearfix dropdown">';
    menu += '<ul class="dropdown-menu" role="' + role + '">' + titleLine + body + '</ul></div>';
    var hiddenYpix = 0
    var hiddenXpix = 0



    if(addToBody){
        $('body').append(menu);
    } else {
        $('#lab-viewport').append(menu);
        hiddenYpix=$('#lab-viewport').scrollTop();
        hiddenXpix=$('#lab-viewport').scrollLeft();
    }

    // Set initial status
    $('.menu-interface, .menu-edit').slideToggle();
    $('.menu-interface, .menu-edit').hide();
    setZoom(100/zoomvalue,lab_topology,[0,0],$('#context-menu')[0])

    // Calculating position
    if (pageX + $('#context-menu').width() + 30 > $(window).width()) {
        // Dropright
        logger(1,'Drop right');
        var left = pageX - $('#context-menu').width() + hiddenXpix;
    } else {
        // Dropleft
        var left = pageX+hiddenXpix;
    }
    if ($('#context-menu').height() > $(window).height()) {
        // Page is too short, drop down by default
        var top = 0;
        var max_height = $(window).height();
    } else if ($(window).height()/zoomvalue*100 - pageY >= $('#context-menu').height()) {
        // Dropdown if enough space
        var top = pageY+hiddenYpix;
        var max_height = $('#context-menu').height();
    } else {
        // Dropup
        var top = ( $(window).height() - $('#context-menu').height() ) /zoomvalue * 100 + hiddenYpix;
        //var top = $(window).height() - $('#context-menu').height() + hiddenpix;
        var max_height = $('#context-menu').height();
    }

    // Setting position via CSS
    $('#context-menu').css({
        left: left - 30 + 'px',
        maxHeight: max_height,
        top: top + 'px'
    });
    $('#context-menu > ul').css({
        maxHeight: max_height - 5
    });

}

// Add a new lab
export function printFormLab(action, values) {
    
    if (action == 'add') {
        var path = values['path'];
    } else {
        var path = (values['path'] == '/') ? '/' + values['name'] + '.unl' : values['path'] + '/' + values['name'] + '.unl';
    }
    var title = (action == 'add') ? MESSAGES[5] : MESSAGES[87] ;

    var id = $('#lab-viewport').attr('data-path');
    var currentTime = performance.now();

    var html = new EJS({
        url: '/build/editor/ejs/form_lab.ejs'
    }).render({
        name: (values['name'] != null) ? values['name'] : '',
        version: (values['version'] != null) ? values['version'] : '',
        scripttimeout: (values['scripttimeout'] != null) ? values['scripttimeout'] : '300',
        author: (values['author'] != null) ? values['author'] : '',
        description: (values['description'] != null) ? values['description'] : '',
        body: (values['body'] != null) ? values['body'] : '',
        banner: (values['banner'] != null) ? values['banner'] : '',
        timer: (values['timer'] != null) ? values['timer'] : '',
        virtuality: VIRTUALITY,
        srcBanner : '/labs/'+id+'/banner?'+ currentTime,
        title: title,
        path: path,
        action: action,
        MESSAGES: MESSAGES,
    })
    
    logger(1, 'DEBUG: popping up the lab-add form.');
    addModalWide(title, html, '');

    Dropzone.autoDiscover= false;
    var bannerDropzone = new Dropzone("div#bannerDropzone",{
       url: "#",
        uploadMultiple: false,
        method: function (file){
            return postBanner("banner",file);
        },
        disablePreviews: true,
        acceptedFiles: "image/jpg, image/png, image/jpeg",
        createImageThumbnails:false,
        //addRemoveLinks: true,
        success: function (file, response) {
           let newTime=performance.now()
            $("img.bannerDropzone.data-dz-thumbnail").attr("src", "/labs/"+ id+"/banner?"+newTime)
           
        },
        error: function (file, response) {
            file.previewElement.classList.add("dz-error");
        }
    });
 
    validateLabInfo();
}

export function postBanner(banner, attachments) {
    var formData = new FormData();
    $.each(attachments, function (key, value) {
        formData.append("banner", value);
    });
    var lab_filename = $('#lab-viewport').attr('data-path');

    var url = "/api/labs/" + lab_filename +"/banner";
    var type = 'POST';
    $.ajax({
        cache: false,
        type: type,
        url: encodeURI(url),
        processData: false,
        contentType: false,
        data: formData
    });
}

// Edit pratical subject
export function printFormSubjectLab(action, values) {
    var title = 'Edit practical subject' ;

    var html = new EJS({
        url: '/build/editor/ejs/form_subject_lab.ejs'
    }).render({
        name: (values['name'] != null) ? values['name'] : '',
        version: (values['version'] != null) ? values['version'] : '',
        scripttimeout: (values['scripttimeout'] != null) ? values['scripttimeout'] : '300',
        author: (values['author'] != null) ? values['author'] : '',
        description: (values['description'] != null) ? values['description'] : '',
        body: (values['body'] != null) ? values['body'] : '',
        title: title,
        action: action,
        MESSAGES: MESSAGES,
    })

    logger(1, 'DEBUG: popping up the lab-add form.');
    addModalWide(title, html, '');
    var subjectEditor = new EasyMDE({ element: $("#editor")[0] });
    validateLabInfo();
}

// Node form
export function printFormNode(action, values, fromNodeList) {
    logger (2,'action = ' + action)
    var zoom = (action == "add") ? $('#zoomslide').slider("value")/100 : 1 ;
    var id = (values == null || values['id'] == null) ? null : values['id'];
    var left = (values == null || values['left'] == null) ? null : Math.trunc(values['left']/zoom);
    var top = (values == null || values['top'] == null) ? null : Math.trunc(values['top']/zoom);
    var template = (values == null || values['template'] == null) ? null : values['template'];

    var title = (action == 'add') ? MESSAGES[85] : MESSAGES[86];
    var template_disabled = (values == null || values['template'] == null ) ? '' : 'disabled ';

    $.when(getTemplates(null)).done(function (templates) {
        var html = '';
        html += '<form id="form-node-' + action + '" >'+
                    '<div class="form-group col-sm-12">'+
                        '<label class="control-label">' + MESSAGES[84] + '</label>' +
                            '<select id="form-node-template" class="selectpicker form-control" name="node[template]" data-live-search="true" data-size="auto" data-style="selectpicker-button">'+
                                '<option value="">' + MESSAGES[102] + '</option>';
        $.each(templates, function (key, value) {
        var valdisabled  = (/missing/i.test(value)) ? 'disabled="disabled"' : '';
        //var valdisabled  = '' ;
            // Adding all templates
            if (! /hided/i.test(value) ) html += '<option value="' + key + '" '+ valdisabled +' >' + value.replace('.missing','') + '</option>';
        });
        html += '</select></div><div id="form-node-data"></div><div id="form-node-buttons"></div></form>';

        // Show the form
        addModal(title, html, '', 'second-win');
        $('.selectpicker').selectpicker();
        if(!fromNodeList){
            $('.selectpicker-button').trigger('click');
            $('.selectpicker').selectpicker();
            setTimeout(function(){
                $('.bs-searchbox input').focus()
            }, 500);
        }

        $('#form-node-template').change(function (e2) {
            id = (id == '') ? null : id;    // Ugly fix for change template after selection
            template = $(this).find("option:selected").val();
            var idTemplate = template.split(/(\d+)/)[1];
            if (template != '') {
                // Getting template only if a valid option is selected (to avoid requests during typewriting)
                $.when(getTemplates(idTemplate), getNodes(id)).done(function (template_values, node_values) {
                    // TODO: this event is called twice
                    id = (id == null) ? '' : id;
                    var html_data = '<input name="node[type]" value="' + template_values['type'] + '" type="hidden"/>';
                    if (action == 'add') {
                        if (VIRTUALITY == 1) {
                            // If action == add -> print the nework count input
                            html_data += '<div class="form-group col-sm-5"><label class=" control-label">' + MESSAGES[113] + '</label>'+
                            '<input class="form-control" name="node[count]" max=50 value="1" type="text"/>'+
                            '</div>';
                        }
                    } else {
                        // If action == edit -> print the network ID
                        html_data += '<div class="form-group col-sm-12">'+
                                        '<label class="control-label">' + MESSAGES[92] + '</label>'+
                                        '<input class="form-control" disabled name="node[id]" value="' + id + '" type="text"/>'+
                                     '</div>';
                    }

                    var bothRam = template_values['options'].hasOwnProperty('ram') && template_values['options'].hasOwnProperty('nvram')
                    var bothConnTypes = template_values['options'].hasOwnProperty('ethernet') && template_values['options'].hasOwnProperty('serial')

                    $.each(template_values['options'], function (key, value) {

                        if(key == 'ram') postName = '(MB)';
                        if(key == 'nvram') postName = '(KB)';
                        // Print all options from template
                        var value_set = (node_values != null && node_values[key] != null) ? node_values[key] : value['value'];
                        if (value['type'] == 'list') {
                            var select = '<select class="selectpicker form-control" name="node[' + key + ']" data-size="5" data-style="selectpicker-button">';
                            if (value['multiple'] != false) {
                                select = '<select class="selectpicker form-control" name="node[' + key + ']" multiple data-size="5" data-style="selectpicker-button">';
                            }
                            // Option is a list
                            var widthClass = ' col-sm-12 '
                            if(key == 'image' && action == 'add') widthClass = ' col-sm-7'
                            if(key == 'qemu_version') {
				widthClass = ' col-sm-4 ';
				if ( action == 'add' ) value_set = '';
                            }
                            if(key == 'qemu_arch') {
				widthClass = ' col-sm-4 ';
				if ( action == 'add' ) value_set = '';
			    }
                            if(key == 'qemu_nic') {
				widthClass = ' col-sm-4 ';
				if ( action == 'add' ) value_set = '';
			    }
                            if (key.startsWith('slot')) widthClass = ' col-sm-6 '
                            html_data += '<div class="form-group '+widthClass+'">'+
                                            '<label class=" control-label">' + value['name'] + '</label>'+
                                            select;
                            $.each(value['list'], function (list_key, list_value) {
                                var selected = (list_key == value_set) ? 'selected ' : '';
                                if(typeof(value_set) == "object") {
                                    var contain = false;
                                    value_set.forEach(value => {
                                        if(list_key == value) {
                                            contain = true;
                                            return;
                                        }
                                    });
                                    selected = (contain) ? 'selected ' : '';
                                    html_data += '<option ' + selected + 'value="' + list_key + '">' + list_value + '</option>';
                                }
                                else{
                                    var iconselect = '' ;
                                    if ( key == "icon" ) { iconselect = 'data-content="<img src=\'/build/editor/images/icons/'+list_value+'\' height=15 width=15>&nbsp;&nbsp;&nbsp;'+list_value+'"' };
                                    html_data += '<option ' + selected + 'value="' + list_key + '" '+ iconselect +'>' + list_value + '</option>';
                                }
                            });
                            html_data += '</select>';
                            html_data += '</div>';
                        } else if ( value['type'] == 'checkbox') {
				if(key == 'cpulimit') {
					widthClass = ' col-sm-2 ';
					html_data += '<div class="'+widthClass+'" style="padding-right: 0px;">'+
					'<label class="control-label" style="height: 34px;margin-top: 8px;margin-bottom: 0px;">' + value['name'] + '</label>'+
					'</div><div class="form-group col-sm-8" style="padding-left: 0px;" >'+
					'<input type="checkbox"  style="width: 34px;" class="form-control" value='+ values['cpulimit']  +' name="node[' + key + ']" '+ (( values['cpulimit'] == 1) ? 'checked' : '' ) +'/>'+
					'</div>';
				}
			} else {
                            // Option is standard
                            var widthClass = ' col-sm-12 '
                            var ram_value = key == 'ram' ? ' (MB)' : key == 'nvram' ? ' (KB)' : ' ';
                            var postName = '';
                            if (!bothRam && template_values['options'].hasOwnProperty('cpu') &&
                                template_values['options'].hasOwnProperty('ethernet') &&
                                template_values['options'].hasOwnProperty('ram')) {
                                if (key == 'ram' || key == 'ethernet' || key == 'cpu') widthClass = ' col-sm-4 '
                            } else if (key == 'ram' || key == 'nvram') widthClass = ' col-sm-6 '
                            if (bothConnTypes && (key == 'ethernet' || key == 'serial')) widthClass = ' col-sm-6 '
                            var tpl = '' ;
			    if (key == 'qemu_options' && value_set == '') value_set = template_values['options'][key]['value'] ;
                            if (key == 'qemu_options')  tpl = " ( reset to template value )"
			    value_set = (key == 'qemu_options')?value_set.replace(/"/g,'&quot;'):value_set;
			    template_values['options'][key]['value'] = (key == 'qemu_options')?template_values['options'][key]['value'].replace(/"/g,'&quot;'):template_values['options'][key]['value'];

                            html_data += '<div class="form-group'+ widthClass+'">'+
                                            '<label class=" control-label"> ' + value['name'] + '<a id="link_'+key+'" onClick="javascript:document.getElementById(\'input_'+key+'\').value=\''+template_values['options'][key]['value']+'\';document.getElementById(\'link_'+key+'\').style.visibility=\'hidden\'" style="visibility: '+ (( value_set != template_values['options'][key]['value'] ) ? 'visible':'hidden') +';" >' + tpl + '</a>' + ram_value + '</label>'+
                                            '<input class="form-control' + ((key == 'name') ? ' autofocus' : '') + '" name="node[' + key + ']" value="' + value_set + '" type="text" id="input_'+ key  +'" onClick="javascript:document.getElementById(\'link_'+key+'\').style.visibility=\'visible\'""/>'+
                                         '</div>';
                            if ( key  == 'qemu_options' ) {
			         html_data += '<div class="form-group'+ widthClass+'">'+
                                            '<input class="form-control hidden" name="node[ro_' + key + ']" value="' + template_values['options'][key]['value']  + '" type="text" disabled/>'+
                                         '</div>';
                            }
                        }
                    });
                    html_data += '<div class="form-group col-sm-6">'+
                                    '<label class=" control-label">' + MESSAGES[93] + '</label>'+
                                    '<input class="form-control" name="node[left]" value="' + left + '" type="text"/>'+
                                 '</div>'+
                                 '<div class="form-group col-sm-6">'+
                                    '<label class=" control-label">' + MESSAGES[94] + '</label>'+
                                    '<input class="form-control" name="node[top]" value="' + top + '" type="text"/>'+
                                 '</div>';

                    // Show the buttons
                    $('#form-node-buttons').html('<div class="form-group"><div class="col-md-5 col-md-offset-3"><button type="submit" class="btn btn-success">' + MESSAGES[47] + '</button> <button type="button" class="btn" data-dismiss="modal">' + MESSAGES[18] + '</button></div>');

                    // Show the form
                    $('#form-node-data').html(html_data);
                    $('.selectpicker').selectpicker();
                    if(!fromNodeList){
                        setTimeout(function(){
                            $('.selectpicker').selectpicker().data("selectpicker").$button.focus();
                        }, 500);
                    }
                    validateNode();
                }).fail(function (message1, message2) {
                    // Cannot get data
                    if (message1 != null) {
                        addModalError(message1);
                    } else {
                        addModalError(message2)
                    }
                    ;
                });
            }
        });

        if (action == 'edit') {
            // If editing a node, disable the select and trigger
            $('#form-node-template').val(template).change();
            $('#form-node-template').prop('disabled', 'disabled');
            //$('#form-node-template').val(template).change();
        }

    }).fail(function (message) {
        // Cannot get data
        addModalError(message);
    });
}

export function printFormNodeConfigs(values, cb) {
    var title = values['name'] + ': ' + MESSAGES[123];
    if ((((ROLE == 'ROLE_TEACHER' || ROLE == 'ROLE_TEACHER_EDITOR') && AUTHOR == 1) || (ROLE == 'ROLE_ADMINISTRATOR' || ROLE == 'ROLE_SUPER_ADMINISTRATOR')) && EDITION ==1 && LOCK == 0 ) 
    //if ((ROLE != 'ROLE_USER') && LOCK == 0 )
    {
        var ace_themes = [
            'cobalt', 'github', 'crimson_editor', 'iplastic', 'draw', 'clouds_midnight',
            'monokai', 'ambiance', 'chaos', 'chrome', 'clouds', 'eclipse', 'dreamweaver',
            'kr_theme', 'kuroir', 'merbivore', 'idle_fingers', 'katzenmilch', 'merbivore_soft',
        ];

        var ace_themes = [
            { title: 'Dark', key: 'cobalt'},
            { title: 'Light', key:'github'}
        ];

        var ace_languages = [
            { title: 'Cisco-IOS', key: 'cisco_ios' },
            { title: 'Juniper JunOS', key: 'juniper_jun_os' }
        ];

        var ace_font_size = [
            '12px', '13px', '14px', '16px', '18px', '20px', '24px', '28px'
        ];

        var html = new EJS({
            url: '/build/editor/ejs/form_node_configs.ejs'
        }).render({
            MESSAGES: MESSAGES,
            values: values,
            ace_themes: ace_themes,
            ace_languages: ace_languages,
            ace_font_size: ace_font_size,
            r: readCookie
        })

    } else {
        var html = new EJS({
            url: '/build/editor/ejs/locked_node_configs.ejs'
        }).render({
             values: values
        })
    }

    $('#config-data').html(html);
    if(readCookie("editor")) {
        initEditor();
    } else {
        initTextarea();
        $('#nodeconfig').focus();
    }
    $('#nodeconfig').val(values['data']);
    ace.edit("editor").setValue(values['data'], 1)

    cb && cb();
}

// Custom Shape form
export function printFormCustomShape(values) {
    var shapeTypes = ['square', 'circle'],
        borderTypes = ['solid', 'dashed'],
        left = (values == null || values['left'] == null) ? null : values['left'],
        top = (values == null || values['top'] == null) ? null : values['top'];

  var html = '<form id="main-modal" class="container col-md-12 col-lg-12 custom-shape-form">' +
        '<div class="row">' +
        '<div class="col-md-8 col-md-offset-1 form-group">' +
        '<label class="col-md-3 control-label form-group-addon">Type</label>' +
        '<div class="col-md-5">' +
        '<select class="form-control shape-type-select">' +
        '</select>' +
        '</div>' +
        '</div> <br>' +
        '<div class="col-md-8 col-md-offset-1 form-group">' +
        '<label class="col-md-3 control-label form-group-addon">Name</label>' +
        '<div class="col-md-5">' +
        '<input type="text" class="form-control shape_name" placeholder="Name">' +
        '</div>' +
        '</div> <br>' +
        '<div class="col-md-8 col-md-offset-1 form-group">' +
        '<label class="col-md-3 control-label form-group-addon">Border-type</label>' +
        '<div class="col-md-5">' +
        '<select class="form-control border-type-select" >' +
        '</select>' +
        '</div>' +
        '</div> <br>' +
        '<div class="col-md-8 col-md-offset-1 form-group">' +
        '<label class="col-md-3 control-label form-group-addon">Border-width</label>' +
        '<div class="col-md-5">' +
        '<input type="number" min="0" value="5" class="form-control shape_border_width">' +
        '</div>' +
        '</div> <br>' +
        '<div class="col-md-8 col-md-offset-1 form-group">' +
        '<label class="col-md-3 control-label form-group-addon">Border-color</label>' +
        '<div class="col-md-5">' +
        '<input type="color" class="form-control shape_border_color">' +
        '</div>' +
        '</div> <br>' +
        '<div class="col-md-8 col-md-offset-1 form-group">' +
        '<label class="col-md-3 control-label form-group-addon">Background-color</label>' +
        '<div class="col-md-5">' +
        '<input type="color" class="form-control shape_background_color">' +
        '</div>' +
        '</div> <br>' +
        '<button type="submit" class="btn btn-success col-md-offset-1">' + MESSAGES[47] + '</button>' +
        '<button type="button" class="btn" data-dismiss="modal">' + MESSAGES[18] + '</button>' +
        '</div>' +
        '<input  type="text" class="hide left-coordinate" value="' + left + '">' +
        '<input  type="text" class="hide top-coordinate" value="' + top + '">' +
        '</form>';

    addModal("ADD CUSTOM SHAPE", html, '');
    $('.custom-shape-form .shape_background_color').val('#ffffff');

    for (var i = 0; i < shapeTypes.length; i++) {
        $('.shape-type-select').append($('<option></option>').val(shapeTypes[i]).html(shapeTypes[i]));
    }

    for (var j = 0; j < borderTypes.length; j++) {
        $('.border-type-select').append($('<option></option>').val(borderTypes[j]).html(borderTypes[j]));
    }

    if(isIE){
        $('input[type="color"]').hide()
        $('input.shape_border_color').colorpicker({
            color: "#000000",
            defaultPalette: 'web'
        })
        $('input.shape_background_color').colorpicker({
            color: "#ffffff",
            defaultPalette: 'web'
        })
    }


    $(".custom-shape-form").find('input:eq(0)').delay(500).queue(function() {
     $(this).focus();
     $(this).dequeue();
    });
};

// Text form
export function printFormText(values) {
    var left = (values == null || values['left'] == null) ? null : values['left']
        , top = (values == null || values['top'] == null) ? null : values['top']
        , fontStyles = ['normal', 'bold', 'italic'];
    var html = new EJS({
        url: '/build/editor/ejs/form_text.ejs'
    }).render({ MESSAGES: MESSAGES, left: left, top: top});
    addModal("ADD TEXT", html, '');

    $('.autofocus').focus();
    $('.add-text-form .text_background_color').val('#ffffff');

    for (var i = 0; i < fontStyles.length; i++) {
        $('.text-font-style-select').append($('<option></option>').val(fontStyles[i]).html(fontStyles[i]));
    }

    if(isIE){
        $('input[type="color"]').hide()
        $('input.shape_border_color').colorpicker({
            color: "#000000",
            defaultPalette: 'web'
        })
        $('input.shape_background_color').colorpicker({
            color: "#ffffff",
            defaultPalette: 'web'
        })
    }
};


//save lab handler
/*export function saveLab(form) {
    var lab_filename = $('#lab-viewport').attr('data-path');
    var form_data = form2Array('config');
    var url = '/api/labs/' + lab_filename + '/configs/' + form_data['id'];
    var type = 'PUT';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        data: JSON.stringify(form_data),
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: config saved.');
                // Close the modal
                $('body').children('.modal').attr('skipRedraw', true);
                if (form) {
                    //$('body').children('.modal').modal('hide');
                    addMessage(data['status'], data['message']);
                }
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                addModal('ERROR', '<p>' + data['message'] + '</p>', '<button type="button" class="btn btn-flat" data-dismiss="modal">Close</button>');
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            addModal('ERROR', '<p>' + message + '</p>', '<button type="button" class="btn btn-flat" data-dismiss="modal">Close</button>');
        }
    });
    return false;  // Stop to avoid POST
}*/


// Drag jsPlumb helpers
// Jquery-ui freeselect


export function updateFreeSelect ( e , ui ) {
    if ( $('.node_frame.ui-selected, node_frame.ui-selecting, .network_frame.ui-selected,.network_ui-selecting, .customShape.ui-selected, .customShape.ui-selecting').length > 0 ) {
        $('#lab-viewport').addClass('freeSelectMode')
    }
    window.freeSelectedNodes = []
        if ((((ROLE == 'ROLE_TEACHER' || ROLE == 'ROLE_TEACHER_EDITOR') && AUTHOR == 1) || (ROLE == 'ROLE_ADMINISTRATOR' || ROLE == 'ROLE_SUPER_ADMINISTRATOR')) && EDITION ==1 && LOCK == 0 ) {
            $.when ( lab_topology.setDraggable($('.node_frame, .network_frame, .customShape'), false) ).done ( function () {
               $.when( lab_topology.clearDragSelection() ).done(  function () {
                    lab_topology.setDraggable($('.node_frame.ui-selected, node_frame.ui-selecting, .network_frame.ui-selected,.network_ui-selecting, .customShape.ui-selected, .customShape.ui-selecting'),true)
                    lab_topology.addToDragSelection($('.node_frame.ui-selected, node_frame.ui-selecting, .network_frame.ui-selected,.network_ui-selecting, .customShape.ui-selected, .customShape.ui-selecting'))
              });

            });
         } else {
            $('.customShape.ui-selected, .customShape.ui-selecting').removeClass('ui-selecting').removeClass('ui-selected')
         }
    $('.free-selected').removeClass('free-selected')
    $('.node_frame.ui-selected, node_frame.ui-selecting').addClass('free-selected')
    $('.network_frame.ui-selected, network_frame.ui-selecting').addClass('free-selected')
    $('.customShape.ui-selected, customShape.ui-selecting').addClass('free-selected')
    $('.node_frame.ui-selected, .node_frame.ui-selecting').each(function() {
         window.freeSelectedNodes.push({ name: $(this).data("name") , path: $(this).data("path") , type: 'node'  });

    });
}

// Print lab topology
export function printLabTopology() {
    var defer  = $.Deferred();
    $('#lab-viewport').empty();
    $('#lab-viewport').selectable();
    $('#lab-viewport').selectable("destroy");
    $('#lab-viewport').selectable({
        filter: ".customShape, .network, .node",
        start: function () {
            window.newshape = [];
            $('.customShape').each(function ()
            {
                var $this = $(this);
                var width;
                var height;
                window.newshape[$this.attr('id')] = ({width: Math.trunc($this.innerWidth()), height: Math.trunc($this.innerHeight()) })
            })
        },
        stop: function ( event, ui ) {
            $('.customShape').each(function (index) {
                var $this = $(this);
                $this.height(window.newshape[$this.attr('id')]['height'])
                $this.width(window.newshape[$this.attr('id')]['width'])
            });
            delete window.newshape;
            updateFreeSelect ( event, ui )
        },
        distance: 1
    });

    var lab_filename = $('#lab-viewport').attr('data-path')
        , $labViewport = $('#lab-viewport')
        , loadingLabHtml = '' +
            '<div id="loading-lab" class="loading-lab">' +
            '<div class="container">' +
            '<img src="/build/editor/images/wait.gif"/><br />' +
            '<h3>Loading Lab</h3>' +
            '<div class="progress">' +
            '<div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>' +
            '</div>' +
            '</div>' +
            '</div>'
        , labNodesResolver = $.Deferred()
        , labTextObjectsResolver = $.Deferred()
        , progressbarValue = 0
        , progressbarMax = 100
        ;

    if ($labViewport.data("refreshing")) {
        return ;
    }
    window.lab_topology = undefined;
    $labViewport.empty();
    $labViewport.data('refreshing', true);
    $labViewport.after(loadingLabHtml);
    $("#lab-sidebar *").hide();

     $.when(
        getNodes(null),
        getTopology(),
        getTextObjects(),
        getLabInfo(lab_filename)
    ).done(function (nodes, topology, textObjects, labinfo) {


        var networkImgs = []
            , nodesImgs = []
            , textObjectsCount = Object.keys(textObjects).length
            ;

        progressbarMax = Object.keys(nodes).length + Object.keys(textObjects).length;
        $(".progress-bar").attr("aria-valuemax", progressbarMax);

        $.each(nodes, function (key, value) {
            var hrefbuf;
            if (EDITION == 0 && value['console'].length > 0 && value['status'] == 2 && value['type'] != "switch") {
                if (value['console'].length > 1 ) {
                    hrefbuf = '<a class="openControlProtocolMenu" id="'+ value['id']+'" href="javascript:void(0)" >'
                }
                else {
                    hrefbuf = '<a href="/instances/' + value['uuid'] +'/view/' + value['console']+ '" target="_blank">';
                }                
            }
            else {
                hrefbuf = '<a href="javascript:void(0)" >' ;
            }

            $labViewport.append(
                '<div id="node' + value['id'] + '" ' +
                'class="context-menu node node' + value['id'] + ' node_frame "' +
                'style="top: ' + value['top'] + 'px; left: ' + value['left'] + 'px;" ' +
                'data-path="' + value['id'] + '" ' +
                'data-status="' + value['status'] + '" ' +
                'data-type="' + value['type'] + '" ' +
                'data-name="' + value['name'] + '">' +
                '<div class="tag  hidden" title="Connect to another node">'+
                '<i class="fa fa-plug plug-icon dropdown-toggle ep"></i>'+
                '</div>'+
                hrefbuf +
                '</a>' +
                '<div class="node_name"><i class="node' + value['id'] + '_status"></i> ' + value['name'] + '</div>' +
                '</div>');
            nodesImgs.push($.Deferred(function (defer) {
                var img = new Image();

                img.onload = resolve;
                img.onerror = resolve;
                img.onabort = resolve;

                img.src = "/build/editor/images/icons/" + value['icon'];

                if(value['status'] == 0) img.className = 'grayscale';

                $(img).appendTo("#node" + value['id'] + " a");

                // need the presence of images in the DOM
                if(isIE && value['status'] == 0){
                    addIEGrayscaleWrapper($(img))
                }

                function resolve(image) {
                    img.onload = null;
                    img.onerror = null;
                    img.onabort = null;
                    defer.resolve(image);
                }
            }));

            $(".progress-bar").css("width", ++progressbarValue / progressbarMax * 100 + "%");
        });
        var checkDeferred;
        // In bad situation resolving textobject will save our soul ;-)
        setTimeout( checkDeferred =  ( labTextObjectsResolver.state() == 'pending' ? true :  labTextObjectsResolver.resolve()  ) , 10000 )
        //add shapes from server to viewport
        $.each(textObjects, function (key, value) {
            getTextObject(value['id']).done(function (textObject) {
                $(".progress-bar").css("width", ++progressbarValue / progressbarMax * 100 + "%");

                var $newTextObject = $(textObject['data']);

                if ($newTextObject.attr("id").indexOf("customShape") !== -1) {
                    $newTextObject.attr("id", "customShape" + textObject.id);
                    $newTextObject.attr("data-path", textObject.id);
                    $labViewport.prepend($newTextObject);

                    $newTextObject
                        .resizable().resizable("destroy")
                        .resizable({
                grid:[3,3],
                            autoHide: true,
                            resize: function (event, ui) {
                                textObjectResize(event, ui, {"shape_border_width": 5});
                            },
                            stop: textObjectDragStop
                        });
                }
                else if ($newTextObject.attr("id").indexOf("customText") !== -1) {
                    $newTextObject.attr("id", "customText" + textObject.id);
                    $newTextObject.attr("data-path", textObject.id);
                    $labViewport.prepend($newTextObject);

                    $newTextObject
                        .resizable().resizable('destroy')
                        .resizable({
                grid:[3,3],
                            autoHide: true,
                            resize: function (event, ui) {
                                textObjectResize(event, ui, {"shape_border_width": 5});
                            },
                            stop: textObjectDragStop
                        });
                }
                else {
                    return void 0;
                }
                // Finally clean old class saved by error or bug
               $newTextObject.removeClass('ui-selected');
               $newTextObject.removeClass('move-selected');
               $newTextObject.removeClass('dragstopped');
               if ( labinfo['lock'] == 1 ) $newTextObject.resizable("disable")
               if (EDITION == 0) {
                $newTextObject.resizable("disable")
               }
                if (--textObjectsCount === 0) {
                    labTextObjectsResolver.resolve();
                }

                //@123

            }).fail(function () {
                logger(1, 'DEBUG: Failed to load Text Object' + value['name'] + '!');
            });
        });
        if (Object.keys(textObjects).length === 0) {
            labTextObjectsResolver.resolve();
        }
        $.when.apply($, networkImgs.concat(nodesImgs)).done(function () {
            // Drawing topology
            jsPlumb.ready(function () {

                // Create jsPlumb topology
                try { window.lab_topology.reset() } catch (ex) { window.lab_topology = jsPlumb.getInstance() };
                window.moveCount = 0
                lab_topology.setContainer($("#lab-viewport"));
                lab_topology.importDefaults({
                    Anchor: 'Continuous',
                    Connector: ['Straight'],
                    Endpoint: 'Blank',
                    PaintStyle: {strokeWidth: 2, stroke: '#c00001'},
                    cssClass: 'link'
                });
                // Read privileges and set specific actions/elements
                if ((((ROLE == 'ROLE_TEACHER' || ROLE == 'ROLE_TEACHER_EDITOR') && AUTHOR == 1) || (ROLE == 'ROLE_ADMINISTRATOR' || ROLE == 'ROLE_SUPER_ADMINISTRATOR')) && EDITION ==1 && labinfo['lock'] == 0 ) {
                    var dragDeferred = $.Deferred()
                    $.when ( labTextObjectsResolver ).done ( function () {
                        logger(1,'DEBUG: '+ textObjectsCount+ ' Shape(s) left');
                        lab_topology.draggable($('.node_frame, .network_frame, .customShape' ), {
                           containment: false,
                           grid: [3, 3],
                           stop: function ( e, ui) {
                                    ObjectPosUpdate(e,ui)
                           }
                        });

                        adjustZoom(lab_topology, window.scroll_top || 0, window.scroll_left || 0)
                        dragDeferred.resolve();
                    });

                    // Node as source or dest link
                     $.when( dragDeferred ).done( function () {
                     $.each(nodes, function (key,value) {
                           lab_topology.makeSource('node' + value['id'], {
                                filter: ".ep",
                                Anchor:"Continuous",
                                extract:{
                                    "action":"the-action"
                                },
                                maxConnections: 30,
                                onMaxConnections: function (info, e) {
                                    alert("Maximum connections (" + info.maxConnections + ") reached");
                                }
                           });

                          lab_topology.makeTarget( $('#node' + value['id']), {
                                dropOptions: { hoverClass: "dragHover" },
                                anchor: "Continuous",
                                allowLoopback: false
                          });
                          adjustZoom(lab_topology, window.scroll_top || 0, window.scroll_left || 0)
                    });
                    });
                }

                $.each(topology, function (id, link) {
                    var type = link['type'],
                        source = link['source'],
                        source_label = link['source_label'],
                        destination = link['destination'],
                        destination_label = link['destination_label'],
                        src_label = ["Label"],
                        dst_label = ["Label"],
                        connector = link['connector'],
                        connector_label = link['connector_label'];

                    if (type == 'ethernet') {
                        if (source_label != '') {
                            src_label.push({
                                label: source_label,
                                location: 0.15,
                                cssClass: 'node_interface ' + source + ' ' + destination
                            });
                        } else {
                            src_label.push(Object());
                        }
                        if (destination_label != '') {
                            dst_label.push({
                                label: destination_label,
                                location: 0.85,
                                cssClass: 'node_interface ' + source + ' ' + destination
                            });
                        } else {
                            dst_label.push(Object());
                        }

                        let overlays = [src_label, dst_label];

                        if (connector_label != null && connector_label !== "") {
                            let conn_label = ["Label", { label:connector_label, location:0.5, cssClass: 'node_interface_label ' + source + ' ' + destination} ];
                            overlays.push(conn_label);
                        }

                        var tmp_conn = lab_topology.connect({
                            source: source,       // Must attach to the IMG's parent or not printed correctly
                            target: destination,  // Must attach to the IMG's parent or not printed correctly
                            cssClass: source + ' ' + destination + ' frame_ethernet',
                            paintStyle: {strokeWidth: 2, stroke: '#0066aa'},
                            overlays: overlays,
                            connector: [connector]
                        });
                        if (destination.substr(0, 7) == 'network') {
                              $.when( getNodeInterfaces(source.replace('node',''))).done( function ( ifaces ) {
                                  for ( ikey in ifaces['ethernet'] ) {
                                      if ( ifaces['ethernet'][ikey]['name'] == source_label ) {
                                         tmp_conn.id = 'iface:'+source+":"+ikey
                                      }
                                  }
                              });
                        } else {
                              tmp_conn.id = 'network_id:'+link['network_id']
                        }
                    } else {
                        src_label.push({
                            label: source_label,
                            location: 0.15,
                            cssClass: 'node_interface ' + source + ' ' + destination
                        });
                        dst_label.push({
                            label: destination_label,
                            location: 0.85,
                            cssClass: 'node_interface ' + source + ' ' + destination
                        });
                        var tmp_conn = lab_topology.connect({
                            source: source,       // Must attach to the IMG's parent or not printed correctly
                            target: destination,  // Must attach to the IMG's parent or not printed correctly
                            cssClass: source + " " + destination + ' frame_serial',
                            paintStyle: {strokeWidth: 2, stroke: "#ffcc00"},
                            overlays: [src_label, dst_label]
                        });
                        $.when( getNodeInterfaces(source.replace('node',''))).done( function ( ifaces ) {
                             for ( ikey in ifaces['serial'] ) {
                                    if ( ifaces['serial'][ikey]['name'] == source_label ) {
                                        tmp_conn.id = 'iface:'+source+':'+ikey
                                    }
                             }
                        });
                    }
                    // If destination is a network, remove the 'unused' class
                    if (destination.substr(0, 7) == 'network') {
                        $('.' + destination).removeClass('unused');
                    }
                });

        printLabStatus();

                // Remove unused elements
                $('.unused').remove();


                // Move elements under the topology node
                //$('._jsPlumb_connector, ._jsPlumb_overlay, ._jsPlumb_endpoint_anchor_').detach().appendTo('#lab-viewport');
                // if lock then freeze node network
                if ( labinfo['lock'] == 1 ) {
                                window.LOCK = 1 ;
                                defer.resolve();
                               $('.action-lock-lab').html('<i style="color:red" class="glyphicon glyphicon-remove-circle"></i>' + MESSAGES[167])
                               $('.action-lock-lab').removeClass('action-lock-lab').addClass('action-unlock-lab')

                }
                defer.resolve(LOCK);
                $labViewport.data('refreshing', false);
                labNodesResolver.resolve();
                lab_topology.bind("connection", function (info , oe ) {
                       newConnModal(info , oe);
                });
                // Bind contextmenu to connections
                lab_topology.bind("contextmenu", function (info) {
                       connContextMenu (info);
                });
           });
        }).fail(function () {
            logger(1, "DEBUG: not all images of networks or nodes loaded");
            $('#lab-viewport').data('refreshing', false);
            labNodesResolver.reject();
            labTextObjectsResolver.reject();
        });


    })
         .fail(function (message1, message2, message3) {
        if (message1 != null) {
            addModalError(message1);
        } else if (message2 != null) {
            addModalError(message2)
        } else {
            addModalError(message3)
        }
        $('#lab-viewport').data('refreshing', false);
        labNodesResolver.reject();
        labTextObjectsResolver.reject();
        $.when(closeLab()).done(function () {
          newUIreturn();
        }).fail(function (message) {
          addModalError(message);
        });
    });

    $.when(labNodesResolver, labTextObjectsResolver).done(function () {
        if ( $.cookie("topo")  != undefined && $.cookie("topo") == 'dark' ) {
            $('#lab-viewport').css('background-image','url(/build/editor/images/grid-dark.png)');
            $('.node_name').css('color','#b8c7ce')
            $('.network_name').css('color','#b8c7ce')
        }
        $("#loading-lab").remove();
        $("#lab-sidebar *").show();

    }).fail(function (message1, message2) {
        if (message1 != null) {
            addModalError(message1);
        } else if (message2 != null) {
            addModalError(message2)
        }
        $("#loading-lab").remove();
        $("#lab-sidebar ul").show();
        $("#lab-sidebar ul li:lt(11)").hide();
    });
      return defer.promise();

}

// Display lab status
export function printLabStatus() {
    $.when(getNodes(null)).done(function (nodes) {
        $.each(nodes, function (node_id, node) {
            if (node['status'] == 0) {
                // Stopped
                $('.node' + node['id'] + '_status').attr('class', 'node' + node['id'] + '_status glyphicon glyphicon-stop');
                $('#node' + node['id'] + ' img').addClass('grayscale')
                if(isIE) toogleIEGrayscle($('#node' + node['id'] + ' img'), true);
            } else if (node['status'] == 1) {
                // Stopped and locked
                $('.node' + node['id'] + '_status').attr('class', 'node' + node['id'] + '_status glyphicon glyphicon-warning-sign');
                $('#node' + node['id'] + ' img').addClass('grayscale')
                if(isIE) toogleIEGrayscle($('#node' + node['id'] + ' img'), true);
            } else if (node['status'] == 2) {
                // Running
                $('.node' + node['id'] + '_status').attr('class', 'node' + node['id'] + '_status glyphicon glyphicon-play');
                $('#node' + node['id'] + ' img').removeClass('grayscale')
                if(isIE) toogleIEGrayscle($('#node' + node['id'] + ' img'), false);
            } else if (node['status'] == 3) {
                // Running and locked
                $('.node' + node['id'] + '_status').attr('class', 'node' + node['id'] + '_status glyphicon glyphicon-time');
                $('#node' + node['id'] + ' img').removeClass('grayscale')
                if(isIE) toogleIEGrayscle($('#node' + node['id'] + ' img'), false);
            } else {
                // Undefined
                $('.node' + node['id'] + '_status').attr('class', 'node' + node['id'] + '_status glyphicon glyphicon-question-sign');
                $('#node' + node['id'] + ' img').addClass('grayscale')
                if(isIE) toogleIEGrayscle($('#node' + node['id'] + ' img'), true);
            }

            $('.node' + node['id']).attr('data-status',node['status']);

            if (EDITION == 0 && node['console'].length > 0 && node['status'] == 2 && node['type'] != "switch") {
                if (node['console'].length > 1 ) {
                    // '<a class="openControlProtocolMenu" id="'+ node['id']+'" href="javascript:void(0)" >'
                    $('.node'+ node["id"] +' a' ).attr("id",node['id']);
                    $('.node'+ node["id"] +' a' ).attr("href","javascript:void(0)");
                    $('.node'+ node["id"] +' a' ).attr("class","openControlProtocolMenu");
                    var targetAttr = $('.node'+ node["id"] +' a' ).attr("target");
                    if (typeof targetAttr !== "undefined" && targetAttr !== false) {
                        $('.node'+ node["id"] +' a' ).removeAttr("target");
                    }
                    
                }
                else {
                    // '<a href="/instances/' + node['uuid'] +'/view/' + node['console']+ '" target="_blank">';
                    var IdAttr = $('.node'+ node["id"] +' a' ).attr("id");
                    if (typeof IdAttr !== "undefined" && IdAttr !== false) {
                        $('.node'+ node["id"] +' a' ).removeAttr("id");
                    }
                    $('.node'+ node["id"] +' a' ).attr("href",'/instances/' + node['uuid'] +'/view/' + node['console']);
                    $('.node'+ node["id"] +' a' ).attr("target","_blank");
                    var classAttr = $('.node'+ node["id"] +' a' ).attr("class");
                    if (typeof classAttr !== "undefined" && classAttr !== false) {
                        $('.node'+ node["id"] +' a' ).removeAttr("class");
                    }
                }                
            }
            else {
                //'<a href="javascript:void(0)" >' ;
                var IdAttr = $('.node'+ node["id"] +' a' ).attr("id");
                if (typeof IdAttr !== "undefined" && IdAttr !== false) {
                    $('.node'+ node["id"] +' a' ).removeAttr("id");
                }
                $('.node'+ node["id"] +' a' ).attr("href","javascript:void(0)");
                var targetAttr = $('.node'+ node["id"] +' a' ).attr("target");
                if (typeof targetAttr !== "undefined" && targetAttr !== false) {
                    $('.node'+ node["id"] +' a' ).removeAttr("target");
                }
                var classAttr = $('.node'+ node["id"] +' a' ).attr("class");
                if (typeof classAttr !== "undefined" && classAttr !== false) {
                    $('.node'+ node["id"] +' a' ).removeAttr("class");
                }
            }
        });
    }).fail(function (message) {
        addMessage('danger', message);
    });
}

// check template's options that field's exists
function checkTemplateValue(template_options, field){
    if(template_options[field]){
        return template_options[field].value.toString();
    } else if(!template_options[field] && parseInt(template_options[field]) === 0) {
        return template_options[field].value.toString();
    } else {
        return "";
    }
}

function createNodeListRow(template, id){
    var html_data = "";
    var defer = $.Deferred();
    var userRight = "readonly";
    var disabledAttr = 'disabled="true"' ;
    if ((((ROLE == 'ROLE_TEACHER' || ROLE == 'ROLE_TEACHER_EDITOR') && AUTHOR == 1) || (ROLE == 'ROLE_ADMINISTRATOR' || ROLE == 'ROLE_SUPER_ADMINISTRATOR')) && EDITION ==1 && LOCK == 0 ) {
         userRight = "";
         disabledAttr = ""
    }
    var idTemplate = template.split(/(\d+)/)[1];

    $.when(getTemplates(idTemplate), getNodes(id)).done(function (template_values, node_values) {
        var value_set = "";
        var readonlyAttr = "";
        var value_name      = node_values['name'];
        var value_cpu       = node_values['cpu'] || 0;
        var value_core = node_values['core'] || 0;
        var value_socket = node_values['socket'] || 0;
        var value_thread = node_values['thread'] || 0;
        var value_flavor = node_values['flavor'] || "n/a";
        var highlightRow = '';
        var disabled = '';
        var disabledClass = '';
        if(node_values['status'] == 2){
            highlightRow = 'node-running';
            disabledAttr = 'disabled="true"' ;
            disabledClass = ' disabled '
        }

        // TODO: this event is called twice
        id = (id == null) ? '' : id;
        var html_data = '<tr class=" ' + highlightRow+ ' "><input name="node[type]" data-path="' + id + '" value="' + template_values['type'] + '" type="hidden"/>';
        html_data += '<input name="node[left]" data-path="' + id + '" value="' + node_values['left'] + '" type="hidden"/>';
        html_data += '<input name="node[top]" data-path="' + id + '" value="' + node_values['top'] + '" type="hidden"/>';

        // node id
        html_data += '<td><input class="hide-border" style="width: 20px;" value="' + id + '" readonly/></td>';

        //node name
        html_data += '<td><input class="configured-nodes-input ' + userRight + '" data-path="' + id + '" name="node[name]" value="' + value_name + '" type="text" ' + disabledAttr + ' /></td>';

        //node template
        html_data += '<td><input class="hide-border ' + userRight + '" data-path="' + id + '" name="node[template]" value="' + template + '" readonly/></td>';

        //node cpu
        readonlyAttr = (value_cpu && value_cpu != "n/a") ? "" : "readonly";
        html_data += '<td><input class="configured-nodes-input short-input ' + readonlyAttr + ' ' + userRight + '" data-path="' + id + '" name="node[cpu]" value="' + value_cpu + '" type="text" ' + readonlyAttr + ' ' + disabledAttr + ' /></td>';
        //node core
        readonlyAttr = (value_core != "n/a") ? "" : "readonly";
        html_data += '<td><input class="configured-nodes-checkbox short-input ' + readonlyAttr + ' ' + userRight + '" data-path="' + id + '" name="node[core]" value="' + value_core + '" type="text" ' + readonlyAttr + ' ' + disabledAttr + '/></td>';
    
        //node socket
        readonlyAttr = (value_socket != "n/a") ? "" : "readonly";
        html_data += '<td><input class="configured-nodes-input ' + readonlyAttr + ' ' + userRight + '" data-path="' + id + '" name="node[socket]" value="' + value_socket + '" type="text" ' + readonlyAttr + ' ' + disabledAttr + ' /></td>';

        //node thread
        readonlyAttr = (value_thread != "n/a") ? "" : "readonly";
        html_data += '<td><input class="configured-nodes-input short-input ' + readonlyAttr + ' ' + userRight + '" data-path="' + id + '" name="node[thread]" value="' + value_thread + '" type="text" ' + readonlyAttr + ' ' + disabledAttr + ' /></td>';

        //node flavor
        html_data += '<td><select class="selectpicker configured-nods-select form-control"' + disabledAttr + ' data-path="' + id + '" data-size="5" name="node[flavor]" data-container="body">'
        value_set = (node_values != null && node_values['flavor'] != null) ? node_values['flavor'] : value['value'];
        $.each(template_values['options']['flavor']['list'], function (list_key, list_value) {
            var selected = (list_key == value_set) ? 'selected ' : '';
            //var iconselect = 'data-content="&nbsp;&nbsp;'+list_value+'&nbsp;&nbsp;"';
            html_data += '<option ' + selected + 'value="' + list_key + '">' + list_value + '</option>';
        });
        html_data += '</select></td>';

        //node icon
        html_data += '<td><select class="selectpicker configured-nods-select form-control"' + disabledAttr + ' data-path="' + id + '" data-size="5" name="node[icon]" data-container="body">'
        value_set = (node_values != null && node_values['icon'] != null) ? node_values['icon'] : value['value'];
        $.each(template_values['options']['icon']['list'], function (list_key, list_value) {
            var selected = (list_key == value_set) ? 'selected ' : '';
            var iconselect = 'data-content="<img src=\'/build/editor/images/icons/'+list_value+'\' height=15 width=15>&nbsp;&nbsp;&nbsp;'+list_value+'&nbsp;&nbsp;"';
            html_data += '<option ' + selected + 'value="' + list_key + '" ' + iconselect + '>' + list_value + '</option>';
        });
        html_data += '</select></td>';

        //node actions
        html_data += '<td><div class="action-controls">';
        if (EDITION == 0 && (ISGROUPOWNER == 0 ||(ISGROUPOWNER == 1 && HASGROUPACCESS == 1))) {
            if (node_values['type'] != 'switch') {
                html_data += '<a class="action-nodestart" data-path="' + id + '" data-name="' + checkTemplateValue(template_values['options'],'name') + '" href="javascript:void(0)" title="' + MESSAGES[66] + '"><i class="glyphicon glyphicon-play"></i></a>'+
                            '<a class="action-nodestop" data-path="' + id + '" data-name="' + checkTemplateValue(template_values['options'],'name') + '" href="javascript:void(0)" title="' + MESSAGES[67] + '"><i class="glyphicon glyphicon-stop"></i></a>';
            }
        }
        if ((((ROLE == 'ROLE_TEACHER' || ROLE == 'ROLE_TEACHER_EDITOR') && AUTHOR == 1) || (ROLE == 'ROLE_ADMINISTRATOR' || ROLE == 'ROLE_SUPER_ADMINISTRATOR')) && EDITION ==1 && LOCK == 0 ) {
            html_data += '<a class="action-nodeedit control'+ disabledClass +'" data-path="' + id + '" data-name="' + checkTemplateValue(template_values['options'],'name') + '" href="javascript:void(0)" title="' + MESSAGES[71] + '"><i class="glyphicon glyphicon-edit"></i></a>'+
                         '<a class="action-nodedelete'+ disabledClass +'" data-path="' + id + '" data-name="' + checkTemplateValue(template_values['options'],'name') + '" href="javascript:void(0)" title="' + MESSAGES[65] + '"><i class="glyphicon glyphicon-trash"></i></a>';
        }
        html_data += '</div></td></tr>';
        defer.resolve({"html": html_data, "id": id});
    }).fail(function (message1, message2) {
        // Cannot get data
        if (message1 != null) {
            addModalError(message1);
        } else {
            addModalError(message2)
        }
        // return html_data;
        defer.resolve({"html": html_data, "id": id});
    });

    return defer;
}

// Display all nodes in a table
export function printListNodes(nodes) {
    logger(1, 'DEBUG: printing node list');
    var body = '<div class="table-responsive"><form id="form-node-edit-table" ><table class="configured-nodes table"><thead><tr><th>' + MESSAGES[92] + '</th><th>' + MESSAGES[19] + '</th><th>' + MESSAGES[111] + '</th><th>' + MESSAGES[105] + '</th><th>' + 'Core' + '</th><th>' + 'Socket' + '</th><th>'+ 'Thread' + '</th><th>' + 'Flavor' + '</th><th>' + MESSAGES[164] + '</th><th>' + MESSAGES[99] + '</th></tr></thead><tbody>';
    
    var html_rows = [];
    var promises = [];

    var composePromise = function (key, value) {
        var defer = $.Deferred();
        var cpu = (value['cpu'] != null) ? value['cpu'] : '';
        var core = (value['core'] != null) ?value['core'] : '';
        var socket = (value['socket'] != null) ?value['socket'] : '';
        var thread = (value['thread'] != null) ?value['thread'] : '';
        var flavor = (value['flavor'] != null) ?value['flavor'] : '';

        $.when(createNodeListRow(value['template'], value['id'])).done(function (data) {
            html_rows.push(data);

            defer.resolve();
        });
        return defer;
    };

    $.each(nodes, function (key, value) {
        promises.push(composePromise(key, value));
    })

    $.when.apply($, promises).done(function () {
        var html_data = html_rows.sort(function(a, b){
            return (a.id < b.id) ? -1 : (a.id > b.id) ? 1 : 0
        })
        $.each(html_data, function(key, value){
            body += value.html;
        });
        body += '</tbody></table></form></div>';
        $("#progress-loader").remove();
        addModalWide(MESSAGES[118], body, '');
        $('.selectpicker').selectpicker();
    })
}

// Display all text objects in a table
export function printListTextobjects(textobjects) {
    logger(1, 'DEBUG: printing text objects list');
    var text
        , body = '<div class="table-responsive">' +
            '<table class="table">' +
            '<thead>' +
            '<tr>' +
            '<th>' + MESSAGES[92] + '</th>' +
            '<th>' + MESSAGES[19] + '</th>' +
            '<th>' + MESSAGES[95] + '</th>' +
            '<th style="width:69%">' + MESSAGES[146] + '</th>';
            if ((((ROLE == 'ROLE_TEACHER' || ROLE == 'ROLE_TEACHER_EDITOR' ) && AUTHOR == 1) || (ROLE == 'ROLE_ADMINISTRATOR' || ROLE == 'ROLE_SUPER_ADMINISTRATOR')) && EDITION ==1 && LOCK == 0 ) {
                body += '<th style="width:9%">' + MESSAGES[99] + '</th>';
            }
            body +='</tr>' +
            '</thead>' +
            '<tbody>'
        ;

    $.each(textobjects, function (key, value) {
        var textClass = '',
            text = '';
        if (value['type'] == 'text') {
            text = $('#customText' + value['id'] + ' p').html();
            textClass ='customText'
        }

        body +=
            '<tr class="textObject' + value['id'] + '">' +
            '<td>' + value['id'] + '</td>' +
            '<td>' + value['name'] + '</td>' +
            '<td>' + value['type'] + '</td>' +
            '<td>' + text + '</td>';
        if ((((ROLE == 'ROLE_TEACHER' || ROLE == 'ROLE_TEACHER_EDITOR') && AUTHOR == 1) || (ROLE == 'ROLE_ADMINISTRATOR' || ROLE == 'ROLE_SUPER_ADMINISTRATOR')) && EDITION ==1 && LOCK == 0 ) {
             body += '<td><a class="action-textobjectdelete '+ textClass +'" data-path="' + value['id'] + '" data-name="' + value['name'] + '" href="javascript:void(0)" title="' + MESSAGES[65] + '">' +
                '<i class="glyphicon glyphicon-trash" style="margin-left:20px;"></i>' +
                '</a></td>'
        }
        body += '</tr>';
    });
    body += '</tbody></table></div>';
    addModalWide(MESSAGES[150], body, '');
}

// Print Authentication Page
export function printPageAuthentication() {
    location.href = "/" ;
}

// Print lab open page
function printPageLabOpen(lab) {
    if ( $.cookie("topo") == undefined ) $.cookie("topo", 'light');
    var html = '<div id="lab-sidebar"><ul></ul></div><div id="lab-viewport" data-path="' + lab + '"></div>';
    $('#body').html(html);
    // Print topology
    $.when(printLabTopology()).done( function (rc) {
        if ((((ROLE == 'ROLE_TEACHER' || ROLE == 'ROLE_TEACHER_EDITOR') && AUTHOR == 1) || (ROLE == 'ROLE_ADMINISTRATOR' || ROLE == 'ROLE_SUPER_ADMINISTRATOR')) && EDITION ==1 && LOCK == 0 ) {
              $('#lab-sidebar ul').append('<li class="action-labobjectadd-li"><a class="action-labobjectadd" href="javascript:void(0)" title="' + MESSAGES[56] + '"><i class="glyphicon glyphicon-plus"></i></a></li>');
         }
         $('#lab-sidebar ul').append('<li class="action-nodesget-li"><a class="action-nodesget" href="javascript:void(0)" title="' + MESSAGES[62] + '"><i class="glyphicon glyphicon-hdd"></i></a></li>');
         //$('#lab-sidebar ul').append('<li><a class="action-configsget"  href="javascript:void(0)" title="' + MESSAGES[58] + '"><i class="glyphicon glyphicon-align-left"></i></a></li>');
         if ((((ROLE == 'ROLE_TEACHER' || ROLE == 'ROLE_TEACHER_EDITOR') && AUTHOR == 1) || (ROLE == 'ROLE_ADMINISTRATOR' || ROLE == 'ROLE_SUPER_ADMINISTRATOR')) && EDITION ==1 && LOCK == 0 ) {
         $('#lab-sidebar ul').append('<li><a class="action-textobjectsget" href="javascript:void(0)" title="' + MESSAGES[150] + '"><i class="glyphicon glyphicon-text-background"></i></a></li>');
         }
         $('#lab-sidebar ul').append('<li><a class="action-moreactions" href="javascript:void(0)" title="' + MESSAGES[125] + '"><i class="glyphicon glyphicon-th"></i></a></li>');
         $('#lab-sidebar ul').append('<li><a class="action-labtopologyrefresh" href="javascript:void(0)" title="' + MESSAGES[57] + '"><i class="glyphicon glyphicon-refresh"></i></a></li>');
         $('#lab-sidebar ul').append('<li class="plus-minus-slider"><i class="fa fa-minus"></i><div class="col-md-2 glyphicon glyphicon-zoom-in sidemenu-zoom"></div><div id="zoomslide" class="col-md-5"></div><div class="col-md-5"></div><i class="fa fa-plus"></i><br></li>');
         $('#zoomslide').slider({value:100,min:10,max:200,step:10,slide:zoomlab});
         $('#lab-sidebar ul').append('<li><a class="action-labbodyget" href="javascript:void(0)" title="' + MESSAGES[64] + '"><i class="glyphicon glyphicon-list-alt"></i></a></li>');
         $('#lab-sidebar ul').append('<li><a class="action-labsubjectget" href="javascript:void(0)" title="Practical subject"><i class="glyphicon glyphicon-tasks"></i></a></li>');
         if ((((ROLE == 'ROLE_TEACHER' || ROLE == 'ROLE_TEACHER_EDITOR') && AUTHOR == 1) || (ROLE == 'ROLE_ADMINISTRATOR' || ROLE == 'ROLE_SUPER_ADMINISTRATOR')) && EDITION ==1 && LOCK == 0 ) {
            $('#lab-sidebar ul').append('<li><a class="action-lock-lab" href="javascript:void(0)" title="' + MESSAGES[166] + '"><i class="glyphicon glyphicon-ok-circle"></i></a></li>');
         }
            if ( $.cookie("topo") == 'dark' ) {
                $('#lab-sidebar ul').append('<li><a class="action-lightmode" href="javascript:void(0)" title="' + MESSAGES[236] + '"><i class="fas fa-sun"></i></a></li>');
         } else {
                $('#lab-sidebar ul').append('<li><a class="action-nightmode" href="javascript:void(0)" title="' + MESSAGES[235] + '"><i class="fas fa-moon"></i></a></li>');
        }
         $('#lab-sidebar ul').append('<div id="action-labclose"><li><a class="action-labclose" href="javascript:void(0)" title="' + MESSAGES[60] + '"><i class="glyphicon glyphicon-off"></i></a></li></div>');
         $('#lab-sidebar ul a').each(function () {
             var t = $(this).attr("title");
             $(this).append(t);


             })
        if ( LOCK == 1 ) {
            lab_topology.setDraggable($('.node_frame, .network_frame, .customShape'), false);
            $('.customShape').resizable('disable');
        }
    })
}

/*******************************************************************************
 * Custom Shape Functions
 * *****************************************************************************/
// Get All Text Objects
export function getTextObjects() {
    var deferred = $.Deferred();
    var lab_filename = $('#lab-viewport').attr('data-path');
    var url = '/api/labs/' + lab_filename + '/textobjects';
    var type = 'GET';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: got shape(s) from lab "' + lab_filename + '".');
                deferred.resolve(data['data']);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    return deferred.promise();
}

// Get Text Object By Id
function getTextObject(id) {
    var deferred = $.Deferred();
    var lab_filename = $('#lab-viewport').attr('data-path');
    var url = '/api/labs/' + lab_filename + '/textobjects/' + id;
    var type = 'GET';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: got shape ' + id + 'from lab "' + lab_filename + '".');

                try {
                    if ( data['data'].data.indexOf('div') != -1  ) {
                                   // nothing to do ?
                    } else {
                                   data['data'].data =  new TextDecoderLite('utf-8').decode(toByteArray(data['data'].data));
                    }
                }
                catch (e) {
                    console.warn("Compatibility issue", e);
                }

                deferred.resolve(data['data']);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    return deferred.promise();
}

// Create New Text Object
export function createTextObject(newData) {
    var deferred = $.Deferred()
        , lab_filename = $('#lab-viewport').attr('data-path')
        , url = '/api/labs/' + lab_filename + '/textobjects'
        , type = 'POST';

    if (newData.data) {
        newData.data = fromByteArray(new TextEncoderLite('utf-8').encode(newData.data));
    }
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        data: JSON.stringify(newData),
        dataType: 'json',
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: create shape ' + 'for lab "' + lab_filename + '".');
                deferred.resolve(data['data']);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    return deferred.promise();
}

// Update Text Object
export function editTextObject(id, newData) {
    var lab_filename = $('#lab-viewport').attr('data-path');
    var deferred = $.Deferred();
    var type = 'PUT';
    var url = '/api/labs/' + lab_filename + '/textobjects/' + id;

    if (newData.data) {
        newData.data = fromByteArray(new TextEncoderLite('utf-8').encode(newData.data));
    }

    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        data: JSON.stringify(newData), // newData is object with differences between old and new data
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: custom shape text object updated.');
                deferred.resolve(data['message']);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    deferred.resolve();
    return deferred.promise();
}

// Update Multiple Text Object
export function editTextObjects(newData) {
    var lab_filename = $('#lab-viewport').attr('data-path');
    var deferred = $.Deferred();
    if (newData.length == 0 ) { deferred.resolve(); return deferred.promise(); }
    var type = 'PUT';
    var url = '/api/labs/' + lab_filename + '/textobjects';

    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        data: JSON.stringify(newData), // newData is object with differences between old and new data
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: custom shape text object updated.');
                deferred.resolve(data['message']);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    deferred.resolve();
    return deferred.promise();
}
// Delete Text Object By Id
export function deleteTextObject(id) {
    var deferred = $.Deferred();
    var type = 'DELETE';
    var lab_filename = $('#lab-viewport').attr('data-path');
    var url = '/api/labs/' + lab_filename + '/textobjects/' + id;
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: shape/text deleted.');
                deferred.resolve();
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    return deferred.promise();
}

// Text Object Drag Stop / Resize Stop
export function textObjectDragStop(event, ui) {
    var id
        , objectData
        , shape_border_width
        ;
    if (event.target.id.indexOf("customShape") != -1) {
        id = event.target.id.slice("customShape".length);
        shape_border_width = $("#customShape" + id + " svg").children().attr('stroke-width');
    }
    else if (event.target.id.indexOf("customText") != -1) {
        id = event.target.id.slice("customText".length);
        shape_border_width = 5;
    }

    objectData = event.target.outerHTML;


    editTextObject(id, {
        data: objectData
    });
}

// Text Object Resize Event
function textObjectResize(event, ui, shape_options) {
    var newWidth = ui.size.width
        , newHeight = ui.size.height
        ;

    $("svg", ui.element).attr({
        width: newWidth,
        height: newHeight
    });
    $("svg > rect", ui.element).attr({
        width: newWidth,
        height: newHeight
    });
    $("svg > ellipse", ui.element).attr({
        rx: newWidth / 2 - shape_options['shape_border_width'] / 2,
        ry: newHeight / 2 - shape_options['shape_border_width'] / 2,
        cx: newWidth / 2,
        cy: newHeight / 2
    });
    var n = $("br", ui.element).length;
    if (n) {
        $("p", ui.element).css({
            "font-size": newHeight / (n * 1.5 + 1)
        });
    } else {
        $("p", ui.element).css({
            "font-size": newHeight / 2
        });
    }
    if ($("p", ui.element).length && $(ui.element).width() > newWidth) {
        ui.size.width = $(ui.element).width();
    }
}

// Edit Form: Custom Shape
export function printFormEditCustomShape(id) {
    $('.edit-custom-shape-form').remove();
    $('.edit-custom-text-form').remove();
    $('.customShape').each(function (index) {
        $(this).removeClass('in-editing');
    });
    getTextObject(id).done(function(res){
        var borderTypes = ['solid', 'dashed']
        , firstShapeValues = {}
        , shape
        , transparent = false
        , colorDigits
        , bgColor
        , html = new EJS({
            url: '/build/editor/ejs/form_edit_custom_shape.ejs'
        }).render({
            MESSAGES: MESSAGES,
            id: id
        })

        $('#body').append(html);

        if(isIE){
            $('input[type="color"]').hide()
            $('input.shape_border_color').colorpicker({
                color: "#000000",
                defaultPalette: 'web'
            })
            $('input.shape_background_color').colorpicker({
                color: "#ffffff",
                defaultPalette: 'web'
            })
        }
        for (var i = 0; i < borderTypes.length; i++) {
            $('.edit-custom-shape-form .border-type-select').append($('<option></option>').val(borderTypes[i]).html(borderTypes[i]));
        }

        if ($("#customShape" + id + " svg").children().attr('stroke-dasharray')) {
            $('.edit-custom-shape-form .border-type-select').val(borderTypes[1]);
            firstShapeValues['border-types'] = borderTypes[1];
        } else {
            $('.edit-custom-shape-form .border-type-select').val(borderTypes[0]);
            firstShapeValues['border-types'] = borderTypes[0];
        }

        bgColor = $("#customShape" + id + " svg").children().attr('fill');
        colorDigits = /(.*?)rgba{0,1}\((\d+), (\d+), (\d+)\)/.exec(bgColor);
        if (colorDigits === null) {
            var ifHex = bgColor.indexOf('#');
            if (ifHex < 0) {
                transparent = true;
            }
        }

        if (transparent) {
            $('.edit-custom-shape-form .shape_background_transparent').addClass('active  btn-success').text('On');
        } else {
            $('.edit-custom-shape-form .shape_background_transparent').removeClass('active  btn-success').text('Off');
        }

        firstShapeValues['shape-name'] = res.name;
        firstShapeValues['shape-z-index'] = $('#customShape' + id).css('z-index');
        firstShapeValues['shape-background-color'] = rgb2hex($("#customShape" + id + " svg").children().attr('fill'));
        firstShapeValues['shape-border-color'] = rgb2hex($("#customShape" + id + " svg ").children().attr('stroke'));
        firstShapeValues['shape-border-width'] = $("#customShape" + id + " svg").children().attr('stroke-width');
        firstShapeValues['shape-rotation'] = getElementsAngle("#customShape" + id);   

        // fill inputs
        $('.edit-custom-shape-form .shape-z_index-input').val(firstShapeValues['shape-z-index'] - 1000);
        $('.edit-custom-shape-form .shape_background_color').val(firstShapeValues['shape-background-color']);
        $('.edit-custom-shape-form .shape_border_color').val(firstShapeValues['shape-border-color']);
        $('.edit-custom-shape-form .shape_border_width').val(firstShapeValues['shape-border-width']);
        $('.edit-custom-shape-form .shape-rotation-input').val(firstShapeValues['shape-rotation']);
        $('.edit-custom-shape-form .shape-name-input').val(firstShapeValues['shape-name']);

        // fill backup
        $('.edit-custom-shape-form .firstShapeValues-z_index').val(firstShapeValues['shape-z-index']);
        $('.edit-custom-shape-form .firstShapeValues-border-color').val(firstShapeValues['shape-border-color']);
        $('.edit-custom-shape-form .firstShapeValues-background-color').val(firstShapeValues['shape-background-color']);
        $('.edit-custom-shape-form .firstShapeValues-border-type').val(firstShapeValues['border-types']);
        $('.edit-custom-shape-form .firstShapeValues-border-width').val(firstShapeValues['shape-border-width']);
        $('.edit-custom-shape-form .firstShapeValues-rotation').val(firstShapeValues['shape-rotation']);

        if ($("#customShape" + id + " svg").children().attr('cx')) {
            $('.edit-custom-shape-form .shape_border_width').val(firstShapeValues['shape-border-width'] * 2);
            $('.edit-custom-shape-form .firstShapeValues-border-width').val(firstShapeValues['shape-border-width'] * 2);
        }
        $("#customShape" + id).addClass('in-editing');
    });

}

// Edit Form: Text
export function printFormEditText(id) {
    $('.edit-custom-shape-form').remove();
    $('.edit-custom-text-form').remove();
    $('.customShape').each(function (index) {
        $(this).removeClass('in-editing');
    });

    var firstTextValues = {}
        , transparent = false
        , colorDigits
        , bgColor
        , html = new EJS({
            url: '/build/editor/ejs/form_edit_text.ejs'
        }).render({
            id: id,
            MESSAGES: MESSAGES
        })

    $('#body').append(html);

    if(isIE){
        $('input[type="color"]').hide()
        $('input.shape_border_color').colorpicker({
            color: "#000000",
            defaultPalette: 'web'
        })
        $('input.shape_background_color').colorpicker({
            color: "#ffffff",
            defaultPalette: 'web'
        })
    }
    bgColor = $("#customText" + id + " p").css('background-color');
    colorDigits = /(.*?)rgba{0,1}\((\d+), (\d+), (\d+)\)/.exec(bgColor);
    if (colorDigits === null) {
        var ifHex = bgColor.indexOf('#');
        if (ifHex < 0) {
            transparent = true;
        }
    }

    if (transparent) {
        $('.edit-custom-text-form .text_background_transparent').addClass('active  btn-success').text('On');
    } else {
        $('.edit-custom-text-form .text_background_transparent').removeClass('active  btn-success').text('Off');
    }

    firstTextValues['text-z-index'] = parseInt($('#customText' + id).css('z-index'));
    firstTextValues['text-color'] = rgb2hex($("#customText" + id + " p").css('color'));
    firstTextValues['text-background-color'] = rgb2hex($("#customText" + id + " p").css('background-color'));
    firstTextValues['text-rotation'] = getElementsAngle("#customText" + id);


    $('.edit-custom-text-form .text-z_index-input').val(parseInt(firstTextValues['text-z-index']) - 1000);
    $('.edit-custom-text-form .text_color').val(firstTextValues['text-color']);
    $('.edit-custom-text-form .text_background_color').val(firstTextValues['text-background-color']);
    $('.edit-custom-text-form .text-rotation-input').val(firstTextValues['text-rotation']);

    if ($("#customText" + id + " p").css('font-style') == 'italic') {
        $('.edit-custom-text-form .btn-text-italic').addClass('active');
        firstTextValues['text-type-italic'] = 'italic'
    }
    if ($("#customText" + id + " p").css('font-weight') == 'bold' || $("#customText" + id + " p").css('font-weight') == 700) {
        $('.edit-custom-text-form .btn-text-bold').addClass('active');
        firstTextValues['text-type-bold'] = 'bold';
    }
    if ($("#customText" + id + " p").attr('align') == 'left') {
        $('.edit-custom-text-form .btn-align-left').addClass('active');
        firstTextValues['text-align'] = 'left';
    } else if ($("#customText" + id + " p").attr('align') == 'center') {
        $('.edit-custom-text-form .btn-align-center').addClass('active');
        firstTextValues['text-align'] = 'center';
    } else if ($("#customText" + id + " p").attr('align') == 'right') {
        $('.edit-custom-text-form .btn-align-right').addClass('active');
        firstTextValues['text-align'] = 'right';
    }

    $('.edit-custom-text-form .firstTextValues-z_index').val(parseInt(firstTextValues['text-z-index']));
    $('.edit-custom-text-form .firstTextValues-color').val(firstTextValues['text-color']);
    $('.edit-custom-text-form .firstTextValues-background-color').val($("#customText" + id + " p").css('background-color'));
    $('.edit-custom-text-form .firstTextValues-italic').val(firstTextValues['text-type-italic']);
    $('.edit-custom-text-form .firstTextValues-bold').val(firstTextValues['text-type-bold']);
    $('.edit-custom-text-form .firstTextValues-align').val(firstTextValues['text-align']);
    $('.edit-custom-text-form .firstTextValues-rotation').val(firstTextValues['text-rotation']);

    $("#customText" + id).addClass('in-editing');
}

// Change from RGB to Hex color
function rgb2hex(color) {
    if (color.substr(0, 1) === '#') {
        return color;
    }
    var digits = /(.*?)rgba{0,1}\((\d+), (\d+), (\d+)\)/.exec(color);

    if (digits == null) {
        digits = /(.*?)rgba\((\d+), (\d+), (\d+), (\d+)\)/.exec(color);
    }

    var red = parseInt(digits[2]);
    var green = parseInt(digits[3]);
    var blue = parseInt(digits[4]);

    var rgb = blue | (green << 8) | (red << 16);
    return digits[1] + '#' + ("000000" + rgb.toString(16)).slice(-6);
}

// Change from Hex to RGB color
export function hex2rgb(hex, opacity) {
    hex = hex.replace('#', '');
    var r = parseInt(hex.substring(0, 2), 16);
    var g = parseInt(hex.substring(2, 4), 16);
    var b = parseInt(hex.substring(4, 6), 16);

    return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + opacity + ')';
}

function getElementsAngle(selector) {
    var el = document.querySelector(selector)
        , st = window.getComputedStyle(el, null)
        , tr = st.getPropertyValue("-webkit-transform") ||
        st.getPropertyValue("-moz-transform") ||
        st.getPropertyValue("-ms-transform") ||
        st.getPropertyValue("-o-transform") ||
        st.getPropertyValue("transform") ||
        "FAIL";

    if (tr === "FAIL" || tr === "none") {
        return 0;
    }

    // With rotate(30deg)...
    // matrix(0.866025, 0.5, -0.5, 0.866025, 0px, 0px)
    // rotation matrix - http://en.wikipedia.org/wiki/Rotation_matrix

    var values = tr.split('(')[1].split(')')[0].split(',')
        , a = values[0]
        , b = values[1]
        , c = values[2]
        , d = values[3]
        , scale = Math.sqrt(a * a + b * b)
        , sin = b / scale
        , angle = Math.round(Math.atan2(b, a) * (180 / Math.PI))
        ;

    return angle;
}

export function lockLab() {
    var lab_topology = window.lab_topology
    lab_topology.setDraggable($('.node_frame, .network_frame, .customShape'), false);
    $('.customShape').resizable('disable');
    $('.action-lock-lab').html('<i style="color:red" class="glyphicon glyphicon-remove-circle"></i>' + MESSAGES[167])
    $('.action-lock-lab').removeClass('action-lock-lab').addClass('action-unlock-lab')
    var deferred = $.Deferred();
    var lab_filename = $('#lab-viewport').attr('data-path');
    var url = '/api/labs/' + lab_filename + '/Lock' ;
    var type = 'PUT';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: network position updated.');
                deferred.resolve();
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
            addMessage(data['status'], data['message']);
            setLock(1) ;

        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    $('.action-labobjectadd-li').hide();
    return deferred.promise();
}

export function unlockLab(){
    lab_topology = window.lab_topology
    lab_topology.setDraggable($('.node_frame, .network_frame, .customShape'), true);
    lab_topology.draggable($('.node_frame, .network_frame, .customShape'), {
                       grid: [3, 3],
                       stop: ObjectPosUpdate
                    });

    $('.customShape').resizable('enable');
    $('.action-unlock-lab').html('<i class="glyphicon glyphicon-ok-circle"></i>' + MESSAGES[166])
    $('.action-unlock-lab').removeClass('action-unlock-lab').addClass('action-lock-lab')
    var deferred = $.Deferred();
    var lab_filename = $('#lab-viewport').attr('data-path');
    var url = '/api/labs/' + lab_filename + '/Unlock' ;
    var type = 'PUT';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: network position updated.');
                deferred.resolve();
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                deferred.reject(data['message']);
            }
            addMessage(data['status'], data['message']);
        setLock(0) ;

        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            deferred.reject(message);
        }
    });
    if ($('.action-labobjectadd-li').length == 0) {
         $('.action-nodesget-li').before('<li class="action-labobjectadd-li"><a class="action-labobjectadd" href="javascript:void(0)" title="' +
         MESSAGES[56] + '"><i class="glyphicon glyphicon-plus"></i>' + MESSAGES[56] + '</a></li>');
    } else {
         $('.action-labobjectadd-li').show();
   }
    return deferred.promise();
}

function sleep(milliseconds) {
  var start = new Date().getTime();
  for (var i = 0; i < 1e7; i++) {
    if ((new Date().getTime() - start) > milliseconds){
      break;
    }
  }
}

function natSort(as, bs){
    var a, b, a1, b1, i= 0, L, rx=  /(\d+)|(\D+)/g, rd=  /\d/;
    if(isFinite(as) && isFinite(bs)) return as - bs;
    a= String(as).toLowerCase();
    b= String(bs).toLowerCase();
    if(a=== b) return 0;
    if(!(rd.test(a) && rd.test(b))) return a> b? 1: -1;
    a= a.match(rx);
    b= b.match(rx);
    L= a.length> b.length? b.length: a.length;
    while(i < L){
        a1= a[i];
        b1= b[i++];
        if(a1!== b1){
            if(isFinite(a1) && isFinite(b1)){
                if(a1.charAt(0)=== "0") a1= "." + a1;
                if(b1.charAt(0)=== "0") b1= "." + b1;
                return a1 - b1;
            }
            else return a1> b1? 1: -1;
        }
    }
    return a.length - b.length;
}

function newConnModal(info , oe ) {
        if ( !oe ) return ;
    $.when(
        getNodes(null),
        getTopology()
        ).done(function (nodes, topology ) {
            var linksourcestyle = '' ;
            var linktargetstyle = '' ;
        $('#'+info.source.id).addClass("startNode")
            if ( info.source.id.search('node')  != -1  ) {
                  var linksourcedata =  nodes[ info.source.id.replace('node','') ] ;
                  var linksourcetype = 'node' ;
                  linksourcedata['interfaces'] = getNodeInterfaces(linksourcedata['id'])
                  if ( linksourcedata['status'] == 0 ) linksourcestyle = 'grayscale'
             } 
             if ( info.target.id.search('node')  != -1  ) {
                  var linktargetdata =  nodes[ info.target.id.replace('node','') ] ;
                  var linktargettype = 'node' ;
                  linktargetdata['interfaces'] = getNodeInterfaces(linktargetdata['id'])
                  if ( linktargetdata['status'] == 0 ) linktargetstyle = 'grayscale'
             }
             var title = 'Add connection between ' + linksourcedata['name'] + ' and ' + linktargetdata['name'] ;
             $.when( linksourcedata['interfaces'] , linktargetdata['interfaces'] ).done( function ( sourceif, targetif) {
             /* choose first free interface */
                  if ( linksourcetype == 'node' )  {
                       logger(1,'DEBUG: looking interfaces... ');
                   linksourcedata['selectedif'] = '' ;
                       var tmp_interfaces = {} ;
                       for ( var key in sourceif['ethernet'] ) {
                 logger(1,'DEBUG: interface id ' + key + ' named ' + sourceif['ethernet'][key]['name']  + ' ' + sourceif['ethernet'][key]['network_id'])
                             tmp_interfaces[key] = sourceif['ethernet'][key]
                             tmp_interfaces[key]['type'] = 'ethernet'
                 if ( (sourceif['ethernet'][key]['network_id'] == 0 )  && ( linksourcedata['selectedif'] == '') ) {
                                    linksourcedata['selectedif'] = key ;
                             }
                       }
                       for ( var key in sourceif['serial'] ) {
                             logger(1,'DEBUG: interface id ' + key + ' named ' + sourceif['serial'][key]['name']  + ' ' + sourceif['serial'][key]['remote_id'])
                             tmp_interfaces[key] =  sourceif['serial'][key]
                             tmp_interfaces[key]['type']  =  'serial'
                             if ( (sourceif['serial'][key]['remote_id'] == 0 )  && ( linksourcedata['selectedif'] == '') ) {
                                    linksourcedata['selectedif'] = key ;
                             }
                       }
                       
                       linksourcedata['interfaces'] = tmp_interfaces
                  }
                  if ( linksourcedata['selectedif'] == '') linksourcedata['selectedif'] = 0 ;
                  if ( linktargettype == 'node' )  {
                       logger(1,'DEBUG: looking interfaces... ') ;
                       linktargetdata['selectedif'] = '' ;
                       var tmp_interfaces = []
                       for ( var key in targetif['ethernet'] ) {
                             logger(1,'DEBUG: interface id ' + key + ' named ' + targetif['ethernet'][key]['name']  + ' ' + targetif['ethernet'][key]['network_id'])
                             tmp_interfaces[key] = targetif['ethernet'][key];
                             tmp_interfaces[key]['type'] = 'ethernet'
                             if ( (targetif['ethernet'][key]['network_id'] == 0 )  && ( linktargetdata['selectedif'] == '') ) {
                                    linktargetdata['selectedif'] = key ;
                             }
                       }
                       for ( var key in targetif['serial'] ) {
                             logger(1,'DEBUG: interface id ' + key + ' named ' + targetif['serial'][key]['name']  + ' ' + targetif['serial'][key]['remote_id'])
                             tmp_interfaces[key] = targetif['serial'][key];
                             tmp_interfaces[key]['type'] = 'serial' ;
                             if ( (targetif['serial'][key]['remote_id'] == 0 )  && ( linktargetdata['selectedif'] == '') ) {
                                    linktargetdata['selectedif'] = key ;
                             }
                       }
                       linktargetdata['interfaces'] = tmp_interfaces
                  }
                  if ( linktargetdata['selectedif'] == '' ) linktargetdata['selectedif'] = 0 ;
                  if ( linksourcedata['status'] == 2 || linktargetdata['status'] == 2 ) { lab_topology.detach( info.connection ) ; return }
                  window.tmpconn = info.connection
                  var html = '<form id="addConn" class="addConn-form">' +
                           '<input type="hidden" name="addConn[srcNodeId]" value="'+linksourcedata['id']+'">' +
                           '<input type="hidden" name="addConn[dstNodeId]" value="'+linktargetdata['id']+'">' +
                           '<input type="hidden" name="addConn[srcNodeType]" value="'+linksourcetype+'">' +
                           '<input type="hidden" name="addConn[dstNodeType]" value="'+linktargettype+'">' +
                           '<input type="hidden" name="addConn[srcElementType]" value="'+linksourcedata['type']+'">' +
                           '<input type="hidden" name="addConn[dstElementType]" value="'+linktargetdata['type']+'">' +
                           '<div class="row">' +
                            '<div class="col-md-4">' +
                                '<div style="text-align:center;" >'+ linksourcedata['name']  + '</div>' +
                                '<img src="'+ '/build/editor/images/icons/' + linksourcedata['icon'] + '" class="'+ linksourcestyle  +' img-responsive" style="margin:0 auto;">' +
                                '<div style="width:3px;height: ' + ( (linksourcetype == 'net') ? '0' : '10' ) + 'px; margin: 0 auto; background-color:#444"></div>' +
                                '<div style="margin: 0 auto; width:50%; text-align:center;" class="' + (( linksourcetype == 'net') ? 'hidden' : '')  +  '">' +
                                    '<text class="aLabel addConnSrc text-center" >'+ (( linksourcetype == 'node') ? linksourcedata['interfaces'][linksourcedata['selectedif']]['name'] : '' )  +'</text>' +
                                '</div>' +
                                '<div style="width:3px;height:160px; margin: 0 auto; background-color:#444"></div>' +
                                '<div style="margin: 0 auto; width:50%; text-align:center;" class="' + ((linktargettype == 'net') ? 'hidden' : '')  + '">' +
                                    '<text class="aLabel addConnDst text-center" >'+ ((linktargettype == 'node') ?  linktargetdata['interfaces'][linktargetdata['selectedif']]['name'] : '' ) +'</text>' +
                                '</div>' +
                                '<div style="width:3px;height: '+ ( ( linktargettype  == 'net') ? '0' : '10')  + 'px; margin: 0 auto; background-color:#444"></div>' +
                                '<img src="/build/editor/images/icons/'+linktargetdata['icon']+'" class="'+linktargetstyle+' img-responsive" style="margin:0 auto;">' +
                                '<div style="text-align:center;" >'+linktargetdata['name']+'</div>' +
                            '</div>' +
                            '<div class="col-md-8">' +
                                '<div class="form-group">' +
                                    '<label>Source ID: '+linksourcedata['id']+'</label>' +
                                    '<p style="margin:0px;"></p>' +
                                    '<label>Source Name: '+ linksourcedata['name'] +'</label>' +
                                    '<p style="">type - '+ ((linksourcetype == 'net') ? 'Network' : 'Node') +'</p>' +
                                '</div>' +
                                '<div class="form-group">' +
                                    '<div class="form-group ' + (( linksourcetype == 'net') ? 'hidden' : '')  +  '">'  +
                                        '<label>Choose Interface for '+ linksourcedata['name'] +'</label>' +
                                        '<select name="addConn[srcConn]" class="form-control srcConn">'
                                        if ( linksourcetype == 'node' ) {
                                            // Eth first
                                            var tmp_name = [];
                                            var reversetab = [];
                                            for ( key in linksourcedata['interfaces'] ) {
                                                 tmp_name.push(linksourcedata['interfaces'][key]['name'])
                                                 reversetab[linksourcedata['interfaces'][key]['name']] = key
                                            }
                                            var ordered_name = tmp_name.sort(natSort)
                                            for ( key in ordered_name ) {
                                                var okey = reversetab[ordered_name[key]] ;
                                                if ( linksourcedata['interfaces'][okey]['type'] == 'ethernet' ) {
                                                    html += '<option value="' + okey + ',ethernet' +'" '+((linksourcedata['interfaces'][okey]['network_id'] != -1) ? 'disabled="true"' : '' ) +'>' + linksourcedata['interfaces'][okey]['name']
                                                    if ( linksourcedata['interfaces'][okey]['network_id'] != -1) {
                                                        html += ' connected to '
                                                        for ( var tkey in topology ) {
                                                            if ( ( topology[tkey]['source'] == ( 'node' + linksourcedata['id'] ))  && ( topology[tkey]['source_label'] == linksourcedata['interfaces'][okey]['name'] )) {
                                                                if (topology[tkey]['destination_type'] == 'node'  ) html += nodes[topology[tkey]['destination'].replace('node','')]['name']
                                                                if (topology[tkey]['destination_type'] == 'node' ) html += ' ' + topology[tkey]['destination_label']
                                                                if (topology[tkey]['destination_type'] == 'network' ) html += ' ' + networks[ linksourcedata['interfaces'][okey]['network_id'] ]['name']
                                                            }
                                                            if ( ( topology[tkey]['destination'] == ( 'node' + linksourcedata['id'] ))  && ( topology[tkey]['destination_label'] == linksourcedata['interfaces'][okey]['name'] )) {
                                                                if (topology[tkey]['source_type'] == 'node'  ) html += nodes[topology[tkey]['source'].replace('node','')]['name']
                                                                if (topology[tkey]['source_type'] == 'node'  ) html += ' ' + topology[tkey]['source_label']
                                                                if ( topology[tkey]['source_type'] == 'network' ) html += ' ' + networks[ linksourcedata['interfaces'][okey]['network_id'] ]['name']
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                            for ( var key in ordered_name ) {
                                                okey = reversetab[ordered_name[key]] ;
                                                if ( linksourcedata['interfaces'][okey]['type'] == 'serial' ) {
                                                    html += '<option value="' + okey + ',serial' +'" '+ ((linksourcedata['interfaces'][okey]['remote_id'] != -1) ? 'disabled="true"' : '' )  +'>' + linksourcedata['interfaces'][okey]['name']
                                                    if ( linksourcedata['interfaces'][okey]['remote_id'] != -1) {
                                                    html += ' connected to '
                                                    html += nodes[ linksourcedata['interfaces'][okey]['remote_id'] ]['name']
                                                    html += ' ' + linksourcedata['interfaces'][okey]['remote_if_name']
                                                    }
                                                }
                                            }
                                         }
                                        html += '</option>'
                                        html += '</select>' +
                                        '<div style="width:3px;height:5px;"></div>' +
                                    '</div>' +
                                '</div>' +
                                '<div class="form-group">'+
                                    '<label>Choose connector to link nodes</label>' +
                                    '<select name="addConn[connector]" class="form-control">' +
                                        '<option value="Straight">Straight</option>' +
                                        '<option value="Bezier">Bezier</option>' +
                                        '<option value="Flowchart">Flowchart</option>' +
                                    '</select>'+
                                '</div>' +
                                '<div class="form-group">'+
                                    '<label>Write a label for the connector</label>' +
                                    '<input type="text" name="addConn[connector_label]" class="form-control"/>' +
                                '</div>' +
                                '<div class="form-group">' +
                                    '<div class="form-group ' + (( linktargettype == 'net') ? 'hidden' : '')  +  '">'  +
                                        '<label>Choose Interface for '+ linktargetdata['name'] +'</label>' +
                                        '<select name="addConn[dstConn]" class="form-control dstConn">'
                                        if ( linktargettype == 'node' ) {
                                            // Eth first
                                            var tmp_name = [];
                                            var reversetab = [];
                                            for ( key in linktargetdata['interfaces'] ) {
                                                 tmp_name.push(linktargetdata['interfaces'][key]['name'])
                                                 reversetab[linktargetdata['interfaces'][key]['name']] = key
                                            }
                                            var ordered_name = tmp_name.sort(natSort) ;
                                            for ( key in ordered_name ) {
                                            okey = reversetab[ordered_name[key]] ;
                                                if ( linktargetdata['interfaces'][okey]['type'] == 'ethernet' ) {
                                                    html += '<option value="' + okey + ',ethernet' +'" '+((linktargetdata['interfaces'][okey]['network_id'] != -1) ? 'disabled="true"' : '' ) +'>' + linktargetdata['interfaces'][okey]['name']
                                                    if ( linktargetdata['interfaces'][okey]['network_id'] != -1) {
                                                        html += ' connected to '
                                                        for ( tkey in topology ) {
                                                            if ( ( topology[tkey]['source'] == ( 'node' + linktargetdata['id'] ))  && ( topology[tkey]['source_label'] == linktargetdata['interfaces'][okey]['name'] )) {
                                                                if (topology[tkey]['destination_type'] == 'node'  ) html += nodes[topology[tkey]['destination'].replace('node','')]['name']
                                                                if (topology[tkey]['destination_type'] == 'node' ) html += ' ' + topology[tkey]['destination_label']
                                                                if (topology[tkey]['destination_type'] == 'network' ) html += ' ' + networks[ linktargetdata['interfaces'][okey]['network_id'] ]['name']
                                                            }
                                                            if ( ( topology[tkey]['destination'] == ( 'node' + linktargetdata['id'] ))  && ( topology[tkey]['destination_label'] == linktargetdata['interfaces'][okey]['name'] )) {
                                                                if (topology[tkey]['source_type'] == 'node'  ) html += nodes[topology[tkey]['source'].replace('node','')]['name']
                                                                if (topology[tkey]['source_type'] == 'node'  ) html += ' ' + topology[tkey]['source_label']
                                                                if ( topology[tkey]['source_type'] == 'network' ) html += ' ' + networks[ linktargetdata['interfaces'][okey]['network_id'] ]['name']
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                            // Serial first
                                            for ( key in ordered_name ) {
                                            okey = reversetab[ordered_name[key]] ;
                                                if ( linktargetdata['interfaces'][okey]['type'] == 'serial' ) {
                                                    html += '<option value="' + okey + ',serial' +'" '+ ((linktargetdata['interfaces'][okey]['remote_id'] != -1) ? 'disabled="true"' : '' )  +'>' + linktargetdata['interfaces'][okey]['name']
                                                    if ( linktargetdata['interfaces'][okey]['remote_id'] != -1) {
                                                    html += ' connected to '
                                                    html += nodes[ linktargetdata['interfaces'][okey]['remote_id'] ]['name']
                                                    html += ' ' + linktargetdata['interfaces'][okey]['remote_if_name']
                                                    }
                                                }
                                            }
                                         }
                                        html += '</option>'
                                        html += '</select>' +
                                        '<div style="width:3px;height:30px;"></div>' +
                                    '</div>' +
                                '</div>' +
                                '<div class="form-group">' +
                                    '<label>Destination ID: ' + linktargetdata['id'] + '</label>' +
                                    '<p style="margin:0px;"></p>' +
                                    '<label>Destination Name: ' + linktargetdata['name'] + '</label>' +
                                    '<p style="text-muted">type - '+ ((linktargettype == 'net') ? 'Network' : 'Node') +'</p>' +
                                '</div>' +
                            '</div>' +
                            '<div class="col-md-8 btn-part col-md-offset-6">' +
                                '<div class="form-group">' +
                                    '<button type="submit" class="btn btn-success addConn-form-save">' + MESSAGES[47] + '</button>' +
                                    '<button type="button" class="btn cancelForm" data-dismiss="modal">' + MESSAGES[18] + '</button>' +
                                '</div>' +
                            '</div>' +
                           '</div>' +
                         '</form>'

                  addModal(title, html, '');
             });
        });
     $('body').on('change','select.srcConn', function (e) {
          var iname =  $('select.srcConn option[value="' + $('select.srcConn').val() + '"]').text();
      $('.addConnSrc').html(iname)
     });
     $('body').on('change','select.dstConn', function (e) {
          var iname =  $('select.dstConn option[value="' + $('select.dstConn').val() + '"]').text();
          $('.addConnDst').html(iname)
     });
}

function connContextMenu ( e, ui ) {
         window.connContext = 1
         window.connToDel = e
}

function zoomlab ( event, ui ) {
    var zoom=ui.value/100
    setZoom(zoom,lab_topology,[0.0,0.0])
    $('#lab-viewport').width(($(window).width()-40)/zoom)
    $('#lab-viewport').height($(window).height()/zoom);
    $('#lab-viewport').css({top: 0,left: 40,position: 'absolute'});
    $('#zoomslide').slider({value:ui.value})
}



// Function from jsPlumb Doc
window.setZoom = function(zoom, instance, transformOrigin, el) {
  transformOrigin = transformOrigin || [ 0.5, 0.5 ];
  instance = instance || jsPlumb;
  el = el || instance.getContainer();
  var p = [ "webkit", "moz", "ms", "o" ],
      s = "scale(" + zoom + ")",
      oString = (transformOrigin[0] * 100) + "% " + (transformOrigin[1] * 100) + "%";

  for (var i = 0; i < p.length; i++) {
    el.style[p[i] + "Transform"] = s;
    el.style[p[i] + "TransformOrigin"] = oString;
  }

  el.style["transform"] = s;
  el.style["transformOrigin"] = oString;

  instance.setZoom(zoom);
};

// Form upload node config
// Import external labs
function printFormUploadNodeConfig(path) {
    var html = '<form id="form-upload-node-config" class="form-horizontal form-upload-node-config">' +
                    '<div class="form-group">' +
                         '<label class="col-md-3 control-label">' + MESSAGES[2] + '</label>' +
                         '<div class="col-md-5">' +
                              '<input class="form-control" name="upload[path]" value="" disabled="" placeholder="' + MESSAGES[25] + '" "type="text"/>' +
                         '</div>' +
                    '</div>' +
                    '<div class="form-group">' +
                         '<div class="col-md-7 col-md-offset-3">' +
                               '<span class="btn btn-default btn-file btn-success">' + MESSAGES[23] +
                                    '<input accept="text/plain" class="form-control" name="upload[file]" value="" type="file">' +
                               '</span>' +
                               '<button type="submit" class="btn btn-flat">' + MESSAGES[200] + '</button>' +
                               '<button type="button" class="btn btn-flat" data-dismiss="modal">' + MESSAGES[18] + '</button>' +
                         '</div>' +
                   '</div>' +
                 '</form>';
    logger(1, 'DEBUG: popping up the upload form.');
    addModal(MESSAGES[201], html, '', 'upload-modal');
    validateImport();
}

