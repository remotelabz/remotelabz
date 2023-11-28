// vim: syntax=javascript tabstop=4 softtabstop=0 noexpandtab laststatus=1 ruler

/**
 * html/themes/default/js/actions.js
 *
 * Actions for HTML elements
 *
 * @author Andrea Dainese <andrea.dainese@gmail.com>
 * @copyright 2014-2016 Andrea Dainese
 * @license BSD-3-Clause https://github.com/dainok/unetlab/blob/master/LICENSE
 * @link http://www.unetlab.com/
 * @version 20160719
 */

import {TIMEOUT, FOLDER, ROLE, TENANT, LOCK, AUTHOR, EDITION, setFolder, setLab, setLang, setLock, setName, setRole, setTenant, setUpdateId, LONGTIMEOUT, ATTACHMENTS, setAttachements} from './javascript';
import {MESSAGES} from './messages_en';
import '../bootstrap/js/jquery-3.2.1.min';
import '../bootstrap/js/tinytools.toggleswitch.min';
import '../bootstrap/js/jquery-ui-1.12.1.min';
import '../bootstrap/js/jquery-cookie-1.4.1';
import '../bootstrap/js/jquery.validate-1.14.0.min';
import '../bootstrap/js/jquery.hotkey';
import '../bootstrap/js/jsPlumb-2.4.min';
import '../bootstrap/js/imageMapResizer.min';
import '../bootstrap/js/bootstrap.min';
import '../bootstrap/js/bootstrap-select.min';
import './ejs';
import { logger, getJsonMessage, newUIreturn, printPageAuthentication, getUserInfo, getLabInfo, getLabBody, closeLab, postBanner,
         lockLab, printFormLab, unlockLab, saveLab, printLabStatus, postLogin, getNodeInterfaces, deleteNode, form2Array, getVlan, getConnection, removeConnection, setNodeInterface,
         setNodesPosition, printLabTopology, printContextMenu, getNodes, getNodeConfigs, start, recursive_start, stop, printFormNode, printFormNodeConfigs, 
         printListNodes, setNodeData, printFormCustomShape, printFormPicture, printFormText, printListTextobjects, printFormEditCustomShape,
         printFormEditText, printFormSubjectLab, getPictures, printPictureInForm, deletePicture, displayPictureForm, getTextObjects, createTextObject, 
         editTextObject, editTextObjects, deleteTextObject, textObjectDragStop, addMessage, addModal, addModalError, addModalWide,
         zoompic, dirname, basename, hex2rgb, updateFreeSelect, getTopology, editConnection } from'./functions.js';
import {fromByteArray,TextEncoderLite} from './b64encoder';
import { adjustZoom, resolveZoom, saveEditorLab } from './ebs/functions';
import Showdown, { extension } from 'showdown';
//import * as ace from 'ace-builds/src-noconflict/ace';

var KEY_CODES = {
    "tab": 9,
    "enter": 13,
    "shift": 16,
    "ctrl": 17,
    "alt": 18,
    "escape": 27
};

// Attach files
$('body').on('change', 'input[type=file]', function (e) {
    setAttachements(e.target.files);
});

// Add the selected filename to the proper input box
$('body').on('change', 'input[name="import[file]"]', function (e) {
    $('input[name="import[local]"]').val($(this).val());
});

// Choose node config upload file
$('body').on('change', 'input[name="upload[file]"]', function (e) {
    $('input[name="upload[path]"]').val($(this).val());
});

// On escape remove mouse_frame
$(document).on('keydown', 'body', function (e) {
    var $labViewport = $("#lab-viewport")
        , isFreeSelectMode = $labViewport.hasClass("freeSelectMode")
        , isEditCustomShape = $labViewport.has(".edit-custom-shape-form").length > 0
        , isEditText = $labViewport.has(".edit-custom-text-form").length > 0
        , isEditcustomText = $labViewport.has(".editable").length > 0
        ;

    if (KEY_CODES.escape == e.which) {
        $('.lab-viewport-click-catcher').unbind('click');
        $('#mouse_frame').remove();
        $('#lab-viewport').removeClass('lab-viewport-click-catcher').data("prevent-contextmenu", false);
        $('#context-menu').remove();
        $('.free-selected').removeClass('free-selected')
        $('.ui-selected').removeClass('ui-selected')
        $('.ui-selecting').removeClass('ui-selecting')
        $("#lab-viewport").removeClass('freeSelectMode')
        lab_topology.clearDragSelection();
        if (((ROLE == 'ROLE_TEACHER' && AUTHOR == 1) || (ROLE != 'ROLE_USER' && ROLE !='ROLE_TEACHER')) && EDITION ==1 && LOCK == 0 ) {
        //if ((ROLE != 'ROLE_USER') &&  LOCK == 0  ) {
              lab_topology.setDraggable($('.node_frame, .network_frame, .customShape'), true)
        }
    }
    if (isEditCustomShape && KEY_CODES.escape == e.which) {
        $(".edit-custom-shape-form button.cancelForm").click(); // it will handle all the stuff
    }
    if (isEditText && KEY_CODES.escape == e.which) {
        $(".edit-custom-text-form button.cancelForm").click();  // it will handle all the stuff
    }
    if (isEditcustomText && KEY_CODES.escape == e.which) {
        $("p").blur()
        $("p").focusout()
    }
});

//Add picture MAP
$('body').on('click', '.follower-wrapper', function (e) {
    var img_width_original  = +$(".follower-wrapper img").attr('width-val')
    var img_height_original = +$(".follower-wrapper img").attr('height-val')
    var data_x = $("#follower").data("data_x");
    var data_y = $("#follower").data("data_y");
    var img_width_resized = $(".follower-wrapper img").width()
    var img_height_resized = $(".follower-wrapper img").height()

    var k = 1;
    if($('.follower-wrapper img').hasClass('picture-img-autosozed')){
        k = img_width_original / img_width_resized;
    }

    var y = (parseInt((data_y).toFixed(0)) * k).toFixed(0);
    var x = (parseInt((data_x).toFixed(0)) * k).toFixed(0);
    var current_href=""
    if ( $("#map_nodeid option:selected").val().match(/CUSTOM/) ) {
         $('form textarea.custommap').val($('form textarea.custommap').val() + "<area shape='circle' alt='img' coords='" + x + "," + y + (",30' href='telnet://{{IP}}:{{NODE"+$("#map_nodeid option:selected").val()+"}}'>\n").replace(/telnet.*NODECUSTOM}}/,"proto://CUSTOM_IP:CUSTOM_PORT"));
    } else {
         $('form textarea.map').val($('form textarea.map').val() + "<area shape='circle' alt='img' coords='" + x + "," + y + (",30' href='telnet://{{IP}}:{{NODE"+$("#map_nodeid option:selected").val()+"}}'>\n").replace(/telnet.*NODECUSTOM}}/,"proto://CUSTOM_IP:CUSTOM_PORT"));
    }
    var htmlsvg="" ;
    htmlsvg = '<div class="map_mark" id="'+x+","+y+","+30+'" style="position:absolute;top:'+(y-30)+'px;left:'+(x-30)+'px;width:60px;height:60px;"><svg width="60" height="60"><g><ellipse cx="30" cy="30" rx="28" ry="28" stroke="#000000" stroke-width="2" fill="#ffffff"></ellipse><text x="50%" y="50%" text-anchor="middle" alignment-baseline="central" stroke="#000000" stroke-width="0px" dy=".2em" font-size="12" >' + ("NODE"+$("#map_nodeid option:selected").val()).replace(/NODE.*CUSTOM.*/,"CUSTOM")+'</text></g></svg></div>'
    $(".follower-wrapper").append(htmlsvg)
});


//<div class="map_mark" id="'+area.coords+'"
// context menu on picture edit
$(document).on('contextmenu', '.follower-wrapper', function(e){
    // Prevent default context menu on viewport
    e.stopPropagation();
    e.preventDefault();
    var body = '';
        body += '<li><a class="action-showfull-picture" href="javascript:void(0)">Set original size</a></li>';
        body += '<li><a class="action-autosize" href="javascript:void(0)">Set autosize</a></li>';
        //printContextMenu('Picture size', body, e.pageX, e.pageY,true,"menu");
})

$(document).on('click', '.action-showfull-picture', function(){
    $('#context-menu').remove();
    FOLLOW_WRAPPER_IMG_STATE = 'full'
    $('.follower-wrapper img').removeClass('picture-img-autosozed')
    $('#lab_picture img').removeClass('picture-img-autosozed')
})

$(document).on('click', '.action-autosize', function(){
    $('#context-menu').remove();
    FOLLOW_WRAPPER_IMG_STATE = 'resized'
    $('.follower-wrapper img').addClass('picture-img-autosozed')
    $('#lab_picture img').addClass('picture-img-autosozed')
})

// Accept privacy
$(document).on('click', '#privacy', function () {
    $.cookie('privacy', 'true', {
        expires: 90,
        path: '/'
    });
    if ($.cookie('privacy') == 'true') {
        window.location.reload();
    }
});

// Select folders, labs or users
$(document).on('click', 'a.folder, a.lab, tr.user', function (e) {
    logger(1, 'DEBUG: selected "' + $(this).attr('data-path') + '".');
    if ($(this).hasClass('selected')) {
        // Already selected -> unselect it
        $(this).removeClass('selected');
    } else {
        // Selected it
        $(this).addClass('selected');
    }
});

// Remove modal on close
$(document).on('hidden.bs.modal', '.modal', function (e) {
    if ( $(".addConn-form").length > 0 || $(".editConn-form").length > 0) {
        $('.action-labtopologyrefresh').click();
    }
    $(this).remove();
    if ($('body').children('.modal.fade.in')) {
        $('body').children('.modal.fade.in').focus();
        $('body').children('.modal.fade.in').css("overflow-y", "auto");
    }
    if ($(this).prop('skipRedraw') && !$(this).attr('skipRedraw')) {
        printLabTopology();
    }
    $(this).attr('skipRedraw', false);
});

// Set autofocus on show modal
$(document).on('shown.bs.modal', '.modal', function () {
    $('.autofocus').focus();
});

// After node/network move
export function ObjectPosUpdate (event ,ui) {
     var groupMove = []
     if ( $('.node_frame.ui-selected, node_frame.ui-selecting, .network_frame.ui-selected,.network_ui-selecting, .customShape.ui-selected, .customShape.ui-selecting').length == 0 ) {
          groupMove.push(event.el)
     } else {
          $('.node_frame.ui-selected, node_frame.ui-selecting, .network_frame.ui-selected,.network_ui-selecting, .customShape.ui-selected, .customShape.ui-selecting').each( function ( id, node ) {
                groupMove.push(node)
          });
     }
     logger(1,'DEBUG: moving objects...0');
     window.dragstop = 0;
     var zoom = $('#zoomslide').slider("value")/100 ;
     if ( groupMove.length > 1 ) window.dragstop = 1
     if (  event.metaKey || ( event.e != undefined && event.e.metaKey )  || event.ctrlKey || (  event.e != undefined && event.e.ctrlKey)  ) return
     logger(1,'DEBUG: moving objects...1');
     window.moveCount += 1
     if ( window.moveCount != groupMove.length ) return
     logger(1,'DEBUG: moving objects...2');
     var tmp_nodes = [],
         tmp_shapes = [],
         tmp_networks = [];
     $.each( groupMove,  function ( id, node ) {
          //eLeft = Math.round(node.offsetLeft + $('#lab-viewport').scrollLeft())
          var eLeft = Math.round($('#'+node.id).position().left / zoom + $('#lab-viewport').scrollLeft());
          //eTop = Math.round(node.offsetTop + $('#lab-viewport').scrollTop())
          var eTop = Math.round($('#'+node.id).position().top / zoom + $('#lab-viewport').scrollTop());
          id = node.id
          $('#'+id).addClass('dragstopped')
          if ( id.search('node') != -1 ) {
               logger(1, 'DEBUG: setting' + id + ' position.');
               tmp_nodes.push( { id : id.replace('node','') , left: eLeft, top: eTop } )
          } else if  ( id.search('network') != -1 )  {
              logger(1, 'DEBUG: setting ' + id + ' position.');
              tmp_networks.push( { id : id.replace('network','') , left: eLeft, top: eTop } )
          } else if ( id.search('custom') != -1 )  {
              logger(1, 'DEBUG: setting ' + id + ' position.');
              var objectData = node.outerHTML;
              objectData = fromByteArray(new TextEncoderLite('utf-8').encode(objectData));
              tmp_shapes.push( { id : id.replace(/customShape/,'').replace(/customText/,'') , data: objectData } )
          }
     });
     // Bulk for nodes
        // lab_topology.repaintEverything();
        // lab_topology.repaintEverything();
     $.when(setNodesPosition(tmp_nodes)).done(function () {
           logger(1, 'DEBUG: all selected node position saved.');
           $.when(editTextObjects(tmp_shapes)).done(function () {
                logger(1, 'DEBUG: all selected shape position saved.');
                /*$.when(setNetworksPosition(tmp_networks)).done(function () {
                     logger(1, 'DEBUG: all selected networks position saved.');
                }).fail(function (message) {
                     addModalError(message);
                });*/
           }).fail(function (message) {
                addModalError(message);
           });
     }).fail(function (message) {
         // Error on save
         addModalError(message);
     });
     window.moveCount = 0
}

// Close all context menu
$(document).on('mousedown', '*', function (e) {
    if (!$(e.target).is('#context-menu, #context-menu *')) {
        // If click outside context menu, remove the menu
        e.stopPropagation();
        $('#context-menu').remove();

    }
});

// Open context menu block
$(document).on('click', '.menu-collapse, .menu-collapse i', function (e) {
    e.preventDefault();  // Prevent default behaviour
    var item_class = $(this).attr('data-path');
    $('.' + item_class).slideToggle('fast');
});

// Open context menu block
$(document).on('click', '.menu-appear, .menu-appear i', function (e) {
    e.preventDefault();  // Prevent default behaviour
    var windowWidth = $(window).width();
    var windowHeight = $(window).height();
    var contextMenuClickX = $("#lab-viewport").data('contextMenuClickXY').x
    var contextMenuClickY = $("#lab-viewport").data('contextMenuClickXY').y
    if(windowWidth - 320 <= contextMenuClickX){
        $('#capture-menu').css('left', -150)
    } else {
        $('#capture-menu').css('right', -150)
    }
    $('#capture-menu li a').toggle('fast')
    $('#capture-menu').toggle({
        duration: 10,
        progress: function(){
                // console.log('arguments',arguments)
                // console.log("height, fix", $('#capture-menu').height(), windowHeight - contextMenuClickY - 145)
                if(contextMenuClickY > windowHeight - 300){
                    if($('#capture-menu').height() > contextMenuClickY + 145){
                        $('#capture-menu').css({
                            'height': contextMenuClickY - 145,
                            'overflow': 'hidden',
                            'overflow-y': 'scroll'
                        })
                    }
                    $('#capture-menu').css('bottom', '114px')
                } else {
                    if($('#capture-menu').height() > (windowHeight - contextMenuClickY - 145)){
                        $('#capture-menu').css({
                                'height': windowHeight - contextMenuClickY - 145,
                                'top': '136px',
                                'overflow': 'hidden',
                                'overflow-y': 'scroll'
                            })
                    }
                }
        },
        complete: function(){

            if(!contextMenuClickY > windowHeight - 300 && $('#capture-menu').height() > (windowHeight - contextMenuClickY - 145)){
                $('#capture-menu').css({
                            'height': windowHeight - contextMenuClickY - 145,
                            'top': '136px',
                            'overflow': 'hidden',
                            'overflow-y': 'scroll'
                        })
                console.log('hei2', windowHeight - contextMenuClickY - 145)
            }

        }
    })
    if($('.menu-appear > i').hasClass('glyphicon-chevron-left')){
        $('.menu-appear > i').addClass('glyphicon-chevron-right').removeClass('glyphicon-chevron-left')
    } else {
        $('.menu-appear > i').addClass('glyphicon-chevron-left').removeClass('glyphicon-chevron-right')
    }
});

$(document).on('contextmenu', '#lab-viewport', function (e) {
    // Prevent default context menu on viewport
    e.stopPropagation();
    e.preventDefault();

    $("#lab-viewport").data('contextClickXY', {'x': e.pageX, 'y': e.pageY})

    logger(1, 'DEBUG: action = opencontextmenu');

    if ($(this).hasClass("freeSelectMode")) {
        // prevent 'contextmenu' on non Free Selected Elements

        return;
    }

    if ($(this).data("prevent-contextmenu")) {
        // prevent code execution

        return;
    }

    if ( window.connContext == 1 ) {
           window.connContext = 0
           if (((ROLE == 'ROLE_TEACHER' && AUTHOR == 0) || (ROLE == 'ROLE_USER')) || LOCK == 1 || EDITION == 0) return;
           //if (ROLE == "ROLE_USER" || LOCK == 1 ) return;
           body = '';
           body += '<li><a class="action-connedit" href="javascript:void(0)"><i class="glyphicon glyphicon-edit"></i> Edit</a></li>';
           body += '<li><a class="action-conndelete" href="javascript:void(0)"><i class="glyphicon glyphicon-trash"></i> Delete</a></li>';
           printContextMenu('Connection', body, e.pageX, e.pageY,false,"menu");
           return;
    }

    if (((ROLE == 'ROLE_TEACHER' && AUTHOR == 1) || (ROLE != 'ROLE_USER' && ROLE !='ROLE_TEACHER')) && EDITION ==1 && LOCK == 0 ) {
    //if (ROLE != "ROLE_USER" && LOCK == 0 ) {
        var body = '';
        body += '<li><a class="action-nodeplace" href="javascript:void(0)"><i class="glyphicon glyphicon-hdd"></i> ' + MESSAGES[81] + '</a></li>';
        body += '<li><a class="action-networkplace" href="javascript:void(0)"><i class="glyphicon glyphicon-transfer"></i> ' + MESSAGES[82] + '</a></li>';
        body += '<li><a class="action-pictureadd" href="javascript:void(0)"><i class="glyphicon glyphicon-picture"></i> ' + MESSAGES[83] + '</a></li>';
        body += '<li><a class="action-customshapeadd" href="javascript:void(0)"><i class="glyphicon glyphicon-unchecked"></i> ' + MESSAGES[145] + '</a></li>';
        body += '<li><a class="action-textadd" href="javascript:void(0)"><i class="glyphicon glyphicon-font"></i> ' + MESSAGES[146] + '</a></li>';
        body += '<li role="separator" class="divider">';
        body += '<li><a class="action-autoalign" href="javascript:void(0)"><i class="glyphicon glyphicon-th"></i> ' + MESSAGES[207] + '</a></li>';
        printContextMenu(MESSAGES[80], body, e.pageX, e.pageY,false,"menu");
    }
});

// Manage context menu
$(document).on('contextmenu', '.context-menu', function (e) {

    e.stopPropagation();
    e.preventDefault();  // Prevent default behaviour
    var body = '' ;
    if ($("#lab-viewport").data("prevent-contextmenu")) {
        // prevent code execution

        return;
    }
    var isFreeSelectMode = $("#lab-viewport").hasClass("freeSelectMode");

    //if (isFreeSelectMode && !$(this).is(".network_frame.free-selected, .node_frame.free-selected")) {
    if (isFreeSelectMode && !$(this).is(".network_frame.free-selected, .node_frame.free-selected, .customShape.free-selected")) {
        // prevent 'contextmenu' on non Free Selected Elements
        return;
    }
    logger(1,"context menu called");
    $("#lab-viewport").data('contextMenuClickXY', {'x': e.pageX, 'y': e.pageY})

    var isNodeRunning = $(this).attr('data-status') > 1;
    var status = $(this).attr('data-status')
    var content = '';


    if ($(this).hasClass('node_frame')) {
        logger(1, 'DEBUG: opening node context menu');

    var node_id = $(this).attr('data-path');
        if(parseInt($('#node'+node_id).attr('data-status')) != 2){
            if ($(this).attr('data-type') != "switch") {
                content += '<li><a class="action-nodestart  menu-manage" data-path="' + node_id + '" data-name="' + title + '" href="javascript:void(0)">' +
                '<i class="glyphicon glyphicon-play"></i> ' + MESSAGES[66] +
                '</a>' +
                '</li>';
            }
            
        }

            var title = $(this).attr('data-name') + " (" + node_id + ")";
            if(EDITION == 0) {
                if ($(this).attr('data-type') != "switch") {
                body +=
                    content+
                    '<li>' +
                            '<a class="action-nodestop  menu-manage" data-path="' + node_id + '" data-name="' + title + '" href="javascript:void(0)">' +
                    '<i class="glyphicon glyphicon-stop"></i> ' + MESSAGES[67] +
                    '</a>' +
                    '</li>';
                    /*if((ROLE != 'ROLE_USER') && LOCK == 0) {
                    body += '<li>' +
                            '<a class="action-nodewipe menu-manage" data-path="' + node_id + '" data-name="' + title + '" href="javascript:void(0)">' +
                    '<i class="glyphicon glyphicon-erase"></i> ' + MESSAGES[68] +
                    '</a>' +
                    '</li>';
                    }*/
                    body += '</li>';
                }
            
            }
        //if (((ROLE == 'ROLE_TEACHER' && AUTHOR == 1) || (ROLE != 'ROLE_USER' && ROLE !='ROLE_TEACHER')) && EDITION ==1 && LOCK == 0 ) {
        //if ((ROLE != 'ROLE_USER') &&  LOCK == 0  ) {
                 /*body +=   '<li>' +
                           '<a class="action-nodeexport" data-path="' + node_id + '" data-name="' + title + '" href="javascript:void(0)">' +
                           '<i class="glyphicon glyphicon-save"></i> ' + MESSAGES[69] +
                           '</a>' +
                           '</li>';*/
        //}
                // capture section
                /*body += '<li role="separator" class="divider">' +
                '</li>' +
                '<li id="menu-node-interfaces">' +
                    '<a class="menu-appear" data-path="menu-interface" href="javascript:void(0)">' +
                        '<i class="glyphicon glyphicon-chevron-right"></i> ' + MESSAGES[70] +
                    '</a>' +
                    '<div id="capture-menu">' +
                        '<ul></ul>' +
                    '</div>'+
                '</li>';*/
                // Read privileges and set specific actions/elements
                if (((ROLE == 'ROLE_TEACHER' && AUTHOR == 1) || (ROLE != 'ROLE_USER' && ROLE !='ROLE_TEACHER')) && EDITION ==1 && LOCK == 0 ) {
                //if ((ROLE != 'ROLE_USER') &&  LOCK == 0  ) {

                    //body += '<li role="separator" class="divider">';
                    /* +
                        '<li>' +
                            '<a class="action-nodeinterfaces" data-path="' + node_id + '" data-name="' + title + '"  data-status="'+ status +'" href="javascript:void(0)">' +
                        '<i class="glyphicon glyphicon-transfer"></i> ' + MESSAGES[72] +
                        '</a>' +
                        '</li>';*/
                        if(!isNodeRunning){
                            body += '<li>' +
                            '<a class="action-nodeedit control" data-path="' + node_id + '" data-name="' + title + '" href="javascript:void(0)">' +
                            '<i class="glyphicon glyphicon-edit"></i> ' + MESSAGES[71] +
                            '</a>' +
                            '</li>' +
                            '<li>' +
                                '<a class="action-nodedelete" data-path="' + node_id + '" data-name="' + title + '" href="javascript:void(0)">' +
                            '<i class="glyphicon glyphicon-trash"></i> ' + MESSAGES[65] +
                            '</a>' +
                            '</li>';
                        }
                };



        // Adding interfaces
        $.when(getNodeInterfaces(node_id)).done(function (values) {
            var interfaces = '';
            var eth_sortable = []
            for(var eth in values['ethernet']){
                values['ethernet'][eth]['id'] = eth
                eth_sortable.push(values['ethernet'][eth])
            }
        eth_sortable.sort(function(as, bs){
            var a, b, a1, b1, i= 0, L, rx=  /(\d+)|(\D+)/g, rd=  /\d/;
           if(isFinite(as.name) && isFinite(bs.name)) return as - bs;
            a= String(as.name).toLowerCase();
            b= String(bs.name).toLowerCase();
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
        })
	$.each(eth_sortable, function (id, object) {
                interfaces += '<li><a class="action-nodecapture context-collapsible menu-interface" href="capture://' + window.location.hostname + '/vunl' + TENANT + '_' + node_id + '_' + object.id + '" style="display: none;"><i class="glyphicon glyphicon-search"></i> ' + object['name'] + '</a></li>';
            });

            $(interfaces).appendTo('#capture-menu ul');

        }).fail(function (message) {
            // Error on getting node interfaces
            addModalError(message);
        });


        if (isFreeSelectMode) {
            window.contextclick = 1
            body = '' ;
            if (EDITION == 0) {
                body += '<li>' +
                    '<a class="action-nodestart-group context-collapsible menu-manage" href="javascript:void(0)"><i class="glyphicon glyphicon-play"></i> ' + MESSAGES[153] + '</a>' +
                '</li>' +
                '<li>' +
                    '<a class="action-nodestop-group context-collapsible menu-manage" href="javascript:void(0)"><i class="glyphicon glyphicon-stop"></i> ' + MESSAGES[154] + '</a>' +
                '</li>';
            }
            /*body += '<li>' +
                    '<a class="action-nodewipe-group context-collapsible menu-manage" href="javascript:void(0)"><i class="glyphicon glyphicon-erase"></i> ' + MESSAGES[155] + '</a>' +
                '</li>' +
                '<li>' +
                        '<a class="action-openconsole-group context-collapsible menu-manage" href="javascript:void(0)"><i class="glyphicon glyphicon-console"></i> ' + MESSAGES[169] + '</a>' +
                '</li>';*/
            if (((ROLE == 'ROLE_TEACHER' && AUTHOR == 1) || (ROLE != 'ROLE_USER' && ROLE !='ROLE_TEACHER')) && EDITION ==1 && LOCK == 0 ) {
            //if ((ROLE != 'ROLE_USER') && LOCK == 0 ) {
                /*body += '' +
                    '<li role="separator" class="divider"></li>' +
                    '<li>' +
                        '<a class="action-nodeexport-group context-collapsible menu-manage" href="javascript:void(0)"><i class="glyphicon glyphicon-save"></i> ' + MESSAGES[129] + '</a>' +
                    '</li>' +
                    '<li>' +
                        '<a class="action-nodesbootsaved-group" href="javascript:void(0)"><i class="glyphicon glyphicon-floppy-saved"></i> ' + MESSAGES[139] + '</a>' +
                    '</li>' +
                    '<li>' +
                        '<a class="action-nodesbootscratch-group" href="javascript:void(0)"><i class="glyphicon glyphicon-floppy-save"></i> ' + MESSAGES[140] + '</a>' +
                    '</li>';*/
                //body += '<li role="separator" class="divider">' +
                body += '<li>' +
                        '<a class="action-halign-group" data-path="node' + node_id + '" data-name="' + title + '" href="javascript:void(0)">' +
                        '<i class="glyphicon glyphicon-object-align-horizontal"></i> ' + MESSAGES[204] +
                        '</a>' +
                        '</li>' +
                        '<li>' +
                        '<a class="action-valign-group" data-path="node' + node_id + '" data-name="' + title + '" href="javascript:void(0)">' +
                        '<i class="glyphicon glyphicon-object-align-vertical"></i> ' + MESSAGES[205] +
                        '</a>' +
                        '</li>' +
                        '<li>' +
                        '<a class="action-calign-group" data-path="node' + node_id + '" data-name="' + title + '" href="javascript:void(0)">' +
                        '<i class="glyphicon glyphicon-record"></i> ' + MESSAGES[206] +
                        '</a>' +
                        '</li>' ;
            body += '' +
                '<li role="separator" class="divider"></li>' +
                /*'<li>' +
                    '<a class="action-nodesbootdelete-group" href="javascript:void(0)"><i class="glyphicon glyphicon-trash"></i> ' + MESSAGES[159] + '</a>' +
                '</li>' +*/
                '<li>' +
                    '<a class="action-nodedelete-group context-collapsible menu-manage" href="javascript:void(0)"><i class="glyphicon glyphicon-trash"></i> ' + MESSAGES[157] + '</a>' +
                '</li>' +
                '';
            }
            title = 'Group of ' + window.freeSelectedNodes.map(function (node) {
                   if ( node.type == 'node' ) return node.name;
                }).join(", ").replace(', ,',', ').replace(/^,/,'').slice(0, 16);
            title += title.length > 24 ? "..." : "";

        }

    } else if ($(this).hasClass('network_frame')) {
        if (((ROLE == 'ROLE_TEACHER' && AUTHOR == 1) || (ROLE != 'ROLE_USER' && ROLE !='ROLE_TEACHER')) && EDITION ==1 && LOCK == 0 ) {
        //if ((ROLE != 'ROLE_USER') && LOCK == 0 ) {


            logger(1, 'DEBUG: opening network context menu');
            var network_id = $(this).attr('data-path');
            var title = $(this).attr('data-name');
            var body = '<li><a class="context-collapsible  action-networkedit" data-path="' + network_id + '" data-name="' + title + '" href="javascript:void(0)"><i class="glyphicon glyphicon-edit"></i> ' + MESSAGES[71] + '</a></li><li><a class="context-collapsible  action-networkdelete" data-path="' + network_id + '" data-name="' + title + '" href="javascript:void(0)"><i class="glyphicon glyphicon-trash"></i> ' + MESSAGES[65] + '</a></li>';
        }
	    if (isFreeSelectMode) {
            window.contextclick = 1
                body += '<li role="separator" class="divider">' +
                        '<li>' +
                        '<a class="action-halign-group" data-path=network"' + node_id + '" data-name="' + title + '" href="javascript:void(0)">' +
                        '<i class="glyphicon glyphicon-object-align-horizontal"></i> ' + MESSAGES[204] +
                        '</a>' +
                        '</li>' +
                        '<li>' +
                        '<a class="action-valign-group" data-path="network' + node_id + '" data-name="' + title + '" href="javascript:void(0)">' +
                        '<i class="glyphicon glyphicon-object-align-vertical"></i> ' + MESSAGES[205] +
                        '</a>' +
                        '</li>' +
                        '<li>' +
                        '<a class="action-calign-group" data-path="network' + network_id + '" data-name="' + title + '" href="javascript:void(0)">' +
                        '<i class="glyphicon glyphicon-record"></i> ' + MESSAGES[206] +
                        '</a>' +
                        '</li>' ;
	  }
    } else if ($(this).hasClass('customShape')) {
        if (((ROLE == 'ROLE_TEACHER' && AUTHOR == 1) || (ROLE != 'ROLE_USER' && ROLE !='ROLE_TEACHER')) && EDITION ==1 && LOCK == 0 ) {
        //if ((ROLE != 'ROLE_USER') && LOCK == 0 ) {
            logger(1, 'DEBUG: opening text object context menu');
            var textObject_id = $(this).attr('data-path')
            var elId =  $(this).attr('id');
            var title = 'Edit: ' + $(this).attr('data-path')
            var textClass = $(this).hasClass('customText') ? ' customText ': ''
                var body =
                '<li>' +
                      '<a class="context-collapsible  action-textobjectduplicate" href="javascript:void(0)" data-path="' + textObject_id + '">' +
                '<i class="glyphicon glyphicon-duplicate"></i> ' + MESSAGES[149] +
                '</a>' +
                '</li>' +
                '<li>' +
                      '<a class="context-collapsible  action-textobjecttoback" href="javascript:void(0)" data-path="' + textObject_id + '">' +
                '<i class="glyphicon glyphicon-save"></i> ' + MESSAGES[147] +
                '</a>' +
                '</li>' +
                '<li>' +
                      '<a class="context-collapsible  action-textobjecttofront" href="javascript:void(0)" data-path="' + textObject_id + '">' +
                '<i class="glyphicon glyphicon-open"></i> ' + MESSAGES[148] +
                '</a>' +
                '</li>' +
                '<li>' +
                      '<a class="context-collapsible action-textobjectedit" href="javascript:void(0)" data-path="' + textObject_id + '">' +
                '<i class="glyphicon glyphicon-edit"></i> ' + MESSAGES[71] +
                '</a>' +
                '</li>' +
                '<li>' +
                      '<a class="context-collapsible '+ textClass +' action-textobjectdelete" href="javascript:void(0)" data-path="' + textObject_id + '">' +
                '<i class="glyphicon glyphicon-trash"></i> ' + MESSAGES[65] +
                '</a>' +
                '</li>';
            if (isFreeSelectMode) {
             window.contextclick = 1
             var   body = '<li role="separator" class="divider">' +
                        '<li>' +
                        '<a class="action-halign-group" data-path="' + elId + '" data-name="' + title + '" href="javascript:void(0)">' +
                        '<i class="glyphicon glyphicon-object-align-horizontal"></i> ' + MESSAGES[204] +
                        '</a>' +
                        '</li>' +
                        '<li>' +
                        '<a class="action-valign-group" data-path="' + elId + '" data-name="' + title + '" href="javascript:void(0)">' +
                        '<i class="glyphicon glyphicon-object-align-vertical"></i> ' + MESSAGES[205] +
                        '</a>' +
                        '</li>' +
                        '<li>' +
                        '<a class="action-calign-group" data-path="' + elId + '" data-name="' + title + '" href="javascript:void(0)">' +
                        '<i class="glyphicon glyphicon-record"></i> ' + MESSAGES[206] +
                        '</a>' +
                        '</li>' ;
         }

        }
    } else {
        // Context menu not defined for this object
        return false;
    }
    if (body.length) {

        printContextMenu(title, body, e.pageX, e.pageY,false,"menu");

    }

});

// remove context menu after click on capture interface
$(document).on('click', '.action-nodecapture', function(){
    $("#context-menu").remove();
})

// Window resize
$(window).resize(function () {
    if ($('#lab-viewport').length) {
        // Update topology on window resize
        lab_topology.repaintEverything();
        // Update picture map on window resize
        jQuery(function($) {
            $('map').imageMapResize();
        });
    }
});

// disable submit button if count addition nodes more than 50
$(document).on('change input', 'input[name="node[count]"]', function(e){
    var count = $(this).val()
    console.log('val', count)
    if( count > 50){
        $("#form-node-add button[type='submit']").attr('disabled', true)
    } else {
        $("#form-node-add button[type='submit']").attr('disabled', false)
    }
})

// plug show/hide event

$(document).on('mouseover','.node_frame, .network_frame', function (e) {
    if (((ROLE == 'ROLE_TEACHER' && AUTHOR == 1) || (ROLE != 'ROLE_USER' && ROLE !='ROLE_TEACHER')) && EDITION ==1 && LOCK == 0  && ( $(this).attr('data-status') == 0 || $(this).attr('data-status') == undefined ) && !$('#lab-viewport').hasClass('freeSelectMode') ) {
    //if ((ROLE != 'ROLE_USER') && LOCK == 0 && ( $(this).attr('data-status') == 0 || $(this).attr('data-status') == undefined ) && !$('#lab-viewport').hasClass('freeSelectMode') ) {
         $(this).find('.tag').removeClass("hidden");
        }
}) ;

$(document).on('mouseover','.ep' , function (e) {
    //lab_topology.setDraggable ( this , false )
});

$(document).on('mouseleave','.node_frame, .network_frame', function (e) {
        $(this).find('.tag').addClass("hidden");
        //lab_topology.setDraggable ( this , true )
});
/***************************************************************************
 * Actions links
 **************************************************************************/

// startup-config menu
$(document).on('click', '.action-configsget', function (e) {
    logger(1, 'DEBUG: action = configsget');
    $.when(getNodeConfigs(null)).done(function (configs) {
        var configTable= [];
        for (var i  in configs) {
            configs[i] = { ...configs[i], key: i };
            console.log(i);
            configTable.push(configs[i]);
        }
        printConfigEjs(configTable);
    }).fail(function (message) {
        addModalError(message);
    });
});


function printConfigEjs(configs) {
    var html = new EJS({ url: '/build/editor/ejs/action_configsget.ejs' }).render({ configs: configs, MESSAGES: MESSAGES })
    addModalWide(MESSAGES[120], html, '');
}
// Change opacity
$(document).on('click', '.action-changeopacity', function (e) {
    if ($(this).data("transparent")) {
        $('.modal-content').fadeTo("fast", 1);
        $(this).data("transparent", false);
    } else {
        $('.modal-content').fadeTo("fast", 0.3);
        $(this).data("transparent", true);
    }
});

// Get startup-config
$(document).on('click', '.action-configget', function (e) {
    logger(1, 'DEBUG: action = configget');
    var el = $(document).find('.action-configget').filter('.selected');
    if( LOCK ==0 && el.length > 0) {
        saveEditorLab('form-node-config', true); // added one additional paramter to not close the popup
    }
    $(".action-configget").removeClass("selected");
    $(this).addClass("selected");
    var id = $(this).attr('data-path');
    $.when(getNodeConfigs(id)).done(function (config) {
        printFormNodeConfigs(config);
        $('#config-data').find('.form-control').focusout(function () {
            saveLab();
        })
    }).fail(function (message) {
        addModalError(message);
    });
    $('#context-menu').remove();
});

// Add a new folder
$(document).on('click', '.action-folderadd', function (e) {
    logger(1, 'DEBUG: action = folderadd');
    var data = {};
    data['path'] = $('#list-folders').attr('data-path');
    printFormFolder('add', data);
});

// Open an existent folder
$(document).on('dblclick', '.action-folderopen', function (e) {
    logger(1, 'DEBUG: opening folder "' + $(this).attr('data-path') + '".');
    printPageLabList($(this).attr('data-path'));
});

// Rename an existent folder
$(document).on('click', '.action-folderrename', function (e) {
    logger(1, 'DEBUG: action = folderrename');
    var data = {};
    data['path'] = dirname($('#list-folders').attr('data-path'));
    data['name'] = basename($('#list-folders').attr('data-path'));
    printFormFolder('rename', data);
});

// Import labs
$(document).on('click', '.action-import', function (e) {
    logger(1, 'DEBUG: action = import');
    printFormImport($('#list-folders').attr('data-path'));
});

// Add a new lab
$(document).on('click', '.action-labadd', function (e) {
    logger(1, 'DEBUG: action = labadd');
    var values = {};
    values['path'] = $('#list-folders').attr('data-path');
    printFormLab('add', values);
});

// Print lab body
$(document).on('click', '.action-labbodyget', function (e) {
    logger(1, 'DEBUG: action = labbodyget');
    $.when(getLabInfo($('#lab-viewport').attr('data-path')), getLabBody()).done(function (info, body) {
        var labId = $('#lab-viewport').attr('data-path');
        var html =  '<div class="row"><div class="col-md-10"><h1>' + info['name'] + '</h1> </br><center><p><code>ID: ' + info['id'] + '</code></p>';
        
        if(info['description'] != null) {
            html +='<p>' + info['description'] + '</p>';
        }
        html += '</center></div>';
        html += '<div class="col-sm-2"><img src="/labs/'+labId+'/banner" alt="banner" class="img-thumbnail" height="50M" /></div></div>';
        addModalWide(MESSAGES[64],html, '')
    }).fail(function (message1, message2) {
        if (message1 != null) {
            addModalError(message1);
        } else {
            addModalError(message2)
        }
        ;
    });
});

// Print lab body
$(document).on('click', '.action-labsubjectget', function (e) {
    logger(1, 'DEBUG: action = labbodyget');
    $.when(getLabBody()).done(function (body) {
        var html =  '';
        if (body != null) {
            var converter = new Showdown.Converter();
            var htmlBody = converter.makeHtml(body);
            html += htmlBody;
        }
        else {
            html += '<center><p>This lab does not have any subject.</p></center>'
        }
        addModalWide('Practical subject',html, '')
    }).fail(function (message1, message2) {
        if (message1 != null) {
            addModalError(message1);
        } else {
            addModalError(message2)
        }
        ;
    });
});

// Edit/print lab network
$(document).on('click', '.action-networkedit', function (e) {

    $('#context-menu').remove();
    logger(1, 'DEBUG: action = action-networkedit');
    var id = $(this).attr('data-path');
    $.when(getNetworks(id)).done(function (values) {
        values['id'] = id;
        printFormNetwork('edit', values)
        // window.closeModal = true;
    }).fail(function (message) {
        addModalError(message);
    });
});

// Edit/print lab network
$(document).on('click', '.action-networkdeatach', function (e) {

    $('#context-menu').remove();
    logger(1, 'DEBUG: action = action-networkdeatach');
    var node_id = $(this).attr('node-id');
    var interface_id = $(this).attr('interface-id');

    $.when(setNodeInterface(node_id, '', interface_id))
        .done(function (values) {

            window.location.reload();
        }).fail(function (message) {
        addModalError(message);
    });
});

// Print lab networks
$(document).on('click', '.action-networksget', function (e) {
    logger(1, 'DEBUG: action = networksget');
    $.when(getNetworks(null)).done(function (networks) {
        printListNetworks(networks);
    }).fail(function (message) {
        addModalError(message);
    });


});

// Delete lab network
$(document).on('click', '.action-networkdelete', function (e) {
    var id = $(this).attr('data-path');
    var body = '<div class="form-group">' +
                    '<div class="question">Are you sure to delete this network?</div>' +
                '</div>' +
                '<div class="form-group">' +
                    '<div class="col-md-5 col-md-offset-3">' +
                        '<button id="networkdelete" class="btn btn-success"  data-path="'+id+'" data-dismiss="modal">Yes</button>' +
                        '<button type="button" class="btn" data-dismiss="modal">Cancel</button>' +
                    '</div>' +
                '</div>'
    var title = "Warning"
    addModal(title, body, "", "make-red make-small");
})

$(document).on('click', '.action-connedit', function (e) {
    var id = window.connToDel.id.replace('network_id:','')
    console.log(id);
    let lab = $('#lab-viewport').attr('data-path');
    $.when(getTopology(lab)).done( function (topology) {
        let network = topology[id];
        let bezier="";
        let flowchart="";
        let straight="";
        let label = "";
        if (network['connector'] == 'Flowchart') {
            flowchart = "selected";
        }
        else if (network['connector'] == 'Bezier') {
            bezier = "selected";
        }
        else {
            straight = "selected";
        }
        if (network['connector_label'] != null) {
            label = network['connector_label'];
        }
        var body = '<form id="editConn" class="editConn-form">' +
                    '<input type="hidden" name="editConn[srcNodeId]" value="'+network['source'].split('node')[0]+'">' +
                    '<input type="hidden" name="editConn[dstNodeId]" value="'+network['destination'].split('node')[0]+'">' +
                    '<input type="hidden" name="editConn[networkId]" value="'+id+'">' +
                    '<div class="form-group">'+
                    '<label>Connector type</label>' +
                    '<select name="editConn[connector]" class="form-control">' +
                        '<option value="Straight" '+straight+'>Straight</option>' +
                        '<option value="Bezier" '+bezier+'>Bezier</option>' +
                        '<option value="Flowchart" '+flowchart+'>Flowchart</option>' +
                    '</select>'+
                '</div>' +
                '<div class="form-group">'+
                    '<label>Connector label</label>' +
                    '<input type="text" name="editConn[connector_label]" value="'+label+'" class="form-control"/>' +
                '</div>' +
                '<div class="form-group">' +
                    '<div class="col-md-5 col-md-offset-3">' +
                    '<button type="submit" class="btn btn-success editConn-form-save">' + MESSAGES[47] + '</button>' +
                    '<button type="button" class="btn cancelForm" data-dismiss="modal">' + MESSAGES[18] + '</button>' +
                    '</div>' +
                '</div>' +
                '</form>'
        var title = "Warning"
        addModal(title, body, "", "make-red make-small");
     }).fail(function (message) {
        addModalError(message);
     });
});

$(document).on('click', '.action-conndelete', function (e) {
     var id = window.connToDel.id
     window.connContext = 0
     if ( id.search('iface') != -1 ) { // serial or network
        node=id.replace('iface:node','').replace(/:.*/,'')
        iface=id.replace(/.*:/,'')
        $.when(setNodeInterface(node,'', iface)).done( function () {
           $('.action-labtopologyrefresh').click();
        }).fail(function (message) {
           addModalError(message);
        });
     } else { // network P2P
        var network_id = id.replace('network_id:','')
        $.when(removeConnection(network_id)).done(function (values) {
           //window.closeModal = true;
           $('.action-labtopologyrefresh').click();
        }).fail(function (message) {
           addModalError(message);
        });
     }
     $('#context-menu').remove();
});

$(document).on('contextmenu', '.map_mark', function (e) {
     //alert (this.id)
     e.preventDefault();
     e.stopPropagation();
     var body =  ''
     body += '<li><a class="action-mapdelete"  id="'+this.id+'" href="javascript:void(0)"><i class="glyphicon glyphicon-trash"></i> Delete</a></li>';
     printContextMenu('Map', body, e.pageX, e.pageY,true,"menu");
});

$(document).on('click', '.action-mapdelete' , function (e) {
   id=this.id.replace(/,/g,"\\,")
  $('#context-menu').remove();
  $('#'+id).remove();
  var mapoldval = $('form :input[name="picture[map]"]').val()
  var custommapoldval = $('form :input[name="picture[custommap]"]').val()
  var regex = new RegExp(".*"+id+".*>\n")
  var mapnewval = mapoldval.replace(regex,'')
  var custommapnewval = custommapoldval.replace(regex,'')
  $('form :input[name="picture[map]"]').val(mapnewval)
  $('form :input[name="picture[custommap]"]').val(custommapnewval)
});

$(document).on('click', '#networkdelete', function (e) {

    $('#context-menu').remove();

    logger(1, 'DEBUG: action = action-networkdelete');
    var id = $(this).attr('data-path');
    $.when(deleteNetwork(id)).done(function (values) {
        $('.network' + id).remove();
        window.closeModal = true;
    }).fail(function (message) {
        addModalError(message);
    });

    $('#context-menu').remove();

});


/**
 * reload on close
 */
$(document).on('hide.bs.modal', function (e) {

    if (window.closeModal) {
        printLabTopology();
        window.closeModal = false;
    }

});


// Delete lab node

$(document).on('click', '.action-nodedelete, .action-nodedelete-group', function (e) {
    if($(this).hasClass('disabled')) return;
    var id = $(this).attr('data-path')
// <form id="form-picture-delete" data-path="' + picture_id + '" class="form-horizontal form-picture" novalidate="novalidate">

    var textQuestion = ""
    if($(this).hasClass('action-nodedelete')) {
        textQuestion = 'Are you sure to delete this node'
    } else {
        textQuestion = 'Are you sure to delete selected nodes?';
    }

    var body = '<div class="form-group">' +
                    '<div class="question">'+textQuestion+'</div>' +
                    '<div class="col-md-5 col-md-offset-3">' +
                        '<button id="deteleNode" class="btn btn-success" data-path="'+id+'" data-dismiss="modal">Yes</button>' +
                        '<button type="button" class="btn" data-dismiss="modal">Cancel</button>' +
                    '</div>' +
                '</div>'
    var title = "Warning"
    addModal(title, body, "", "make-red make-small");
    $('#context-menu').remove();

    $('#deteleNode').on('click', function(){
        logger(1, 'DEBUG: action = action-nodedelete');
        var node_id = $(this).attr('data-path')
            , isFreeSelectMode = $("#lab-viewport").hasClass("freeSelectMode")
            ;

        if (isFreeSelectMode) {
            window.freeSelectedNodes = window.freeSelectedNodes.sort(function (a, b) {
                return a.path < b.path ? -1 : 1
            });
            recursionNodeDelete(window.freeSelectedNodes);
        }
        else {
            $.when(deleteNode(node_id)).done(function (values) {
                $('.node' + node_id).remove();
                  if($('input[data-path='+node_id+'][name="node[type]"]')){
                      $('input[data-path='+node_id+'][name="node[type]"]').parent().remove()
                  }
            }).fail(function (message) {
                addModalError(message);
            });
        }

    })
});


function recursionNodeDelete(restOfList) {
    var node = restOfList.pop();

    if (!node) {
        return 1;
    }

    console.log("Deleting... ", node.path);
    $.when(deleteNode(node.path)).then(function (values) {
        $('.node' + node.path).remove();
        recursionNodeDelete(restOfList);
    }).fail(function (message) {
        addModalError(message);
        recursionNodeDelete(restOfList);
    });
}

// Edit/print node interfaces
$(document).on('click', '.action-nodeinterfaces', function (e) {
    logger(1, 'DEBUG: action = action-nodeinterfaces');
    var id = $(this).attr('data-path');
    var name = $(this).attr('data-name');
    var status = $(this).attr('data-status');
    $.when(getNodeInterfaces(id)).done(function (values) {
        values['node_id'] = id;
        values['node_name'] = name;
        values['node_status'] = status;
        printFormNodeInterfaces(values)
    }).fail(function (message) {
        addModalError(message);
    });
    $('#context-menu').remove();
});

// Deatach network lab node


$(document).on('click', '.action-nodeedit', function (e) {
    logger(1, 'DEBUG: action = action-nodeedit');
    var disabled  = $(this).hasClass('disabled')
    if(disabled) return;
    var fromNodeList  = $(this).hasClass('control')
    var id = $(this).attr('data-path');
    $.when(getNodes(id)).done(function (values) {
        values['id'] = id;
        printFormNode('edit', values, fromNodeList)
    }).fail(function (message) {
        addModalError(message);
    });
    $('#context-menu').remove();
});


// Print lab nodes
$(document).on('click', '.action-nodesget', function (e) {
    logger(1, 'DEBUG: action = nodesget');
    $("#lab-viewport").append("<div id='progress-loader'><label style='float:left'>Generating node list...</label><div class='loader'></div></div>")
    $.when(getNodes(null)).done(function (nodes) {
        printListNodes(nodes);
    }).fail(function (message) {
        addModalError(message);
    });
});

// Lab close
$(document).on('click', '.action-labclose', function (e) {
    logger(1, 'DEBUG: action = labclose');
    $.when(closeLab()).done(function () {
    newUIreturn();
    }).fail(function (message) {
        addModalError(message);
    });
});

// Edit a lab
$(document).on('click', '.action-labedit', function (e) {
    logger(1, 'DEBUG: action = labedit');
    $.when(getLabInfo($('#lab-viewport').attr('data-path'))).done(function (values) {
        values['path'] = dirname($('#lab-viewport').attr('data-path'));
        printFormLab('edit', values);
    }).fail(function (message) {
        addModalError(message);
    });
    $('#context-menu').remove();
});

// Edit a lab inline
$(document).on('click', '.action-labedit-inline', function (e) {
    logger(1, 'DEBUG: action = labedit');
    $.when(getLabInfo($('.action-labedit-inline').attr('data-path'))).done(function (values) {
        values['path'] = dirname($('.action-labedit-inline').attr('data-path'));
        printFormLab('edit', values);
    }).fail(function (message) {
        addModalError(message);
    });
    $('#context-menu').remove();
});


// Edit practical subject
$(document).on('click', '.action-subjectedit', function (e) {
    logger(1, 'DEBUG: action = labedit');
    $.when(getLabInfo($('#lab-viewport').attr('data-path'))).done(function (values) {
        values['path'] = dirname($('#lab-viewport').attr('data-path'));
        printFormSubjectLab('edit', values);
    }).fail(function (message) {
        addModalError(message);
    });
    $('#context-menu').remove();
});
// List all labs
$(document).on('click', '.action-lablist', function (e) {
    bodyAddClass('folders');
    logger(1, 'DEBUG: action = lablist');

    if ($('#list-folders').length > 0) {
        // Already on lab_list view -> open /
        printPageLabList('/');
    } else {
        printPageLabList(FOLDER);
    }

});

// Open a lab
$(document).on('click', '.action-labopen', function (e) {
    logger(1, 'DEBUG: action = labopen');
    var self = this;
    $.when(getUserInfo()).done(function () {
        postLogin($(self).attr('data-path'));
    }).fail(function () {
        // User is not authenticated, or error on API
        logger(1, 'DEBUG: loading authentication page.');
        printPageAuthentication();
    });
});

// Preview a lab
$(document).on('dblclick', '.action-labpreview', function (e) {
    logger(1, 'DEBUG: opening a preview of lab "' + $(this).attr('data-path') + '".');
    $('.lab-opened').each(function () {
        // Remove all previous selected lab
        $(this).removeClass('lab-opened');
    });
    $(this).addClass('lab-opened');
    printLabPreview($(this).attr('data-path'));
});

// Action menu
$(document).on('click', '.action-moreactions', function (e) {
    logger(1, 'DEBUG: action = moreactions');
    var body = '';
    if (EDITION == 0) {
        body += '<li><a class="action-nodesstart" href="javascript:void(0)"><i class="glyphicon glyphicon-play"></i> ' + MESSAGES[126] + '</a></li>';
        body += '<li><a class="action-nodesstop" href="javascript:void(0)"><i class="glyphicon glyphicon-stop"></i> ' + MESSAGES[127] + '</a></li>';
    }
    /*if((ROLE != 'ROLE_USER') && LOCK == 0) {
        body += '<li><a class="action-nodeswipe" href="javascript:void(0)"><i class="glyphicon glyphicon-erase"></i> ' + MESSAGES[128] + '</a></li>';
    }*/

    //body += '<li><a class="action-openconsole-all" href="javascript:void(0)"><i class="glyphicon glyphicon-console"></i> ' + MESSAGES[168] + '</a></li>';
    if (((ROLE == 'ROLE_TEACHER' && AUTHOR == 1) || (ROLE != 'ROLE_USER' && ROLE !='ROLE_TEACHER')) && EDITION ==1 && LOCK == 0 ) {
    //if ((ROLE != 'ROLE_USER') && LOCK == 0 ) {
        //body += '<li><a class="action-nodesexport" href="javascript:void(0)"><i class="glyphicon glyphicon-save"></i> ' + MESSAGES[129] + '</a></li>';
        body += '<li><a class="action-subjectedit" href="javascript:void(0)"><i class="glyphicon glyphicon-pencil"></i>Edit practical subject</a></li>';
        body += '<li><a class="action-labedit" href="javascript:void(0)"><i class="glyphicon glyphicon-pencil"></i> ' + MESSAGES[87] + '</a></li>';
        //body += '<li><a class="action-nodesbootsaved" href="javascript:void(0)"><i class="glyphicon glyphicon-flash"></i> ' + MESSAGES[139] + '</a></li>';
        //body += '<li><a class="action-nodesbootscratch" href="javascript:void(0)"><i class="glyphicon glyphicon-remove"></i> ' + MESSAGES[140] + '</a></li>';
        //body += '<li><a class="action-nodesbootdelete" href="javascript:void(0)"><i class="glyphicon glyphicon-erase"></i> ' + MESSAGES[141] + '</a></li>';
    }
    printContextMenu(MESSAGES[125], body, e.pageX + 3, e.pageY + 3, true,"sidemenu", true);
});

// Redraw topology
$(document).on('click', '.action-labtopologyrefresh', function (e) {
    logger(1, 'DEBUG: action = labtopologyrefresh');
    detachNodeLink();
    $.when(printLabTopology()).done( function () {
         if ( window.LOCK == 1 ) {
            $('.action-labobjectadd-li').remove();
            lab_topology.setDraggable($('.node_frame, .network_frame, .customShape'), false);
            $('.customShape').resizable('disable');
         }
    });

});

// Logout
/*$(document).on('click', '.action-logout', function (e) {
    logger(1, 'DEBUG: action = logout');
    $.when(logoutUser()).done(function () {
        printPageAuthentication();
    }).fail(function (message) {
        addModalError(message);
    });
});*/


// Lock lab
$(document).on('click', '.action-lock-lab', function (e) {
    logger(1, 'DEBUG: action = lock lab');
    lockLab();

});

// Unlock lab
$(document).on('click', '.action-unlock-lab', function (e) {
    logger(1, 'DEBUG: action = unlock lab');
    unlockLab();
});

// hotkey for lock lab
$(document).on('keyup', null, 'alt+l', function(){
    console.log('lock')
    lockLab();
})

// hotkey for unlock lab
$(document).on('keyup', null, 'alt+u', function(){
    console.log('unlock')
    unlockLab();
})



// Add object in lab_view
$(document).on('click', '.action-labobjectadd', function (e) {
    logger(1, 'DEBUG: action = labobjectadd');
    var body = '';
    body += '<li><a class="action-nodeplace" href="javascript:void(0)"><i class="glyphicon glyphicon-hdd"></i> ' + MESSAGES[81] + '</a></li>';
    //body += '<li><a class="action-networkplace" href="javascript:void(0)"><i class="glyphicon glyphicon-transfer"></i> ' + MESSAGES[82] + '</a></li>';
    body += '<li><a class="action-pictureadd" href="javascript:void(0)"><i class="glyphicon glyphicon-picture"></i> ' + MESSAGES[83] + '</a></li>';
  body += '<li><a class="action-customshapeadd" href="javascript:void(0)"><i class="glyphicon glyphicon-unchecked"></i> ' + MESSAGES[145] + '</a></li>';
  body += '<li><a class="action-textadd" href="javascript:void(0)"><i class="glyphicon glyphicon-font"></i> ' + MESSAGES[146] + '</a></li>';
    printContextMenu(MESSAGES[80], body, e.pageX, e.pageY, true,"sidemenu", true);
});

// Add network
$(document).on('click', '.action-networkadd', function (e) {
    logger(1, 'DEBUG: action = networkadd');
    printFormNetwork('add', null);
});

// Place an object
$(document).on('click', '.action-nodeplace, .action-networkplace, .action-customshapeadd, .action-textadd', function (e) {
    var target = $(this)
        , object
        , frame = ''
        ;

    $('#context-menu').remove();

    if (target.hasClass('action-nodeplace')) {
        object = 'node';
    } else if (target.hasClass('action-networkplace')) {
        object = 'network';
    } else if (target.hasClass('action-customshapeadd')) {
        object = 'shape';
    } else if (target.hasClass('action-textadd')) {
        object = 'text';
    } else {
        return false;
    }


    // On click open the form
    // $('.lab-viewport-click-catcher').off("click").on("click", function (e2) {
        $("#lab-viewport").data("prevent-contextmenu", false);
        // if ($(e.target).is('#lab-viewport, #lab-viewport *')) {
            // Click is within viewport
            // if ($('#mouse_frame').length > 0) {
                // ESC not pressed
                var values = {};
                console.log($("#lab-viewport").data('contextClickXY'));
                if ( $("#lab-viewport").data('contextClickXY') ) {
                        values['left'] = $("#lab-viewport").data('contextClickXY').x - 30;
                        values['top'] = $("#lab-viewport").data('contextClickXY').y;
        } else {
            values['left'] = 0;
                        values['top'] = 0;
                }
                if (object == 'node') {
                    printFormNode('add', values);
                } else if (object == 'network') {
                    printFormNetwork('add', values);
                } else if (object == 'shape') {
                    printFormCustomShape(values);
                } else if (object == 'text') {
                    printFormText(values);
                }
                $('#mouse_frame').remove();
            // }
            $('#mouse_frame').remove();
            $('.lab-viewport-click-catcher').off();
        // } else {
        //     addMessage('warning', MESSAGES[101]);
        //     $('#mouse_frame').remove();
        //     $('.lab-viewport-click-catcher').off();
        // }
    // });
});

$(document).on('click', '.action-halign-group', function (e) {
        $('#context-menu').remove();
        var node_id = $(this).attr('data-path');
        var target = $(this);
        var zoom = $('#zoomslide').slider("value")/100 ;
        var isFreeSelectMode = $("#lab-viewport").hasClass("freeSelectMode")
        var height = Math.round( $('#' + node_id).outerHeight(true) / 2)
        var hpos = Math.round($('#' + node_id).position().top / zoom) + height;
        window.moveCount = 0 ;
        $('.node_frame.ui-selected, node_frame.ui-selecting, .network_frame.ui-selected,.network_ui-selecting,.customShape.ui-selected,.customShape.ui-selecting').each( function ( id, node ) {
                height = Math.round( $('#' + node.id).outerHeight(true) / 2)
                $('#' + node.id).css({top: hpos - height });
                window.lab_topology.revalidate($('#' + node.id));
                logger(1, 'DEBUG: action halign pos = ' + hpos );
                ObjectPosUpdate(e);
        });
});

$(document).on('click', '.action-valign-group', function (e) {
        $('#context-menu').remove();
        var node_id = $(this).attr('data-path');
        var target = $(this);
        var zoom = $('#zoomslide').slider("value")/100 ;
        var isFreeSelectMode = $("#lab-viewport").hasClass("freeSelectMode")
        var width = Math.round( $('#' + node_id).outerWidth(true) /  2)  ;
        var vpos = Math.round($('#' + node_id).position().left / zoom ) + width ;
        window.moveCount = 0 ;
        $('.node_frame.ui-selected, node_frame.ui-selecting, .network_frame.ui-selected,.network_ui-selecting,.customShape.ui-selected,.customShape.ui-selecting').each( function ( id, node ) {
                width =  Math.round( $('#' + node.id).outerWidth(true) /  2)
                //$('#' + node.type + node.path).position().top = vpos ;
                $('#' + node.id).css({left: vpos - width });
                window.lab_topology.revalidate($('#' + node.id));
                logger(1, 'DEBUG: action valign pos = ' + vpos );
                ObjectPosUpdate(e);
        });
});

$(document).on('click', '.action-autoalign-group,.action-autoalign', function (e) {
                $('#context-menu').remove();
        var target = $(this);
        var zoom = $('#zoomslide').slider("value")/100 ;
        var isFreeSelectMode = $("#lab-viewport").hasClass("freeSelectMode")
        vpos = undefined ;
        window.moveCount = 0 ;
        step = 25
        if ( $('.node_frame.ui-selected, node_frame.ui-selecting, .network_frame.ui-selected,.network_ui-selecting, .customShape.ui-selected, .customShape.ui-selecting').length == 0 ) {
                $('.node_frame, .network_frame,.network, .customShape').each( function ( id, node ) {
                        width =  Math.round( $('#' + node.id).outerWidth(true) /  2)
                        height = Math.round( $('#' + node.id).outerHeight(true) / 2)
                        logger ( 1, "node: " + node.id  + "width: " + width + ", height: " + height ) ;
                        vpos = Math.round($('#' + node.id).position().left / zoom) + width;
                        hpos = Math.round($('#' + node.id).position().top  / zoom) + height;
                        modx = ( ( hpos % (step*2) ) < (step) ) ? ( hpos % (step*2) )   :   ( hpos % (step*2) ) - (step*2)
                        mody = ( ( vpos % (step*2) ) < (step) ) ? ( vpos % (step*2) )   :   ( vpos % (step*2) ) - (step*2)
                        x = hpos - modx - height
                        y = vpos - mody - width
                        $('#' + node.id).css({top: x });
                        $('#' + node.id).css({left: y });
                        window.lab_topology.revalidate($('#' + node.id));
                        e.el = node ;
                        ObjectPosUpdate(e);
                });
        }


        //$('.node_frame.ui-selected, node_frame.ui-selecting, .network_frame.ui-selected,.network_ui-selecting, .customShape.ui-selected, .customShape.ui-selecting').each( function ( id, node ) {
        //        if ( vpos == undefined ) {
        //                vpos = Math.round($('#' + node.id).position().left / zoom );
        //        }
                //$('#' + node.type + node.path).position().top = vpos ;
                //$('#' + node.id).css({left: vpos });
                //window.lab_topology.revalidate($('#' + node.id));
                //logger(1, 'DEBUG: action valign pos = ' + vpos );
                //ObjectPosUpdate(e);
        //});
});

$(document).on('click', '.action-calign-group', function (e) {
        $('#context-menu').remove();
        var node_id = $(this).attr('data-path');
        var zoom = $('#zoomslide').slider("value")/100 ;
        var isFreeSelectMode = $("#lab-viewport").hasClass("freeSelectMode")
        var width = Math.round( $('#' + node_id).outerWidth(true) /  2)  ;
        var vpos = Math.round($('#' + node_id).position().left / zoom ) + width ;
        var height = Math.round( $('#' + node_id).outerHeight(true) / 2)
        var hpos = Math.round($('#' + node_id).position().top / zoom) + height;
        window.moveCount = 0 ;
        var step = -1 ;
        //angle= Math.round( 360 / ( $('.node_frame.ui-selected, node_frame.ui-selecting, .network_frame.ui-selected,.network_ui-selecting, .customShape.ui-selected, .customS0hape.ui-selecting').length ));
        var nbo=$('.node_frame.ui-selected, node_frame.ui-selecting, .network_frame.ui-selected,.network_ui-selecting, .customShape.ui-selected, .customS0hape.ui-selecting').length;
        var angle=Math.round(360 / nbo )
        logger(1, 'DEBUG: action angle = ' + angle )
        $('.node_frame.ui-selected, node_frame.ui-selecting, .network_frame.ui-selected,.network_ui-selecting, .customShape.ui-selected, .customShape.ui-selecting').each( function ( id, node ) {
                width = Math.round( $('#' + node.id).outerWidth(true) /  2)  ;
                height = Math.round( $('#' + node.id).outerHeight(true) / 2) ;
                step += 1 ;
                var radius =  angle * step * Math.PI / 180 ;
                var x = hpos + Math.round( Math.sin(radius) * nbo * 20 ) - height
                var y = vpos + Math.round( Math.cos(radius) * nbo * 20 ) - width
                //$('#' + node.type + node.path).position().top = vpos ;
                $('#' + node.id).css({top: x });
                $('#' + node.id).css({left: y });
                window.lab_topology.revalidate($('#' + node.id));
                logger(1, 'DEBUG: action calign  nose ' + node.id  + ' ang = ' + (angle*step) );
                ObjectPosUpdate(e);
        });
});


$(document).on('click', '.action-openconsole-all, .action-openconsole-group', function (e) {
    $('#context-menu').remove();
    var target = $(this);
    var isFreeSelectMode = $("#lab-viewport").hasClass("freeSelectMode")

    if (!isFreeSelectMode) {
        $.when(getNodes(null)).done(function (nodes) {
            $.each(nodes, function (node_id, node) {
        if ( node['status'] > 1 ) {
               if (window.chrome && window.chrome.webstore) {
                    openNodeCons( node['url'] );
               } else {
                    $('#node'+node['id']+' a img').click();
               }
        }
            })
        })
    } else {
        freeSelectedNodes.forEach(function(node){
             $("#lab-viewport").removeClass("freeSelectMode");
             if ($('#node' + node.path).attr('data-status') > 1 ){
                  if (window.chrome && window.chrome.webstore) {
                       openNodeCons( $('#node' + node.path +' a').attr('href') );
                  } else {
                       $('#node' + node.path +' a img').click();
                  }
             }
             $("#lab-viewport").addClass("freeSelectMode");
        })
   }
});


// Add picture
$(document).on('click', '.action-pictureadd', function (e) {
    logger(1, 'DEBUG: action = pictureadd');
    $('#context-menu').remove();
    displayPictureForm();


    $("#form-picture-add").find('input:eq(0)').delay(500).queue(function() {
     $(this).focus();
     $(this).dequeue();
    });
    //printFormPicture('add', null);
});

// Attach files
var attachments;
$('body').on('change', 'input[type=file]', function (e) {
    attachments = e.target.files;
});

// Add picture form
$('body').on('submit', '#form-picture-add', function (e) {
    // lab_file = getCurrentLab//getParameter('filename');
    var lab_file = $('#lab-viewport').attr('data-path');
    var form_data = new FormData();
    var picture_name = $('form :input[name^="picture[name]"]').val();
    // Setting options
    $('form :input[name^="picture["]').each(function (id, object) {
        form_data.append($(this).attr('name').substr(8, $(this).attr('name').length - 9), $(this).val());
    });

    // Add attachments
    $.each(attachments, function (key, value) {
        form_data.append(key, value);
    });

    console.log("form_data: ",form_data)
    // Get action URL
    var url = '/api/labs/' + lab_file + '/pictures';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: 'POST',
        url: encodeURI(url),
        contentType: false, // Set content type to false as jQuery will tell the server its a query string request
        processData: false, // Don't process the files
        dataType: 'json',
        data: form_data,
        success: function (data) {
            if (data['status'] == 'success') {
                addMessage('SUCCESS', 'Picture "' + picture_name + '" added.');
                // Picture added -> reopen this page (not reload, or will be posted twice)
                // window.location.href = '/lab_edit.php' + window.location.search;
                $('.action-picturesget-li').removeClass('hidden')
            } else {
                // Fetching failed
                addMessage('DANGER', data['status']);
            }
        },
        error: function (data) {
            addMessage('DANGER', getJsonMessage(data['responseText']));
        }
    });

    // Hide and delete the modal (or will be posted twice)
    $('body').children('.modal').modal('hide');

    // Stop or form will follow the action link
    return false;
});

// Edit picture
$(document).on('click', '.action-pictureedit', function (e) {
    logger(1, 'DEBUG: action = pictureedit');
    $('#context-menu').remove();
    var picture_id = $(this).attr('data-path');
    $.when(getPictures(picture_id)).done(function (picture) {
        picture['id'] = picture_id;
        printFormPicture('edit', picture);
    }).fail(function (message) {
        addModalError(message);
    });
});

// Get picture
$(document).on('click', '.action-pictureget', function (e) {
    logger(1, 'DEBUG: action = pictureget');
    $(".action-pictureget").removeClass("selected");
    $(this).addClass("selected");
    $('#context-menu').remove();
    var picture_id = $(this).attr('data-path');
    printPictureInForm(picture_id);

});

//Show circle under cursor
$(document).on('mousemove', '.follower-wrapper', function (e) {
    var offset = $('.follower-wrapper img').offset()
        , limitY = $('.follower-wrapper img').height()
        , limitX = $('.follower-wrapper img').width()
        , mouseX = Math.min(e.pageX - offset.left, limitX)
        , mouseY = Math.min(e.pageY - offset.top, limitY);

    if (mouseX < 0) mouseX = 0;
    if (mouseY < 0) mouseY = 0;

    $('#follower').css({left: mouseX, top: mouseY});
    $("#follower").data("data_x", mouseX);
    $("#follower").data("data_y", mouseY);
});

$(document).on('click', '#follower', function (e) {
    e.preventDefault();
    e.folowerPosition = {
        left: parseFloat($("#follower").css("left")) - 30,
        top: parseFloat($("#follower").css("top")) + 30
    };
});

// Get pictures list
$(document).on('click', '.action-picturesget', function (e) {
    logger(1, 'DEBUG: action = picturesget');
    $.when(getPictures()).done(function (pictures) {
        if (!$.isEmptyObject(pictures)) {
            var body = '<div class="col-md-1 col-md-offset-10" id="picslider"></div><div class="col-md-1 col-md-offset-11"></div><div class="row"><div class="picture-list col-md-3 col-lg-2"><ul class="map">';
            $.each(pictures, function (key, picture) {
                var title = picture['name'] || "pic name";
                body += '<li>';
                if (((ROLE == 'ROLE_TEACHER' && AUTHOR == 1) || (ROLE != 'ROLE_USER' && ROLE !='ROLE_TEACHER')) && EDITION ==1 && LOCK != 1 ) {
                //if (ROLE != "ROLE_USER" && LOCK != 1 ) {
                    body += '<a class="delete-picture" style="margin-right: 5px;" href="javascript:void(0)" data-path="' + key + '"><i class="glyphicon glyphicon-trash" title="Delete"></i> ';
                    body += '<a class="action-pictureedit" href="javascript:void(0)" data-path="' + key + '"><i class="glyphicon glyphicon-edit" title="Edit"></i> ';
                }
                body += '<a class="action-pictureget" data-path="' + key + '" href="javascript:void(0)" title="' + title + '">&nbsp;&nbsp;' + picture['name'].split(' ')[0] + '</a>';
                body += '</a></li>';
            });
            body += '</ul></div><div id="config-data" class="col-md-9 col-lg-10"></div></div>';
            addModalWide(MESSAGES[59], body, '', "modal-ultra-wide");
            $('#picslider').slider({value:100,min:10,max:200,step:10,slide:zoompic})
        } else {
            addMessage('info', MESSAGES[134]);
        }
    }).fail(function (message) {
        addModalError(message);
    });
});

// Get picture list old
$(document).on('click', '.action-picturesget-stop', function (e) {
    logger(1, 'DEBUG: action = picturesget');
    $.when(getPictures()).done(function (pictures) {
        if (!$.isEmptyObject(pictures)) {
            var body = '';
            $.each(pictures, function (key, picture) {
                body += '<li><a class="action-pictureget" data-path="' + key + '" href="javascript:void(0)" title="' + picture['name'] + '"><i class="glyphicon glyphicon-picture"></i> ' + picture['name'] + '</a></li>';
            });
            printContextMenu(MESSAGES[59], body, e.pageX, e.pageY,false,"menu");
        } else {
            addMessage('info', MESSAGES[134]);
        }
    }).fail(function (message) {
        addModalError(message);
    });
});

//Detele picture
$(document).on('click', '.delete-picture', function (ev) {
    ev.stopPropagation();  // Prevent default behaviour
    ev.preventDefault();  // Prevent default behaviour
    var id = $(this).attr('data-path');
    console.log('this', $(this))
    var body = '<div class="form-group">' +
                    '<div class="question">Are you sure to delete this picture?</div>' +
                    '<div class="col-md-5 col-md-offset-3">' +
                        '<button id="formPictureDelete" class="btn btn-success"  data-path="'+id+'" data-dismiss="modal">Yes</button>' +
                        '<button type="button" class="btn" data-dismiss="modal">Cancel</button>' +
                    '</div>' +
                '</div>'
    var title = "Warning"
    addModal(title, body, "", "make-red make-small");
    $('#formPictureDelete').on('click', function (e) {
        var lab_filename = $('#lab-viewport').attr('data-path');
        var picture_id = $(this).attr('data-path');
        var picture_name = $('li a[data-path="' + picture_id + '"]').attr("title");
        $.when(deletePicture(lab_filename, picture_id)).done(function () {
            $('.modal.make-red').modal('hide')
            addMessage('SUCCESS', 'Picture "' + picture_name + '" deleted.');
            $('li a[data-path="' + picture_id + '"]').parent().remove();
            $("#config-data").html("");
            $.when(getPictures()).done( function (pic) {
             if ( Object.keys(pic)  < 1 ) {
                 $('.action-picturesget-li').addClass('hidden');
              }
            });
        }).fail(function (message) {
            $('.modal.make-red').modal('hide')
            addModalError(message);
        });
        // Hide and delete the modal (or will be posted twice)
        $('body').children('.modal.second-win').modal('hide');

        // Stop or form will follow the action link
        return false;
    });
    // logger(1, 'DEBUG: action = pictureremove');
    // var $self = $(this);

    // var picture_id = $self.parent().attr('data-path');
    // var lab_filename = $('#lab-viewport').attr('data-path');
    // var body = '<form id="form-picture-delete" data-path="' + picture_id + '" class="form-horizontal form-picture" novalidate="novalidate"><div class="form-group"><div class="col-md-5 col-md-offset-3"><button type="submit" class="btn btn-success">Delete</button><button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button></div></div></form>'
    // var title = "Delete this picture?"
    // addModal(title, body, "", "second-win");
});

// Clone selected labs
$(document).on('click', '.action-selectedclone', function (e) {
    if ($('.selected').length > 0) {
        logger(1, 'DEBUG: action = selectedclone');
        $('.selected').each(function (id, object) {
            form_data = {};
            form_data['name'] = 'Copy of ' + $(this).text().slice(0, -4);
            form_data['source'] = $(this).attr('data-path');
            $.when(cloneLab(form_data)).done(function () {
                // Lab cloned -> reload the folder
                printPageLabList($('#list-folders').attr('data-path'));
            }).fail(function (message) {
                // Error on clone
                addModalError(message);
            });
        });
    }
});

// Delete selected folders and labs
$(document).on('click', '.action-selecteddelete', function (ev) {
    var id = $(this).attr('data-path');
    var self = $(this);
    var body = '<div class="form-group">' +
                    '<div class="question">Are you sure to delete selected nodes?</div>' +
                    '<div class="col-md-5 col-md-offset-3">' +
                        '<button id="selectedDelete" class="btn btn-success"  data-path="'+id+'" data-dismiss="modal">Yes</button>' +
                        '<button type="button" class="btn" data-dismiss="modal">Cancel</button>' +
                    '</div>' +
                '</div>'
    var title = "Warning"
    addModal(title, body, "", "make-red make-small");
    $('#selectedDelete').on('click', function deleteSelected(e) {
        if ($('.selected').length > 0) {
            logger(1, 'DEBUG: action = selecteddelete');

            $('.selected').each(function (id, object) {
                var path = self.attr('data-path');
                if (self.hasClass('folder')) {
                    $.when(deleteFolder(path)).done(function () {
                        // Folder deleted
                        $('.folder[data-path="' + path + '"]').fadeOut(300, function () {
                            self.remove();
                        });
                    }).fail(function (message) {
                        // Cannot delete folder
                        addModalError(message);
                    });
                } else if (self.hasClass('lab')) {
                    $.when(deleteLab(path)).done(function () {
                        // Lab deleted
                        $('.lab[data-path="' + path + '"]').fadeOut(300, function () {
                            self.remove();
                        });
                    }).fail(function (message) {
                        // Cannot delete lab
                        addModalError(message);
                    });
                } else if (self.hasClass('user')) {
                    $.when(deleteUser(path)).done(function () {
                        // User deleted
                        $('.user[data-path="' + path + '"]').fadeOut(300, function () {
                            self.remove();
                        });
                    }).fail(function (message) {
                        // Cannot delete user
                        addModalError(message);
                    });
                } else {
                    // Invalid object
                    logger(1, 'DEBUG: cannot delete, invalid object.');
                    return;
                }
            });
        }
    })
})

// Export selected folders and labs
$(document).on('click', '.action-selectedexport', function (e) {
    if ($('.selected').length > 0) {
        logger(1, 'DEBUG: action = selectedexport');
        var form_data = {};
        var i = 0;
        form_data['path'] = $('#list-folders').attr('data-path')
        $('.selected').each(function (id, object) {
            form_data[i] = $(this).attr('data-path');
            i++;
        });
        $.when(exportObjects(form_data)).done(function (url) {
            // Export done
            window.location = url;
        }).fail(function (message) {
            // Cannot export objects
            addModalError(message);
        });
    }
});

// Delete all startup-config
$(document).on('click', '.action-nodesbootdelete, .action-nodesbootdelete-group', function (ev) {
    $('#context-menu').remove();
    var self = $(this);

    var textQuestion = 'Are you sure to delete all startup cfgs?';
    if(self.hasClass('action-nodesbootdelete-group')){
        textQuestion = 'Are you sure to delete selected startup cfgs?';
    }
    var body = '<div class="form-group">' +
                    '<div class="question">' + textQuestion + '</div>' +
                    '<div class="col-md-5 col-md-offset-3">' +
                        '<button id="nodesbootdelete" class="btn btn-success"  data-dismiss="modal">Yes</button>' +
                        '<button type="button" class="btn" data-dismiss="modal">Cancel</button>' +
                    '</div>' +
                '</div>'
    var title = "Warning"
    addModal(title, body, "", "make-red make-small");
    $('#nodesbootdelete').on('click', function (e) {
        var isFreeSelectMode = $("#lab-viewport").hasClass("freeSelectMode")
            ;
        if (isFreeSelectMode) {
            var nodeLenght = window.freeSelectedNodes.length;
            var lab_filename = $('#lab-viewport').attr('data-path');
            $.each(window.freeSelectedNodes, function (i, node) {
                var form_data = {};
                form_data['id'] = node.path;
                form_data['data'] = '';
                var url = '/api/labs/' + lab_filename + '/configs/' + node.path;
                var type = 'PUT';
                $.when($.ajax({
                    cache: false,
                    timeout: TIMEOUT,
                    type: type,
                    url: encodeURI(url),
                    dataType: 'json',
                    data: JSON.stringify(form_data)
                })).done(function (message) {
                    // Config deleted
                    nodeLenght--;
                    if (nodeLenght < 1) {
                        addMessage('success', MESSAGES[160])
                    }
                    ;
                }).fail(function (message) {
                    // Cannot delete config
                    nodeLenght--;
                    if (nodeLenght < 1) {
                        addMessage('danger', node.name + ': ' + message);
                    }
                    ;
                });
            });
        } else {
            $.when(getNodes(null)).done(function (nodes) {
                var nodeLenght = Object.keys(nodes).length;
                $.each(nodes, function (key, values) {
                    var lab_filename = $('#lab-viewport').attr('data-path');
                    var form_data = {};
                    form_data['id'] = key;
                    form_data['data'] = '';
                    var url = '/api/labs/' + lab_filename + '/configs/' + key;
                    var type = 'PUT';
                    $.when($.ajax({
                        cache: false,
                        timeout: TIMEOUT,
                        type: type,
                        url: encodeURI(url),
                        dataType: 'json',
                        data: JSON.stringify(form_data)
                    })).done(function (message) {
                        // Config deleted
                        nodeLenght--;
                        if (nodeLenght < 1) {
                            addMessage('success', MESSAGES[142])
                        }
                        ;
                    }).fail(function (message) {
                        // Cannot delete config
                        nodeLenght--;
                        if (nodeLenght < 1) {
                            addMessage('danger', values['name'] + ': ' + message);
                        }
                        ;
                    });
                });
            }).fail(function (message) {
                addModalError(message);
            });
        }
    });
})

// Configure nodes to boot from scratch
$(document).on('click', '.action-nodesbootscratch, .action-nodesbootscratch-group', function (e) {
    $('#context-menu').remove();

    var isFreeSelectMode = $("#lab-viewport").hasClass("freeSelectMode")
        ;

    if (isFreeSelectMode) {
        $.each(window.freeSelectedNodes, function (i, node) {
            $.when(setNodeBoot(node.path, 0)).done(function () {
                addMessage('success', node.name + ': ' + MESSAGES[144]);
            }).fail(function (message) {
                // Cannot configure
                addMessage('danger', node.name + ': ' + message);
            });
        });
    }
    else {
        $.when(getNodes(null)).done(function (nodes) {
            $.each(nodes, function (key, values) {
                $.when(setNodeBoot(key, 0)).done(function () {
                    // Node configured -> print a small green message
                    addMessage('success', values['name'] + ': ' + MESSAGES[144])
                }).fail(function (message) {
                    // Cannot start
                    addMessage('danger', values['name'] + ': ' + message);
                });
            });
        }).fail(function (message) {
            addModalError(message);
        });
    }
});

// Configure nodes to boot from startup-config
$(document).on('click', '.action-nodesbootsaved, .action-nodesbootsaved-group', function (e) {
    $('#context-menu').remove();

    var isFreeSelectMode = $("#lab-viewport").hasClass("freeSelectMode")
        ;

    if (isFreeSelectMode) {
        $.each(window.freeSelectedNodes, function (i, node) {
            $.when(setNodeBoot(node.path, 1)).done(function () {
                addMessage('success', node.name + ': ' + MESSAGES[143]);
            }).fail(function (message) {
                // Cannot configure
                addMessage('danger', node.name + ': ' + message);
            });
        });
    }
    else {
        $.when(getNodes(null)).done(function (nodes) {
            $.each(nodes, function (key, values) {
                $.when(setNodeBoot(key, 1)).done(function () {
                    // Node configured -> print a small green message
                    addMessage('success', values['name'] + ': ' + MESSAGES[143])
                }).fail(function (message) {
                    // Cannot configure
                    addMessage('danger', values['name'] + ': ' + message);
                });
            });
        }).fail(function (message) {
            addModalError(message);
        });
    }
});

// Export a config
$(document).on('click', '.action-nodeexport, .action-nodesexport, .action-nodeexport-group', function (e) {
    $('#context-menu').remove();

    var node_id
        , isFreeSelectMode = $("#lab-viewport").hasClass("freeSelectMode")
        , exportAll = false
        , nodesLength
        ;

    if ($(this).hasClass('action-nodeexport')) {
        logger(1, 'DEBUG: action = nodeexport');
        node_id = $(this).attr('data-path');
    } else {
        logger(1, 'DEBUG: action = nodesexport');
        exportAll = true;
    }

    $.when(getNodes(null)).done(function (nodes) {
        if (isFreeSelectMode) {
            var nodesLenght = window.freeSelectedNodes.length;
            addMessage('info', 'Export Selected:  Starting');
            $.when(recursive_cfg_export(window.freeSelectedNodes, nodesLenght)).done(function () {
            }).fail(function (message) {
                addMessage('danger', 'Export Selected: Error');
            });
        }
        else if (node_id) {
            addMessage('info', nodes[node_id]['name'] + ': ' + MESSAGES[138]);
            $.when(cfg_export(node_id)).done(function () {
                // Node exported -> print a small green message
                setNodeBoot(node_id, '1');
                addMessage('success', nodes[node_id]['name'] + ': ' + MESSAGES[79])
            }).fail(function (message) {
                // Cannot export
                addMessage('danger', nodes[node_id]['name'] + ': ' + message);
            });
        } else if (exportAll) {
            /*
             * Parallel call for each node
             */
            var nodesLenght = Object.keys(nodes).length;
            addMessage('info', 'Export all:  Starting');
            $.when(recursive_cfg_export(nodes, nodesLenght)).done(function () {
            }).fail(function (message) {
                addMessage('danger', 'Export all: Error');
            });
        }
    }).fail(function (message) {
        addModalError(message);
    });
});

// Start a node
$(document).on('click', '.action-nodestart, .action-nodesstart, .action-nodestart-group', function (e) {
    $('#context-menu').remove();
    var node_id
        , startAll
        , isFreeSelectMode = $("#lab-viewport").hasClass("freeSelectMode")
        , nodeLenght
        ;
	
    if ($(this).hasClass('action-nodestart')) {
        logger(1, 'DEBUG: action = nodestart');
        node_id = $(this).attr('data-path');
    } else {
        logger(1, 'DEBUG: action = nodesstart');
        startAll = true;
    }
	
    $.when(getNodes(null)).done(function (nodes) {
        if (isFreeSelectMode) {
            nodeLenght = window.freeSelectedNodes.length;
            let nodesToStart = [];
            $.each(window.freeSelectedNodes, function (i, node) {
                if (nodes[node.path]['type'] != 'switch') {
                    nodesToStart.push(node);
                }
            })

            addMessage('info', 'Start selected nodes...');
            $.when(recursive_start(nodesToStart, nodesToStart.length)).done(function () {
            }).fail(function (message) {
                addMessage('danger', 'Start all: Error');
            });

        }
        else if (node_id != null) {
            if (nodes[node_id]['type'] != 'switch') {
                $.when(start(node_id)).done(function () {
                    // Node started -> print a small green message
                    addMessage('success', nodes[node_id]['name'] + ': ' + MESSAGES[76]);
                    if($('input[data-path='+node_id+'][name="node[type]"]') &&
                    $('input[data-path='+node_id+'][name="node[type]"]').parent()){
                        $('input[data-path='+node_id+'][name="node[type]"]').parent().addClass('node-running')
                        $('input[data-path='+node_id+']').prop('disabled', true)
                        $('select[data-path='+node_id+']').prop('disabled', true)
                        $("a[data-path="+node_id+"].action-nodeedit").addClass('disabled')
                        $("a[data-path="+node_id+"].action-nodedelete").addClass('disabled')
                        $("a[data-path="+node_id+"].action-nodeinterfaces").attr('data-status', 2)
                    }
                    printLabStatus();
                }).fail(function (message) {
                    // Cannot start
                    addMessage('danger', nodes[node_id]['name'] + ': ' + message);
                });
            }
        }
        else if (startAll) {
            var nodesLenght = Object.keys(nodes).length;
            addMessage('info', 'Start all...');
            $.when(recursive_start(nodes, nodesLenght)).done(function () {
            }).fail(function (message) {
                addMessage('danger', 'Start all: Error');
            });
            
             $.each(nodes, function(key, values) {
                if(values['type'] != "switch") {
                    console.log(values['type'], key)
             $.when(start(key)).done(function() {
             // Node started -> print a small green message
             addMessage('success', values['name'] + ': ' + MESSAGES[76]);
             if($('input[data-path='+values['id']+'][name="node[type]"]') &&
                   $('input[data-path='+values['id']+'][name="node[type]"]').parent()){
                       $('input[data-path='+values['id']+'][name="node[type]"]').parent().addClass('node-running')
                       $('input[data-path='+values['id']+']').prop('disabled', true)
                       $('select[data-path='+values['id']+']').prop('disabled', true)
                       $("a[data-path="+values['id']+"].action-nodeedit").addClass('disabled')
                       $("a[data-path="+values['id']+"].action-nodedelete").addClass('disabled')
                       $("a[data-path="+values['id']+"].action-nodeinterfaces").attr('data-status', 2)
                   }
             nodeLenght--;
             if(nodeLenght < 1){
             printLabStatus();
             }
             }).fail(function(message) {
             // Cannot start
             addMessage('danger', values['name'] + ': ' + message);
             
             });
             
            }

            else {
                nodeLenght--;
                if(nodeLenght < 1){
                printLabStatus();
                }
             }
             });
             
        }


    }).fail(function (message) {
        addModalError(message);
    });
});

// Stop a node
$(document).on('click', '.action-nodestop, .action-nodesstop, .action-nodestop-group', function (e) {
    $('#context-menu').remove();

    var node_id
        , nodeLenght
        , isFreeSelectMode = $("#lab-viewport").hasClass("freeSelectMode")
        , stopAll
        ;

    if ($(this).hasClass('action-nodestop')) {
        logger(1, 'DEBUG: action = nodestop');
        node_id = $(this).attr('data-path');
    } else {
        logger(1, 'DEBUG: action = nodesstop');
        stopAll = true;
    }

    $.when(getNodes(null)).done(function (nodes) {
        if (isFreeSelectMode) {
            nodeLenght = window.freeSelectedNodes.length;
            $.each(window.freeSelectedNodes, function (i, node) {
                console.log(node.type);
                if (nodes[node.path]['type'] != 'switch') {
                    $.when(stop(node.path)).done(function () {
                        // Node stopped -> print a small green message
                        addMessage('success', node.name + ': ' + MESSAGES[77]);
                        nodeLenght--;
                        if (nodeLenght < 1) {
                            setTimeout(printLabStatus, 3000);
                        }
                    }).fail(function (message) {
                        // Cannot stopped
                        addMessage('danger', node.name + ': ' + message);
                        nodeLenght--;
                        if (nodeLenght < 1) {
                            setTimeout(printLabStatus, 3000);
                        }
                    });
                }
                else {
                    nodeLenght--;
                    if (nodeLenght < 1) {
                        setTimeout(printLabStatus, 3000);
                    }
                }
            });
        }
        else if (node_id != null) {
            if (nodes[node_id]['type'] != 'switch') {
                $.when(stop(node_id)).done(function () {
                    // Node stopped -> print a small green message
                    addMessage('success', nodes[node_id]['name'] + ': ' + MESSAGES[77])

                    // remove blue background in node-list
                    if($('input[data-path='+node_id+'][name="node[type]"]') &&
                    $('input[data-path='+node_id+'][name="node[type]"]').parent()){
                        $('input[data-path='+node_id+'][name="node[type]"]').parent().removeClass('node-running')
                        $('input[data-path='+node_id+'][disabled]').prop('disabled', false)
                        $('select[data-path='+node_id+'][disabled]').prop('disabled', false)
                        $("a[data-path="+node_id+"].action-nodeedit").removeClass('disabled')
                        $("a[data-path="+node_id+"].action-nodedelete").removeClass('disabled')
                        $("a[data-path="+node_id+"].action-nodeinterfaces").attr('data-status', 0)
                    }
                    $('#node' + node_id + ' img').addClass('grayscale')
                    printLabStatus();
                }).fail(function (message) {
                    // Cannot stop
                    addMessage('danger', nodes[node_id]['name'] + ': ' + message);
                });
            }
        }
        else if (stopAll) {
            nodeLenght = Object.keys(nodes).length;
            $.each(nodes, function (key, values) {
                if (values['type'] != 'switch') {
                    $.when(stop(key)).done(function () {
                        // Node stopped -> print a small green message
                        addMessage('success', values['name'] + ': ' + MESSAGES[77]);
                        nodeLenght--;
                        if (nodeLenght < 1) {
                            setTimeout(printLabStatus, 3000);
                        }

                        $('#node' + values['id']).attr('data-status', 0);
                    }).fail(function (message) {
                        // Cannot stopped
                        addMessage('danger', values['name'] + ': ' + message);
                        nodeLenght--;
                        if (nodeLenght < 1) {
                            setTimeout(printLabStatus, 3000);
                        }
                    });
                }
            });
        }
    }).fail(function (message) {
        addModalError(message);
    });
});

// Wipe a node
$(document).on('click', '.action-nodewipe, .action-nodeswipe, .action-nodewipe-group', function (e) {
    $('#context-menu').remove();
    var self = $(this);
    var textQuestion = "";
    if(self.hasClass('action-nodewipe')){
        textQuestion = 'Are you sure to wipe this node?'
    } else if(self.hasClass('action-nodeswipe')){
        textQuestion = 'Are you sure to wipe all nodes?'
    } else {
        textQuestion = 'Are you sure to wipe selected nodes ?'
    }

    var body = '<div class="form-group">' +
                    '<div class="question">' + textQuestion + '</div>' +
                    '<div class="col-md-5 col-md-offset-3">' +
                        '<button id="node_wipe" class="btn btn-success"  data-dismiss="modal">Yes</button>' +
                        '<button type="button" class="btn" data-dismiss="modal">Cancel</button>' +
                    '</div>' +
                '</div>'
    var title = "Warning"
    addModal(title, body, "", "make-red make-small");
    $('#node_wipe').on('click', function(ev){

        var node_id
            , isFreeSelectMode = $("#lab-viewport").hasClass("freeSelectMode")
            , wipeAll
            ;

        if (self.hasClass('action-nodewipe')) {
            logger(1, 'DEBUG: action = nodewipe');
            node_id = self.attr('data-path');
        } else {
            logger(1, 'DEBUG: action = nodeswipe');
            wipeAll = true;
        }

        $.when(getNodes(null)).done(function (nodes) {
            if (isFreeSelectMode) {
                $.each(window.freeSelectedNodes, function (i, node) {
                    $.when(setTimeout(function () {
                        wipe(node.path);
                    }, nodes[node.path]['delay'] * 10)).done(function (res) {
                        // Node wiped -> print a small green message
                        addMessage('success', node.name + ': ' + MESSAGES[78])
                    }).fail(function (message) {
                        // Cannot wiped
                        addMessage('danger', node.name + ': ' + message);
                    });
                });
            }
            else if (node_id != null) {
                $.when(wipe(node_id)).done(function () {
                    // Node wiped -> print a small green message
                    addMessage('success', nodes[node_id]['name'] + ': ' + MESSAGES[78])
                }).fail(function (message) {
                    // Cannot wipe
                    addMessage('danger', nodes[node_id]['name'] + ': ' + message);
                });
            }
            else if (wipeAll) {
                $.each(nodes, function (key, values) {
                    $.when(setTimeout(function () {
                        wipe(key);
                    }, values['delay'] * 10)).done(function () {
                        // Node wiped -> print a small green message
                        addMessage('success', values['name'] + ': ' + MESSAGES[78])
                    }).fail(function (message) {
                        // Cannot wiped
                        addMessage('danger', values['name'] + ': ' + message);
                    });
                });
            }
        }).fail(function (message) {
            addModalError(message);
        });
    })
});

// Stop all nodes
$(document).on('click', '.action-stopall', function (e) {
    logger(1, 'DEBUG: action = stopall');
    $.when(stopAll()).done(function () {
        // Stopped all nodes -> reload status page
        printSystemStats();
    }).fail(function (message) {
        // Cannot stop all nodes
        addModalError(message);
    });
});

// Load system status page
$(document).on('click', '.action-sysstatus', function (e) {
    bodyAddClass('status');
    logger(1, 'DEBUG: action = sysstatus');

    //printSystemStats();
    $.when(getSystemStats()).done(function (data) {
        // Main: title
        var html_title = '' +
            '<div class="row row-eq-height"><div id="list-title-folders" class="col-md-12 col-lg-12">' +
            '<span title="' + MESSAGES[13] + '">' + MESSAGES[13] + '</span>' +
            '</div>' +
            '</div>';

        // Main
        var html = '' +
            '<div id="systemStats" class="container col-md-12 col-lg-12">' +
            '<div class="fill-height row row-eq-height">' +
            '<div id="stats-text" class="col-md-3 col-lg-3">' +
            '<ul></ul>' +
            '</div>' +
            '<div id="stats-graph" class="col-md-9 col-lg-9">' +
            '<ul></ul>' +
            '</div>' +
            '</div>' +
            '</div>';

        // Footer
        html += '</div>';

        $('#main-title').html(html_title);
        $('#main-title').show();
        $('#main').html(html);

        printSystemStats(data);

        var statusIntervalID = setInterval(function () {
            $.when(getSystemStats()).done(function (data) {
                updateStatus(statusIntervalID, data);
            }).fail(function (message) {
                // Cannot get status
                addModalError(message);
                clearInterval(statusIntervalID);
            });
        }, 5000);

        bodyAddClass('status');

    }).fail(function (message) {
        addModalError(message);
    });


});

// Add a user
$(document).on('click', '.action-useradd', function (e) {
    logger(1, 'DEBUG: action = useradd');
    printFormUser('add', {});
});

// Edit a user
$(document).on('dblclick', '.action-useredit', function (e) {
    logger(1, 'DEBUG: action = useredit');
    $.when(getUsers($(this).attr('data-path'))).done(function (user) {
        // Got user
        printFormUser('edit', user);
    }).fail(function (message) {
        // Cannot get user
        addModalError(message);
    });
});

// Load user management page
$(document).on('click', '.action-update', function (e) {
    logger(1, 'DEBUG: action = update');
    addMessage('info', MESSAGES[133], true);
    $.when(update()).done(function (message) {
        // Got user
        addMessage('success', message, true);
    }).fail(function (message) {
        // Cannot get user
        addMessage('alert', message, true);
    });
});

// Load user management page
$(document).on('click', '.action-usermgmt', function (e) {
    bodyAddClass('users');
    logger(1, 'DEBUG: action = usermgmt');
    printUserManagement();
});

// Show status
$(document).on('click', '.action-status', function (e) {
    logger(1, 'DEBUG: action = show status');
    $.when(getSystemStats()).done(function (data) {

        // Body
        var html = '<div id="statusModal" class="container col-md-12 col-lg-12">' +
            '<div class="fill-height row row-eq-height">' +
            '<div id="stats-text" class="col-md-3 col-lg-3">' +
            '<ul></ul>' +
            '</div>' +
            '<div id="stats-graph" class="col-md-9 col-lg-9">' +
            '<ul></ul>' +
            '</div>' +
            '</div>' +
            '</div>';

        addModalWide("STATUS", html, '');
        drawStatusInModal(data);

    }).fail(function (message) {
        // Cannot get status
        addModalError(message);
    });

    var statusModalIntervalID = setInterval(function () {
        $.when(getSystemStats()).done(function (data) {
            updateStatusInModal(statusModalIntervalID, data);
        }).fail(function (message) {
            // Cannot get status
            addModalError(message);
            clearInterval(statusModalIntervalID);
        });
    }, 5000);
});

/***************************************************************************
 * Submit
 **************************************************************************/

// Submit folder form
$(document).on('submit', '#form-folder-add, #form-folder-rename', function (e) {
    e.preventDefault();  // Prevent default behaviour
    var form_data = form2Array('folder');
    if ($(this).attr('id') == 'form-folder-add') {
        logger(1, 'DEBUG: posting form-folder-add form.');
        var url = '/api/folders';
        var type = 'POST';
    } else {
        logger(1, 'DEBUG: posting form-folder-rename form.');
        form_data['path'] = (form_data['path'] == '/') ? '/' + form_data['name'] : form_data['path'] + '/' + form_data['name'];
        var url = '/api/folders' + form_data['original'];
        var type = 'PUT';
    }
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        data: JSON.stringify(form_data),
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: folder "' + form_data['name'] + '" added.');
                // Close the modal
                $(e.target).parents('.modal').attr('skipRedraw', true);
                $(e.target).parents('.modal').modal('hide');
                // Reload the folder list
                printPageLabList(form_data['path']);
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                addModal('ERROR', '<p>' + data['message'] + '</p>', '<button type="button" class="btn btn-aqua" data-dismiss="modal">Close</button>');
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            addModal('ERROR', '<p>' + message + '</p>', '<button type="button" class="btn btn-aqua" data-dismiss="modal">Close</button>');
        }
    });
    return false;  // Stop to avoid POST
});

// Submit import form
$(document).on('submit', '#form-import', function (e) {
    e.preventDefault();  // Prevent default behaviour
    var form_data = new FormData();
    var form_name = 'import';
    var url = '/api/import';
    var type = 'POST';
    // Setting options: cannot use form2Array() because not using JSON to send data
    $('form :input[name^="' + form_name + '["]').each(function (id, object) {
        // INPUT name is in the form of "form_name[value]", get value only
        form_data.append($(this).attr('name').substr(form_name.length + 1, $(this).attr('name').length - form_name.length - 2), $(this).val());
    });
    // Add attachments
    $.each(ATTACHMENTS, function (key, value) {
        form_data.append(key, value);
    });
    $.ajax({
        cache: false,
        timeout: LONGTIMEOUT,
        type: type,
        url: encodeURI(url),
        contentType: false, // Set content type to false as jQuery will tell the server its a query string request
        processData: false, // Don't process the files
        dataType: 'json',
        data: form_data,
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: labs imported.');
                // Close the modal
                $(e.target).parents('.modal').attr('skipRedraw', true);
                $(e.target).parents('.modal').modal('hide');
                // Reload the folder list
                printPageLabList($('#list-folders').attr('data-path'));
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                addModal('ERROR', '<p>' + data['message'] + '</p>', '<button type="button" class="btn btn-aqua" data-dismiss="modal">Close</button>');
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            addModal('ERROR', '<p>' + message + '</p>', '<button type="button" class="btn btn-aqua" data-dismiss="modal">Close</button>');
        }
    });
    return false;  // Stop to avoid POST
});

// Submit lab form
$(document).on('submit', '#form-lab-add, #form-lab-edit', function (e) {
    e.preventDefault();  // Prevent default behaviour
    var lab_filename = $('#lab-viewport').attr('data-path');
    var form_data = form2Array('lab');
    var path = form_data['path'].split(/(\d+)/)[1];
    if ($(this).attr('id') == 'form-lab-add') {
        logger(1, 'DEBUG: posting form-lab-add form.');
        var url = '/api/labs';
        var type = 'POST';
    } else {
        logger(1, 'DEBUG: posting form-lab-edit form.');
        var url = '/api/labs/test/' + path;
        var type = 'PUT';
    }

    if ($(this).attr('id') == 'form-node-add') {
        // If adding need to manage multiple add
        if (form_data['count'] > 1) {
            form_data['postfix'] = 1;
        } else {
            form_data['postfix'] = 0;
        }
    } else {
        // If editing need to post once
        form_data['count'] = 1;
        form_data['postfix'] = 0;
    }

    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        data: JSON.stringify(form_data),
        success: function (data) {
            if (data['status'] == 'success') {
                postBanner(form_data["banner"], attachments);
                logger(1, 'DEBUG: lab "' + form_data['name'] + '" saved.');
                // Close the modal
                $(e.target).parents('.modal').attr('skipRedraw', true);
                $(e.target).parents('.modal').modal('hide');
                if (type == 'POST') {
                    // Reload the lab list
                    logger(1, 'DEBUG: lab "' + form_data['name'] + '" renamed.');
                    printPageLabList(form_data['path']);
                } else if (basename(form_data['path']) != form_data['name'] + '.unl') {
                    // Lab has been renamed, need to close it.
                    logger(1, 'DEBUG: lab "' + form_data['name'] + '" renamed.');
                    if ($('#lab-viewport').length) {
                        $('#lab-viewport').attr({'data-path': path});
                        printLabTopology();
                    } else {
                        $.when(closeLab()).done(function () {
                            postLogin();
                            printLabPreview(path);
                        }).fail(function (message) {
                            addModalError(message);
                        });

                    }

                } else {
                    addMessage(data['status'], data['message']);
                }
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                addModal('ERROR', '<p>' + data['message'] + '</p>', '<button type="button" class="btn btn-aqua" data-dismiss="modal">Close</button>');
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            addModal('ERROR', '<p>' + message + '</p>', '<button type="button" class="btn btn-aqua" data-dismiss="modal">Close</button>');
        }
    });
    return false;  // Stop to avoid POST
});

$(document).on('click', '#resetTimer', function(e) {
    document.getElementById('timer').value = "";
})

// Submit lab TP subject form
$(document).on('submit', '#form-subject-lab', function (e) {
    e.preventDefault();  // Prevent default behaviour
    var lab_filename = $('#lab-viewport').attr('data-path');
    var form_data = form2Array('lab');
    //var path = form_data['path'].split(/(\d+)/)[1];
    logger(1, 'DEBUG: posting form-subject-lab form.');
    var url = '/api/labs/subject/' + lab_filename;
    var type = 'PUT';

    /*if ($(this).attr('id') == 'form-node-add') {
        // If adding need to manage multiple add
        if (form_data['count'] > 1) {
            form_data['postfix'] = 1;
        } else {
            form_data['postfix'] = 0;
        }
    } else {*/
        // If editing need to post once
        form_data['count'] = 1;
        form_data['postfix'] = 0;
    //}

    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        data: JSON.stringify(form_data),
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: lab "' + form_data['name'] + '" saved.');
                // Close the modal
                $(e.target).parents('.modal').attr('skipRedraw', true);
                $(e.target).parents('.modal').modal('hide');
                /*if (type == 'POST') {
                    // Reload the lab list
                    logger(1, 'DEBUG: lab "' + form_data['name'] + '" renamed.');
                    printPageLabList(form_data['path']);
                } else if (basename(form_data['path']) != form_data['name'] + '.unl') {
                    // Lab has been renamed, need to close it.
                    logger(1, 'DEBUG: lab "' + form_data['name'] + '" renamed.');
                    if ($('#lab-viewport').length) {
                        $('#lab-viewport').attr({'data-path': path});
                        printLabTopology();
                    } else {
                        $.when(closeLab()).done(function () {
                            postLogin();
                            printLabPreview(path);
                        }).fail(function (message) {
                            addModalError(message);
                        });

                    }

                } else {*/
                    addMessage(data['status'], data['message']);
                //}
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                addModal('ERROR', '<p>' + data['message'] + '</p>', '<button type="button" class="btn btn-aqua" data-dismiss="modal">Close</button>');
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            addModal('ERROR', '<p>' + message + '</p>', '<button type="button" class="btn btn-aqua" data-dismiss="modal">Close</button>');
        }
    });
    return false;  // Stop to avoid POST
});

// Submit network form
$(document).on('submit', '#form-network-add, #form-network-edit', function (e) {
    e.preventDefault();  // Prevent default behaviour
    var lab_filename = $('#lab-viewport').attr('data-path');
    var form_data = form2Array('network');
    var promises = [];
    if ($(this).attr('id') == 'form-network-add') {
        logger(1, 'DEBUG: posting form-network-add form.');
        var url = '/api/labs/' + lab_filename + '/networks';
        var type = 'POST';
    } else {
        logger(1, 'DEBUG: posting form-network-edit form.');
        var url = '/api/labs/' + lab_filename + '/networks/' + form_data['id'];
        var type = 'PUT';
    }

    if ($(this).attr('id') == 'form-network-add') {
        // If adding need to manage multiple add
        if (form_data['count'] > 1) {
            form_data['postfix'] = 1;
        } else {
            form_data['postfix'] = 0;
        }
    } else {
        // If editing need to post once
        form_data['count'] = 1;
        form_data['postfix'] = 0;
    }
    for (var i = 0; i < form_data['count']; i++) {
        form_data['left'] = resolveZoom(form_data['left'], 'left');
        form_data['top'] = resolveZoom(form_data['top'], 'top');
        var request = $.ajax({
            cache: false,
            timeout: TIMEOUT,
            type: type,
            url: encodeURI(url),
            dataType: 'json',
            data: JSON.stringify(form_data),
            success: function (data) {
                if (data['status'] == 'success') {
                    logger(1, 'DEBUG: network "' + form_data['name'] + '" saved.');
                    $(".network" + form_data['id'] + " td:nth-child(2)").text(form_data['name']);
                    $(".network" + form_data['id'] + " td:nth-child(3)").text(form_data['type']);

                    // Close the modal
                    $('body').children('.modal').attr('skipRedraw', true);
                    $('body').children('.modal.second-win').modal('hide');
                    $('body').children('.modal.fade.in').focus();
                    addMessage(data['status'], data['message']);
                } else {
                    // Application error
                    logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                    addModal('ERROR', '<p>' + data['message'] + '</p>', '<button type="button" class="btn btn-aqua" data-dismiss="modal">Close</button>');
                }
            },
            error: function (data) {
                // Server error
                var message = getJsonMessage(data['responseText']);
                logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
                logger(1, 'DEBUG: ' + message);
                addModal('ERROR', '<p>' + message + '</p>', '<button type="button" class="btn btn-aqua" data-dismiss="modal">Close</button>');
            }
        });
        promises.push(request);
    }

    $.when.apply(null, promises).done(function () {
        printLabTopology();
    });
    //console.log(",networks add: ", networks)
    return false;  // Stop to avoid POST
});

// Submit node interfaces formS
$(document).on('submit', '#form-node-connect', function (e) {
    e.preventDefault();  // Prevent default behaviour
    var lab_filename = $('#lab-viewport').attr('data-path');
    var form_data = form2Array('interfc');
    var node_id = $('form :input[name="node_id"]').val();
    var url = '/api/labs/' + lab_filename + '/nodes/' + node_id + '/interfaces';
    /*var interfaces;
    if (node_id == 1) {
        interfaces = {
            id : 1,
            sort: "vpcs",
            ethernet: {
                0: {
                    name: "eth0",
                    network_id: 1
                },
                serial: []
            }
        }
    }
    if (node_id == 2) {
        interfaces = {
            id : 2,
            sort: "vpcs",
            ethernet: {
                0: {
                    name: "eth0",
                    network_id: 0
                },
                serial: []
            }
        }
    }
    interfaces= form_data;
    interfaces.id = nodes_id;
    console.log("nouvelles interfaces: ", interfaces)*/
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
                logger(1, 'DEBUG: node "' + node_id + '" saved.');
                // Close the modal
                $('body').children('.modal').attr('skipRedraw', true);
                $('body').children('.modal.second-win').modal('hide');
                $('body').children('.modal.fade.in').focus();
                addMessage(data['status'], data['message']);
                printLabTopology();
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                addModal('ERROR', '<p>' + data['message'] + '</p>', '<button type="button" class="btn btn-aqua" data-dismiss="modal">Close</button>');
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            addModal('ERROR', '<p>' + message + '</p>', '<button type="button" class="btn btn-aqua" data-dismiss="modal">Close</button>');
        }
    });
});


// Submit node form API Side
$(document).on('submit', '#form-node-add, #form-node-edit', function (e) {
    e.preventDefault();  // Prevent default behaviour
    var self = $(this);
    var lab_filename = $('#lab-viewport').attr('data-path');
    var form_data = form2Array('node');
    console.log('form data ', form_data)
    var promises = [];

    if ( form_data['template'] == "" ) {
          return false;
    }
		
    if ($(this).attr('id') == 'form-node-add') {
        logger(1, 'DEBUG: posting form-node-add form.');
        var url = '/api/labs/' + lab_filename + '/node';
        var type = 'POST';
    } else {
        logger(1, 'DEBUG: posting form-node-edit form.');
        var url = '/api/labs/' + lab_filename + '/node/' + form_data['id'];
        var type = 'PUT';
    }


    if ($(this).attr('id') == 'form-node-add') {
        // If adding need to manage multiple add
        if (form_data['count'] > 1) {
            form_data['postfix'] = 1;
            form_data['numberNodes'] = form_data['count']
        } else {
            form_data['postfix'] = 0;
        }
        form_data['console']="telnet";
    } else {
        // If editing need to post once
        form_data['count'] = 1;
        form_data['postfix'] = 0;
    }
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
                    $('body').children('.modal').attr('skipRedraw', true);
                    $('body').children('.modal.second-win').modal('hide');
                    $('body').children('.modal.fade.in').focus();
                    addMessage(data['status'], data['message']);
                    $(".modal .node" + form_data['id'] + " td:nth-child(2)").text(form_data["name"]);
                    $(".modal .node" + form_data['id'] + " td:nth-child(3)").text(form_data["template"]);
                    $(".modal .node" + form_data['id'] + " td:nth-child(4)").text(form_data["image"]);
                    $(".modal .node" + form_data['id'] + " td:nth-child(5)").text(form_data["cpu"]);
                    $(".modal .node" + form_data['id'] + " td:nth-child(7)").text(form_data["nvram"]);
                    $(".modal .node" + form_data['id'] + " td:nth-child(8)").text(form_data["ram"]);
                    $(".modal .node" + form_data['id'] + " td:nth-child(9)").text(form_data["ethernet"]);
                    $(".modal .node" + form_data['id'] + " td:nth-child(10)").text(form_data["serial"]);
                    $(".modal .node" + form_data['id'] + " td:nth-child(11)").text(form_data["console"]);

                    $("#node" + form_data['id'] + " .node_name").html('<i class="node' + form_data['id'] + '_status glyphicon glyphicon-stop"></i>' + form_data['name'])
                    $("#node" + form_data['id'] + " a img").attr("src", "/build/editor/images/icons/" + form_data['icon'])

                    $("#form-node-edit-table input[name='node[name]'][data-path='" + form_data['id'] + "']").val(form_data["name"])
                    $("#form-node-edit-table input[name='node[template]'][data-path='" + form_data['id'] + "']").val(form_data["template"])
                    $("#form-node-edit-table input[name='node[cpu]'][data-path='" + form_data['id'] + "']").val(form_data["cpu"])
                    $("#form-node-edit-table input[name='node[core]'][data-path='" + form_data['id'] + "']").val(form_data["core"])
                    $("#form-node-edit-table input[name='node[socket]'][data-path='" + form_data['id'] + "']").val(form_data["socket"])
                    $("#form-node-edit-table input[name='node[thread]'][data-path='" + form_data['id'] + "']").val(form_data["thread"])
                    $("#form-node-edit-table select[name='node[flavor]'][data-path='" + form_data['id'] + "']").val(form_data["flavor"]).change()
                    $("#form-node-edit-table select[name='node[icon]'][data-path='" + form_data['id'] + "']").val(form_data["icon"]).change()
                    printLabTopology();
                } else {
                    // Application error
                    logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                    addModal('ERROR', '<p>' + data['message'] + '</p>', '<button type="button" class="btn btn-aqua" data-dismiss="modal">Close</button>');
                }
            },
            error: function (data) {
                // Server error
                var message = getJsonMessage(data['responseText']);
                logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
                logger(1, 'DEBUG: ' + message);
                addModal('ERROR', '<p>' + message + '</p>', '<button type="button" class="btn btn-aqua" data-dismiss="modal">Close</button>');
            }
        });
        $.when.apply(request).done(function () {
            //usleep ( form_data['count'] * 10000 )
            //printLabTopology();
        });
    return false ;
});

// Submit node form
$(document).on('submit', '#oldform-node-add, #oldform-node-edit', function (e) {
    e.preventDefault();  // Prevent default behaviour
    var self = $(this);
    var lab_filename = $('#lab-viewport').attr('data-path');
    var form_data = form2Array('node');
    var promises = [];
    var nodes = {
        1:{
            console:"telnet",
            delay:0, 
            id:1, 
            left: 510, 
            icon:"Desktop.png", 
            image:"", 
            name:"VPC", 
            ram:1024, 
            status:0, 
            template:"vpcs", 
            type:"vpcs", 
            top:186, 
            url:"telnet://192.168.107.182:32769", 
            config_list:[], 
            config:0, 
            ethernet:1
        },
        2:{
            console:"telnet",
            delay:0, 
            id:2, 
            left: 606, 
            icon:"Desktop.png", 
            image:"", 
            name:"VPC", 
            ram:1024, 
            status:0, 
            template:"vpcs", 
            type:"vpcs", 
            top:237, 
            url:"telnet://192.168.107.182:32770", 
            config_list:[], 
            config:0, 
            ethernet:1
        }
    }
    if ($(this).attr('id') == 'form-node-add') {
        logger(1, 'DEBUG: posting form-node-add form.');
        var url = '/api/labs/' + lab_filename + '/nodes';
        var type = 'POST';
    } else {
        logger(1, 'DEBUG: posting form-node-edit form.');
        var url = '/api/labs/' + lab_filename + '/nodes/' + form_data['id'];
        var type = 'PUT';
    }

    if ($(this).attr('id') == 'form-node-add') {
        // If adding need to manage multiple add
        if (form_data['count'] > 1) {
            form_data['postfix'] = 1;
        } else {
            form_data['postfix'] = 0;
        }
    } else {
        // If editing need to post once
        form_data['count'] = 1;
        form_data['postfix'] = 0;
    }

    var inititalLeft = form_data['left']
    var inititalTop  = form_data['top']
    

    for (var i = 0, j = 0; i < form_data['count']; i++) {
        if( i > 0 && i%5 == 0){
            j++
        }
        form_data['left'] = +inititalLeft + i%5 * 60;
        form_data['top'] = +inititalTop + j * 80;
        nodes[Object.keys(nodes).length] = form_data;
        console.log("nodes: ", nodes)
        
        /*var request = $.ajax({
            cache: false,
            timeout: TIMEOUT,
            type: type,
            url: encodeURI(url),
            dataType: 'json',
            data: JSON.stringify(form_data),
            success: function (data) {
                if (data['status'] == 'success') {
                    logger(1, 'DEBUG: node "' + form_data['name'] + '" saved.');*/
                    // Close the modal
                    $('body').children('.modal').attr('skipRedraw', true);
                    $('body').children('.modal.second-win').modal('hide');
                    $('body').children('.modal.fade.in').focus();
                    //addMessage(data['status'], data['message']);
                    $(".modal .node" + form_data['id'] + " td:nth-child(2)").text(form_data["name"]);
                    $(".modal .node" + form_data['id'] + " td:nth-child(3)").text(form_data["template"]);
                    $(".modal .node" + form_data['id'] + " td:nth-child(4)").text(form_data["image"]);
                    $(".modal .node" + form_data['id'] + " td:nth-child(5)").text(form_data["cpu"]);
                    $(".modal .node" + form_data['id'] + " td:nth-child(7)").text(form_data["nvram"]);
                    $(".modal .node" + form_data['id'] + " td:nth-child(8)").text(form_data["ram"]);
                    $(".modal .node" + form_data['id'] + " td:nth-child(9)").text(form_data["ethernet"]);
                    $(".modal .node" + form_data['id'] + " td:nth-child(10)").text(form_data["serial"]);
                    $(".modal .node" + form_data['id'] + " td:nth-child(11)").text(form_data["console"]);

                    $("#node" + form_data['id'] + " .node_name").html('<i class="node' + form_data['id'] + '_status glyphicon glyphicon-stop"></i>' + form_data['name'])
                    $("#node" + form_data['id'] + " a img").attr("src", "/build/editor/images/icons/" + form_data['icon'])

                    $("#form-node-edit-table input[name='node[name]'][data-path='" + form_data['id'] + "']").val(form_data["name"])
                    $("#form-node-edit-table select[name='node[image]'][data-path='" + form_data['id'] + "']").val(form_data["image"])
                    $("#form-node-edit-table input[name='node[cpu]'][data-path='" + form_data['id'] + "']").val(form_data["cpu"])
                    $("#form-node-edit-table input[name='node[nvram]'][data-path='" + form_data['id'] + "']").val(form_data["nvram"])
                    $("#form-node-edit-table input[name='node[serial]'][data-path='" + form_data['id'] + "']").val(form_data["serial"])
                    $("#form-node-edit-table input[name='node[ethernet]'][data-path='" + form_data['id'] + "']").val(form_data["ethernet"])
                    $("#form-node-edit-table select[name='node[console]'][data-path='" + form_data['id'] + "']").val(form_data["console"])
                    $("#form-node-edit-table select[name='node[icon]'][data-path='" + form_data['id'] + "']").val(form_data["icon"])
               /* } else {
                    // Application error
                    logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                    addModal('ERROR', '<p>' + data['message'] + '</p>', '<button type="button" class="btn btn-aqua" data-dismiss="modal">Close</button>');
                }
            },
            error: function (data) {
                // Server error
                var message = getJsonMessage(data['responseText']);
                logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
                logger(1, 'DEBUG: ' + message);
                addModal('ERROR', '<p>' + message + '</p>', '<button type="button" class="btn btn-aqua" data-dismiss="modal">Close</button>');
            }
        });
        promises.push(request);*/
    }

    /*$.when.apply(null, promises).done(function () {
        //if (self.attr('id') == 'form-node-add') {
            printLabTopology();
        //}
    });*/
    
    return false;  // Stop to avoid POST
});

// submit nodeList form by input focusout
$(document).on('focusout', '.configured-nodes-input', function(e){
    e.preventDefault();  // Prevent default behaviour
    var id = $(this).attr('data-path')
    $('input[data-path='+id+'][name="node[type]"]').parent().removeClass('node-editing')
    if(!$(this).attr("readonly")){
        setNodeData(id);
    }
});




$(document).on('focusout', '.configured-nods-select', function(e){
    console.log("here")
    var id = $(this).attr('data-path')
    $('input[data-path='+id+'][name="node[type]"]').parent().removeClass('node-editing')
})


// submit nodeList form
$(document).on('change', '.configured-nods-select', function(e){
    e.preventDefault();  // Prevent default behaviour
    var id = $(this).attr('data-path')
    setNodeData(id);
});

// highlight nodeList form row
$(document).on('focus', '.configured-nods-select, .configured-nodes-input', function(e){
    var id = $(this).attr('data-path')
    $('input[data-path='+id+'][name="node[type]"]').parent().addClass('node-editing')
})


// Submit config form
$(document).on('submit', '#form-node-config', function (e) {
    e.preventDefault();  // Prevent default behaviour

    if($('#toggle_editor').is(':checked')) {
        var editor_data = ace.edit('editor').getValue();
        $('#nodeconfig').val(editor_data);
        //$('#nodeconfig').show()
        console.log($('#nodeconfig').val())
    }
    //saveLab('form-node-config');
    saveEditorLab('form-node-config', true)
});

// Submit login form
$(document).on('submit', '#form-login', function (e) {
    e.preventDefault();  // Prevent default behaviour
    var form_data = form2Array('login');
    var url = '/api/auth/login';
    var type = 'POST';
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        data: JSON.stringify(form_data),
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: user is authenticated.');
                // Close the modal
                $(e.target).parents('.modal').attr('skipRedraw', true);
                $(e.target).parents('.modal').modal('hide');
                $.when(getUserInfo()).done(function () {
                    // User is authenticated
                    logger(1, 'DEBUG: user authenticated.');
                    postLogin();
                }).fail(function () {
                    // User is not authenticated, or error on API
                    logger(1, 'DEBUG: loading authentication page.');
                    printPageAuthentication();
                });
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                addModal('ERROR', '<p>' + data['message'] + '</p>', '<button type="button" class="btn btn-aqua" data-dismiss="modal">Close</button>');
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            addModal('ERROR', '<p>' + message + '</p>', '<button type="button" class="btn btn-aqua" data-dismiss="modal">Close</button>');
        }
    });
    return false;  // Stop to avoid POST
});

// Submit user form
$(document).on('submit', '#form-user-add, #form-user-edit', function (e) {
    e.preventDefault();  // Prevent default behaviour
    var form_data = form2Array('user');
    // Converting data
    if (form_data['expiration'] == '') {
        form_data['expiration'] = -1;
    } else {
        form_data['expiration'] = Math.floor($.datepicker.formatDate('@', new Date(form_data['expiration'])) / 1000);
    }
    if (form_data['pexpiration'] == '') {
        form_data['pexpiration'] = -1;
    } else {
        form_data['pexpiration'] = Math.floor($.datepicker.formatDate('@', new Date(form_data['pexpiration'])) / 1000);
    }
    if (form_data['pod'] == '') {
        form_data['pod'] = -1;
    }

    var username = form_data['username'];
    if ($(this).attr('id') == 'form-user-add') {
        logger(1, 'DEBUG: posting form-user-add form.');
        var url = '/api/users';
        var type = 'POST';
    } else {
        logger(1, 'DEBUG: posting form-user-edit form.');
        var url = '/api/users/' + username;
        var type = 'PUT';
    }
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: type,
        url: encodeURI(url),
        dataType: 'json',
        data: JSON.stringify(form_data),
        success: function (data) {
            if (data['status'] == 'success') {
                logger(1, 'DEBUG: user "' + username + '" saved.');
                // Close the modal
                $(e.target).parents('.modal').attr('skipRedraw', true);
                $(e.target).parents('.modal').modal('hide');
                // Reload the user list
                printUserManagement();
            } else {
                // Application error
                logger(1, 'DEBUG: application error (' + data['status'] + ') on ' + type + ' ' + url + ' (' + data['message'] + ').');
                addModal('ERROR', '<p>' + data['message'] + '</p>', '<button type="button" class="btn btn-aqua" data-dismiss="modal">Close</button>');
            }
        },
        error: function (data) {
            // Server error
            var message = getJsonMessage(data['responseText']);
            logger(1, 'DEBUG: server error (' + data['status'] + ') on ' + type + ' ' + url + '.');
            logger(1, 'DEBUG: ' + message);
            addModal('ERROR', '<p>' + message + '</p>', '<button type="button" class="btn btn-aqua" data-dismiss="modal">Close</button>');
        }
    });
    return false;  // Stop to avoid POST
});

// Edit picture form
$('body').on('submit', '#form-picture-edit', function (e) {
    e.preventDefault();  // Prevent default behaviour
    var picture_id = $(this).attr('data-path');
    var missed_id = []
    var regex = /{{(NODE([0-9]*))}}/g;
    var temp;
    var str = $('form :input[name="picture[map]"]').val()
    $.when(getNodes(null)).then(function (nodes) {
        while (temp = regex.exec(str)) {
            if(temp[2] && !nodes.hasOwnProperty(temp[2])) missed_id.push(temp[2])
        }

        if(missed_id.length > 0) {
            var body = '<div class="form-group">'
            if(missed_id.length > 1){
                body += '<div class="question">Nodes IDs does not exist: '+ missed_id.join(', ') +'</div>' +
                        '<div class="question">Please change it to the correct value</div>'
            } else {
                body += '<div class="question">Node ID does not exist: '+ missed_id[0] + '</div>' +
                        '<div class="question">Please change it to the correct value</div>'
            }
                body += '<div class="col-md-5 col-md-offset-3">' +
                    '<button class="btn" data-dismiss="modal">Cotinue edit picture</button>' +
                    // '<button type="button" class="btn" data-dismiss="modal">Cancel</button>' +
                '</div>' +
            '</div>'
            var title = "Warning"
            addModal(title, body, "", "make-red make-small");
        } else {
            submitPictureEdit(picture_id)
        }
    })
    // return false;
    // Setting options
});

function submitPictureEdit(picture_id){
    var lab_file = $('#lab-viewport').attr('data-path');
    var form_data = {};

    $('form :input[name^="picture["]').each(function (id, object) {
        // Standard options
        var field_name = $(this).attr('name').replace(/^picture\[([a-z]+)\]$/, '$1');
        form_data[field_name] = $(this).val();
    });
    form_data['map'] = form_data['map'] + form_data['custommap']
    form_data['custommap']=''
    // Get action URL
    var url = '/api/labs/' + lab_file + '/pictures/' + picture_id;//form_data['id'];
    $.ajax({
        cache: false,
        timeout: TIMEOUT,
        type: 'PUT',
        url: encodeURI(url),
        dataType: 'json',
        data: JSON.stringify(form_data),
        success: function (data) {
            if (data['status'] == 'success') {
                // Fetching ok
                addMessage('SUCCESS', 'Picture "' + form_data['name'] + '" saved.');
                printPictureInForm(picture_id);
                $('ul.map a.action-pictureget[data-path="' + picture_id + '"]').attr('title', form_data['name']);
                $('ul.map a.action-pictureget[data-path="' + picture_id + '"]').text(form_data['name'].split(" ")[0]);
                $('body').children('.modal.second-win').modal('hide');
                // Picture saved  -> reopen this page (not reload, or will be posted twice)
                // window.location.href = '/lab_edit.php' + window.location.search;
            } else {
                // Fetching failed
                addMessage('DANGER', data['status']);
            }
        },
        error: function (data) {
            addMessage('DANGER', getJsonMessage(data['responseText']));
        }
    });

    // Hide and delete the modal (or will be posted twice)
    $('#form_frame > div').modal('hide');

    // Stop or form will follow the action link
    return false;
}

// Edit picture form
$('body').on('submit', '#form-picture-delete', function (ev) {

})

/*******************************************************************************
 * Custom Shape/Text Functions
 * *****************************************************************************/

// Prevent Drag when Resize
$('body').on('mouseover','.ui-resizable-handle',function (e) {
       lab_topology.setDraggable($('.customShape'), false )
});

$('body').on('mouseleave','.ui-resizable-handle',function (e) {
       if ( LOCK==0 ) lab_topology.setDraggable($('.customShape'), true )
});
// Add Custom Shape
$('body').on('submit', '.custom-shape-form', function (e) {
    var shape_options = {}
        , shape_html
        , dashed = ''
        , dash_spase_length = '10'
        , dash_line_length = '10'
        , z_index = 999
        , radius
        , coordinates
        , current_lab
        , customShape_id = ''
        , generateName = false
        ;

    shape_options['id'] = new Date().getTime();
    shape_options['shape_type'] = $('.custom-shape-form .shape-type-select').val();
    // shape_options['shape_name'] = $('.custom-shape-form .shape_name').val();
    if(!$('.custom-shape-form .shape_name').val()){
        generateName = true;
        shape_options['shape_name'] = $('.custom-shape-form .shape-type-select').val() + customShape_id;
    } else {
        shape_options['shape_name'] = $('.custom-shape-form .shape_name').val();
    }
    shape_options['shape_border_type'] = $('.custom-shape-form .border-type-select').val();
    shape_options['shape_border_color'] = $('.custom-shape-form .shape_border_color').val();
    shape_options['shape_background_color'] = $('.custom-shape-form .shape_background_color').val();
    shape_options['shape_width/height'] = 120;
    shape_options['shape_border_width'] = $('.custom-shape-form .shape_border_width').val();
    shape_options['shape_left_coordinate'] = $('.custom-shape-form .left-coordinate').val();
    shape_options['shape_top_coordinate'] = $('.custom-shape-form .top-coordinate').val();

    coordinates = 'position:absolute;left:' + resolveZoom(shape_options['shape_left_coordinate'], 'left') + 'px;top:' + resolveZoom(shape_options['shape_top_coordinate'], 'top') + 'px;';

    if (shape_options['shape_border_type'] == 'dashed') {
        dashed = ' stroke-dasharray = "' + dash_line_length + ',' + dash_spase_length + '" '
    } else {
        dashed = ''
    }

    if (shape_options['shape_type'] == 'square') {
        shape_html =
            '<div id="customShape' + shape_options['id'] + '" class="customShape context-menu" data-path="' + customShape_id + '" ' +
            'style="display:inline;z-index:' + z_index + ';' + coordinates + '" ' +
            'width="' + shape_options['shape_width/height'] + 'px" height="' + shape_options['shape_width/height'] + 'px" >' +
            '<svg width="' + shape_options['shape_width/height'] + '" height="' + shape_options['shape_width/height'] + '">' +
            '<rect width="' + shape_options['shape_width/height'] + '" ' +
            'height="' + shape_options['shape_width/height'] + '" ' +
            'fill ="' + shape_options['shape_background_color'] + '" ' +
            'stroke-width ="' + shape_options['shape_border_width'] + '" ' +
            'stroke ="' + shape_options['shape_border_color'] + '" ' + dashed +
            '"/>' +
            'Sorry, your browser does not support inline SVG.' +
            '</svg>' +
            '</div>';
    } else if (shape_options['shape_type'] == 'circle') {
        radius = shape_options['shape_width/height'] / 2 - shape_options['shape_border_width'] / 2;

        shape_html =
            '<div id="customShape' + shape_options['id'] + '" class="customShape context-menu" data-path="' + customShape_id + '" ' +
            'style="display:inline;z-index:' + z_index + ';' + coordinates + '"' +
            'width="' + shape_options['shape_width/height'] + 'px" height="' + shape_options['shape_width/height'] + 'px" >' +
            '<svg width="' + shape_options['shape_width/height'] + '" height="' + shape_options['shape_width/height'] + '">' +
            '<ellipse cx="' + (radius + shape_options['shape_border_width'] / 2 ) + '" ' +
            'cy="' + (radius + shape_options['shape_border_width'] / 2 ) + '" ' +
            'rx="' + radius + '" ' +
            'ry="' + radius + '" ' +
            'stroke ="' + shape_options['shape_border_color'] + '" ' +
            'stroke-width="' + shape_options['shape_border_width'] / 2 + '" ' + dashed +
            'fill ="' + shape_options['shape_background_color'] + '" ' +
            '/>' +
            'Sorry, your browser does not support inline SVG.' +
            '</svg>' +
            '</div>';
    }

    current_lab = $('#lab-viewport').attr('data-path');

    // Get action URL
    var url = '/api/labs' + current_lab + '/textobjects';
    var form_data = {};

    form_data['data'] = shape_html;
    form_data['name'] = shape_options["shape_name"];
    form_data['type'] = shape_options["shape_type"];

    createTextObject(form_data).done(function (textObjData) {
        $('#lab-viewport').prepend(shape_html);

        var $added_shape = $("#customShape" + shape_options['id']);
        $added_shape
            .resizable({
                autoHide: true,
                resize: function (event, ui) {
                    textObjectResize(event, ui, shape_options);
                },
                stop: textObjectDragStop
            });


        getTextObjects().done(function (textObjects) {
            $added_shape.attr("id", "customShape" + textObjData.id);
            $added_shape.attr("data-path", textObjData.id);
            var nameObj = generateName ? shape_options['shape_type'] + textObjData.id.toString() : shape_options['shape_name'];
            $added_shape.attr("name", nameObj);
            $added_shape.attr("data-path", textObjData.id);
            var new_data = document.getElementById($added_shape.attr("id")).outerHTML;

            editTextObject(textObjData.id, {data: new_data, name: nameObj})
            .done(function(){
                if ($("#customShape" + textObjData.id).length > 1) {
                    // reload lab
                    addMessage('warning', MESSAGES[156]);
                    printLabTopology();
                }

                // Hide and delete the modal (or will be posted twice)
                $('body').children('.modal').modal('hide');
                printLabTopology();
            }).fail(function(){

            });

        }).fail(function (message) {
            addMessage('DANGER', getJsonMessage(message));
        });
    }).done(function () {
        addMessage('SUCCESS', 'Lab has been saved (60023).');
    }).fail(function (message) {
        addMessage('DANGER', getJsonMessage(message));
    });

    // Stop or form will follow the action link
    return false;
});

// Add Text
$('body').on('submit', '.add-text-form', function (e) {
    var text_options = {}
        , text_html
        , coordinates
        , z_index = 1001
        , text_style = ''
        , customShape_id = ''
        , form_data = {}
        ;

    text_options['id'] = new Date().getTime();
    text_options['text_left_coordinate'] = $('.add-text-form .left-coordinate').val();
    text_options['text_top_coordinate'] = $('.add-text-form .top-coordinate').val();
    text_options['text'] = $('.add-text-form .main-text').val().replace(/\n/g, '<br>');
    text_options['alignment'] = 'center';
    text_options['vertical-alignment'] = 'top';
    text_options['color'] = $('.add-text-form .text_font_color').val();
    text_options['background-color'] = $('.add-text-form .text_background_color').val();
    text_options['text-size'] = $('.add-text-form .text_font_size').val();
    text_options['text-style'] = $('.add-text-form .text-font-style-select').val();

    if (text_options['text-style'] == 'normal') {
        text_style = 'font-weight: normal;';
    } else if (text_options['text-style'] == 'bold') {
        text_style = 'font-weight: bold;';
    } else if (text_options['text-style'] == 'italic') {
        text_style = 'font-style: italic;';
    } else {
        text_style = '';
    }

    coordinates = 'position:absolute;left:' + resolveZoom(text_options['text_left_coordinate'], 'left') + 'px;top:' + resolveZoom(text_options['text_top_coordinate'], 'top') + 'px;';

    text_html =
        '<div id="customText' + text_options['id'] + '" class="customShape customText context-menu" data-path="' + customShape_id + '" ' +
        'style="display:inline;' + coordinates + ' cursor:move; ;z-index:' + z_index + ';" >' +
        '<p align="' + text_options['alignment'] + '" style="' +
        'vertical-align:' + text_options['vertical-alignment'] + ';' +
        'color:' + text_options['color'] + ';' +
        'background-color:' + text_options['background-color'] + ';' +
        'font-size:' + text_options['text-size'] + 'px;' +
        text_style + '">' +
        text_options['text'] +
        '</p>' +
        '</div>';

    form_data['data'] = text_html;
    form_data['name'] = "txt " + ($(".customShape").length + 1);
    form_data['type'] = "text";

    createTextObject(form_data).done(function (data) {
        $('#lab-viewport').prepend(text_html);

        var $added_shape = $("#customText" + text_options['id']);
        $added_shape
            .resizable({
                autoHide: true,
                resize: function (event, ui) {
                    textObjectResize(event, ui, text_options);
                },
                stop: textObjectDragStop
            });

        getTextObjects().done(function (textObjects) {
            var id = data.id;
            $added_shape.attr("id", "customText" + id);
            $added_shape.attr("data-path", id);

            if ($("#customText" + id).length > 1) {
                addMessage('warning', MESSAGES[156]);
                printLabTopology();
            }

            // Hide and delete the modal (or will be posted twice)
            $('body').children('.modal').modal('hide');
            printLabTopology();
        }).fail(function (message) {
            addMessage('DANGER', getJsonMessage(message));
        });
    }).done(function () {
        addMessage('SUCCESS', 'Lab has been saved (60023).');
    }).fail(function (message) {
        addMessage('DANGER', getJsonMessage(message));
    });

    return false;
});

// Edit Custom Shape/Edit Text

$('body').on('click', '.action-textobjectduplicate', function (e) {
    logger(1, 'DEBUG: action = action-textobjectduplicate');
    var id = $(this).attr('data-path')
        , $selected_shape
        , $duplicated_shape
        , new_id
        , textObjectsLength
        , shape_border_width
        , form_data = {}
        , new_data_html;

    $selected_shape = $("#customShape" + id + " svg").children();
    shape_border_width = $("#customShape" + id + " svg").children().attr('stroke-width');

    function getSizeObj(obj) {
        var size = 0, key;
        for (key in obj) {
            if (obj.hasOwnProperty(key)) size++;
        }
        return size;
    }

    if ($("#customShape" + id).length) {
        $selected_shape = $("#customShape" + id);
        $selected_shape.resizable("destroy");
        //$selected_shape.draggable("destroy");
        //lab_topology.setDraggable($selected_shape, false);
        $duplicated_shape = $selected_shape.clone();

        $selected_shape
        .resizable({
            autoHide: true,
            resize: function (event, ui) {
                textObjectResize(event, ui, {"shape_border_width": shape_border_width});
            },
            stop: textObjectDragStop
        });

        getTextObjects().done(function (textObjects) {

            textObjectsLength = getSizeObj(textObjects);

            for (var i = 1; i <= textObjectsLength; i++) {
                if (textObjects['' + i + ''] == undefined) {
                    new_id = i;
                    break
                }
                if (textObjectsLength == i) {
                    new_id = i + 1;
                }
            }

            $duplicated_shape.css('top', parseInt($selected_shape.css('top')) + parseInt($selected_shape.css('width')) / 2);
            $duplicated_shape.css('left', parseInt($selected_shape.css('left')) + parseInt($selected_shape.css('height')) / 2);
            $duplicated_shape.attr("id", "customShape" + new_id);
            $duplicated_shape.attr("data-path", new_id);

            new_data_html = $duplicated_shape[0].outerHTML;
            form_data['data'] = new_data_html;
            form_data['name'] = textObjects[id]["name"];
            form_data['type'] = textObjects[id]["type"];

            createTextObject(form_data).done(function () {
                $('#lab-viewport').prepend(new_data_html);
                printLabTopology()
                addMessage('SUCCESS', 'Lab has been saved (60023).');
            }).fail(function (message) {
                addMessage('DANGER', getJsonMessage(message));
            })
        }).fail(function (message) {
            addMessage('DANGER', getJsonMessage(message));
        });
    } else if ($("#customText" + id).length) {
        $selected_shape = $("#customText" + id);
        $selected_shape.resizable("destroy");
        //lab_topology.setDraggable($selected_shape, false);
        $duplicated_shape = $selected_shape.clone();
        $selected_shape
        .resizable({
            autoHide: true,
            resize: function (event, ui) {
                textObjectResize(event, ui, {"shape_border_width": shape_border_width});
            },
            stop: textObjectDragStop
        });

        getTextObjects().done(function (textObjects) {

            textObjectsLength = getSizeObj(textObjects);

            for (var i = 1; i <= textObjectsLength; i++) {
                if (textObjects['' + i + ''] == undefined) {
                    new_id = i;
                    break
                }
                if (textObjectsLength == i) {
                    new_id = i + 1;
                }
            }

            $duplicated_shape.css('top', parseInt($selected_shape.css('top')) + parseInt($selected_shape.css('width')) / 2);
            $duplicated_shape.css('left', parseInt($selected_shape.css('left')) + parseInt($selected_shape.css('height')) / 2);
            $duplicated_shape.attr("id", "customText" + new_id);
            $duplicated_shape.attr("data-path", new_id);

            console.log(textObjects);
            new_data_html = $duplicated_shape[0].outerHTML;
            form_data['data'] = new_data_html;
            form_data['name'] = 'txt ' + new_id;
            form_data['type'] = textObjects[id]["type"];

            createTextObject(form_data).done(function () {
                $('#lab-viewport').prepend(new_data_html);
/*                lab_topology.draggable('customText' + new_id, {
                       grid: [3, 3],
                       stop: ObjectPosUpdate
                    });
                $('#customText' + new_id)
                .resizable({
                    autoHide: true,
                    resize: function (event, ui) {
                        textObjectResize(event, ui, {"shape_border_width": shape_border_width});
                    },
                    stop: textObjectDragStop
                });
*/
                printLabTopology()
                addMessage('SUCCESS', 'Lab has been saved (60023).');
            }).fail(function (message) {
                addMessage('DANGER', getJsonMessage(message));
            })
        }).fail(function (message) {
            addMessage('DANGER', getJsonMessage(message));
        });
    }
    $('#context-menu').remove();
});

$('body').on('click', '.action-textobjecttoback', function (e) {
    logger(1, 'DEBUG: action = action-textobjecttoback');
    var id = $(this).attr('data-path')
        , old_z_index
        , shape_border_width
        , new_data
        , $selected_shape = '';

    shape_border_width = $("#customShape" + id + " svg").children().attr('stroke-width');
    if ($("#customShape" + id).length) {
        $selected_shape = $("#customShape" + id);
        old_z_index = $selected_shape.css('z-index');
        $selected_shape.css('z-index', parseInt(old_z_index) - 1);
        $selected_shape.resizable("destroy");
        new_data = document.getElementById("customShape" + id).outerHTML;
        $selected_shape.resizable({
            autoHide: true,
            resize: function (event, ui) {
                textObjectResize(event, ui, {"shape_border_width": shape_border_width});
            },
            stop: textObjectDragStop
        });
    } else if ($("#customText" + id).length) {
        $selected_shape = $("#customText" + id);
        old_z_index = $selected_shape.css('z-index');
        $selected_shape.css('z-index', parseInt(old_z_index) - 1);
        $selected_shape.resizable("destroy");
        new_data = document.getElementById("customText" + id).outerHTML;
        $selected_shape.resizable({
            autoHide: true,
            resize: function (event, ui) {
                textObjectResize(event, ui, {"shape_border_width": 5});
            },
            stop: textObjectDragStop
        });
    }
    editTextObject(id, {data: new_data}).done(function () {

    }).fail(function () {
        addMessage('DANGER', getJsonMessage(message));
    });
    $('#context-menu').remove();
});

$('body').on('click', '.action-textobjecttofront', function (e) {
    logger(1, 'DEBUG: action = action-textobjecttofront');
    var id = $(this).attr('data-path')
        , old_z_index
        , shape_border_width
        , new_data
        , $selected_shape = '';

    shape_border_width = $("#customShape" + id + " svg").children().attr('stroke-width');
    if ($("#customShape" + id).length) {
        $selected_shape = $("#customShape" + id);
        old_z_index = $selected_shape.css('z-index');
        $selected_shape.css('z-index', parseInt(old_z_index) + 1);
        $selected_shape.resizable("destroy");
        new_data = document.getElementById("customShape" + id).outerHTML;
        $('#context-menu').remove();
        $selected_shape.resizable({
            autoHide: true,
            resize: function (event, ui) {
                textObjectResize(event, ui, {"shape_border_width": shape_border_width});
            },
            stop: textObjectDragStop
        });
    } else if ($("#customText" + id).length) {
        $selected_shape = $("#customText" + id);
        old_z_index = $selected_shape.css('z-index');
        $selected_shape.css('z-index', parseInt(old_z_index) + 1);
        $selected_shape.resizable("destroy");
        new_data = document.getElementById("customText" + id).outerHTML;
        $selected_shape.resizable({
            autoHide: true,
            resize: function (event, ui) {
                textObjectResize(event, ui, {"shape_border_width": 5});
            },
            stop: textObjectDragStop
        });
        $('#context-menu').remove();
    }
    editTextObject(id, {data: new_data}).done(function () {

    }).fail(function () {
        addMessage('DANGER', getJsonMessage(message));
    });
    $('#context-menu').remove();
});

$('body').on('click', '.action-textobjectedit', function (e) {
    logger(1, 'DEBUG: action = action-textobjectedit');
    var id = $(this).attr('data-path');

    if ($("#customShape" + id).length) {
        printFormEditCustomShape(id);
    } else if ($("#customText" + id).length) {
        printFormEditText(id);
    }
    $('#context-menu').remove();
});

$('body').on('click', '.action-textobjectdelete', function (ev) {
    $('#context-menu').remove();
    var id = $(this).attr('data-path')
    var self = $(this);
    var textQuestion = $(this).hasClass('customText') ? 'Are you sure to delete this text?'
                                                      : 'Are you sure to delete this shape?'
    var body = '<div class="form-group">' +
                    '<div class="question">'+ textQuestion +'</div>' +
                    '<div class="col-md-5 col-md-offset-3">' +
                        '<button id="textobjectdelete" class="btn btn-success"  data-path="'+id+'" data-dismiss="modal">Yes</button>' +
                        '<button type="button" class="btn" data-dismiss="modal">Cancel</button>' +
                    '</div>' +
                '</div>'
    var title = "Warning"
    addModal(title, body, "", "make-red make-small");
    $('#textobjectdelete').on('click', function (e) {
        logger(1, 'DEBUG: action = action-textobjectdelete');
        var id = self.attr('data-path')
            , $table = self.closest('table')
            , $selected_shape = '';
        if ($("#customShape" + id).length) {
            $selected_shape = $("#customShape" + id);
        } else if ($("#customText" + id).length) {
            $selected_shape = $("#customText" + id);
        }
        deleteTextObject(id).done(function () {
            if (self.parent('tr')) {
                $('.textObject' + id, $table).remove();
            }
            $selected_shape.remove();
        }).fail(function (message) {
            addModalError(message);
        });
    });
})

$('body').on('contextmenu', '.edit-custom-shape-form, .edit-custom-text-form, #context-menu', function (e) {
    e.preventDefault();
    e.stopPropagation();
});

/*******************************************************************************
 * Text Edit Form
 * *****************************************************************************/

$('body').on('click', '.edit-custom-text-form .btn-align-left', function (e) {
    logger(1, 'DEBUG: action = action-set/delete left alignment');
    var id = $(this).attr('data-path');

    $("#customText" + id + " p").attr('align', 'left');

    if ($('.edit-custom-text-form .btn-align-left').hasClass('active')) {
        $('.edit-custom-text-form .btn-align-left').removeClass('active');
    } else if ($('.edit-custom-text-form .btn-align-center').hasClass('active')) {
        $('.edit-custom-text-form .btn-align-center').removeClass('active');
    } else if ($('.edit-custom-text-form .btn-align-right').hasClass('active')) {
        $('.edit-custom-text-form .btn-align-right').removeClass('active');
    }
    $('.edit-custom-text-form .btn-align-left').addClass('active');
});

$('body').on('click', '.edit-custom-text-form .btn-align-center', function (e) {
    logger(1, 'DEBUG: action = action-set/delete center alignment');
    var id = $(this).attr('data-path');
    $("#customText" + id + " p").attr('align', 'center');

    if ($('.edit-custom-text-form .btn-align-left').hasClass('active')) {
        $('.edit-custom-text-form .btn-align-left').removeClass('active');
    } else if ($('.edit-custom-text-form .btn-align-center').hasClass('active')) {
        $('.edit-custom-text-form .btn-align-center').removeClass('active');
    } else if ($('.edit-custom-text-form .btn-align-right').hasClass('active')) {
        $('.edit-custom-text-form .btn-align-right').removeClass('active');
    }
    $('.edit-custom-text-form .btn-align-center').addClass('active');
});

$('body').on('click', '.edit-custom-text-form .btn-align-right', function (e) {
    logger(1, 'DEBUG: action = action-set/delete left alignment');
    var id = $(this).attr('data-path');
    $("#customText" + id + " p").attr('align', 'right');

    if ($('.edit-custom-text-form .btn-align-left').hasClass('active')) {
        $('.edit-custom-text-form .btn-align-left').removeClass('active');
    } else if ($('.edit-custom-text-form .btn-align-center').hasClass('active')) {
        $('.edit-custom-text-form .btn-align-center').removeClass('active');
    } else if ($('.edit-custom-text-form .btn-align-right').hasClass('active')) {
        $('.edit-custom-text-form .btn-align-right').removeClass('active');
    }
    $('.edit-custom-text-form .btn-align-right').addClass('active');
});

$('body').on('click', '.edit-custom-text-form .btn-text-italic', function (e) {
    logger(1, 'DEBUG: action = action-set/delete font style');
    var id = $(this).attr('data-path');

    if ($('.edit-custom-text-form .btn-text-italic').hasClass('active')) {
        $('.edit-custom-text-form .btn-text-italic').removeClass('active');
        $("#customText" + id + " p").css('font-style', 'normal');
    } else if (!$('.edit-custom-text-form .btn-text-italic').hasClass('active')) {
        $('.edit-custom-text-form .btn-text-italic').addClass('active');
        $("#customText" + id + " p").css('font-style', 'italic');
    }
});

$('body').on('click', '.edit-custom-text-form .btn-text-bold', function (e) {
    logger(1, 'DEBUG: action = action-set/delete font weight');
    var id = $(this).attr('data-path');

    if ($('.edit-custom-text-form .btn-text-bold').hasClass('active')) {
        $('.edit-custom-text-form .btn-text-bold').removeClass('active');
        $("#customText" + id + " p").css('font-weight', 'normal');
    } else if (!$('.edit-custom-text-form .btn-text-bold').hasClass('active')) {
        $('.edit-custom-text-form .btn-text-bold').addClass('active');
        $("#customText" + id + " p").css('font-weight', 'bold');
    }
});

$('body').on('change', '.edit-custom-text-form .text-z_index-input', function (e) {
    logger(1, 'DEBUG: action = action-change text z-index');
    var id = $(this).attr('data-path');
    $("#customText" + id).css('z-index', parseInt($(".edit-custom-text-form .text-z_index-input").val()) + 1000);
});

$('body').on('change', '.edit-custom-text-form .text_background_color', function (e) {
    logger(1, 'DEBUG: action = action-change text background color');
    var id = $(this).attr('data-path');
    $('.edit-custom-text-form .text_background_transparent').removeClass('active  btn-success').text('Off');
    $("#customText" + id + " p").css('background-color', $(".edit-custom-text-form .text_background_color").val());
});

$('body').on('click', '.edit-custom-text-form .text_background_transparent', function (e) {
    logger(1, 'DEBUG: action = action-change text background color');
    var id = $(this).attr('data-path');

    if ($('.edit-custom-text-form .text_background_transparent').hasClass('active')) {
        $('.edit-custom-text-form .text_background_transparent').removeClass('active  btn-success').text('Off');
        $("#customText" + id + " p").css('background-color', $(".edit-custom-text-form .text_background_color").val());
    } else {
        $('.edit-custom-text-form .text_background_transparent').addClass('active  btn-success').text('On');
        $("#customText" + id + " p").css('background-color', hex2rgb($(".edit-custom-text-form .text_background_color").val(), 0));
    }
});

$('body').on('change', '.edit-custom-text-form .text_color', function (e) {
    logger(1, 'DEBUG: action = action-change text color');
    var id = $(this).attr('data-path');
    $("#customText" + id + " p").css('color', $(".edit-custom-text-form .text_color").val());
});

$('body').on('change', '.edit-custom-text-form .text-rotation-input', function (e) {
    logger(1, 'DEBUG: action = action-rotate shape');
    var id = $(this).attr('data-path')
        , angle = parseInt(this.value);

    $("#customText" + id).css("-ms-transform", "rotate(" + angle + "deg)");
    $("#customText" + id).css("-webkit-transform", "rotate(" + angle + "deg)");
    $("#customText" + id).css("transform", "rotate(" + angle + "deg)");
});

$('body').on('click', '.edit-custom-text-form .cancelForm', function (e) {
    logger(1, 'DEBUG: action = action-return old text values');
    var id = $(this).attr('data-path')
        , angle = $('.edit-custom-text-form .firstTextValues-rotation').val();

    //Return z-index value
    $("#customText" + id).css('z-index', parseInt($('.edit-custom-text-form .firstTextValues-z_index').val()));

    // Return alignment value
    $('.edit-custom-text-form .btn-align-left').removeClass('active');
    $('.edit-custom-text-form .btn-align-center').removeClass('active');
    $('.edit-custom-text-form .btn-align-right').removeClass('active');

    if ($('.edit-custom-text-form .firstTextValues-align').val() == "left") {
        $("#customText" + id + " p").attr('align', 'left');
    } else if ($('.edit-custom-text-form .firstTextValues-align').val() == "center") {
        $("#customText" + id + " p").attr('align', 'center');
    } else if ($('.edit-custom-text-form .firstTextValues-align').val() == "right") {
        $("#customText" + id + " p").attr('align', 'right');
    }

    // Return text type value
    $('.edit-custom-text-form .btn-text-bold').removeClass('active');
    $('.edit-custom-text-form .btn-text-italic').removeClass('active');

    if ($('.edit-custom-text-form .firstTextValues-italic').val()) {
        $("#customText" + id + " p").css('font-style', 'italic');
    } else if ($('.edit-custom-text-form .firstTextValues-bold').val()) {
        $("#customText" + id + " p").css('font-weight', 'bold');
    }

    // Return text color value
    $("#customText" + id + " p").css('color', $('.edit-custom-text-form .firstTextValues-color').val());

    // Return background color value
    $("#customText" + id + " p").css('background-color', $(".edit-custom-text-form .firstTextValues-background-color").val());

    // Return rotation angle
    $("#customText" + id).css("-ms-transform", "rotate(" + angle + "deg)");
    $("#customText" + id).css("-webkit-transform", "rotate(" + angle + "deg)");
    $("#customText" + id).css("transform", "rotate(" + angle + "deg)");

    // Remove edit class
    $("#customText" + id).removeClass('in-editing');

    $('.edit-custom-text-form').remove();
});

$('body').on('click', '.edit-custom-text-form-save', function (e) {
    logger(1, 'DEBUG: action = action-save new text values');
    var id = $(this).attr('data-path')
        , $selected_shape = $("#customText" + id)
        , new_data;

    $selected_shape.resizable("destroy");
    $selected_shape.removeClass('in-editing');
    new_data = document.getElementById("customText" + id).outerHTML;
    $selected_shape.resizable({
        autoHide: true,
        resize: function (event, ui) {
            textObjectResize(event, ui, {"shape_border_width": 5});
        },
        stop: textObjectDragStop
    });

    editTextObject(id, {data: new_data}).done(function () {
        addMessage('SUCCESS', 'Lab has been saved (60023).');
        adjustZoom(lab_topology)
    }).fail(function (message) {
        addModalError(message);
    });
    $('.edit-custom-text-form').remove();
});

$(document).on('dblclick', '.customText', function (e) {
    if ( LOCK == 1 ) {
    return 0;
    }
    logger(1, 'DEBUG: action = action-edit text');
    // need to disable select mode
    $("#lab-viewport").selectable("disable");
    var id = $(this).attr('data-path')
        , $selectedCustomText = $("#customText" + id + " p")
        ;

    // Disable draggable and resizable before sending request
    try {
        lab_topology.setDraggable('customText'+id, false);
        $(this).resizable("destroy");
    }
    catch (e) {
        console.warn(e);
    }

    $selectedCustomText.attr('contenteditable', 'true').focus().addClass('editable');
});

$(document).on('paste', '[contenteditable="true"]', function (e) {
    e.preventDefault();
    var text = null;
    text = (e.originalEvent || e).clipboardData.getData('text/plain') || prompt('Paste Your Text Here');
    document.execCommand("insertText", false, text);
});

$(document).on('focusout', '.editable', function (e) {
    $("#lab-viewport").selectable("enable");
    var new_data
        , id = $(this).parent().attr('data-path')
        , $selected_shape = $("#customText" + id)
        , innerHtml = $("p", $selected_shape).html()
        , textLines = 0
        ;

    $("#customText" + id + " p").removeClass('editable');
    $("#customText" + id + " p").attr('contenteditable', 'false');
    innerHtml = innerHtml.replace(/^(<br>)+/, "").replace(/(<br>)+$/, "");

    // replace all HTML tags except <br>, replace closing DIV </div> with br
    innerHtml = innerHtml.replace(/<(\w+\b)[^>]*>([^<>]*)<\/\1>/g, '$2<br>');

    if (!innerHtml) {
        innerHtml = "<br>";
    }

    $("p", $selected_shape).html(innerHtml);
    // Calculate and apply new Width / Height based lines count
    textLines = $("br", $selected_shape).length;
    if (textLines) {
        // multilines text
        $selected_shape.css("height", parseFloat($("p", $selected_shape).css("font-size")) * (textLines * 1.5 + 1) + "px");
    }
    else {
        // 1 line text
        $selected_shape.css("height", parseFloat($("p", $selected_shape).css("font-size")) * 2 + "px");
    }
    $selected_shape.css("width", "auto");

    new_data = document.getElementById("customText" + id).outerHTML;
    editTextObject(id, {data: new_data}).done(function () {
        addMessage('SUCCESS', 'Lab has been saved (60023).');
        //printLabTopology()
    }).fail(function (message) {
        addModalError(message);
    });
    lab_topology.setDraggable('customText'+id, true);
    logger (1,  ' DEBUG: focusout will apply jsplum drggable to customText'+id )
    $selected_shape
    .resizable({
        autoHide: true,
        resize: function (event, ui) {
            textObjectResize(event, ui, {"shape_border_width": 5});
        },
        stop: textObjectDragStop
    });
});

// Fix "Enter" behaviour in contenteditable elements
$(document).on('keydown', '.editable', function (e) {
    var editableText = $('.editable')
        ;

    if (KEY_CODES.enter == e.which) {
        function brQuantity() {
            if (parseInt(editableText.text().length) <= getCharacterOffsetWithin(window.getSelection().getRangeAt(0), document.getElementsByClassName("editable")[0])) {
                return '<br><br>'
            } else {
                return '<br>'
            }
        };
        document.execCommand('insertHTML', false, brQuantity());
        return false;
    }
});

//Get caret position
// node - need to get by pure js
function getCharacterOffsetWithin(range, node) {
    var treeWalker = document.createTreeWalker(
        node,
        NodeFilter.SHOW_TEXT,
        function (node) {
            var nodeRange = document.createRange();
            nodeRange.selectNodeContents(node);
            return nodeRange.compareBoundaryPoints(Range.END_TO_END, range) < 1 ?
                NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
        },
        false
    );

    var charCount = 0;
    while (treeWalker.nextNode()) {
        charCount += treeWalker.currentNode.length;
    }
    if (range.startContainer.nodeType == 3) {
        charCount += range.startOffset;
    }
    return charCount;
}

/*******************************************************************************
 * Custom Shape Edit Form
 * *****************************************************************************/

$('body').on('click', '.edit-custom-shape-form .cancelForm', function (e) {
    logger(1, 'DEBUG: action = action-return old shape values');
    var id = $(this).attr('data-path')
        , angle = $(".edit-custom-shape-form .firstShapeValues-rotation").val();

    //Return z-index value
    $("#customShape" + id).css('z-index', parseInt($('.edit-custom-shape-form .firstShapeValues-z_index').val()));

    //Return border width value
    if ($("#customShape" + id + " svg").children().attr('cx')) {
        $("#customShape" + id + " svg").children().attr('stroke-width', $('.edit-custom-shape-form .firstShapeValues-border-width').val() / 2);
    } else {
        $("#customShape" + id + " svg").children().attr('stroke-width', $('.edit-custom-shape-form .firstShapeValues-border-width').val());
    }

    //Return border type value
    if ($('.edit-custom-shape-form .firstShapeValues-border-type').val() == 'solid') {
        $("#customShape" + id + " svg").children().removeAttr('stroke-dasharray');
    } else if ($('.edit-custom-shape-form .firstShapeValues-border-type').val() == 'dashed') {
        if (!$("#customShape" + id + " svg").children().attr('stroke-dasharray')) {
            $("#customShape" + id + " svg").children().attr('stroke-dasharray', '10,10');
        }
    }

    //Return border color value
    $("#customShape" + id + " svg").children().attr('stroke', $(".edit-custom-shape-form .firstShapeValues-border-color").val());

    //Return background color value
    $("#customShape" + id + " svg").children().attr('fill', $(".edit-custom-shape-form .firstShapeValues-background-color").val());

    //Return rotation angle
    $("#customShape" + id).css("-ms-transform", "rotate(" + angle + "deg)");
    $("#customShape" + id).css("-webkit-transform", "rotate(" + angle + "deg)");
    $("#customShape" + id).css("transform", "rotate(" + angle + "deg)");

    $("#customShape" + id).removeClass('in-editing');

    $('.edit-custom-shape-form').remove();
});

$('body').on('change', '.edit-custom-shape-form .shape-z_index-input', function (e) {
    logger(1, 'DEBUG: action = action-change shape z-index');
    var id = $(this).attr('data-path');
    $("#customShape" + id).css('z-index', parseInt($(".edit-custom-shape-form .shape-z_index-input").val()) + 1000);
});

$('body').on('change', '.edit-custom-shape-form .shape_border_width', function (e) {
    logger(1, 'DEBUG: action = action-change shape border width');
    var id = $(this).attr('data-path');

    if ($("#customShape" + id + " svg").children().attr('cx')) {
        $("#customShape" + id + " svg").children().attr('stroke-width', $(".edit-custom-shape-form .shape_border_width").val() / 2);
    } else {
        $("#customShape" + id + " svg").children().attr('stroke-width', $(".edit-custom-shape-form .shape_border_width").val());
    }
});

$('body').on('change', '.edit-custom-shape-form .border-type-select', function (e) {
    logger(1, 'DEBUG: action = action-change shape border type');
    var id = $(this).attr('data-path');

    if ($(".edit-custom-shape-form .border-type-select").val() == 'solid') {
        if ($("#customShape" + id + " svg").children().attr('stroke-dasharray')) {
            $("#customShape" + id + " svg").children().removeAttr('stroke-dasharray');
        }
    } else if ($(".edit-custom-shape-form .border-type-select").val() == 'dashed') {
        if (!$("#customShape" + id + " svg").children().attr('stroke-dasharray')) {
            $("#customShape" + id + " svg").children().attr('stroke-dasharray', '10,10');
        }
    }
});

$('body').on('change', '.edit-custom-shape-form .shape_background_color', function (e) {
    logger(1, 'DEBUG: action = action-change shape background color');
    var id = $(this).attr('data-path');
    $("#customShape" + id + " svg").children().attr('fill', $(".edit-custom-shape-form .shape_background_color").val());
    $('.edit-custom-shape-form .shape_background_transparent').removeClass('active  btn-success').text('Off');
});

$('body').on('click', '.edit-custom-shape-form .shape_background_transparent', function (e) {
    logger(1, 'DEBUG: action = action-change shape background color');
    var id = $(this).closest('form').attr('data-path');

    if ($('.edit-custom-shape-form .shape_background_transparent').hasClass('active')) {
        $('.edit-custom-shape-form .shape_background_transparent').removeClass('active  btn-success').text('Off');
        $("#customShape" + id + " svg").children().attr('fill', $(".edit-custom-shape-form .shape_background_color").val());
    }
    else {
        $('.edit-custom-shape-form .shape_background_transparent').addClass('active  btn-success').text('On');
        $("#customShape" + id + " svg").children().attr('fill', hex2rgb($(".edit-custom-shape-form .shape_background_color").val(), 0));
    }
});

$('body').on('change', '.edit-custom-shape-form .shape_border_color', function (e) {
    logger(1, 'DEBUG: action = action-change shape border color');
    var id = $(this).attr('data-path');
    $("#customShape" + id + " svg").children().attr('stroke', $(".edit-custom-shape-form .shape_border_color").val());
});

$('body').on('change', '.edit-custom-shape-form .shape-rotation-input', function (e) {
    logger(1, 'DEBUG: action = action-rotate shape');
    var id = $(this).attr('data-path')
        , angle = parseInt(this.value);

    $("#customShape" + id).css("-ms-transform", "rotate(" + angle + "deg)");
    $("#customShape" + id).css("-webkit-transform", "rotate(" + angle + "deg)");
    $("#customShape" + id).css("transform", "rotate(" + angle + "deg)");
});

$('body').on('click', '.edit-custom-shape-form-save', function (e) {
    logger(1, 'DEBUG: action = action-save new shape values');
    var id = $(this).attr('data-path')
        , $selected_shape = $("#customShape" + id)
        , shape_border_width
        , new_data
        , shape_name = $(".shape-name-input").val()
        ;

    $('.edit-custom-shape-form .firstShapeValues-background-color').val($(".edit-custom-shape-form .shape_background_color").val());
    shape_border_width = $("#customShape" + id + " svg").children().attr('stroke-width');
    $selected_shape.resizable("destroy");
    $("#customShape" + id).removeClass('in-editing');
    new_data = document.getElementById("customShape" + id).outerHTML;
    $('#context-menu').remove();
    $selected_shape.resizable({
        autoHide: true,
        resize: function (event, ui) {
            textObjectResize(event, ui, {"shape_border_width": shape_border_width});
        },
        stop: textObjectDragStop
    });

    editTextObject(id, {data: new_data, name: shape_name}).done(function () {
        $("#customShape" + id ).attr('name', shape_name);
        addMessage('SUCCESS', 'Lab has been saved (60023).');
        adjustZoom(lab_topology)
    }).fail(function (message) {
        addModalError(message);
    });
    $('.edit-custom-shape-form').remove();
});

// Print lab textobjects
$(document).on('click', '.action-textobjectsget', function (e) {
    logger(1, 'DEBUG: action = textobjectsget');
    $.when(getTextObjects()).done(function (textobjects) {
        printListTextobjects(textobjects);
    }).fail(function (message) {
        addModalError(message);
    });
});


/*******************************************************************************
 * Free Select
 * ****************************************************************************/
window.freeSelectedNodes = [];
$(document).on("click", ".action-freeselect", function (event) {
    var self = this
        , isFreeSelectMode = $(self).hasClass("active")
        ;

    if (isFreeSelectMode) {
        // TODO: disable Free Select Mode
        $(".node_frame").removeClass("free-selected");
    }
    else {
        // TODO: activate Free Select Mode

    }

    window.freeSelectedNodes = [];
    $(self).toggleClass("active", !isFreeSelectMode);
    $("#lab-viewport").toggleClass("freeSelectMode", !isFreeSelectMode);

});

$(document).on("click", "#lab-viewport.freeSelectMode .onode_frame", function (event) {
    event.preventDefault();
    event.stopPropagation();

    var self = this
        , isFreeSelected = $(self).hasClass("free-selected")
        , name = $(self).data("name")
        , path = $(self).data("path")
        ;

    if (isFreeSelected) {   // already present window.freeSelectedNodes = [];
        window.freeSelectedNodes = window.freeSelectedNodes.filter(function (node) {
            return node.name !== name && node.path !== path;
        });
    }
    else {                  // add to window.freeSelectedNodes = [];
        window.freeSelectedNodes.push({
            name: name
            , path: path
        });
    }

    $(self).toggleClass("free-selected", !isFreeSelected);
});

$(document).on("click", ".user-settings", function () {
    var user = $(this).attr("user");
    $.when(getUsers(user)).done(function (user) {
        // Got user
        printFormUser('edit', user);
    }).fail(function (message) {
        // Cannot get user
        addModalError(message);
    });
});


// Load logs page
$(document).on('click', '.action-logs', function(e) {
    logger(1, 'DEBUG: action = logs');
    printLogs('access.txt', 10, "");
    bodyAddClass('logs');
});

/*******************************************************************************
 * Node link
 * ****************************************************************************/


$(document).on('click', 'a.interfaces.serial', function (e) {
    e.preventDefault();
})

$(document).on('click','#lab-viewport', function (e) {
   var context = 0
   {
        try {    if ( e.target.className.search('action-') != -1 ) context = 1  } catch (ex) {}
   }
   if ( !e.metaKey && !e.ctrlKey && $(this).hasClass('freeSelectMode')   && window.dragstop != 1 && context == 0 ) {
        $('.free-selected').removeClass('free-selected')
        $('.ui-selected').removeClass('ui-selected')
        $('.ui-selecting').removeClass('ui-selecting')
        $('#lab-viewport').removeClass('freeSelectMode')
        lab_topology.clearDragSelection()
        if (((ROLE == 'ROLE_TEACHER' && AUTHOR == 1) || (ROLE != 'ROLE_USER' && ROLE !='ROLE_TEACHER')) && EDITION ==1 && LOCK == 0 ) {
        i//f ((ROLE != 'ROLE_USER') &&  LOCK == 0  ) {
              lab_topology.setDraggable($('.node_frame, .network_frame, .customShape'), true)
        }
   }
   if ( $('.ui-selected').length < 1 ) $('#lab-viewport').removeClass('freeSelectMode')

   //if ( !$(this).parent().hasClass('customText') && !$(this).hasClass('customText')) { $('p').blur() ; $('p').focusout() ;}
   if ( $(e.target).is('p.editable') == false ) { $('p').blur() ; $('p').focusout() ;}
   window.dragstop = 0
   //lab_topology.repaintEverything()
});


$(document).on('click', '.customShape', function (e) {
        var node = $(this)
        var isFreeSelectMode = $("#lab-viewport").hasClass("freeSelectMode")
         if ( e.metaKey || e.ctrlKey  ) {
        node.toggleClass('ui-selected')
        updateFreeSelect(e,node)
        e.preventDefault();
        } else {
                 if (!node.hasClass('ui-selecting') && !node.hasClass('ui-selected')  && isFreeSelectMode ) {
                     $('.free-selected').removeClass('free-selected')
                     $('.ui-selected').removeClass('ui-selected')
                     $('.ui-selecting').removeClass('ui-selecting')
                     $('#lab-viewport').removeClass('freeSelectMode')
                     lab_topology.clearDragSelection()
                     if (((ROLE == 'ROLE_TEACHER' && AUTHOR == 1) || (ROLE != 'ROLE_USER' && ROLE !='ROLE_TEACHER')) && EDITION ==1 && LOCK == 0 ) {
                     //if ((ROLE != 'ROLE_USER') &&  LOCK == 0  ) {
                          lab_topology.setDraggable($('.node_frame, .network_frame, .customShape'), true)
                     }
                     e.preventDefault();
                     e.stopPropagation();
                 }
        }
});

$(document).on('mousedown', '.network_frame, .node_frame, .customShape', function (e) {
          if ( e.which == 1 ) {
          $('.select-move').removeClass('select-move')
          lab_topology.clearDragSelection()
          }
});

// Reset Lab Zoom
$(document).on('click', '.sidemenu-zoom', function (e) {
    var zoom=1
    setZoom(zoom,lab_topology,[0.0,0.0])
    $('#lab-viewport').width(($(window).width()-40) / zoom)
    $('#lab-viewport').height($(window).height() / zoom);
    $('#lab-viewport').css({top: 0,left: 40,position: 'absolute'});
    //setZoom(zoom,lab_topology,[0.0,0.0])
    $('#zoomslide').slider({value:100})
});

//show context menu when node is off
$(document).on('click', '.node.node_frame a', function (e) {

    var node = $(this).parent();
    var node_id = node.attr('data-path');
    var status = parseInt(node.attr('data-status'));
    var $labViewport = $("#lab-viewport")
        , isFreeSelectMode = $labViewport.hasClass("freeSelectMode")


    if ( e.metaKey || e.ctrlKey  ) {
        node.toggleClass('ui-selected')
        updateFreeSelect(e,node)
        e.preventDefault();
        return ;
    }

    //if (islinkActive() || isFreeSelectMode ) return true;
    if (isFreeSelectMode ) {
       e.preventDefault();
       return true;
    }

    if ( node.hasClass('dragstopped') && node.removeClass('dragstopped') ) {
          e.preventDefault();
          return true ;
    }

    if (!status) {

        e.preventDefault();

        $.when(getNodes(node_id))
            .then(function (node) {

                if (EDITION == 0) {
                    if (node.type != "switch") {
                        var network = '<li><a class="action-nodestart menu-manage" data-path="' + node_id +
                        '" data-name="' + node.name + '" href="#"><i class="glyphicon glyphicon-play"></i> Start</a></li>';
                        printContextMenu(node.name, network, e.pageX, e.pageY,false,"menu");
                    }
                }
                if (((ROLE == 'ROLE_TEACHER' && AUTHOR == 1) || (ROLE != 'ROLE_USER' && ROLE !='ROLE_TEACHER')) && EDITION ==1 && LOCK == 0 ) {
                //if  ((ROLE != 'ROLE_USER') &&  LOCK == 0  ) {
                    var network = '<li><a style="display: block;" class="action-nodeedit " data-path="' + node_id +
                     '" data-name="' + node.name + '" href="#"><i class="glyphicon glyphicon-edit"></i> Edit</a></li>';
                     printContextMenu(node.name, network, e.pageX, e.pageY,false,"menu");
                }
            })
            .fail(function (message) {
                addMessage('danger', message);
            });

        return false;
    }

})

//show context when node is started and has multiple console
$(document).on('click', '.openControlProtocolMenu', function (e) {
    var node_id = $(this).attr("id");

    e.preventDefault();

    $.when(getNodes(node_id))
        .then(function (node) {
                var contextBody ="";
                for(let controlProtocol of node.console) {
                    contextBody += '<li><a href="/instances/' + node.uuid +'/view/' + controlProtocol+ '" target="_blank">'+ controlProtocol +'</a></li>';
                }

                printContextMenu(node.name, contextBody, e.pageX, e.pageY,false,"menu");
        })
        .fail(function (message) {
            addMessage('danger', message);
        });

})

$(document).on('submit', '#editConn', function (e) {
    e.preventDefault();  // Prevent default behaviour
    var lab_filename = $('#lab-viewport').attr('data-path');
    var form_data = form2Array('editConn');
    var connection = form_data['networkId'];
    var connector = form_data['connector'];
    var connector_label = form_data['connector_label'];
    $.when(editConnection(connection, connector, connector_label) ).done( function () {
        $.when(editConnection(connection, connector, connector_label)).done( function () {
        //$.when(setNetworkiVisibility( networkId , 0 )).done( function () {
            $(e.target).parents('.modal').attr('skipRedraw', true);
            $(e.target).parents('.modal').modal('hide');
        //});
        });
    });
})

$(document).on('submit', '#addConn', function (e) {
    e.preventDefault();  // Prevent default behaviour
    var lab_filename = $('#lab-viewport').attr('data-path');
    var form_data = form2Array('addConn');
    console.log(form_data);
    //alert ( JSON.stringify( form_data) )
    var srcType = ( ( (form_data['srcConn']+'').search("serial")  != -1 ) ? 'serial' : 'ethernet' )
    var dstType = ( ( (form_data['dstConn']+'').search("serial")  != -1 ) ? 'serial' : 'ethernet' )
    var connector = form_data['connector'];
    var connector_label = form_data['connector_label'];
    // Get src dst type information and check compatibility
    if ( srcType != dstType )  {
         addModalError("Serial and Ethernet cannot be interconnected !!!!" )
         return
    }
    if ( form_data['srcNodeType'] == 'network' && form_data['dstNodeType'] == 'network' ) {
         addModalError("networks cannot be interconnected !!!!" )
         return
    }
    // nonet - nono - netnet
    if ( form_data['srcNodeType'] == 'node' && form_data['dstNodeType'] == 'node' ) {
         if ( srcType == 'serial' ) {
          /// create link S2S between nodes
             //alert ( ' Need to build S2S between Node' + form_data['srcNodeId'] + ' ' + form_data['srcConn'].replace(',serial','') +' and Node' + form_data['dstNodeId'] + ' ' + form_data['dstConn'].replace(',serial','') )
             var node1 = form_data['srcNodeId']
             var iface1 = form_data['srcConn'].replace(',serial','')
             var node2 = form_data['dstNodeId']
             var iface2 = form_data['dstConn'].replace(',serial','')
             $.when(setNodeInterface(node1, node2 + ':' + iface2 , iface1)).done( function () {
                  $(e.target).parents('.modal').attr('skipRedraw', true);
                  $(e.target).parents('.modal').modal('hide');
             });
         } else {
             var bridgename = $('#node'+form_data['srcNodeId']).attr('data-name') + 'iface_' + form_data['srcConn'].replace(',ethernet','')
             var offset = $('#node' + form_data['srcNodeId'] ).offset()
             var node1 = form_data['srcNodeId']
             var iface1 = form_data['srcConn'].replace(',ethernet','')
             var type1 = form_data['srcElementType']
             var node2 = form_data['dstNodeId']
             var iface2 = form_data['dstConn'].replace(',ethernet','')
             var type2 = form_data['dstElementType']
             //$.when(setNetwork(bridgename, offset.left + 20, offset.top + 40)).then( function (response) {
                  //var networkId = response.data.id;
                  //logger(1, 'Link DEBUG: new network created ' + networkId);
                  $.when(getConnection()).done(function (response){
                    var connection = response.data.connection;
                    if (type1 == "switch" || type2 == "switch") {
                        $.when(setNodeInterface(node1, iface1, 'none', connection, connector, connector_label) ).done( function () {
                            $.when(setNodeInterface(node2, iface2, 'none', connection, connector, connector_label)).done( function () {
                            //$.when(setNetworkiVisibility( networkId , 0 )).done( function () {
                                $(e.target).parents('.modal').attr('skipRedraw', true);
                                $(e.target).parents('.modal').modal('hide');
                            //});
                            });
                        });
                    }
                    else {
                        $.when(getVlan()).done(function (response){
                            var vlan = response.data.vlan;
                            $.when(setNodeInterface(node1, iface1, vlan, connection, connector, connector_label) ).done( function () {
                                $.when(setNodeInterface(node2, iface2, vlan, connection, connector, connector_label)).done( function () {
                                //$.when(setNetworkiVisibility( networkId , 0 )).done( function () {
                                    $(e.target).parents('.modal').attr('skipRedraw', true);
                                    $(e.target).parents('.modal').modal('hide');
                                //});
                                });
                            });
                        })
                    }
            });
             //});

         }

    } else {
        if (  form_data['srcNodeType'] == 'node' ) {
             var node = form_data['srcNodeId']
             var iface = form_data['srcConn'].replace(',ethernet','')
             var bridge = form_data['dstNodeId']
        } else {
             var node = form_data['dstNodeId']
             var iface = form_data['dstConn'].replace(',ethernet','')
             var bridge = form_data['srcNodeId']
       }
       $.when(setNodeInterface(node, bridge, iface)).done( function () {
                $(e.target).parents('.modal').attr('skipRedraw', true);
                $(e.target).parents('.modal').modal('hide');
       });
   }

});


/**
 *
 * @returns {*}
 */
function detachNodeLink() {

            if (window.conn || window.startNode) {
                var source = $('#inner').attr('data-source');
                $('#inner').remove();
                $('.link_selected').removeClass('link_selected');
                $('.startNode').removeClass('startNode');
                lab_topology.detach(window.conn);
                delete window.startNode;
                delete window.conn;
            }


}

// CPULIMIT Toggle

$(document).on('change','#ToggleCPULIMIT', function (e) {
 if  ( e.currentTarget.id == 'ToggleCPULIMIT' ) {
        var status=$('#ToggleCPULIMIT').prop('checked');
         if ( status != window.cpulimit ) setCpuLimit (status);
 }
});

// UKSM Toggle

$(document).on('change','#ToggleUKSM', function (e) {
 if  ( e.currentTarget.id == 'ToggleUKSM' ) {
        var status =$('#ToggleUKSM').prop('checked')
        if ( status != window.uksm ) setUksm(status);
 }
});

// KSM Toggle

$(document).on('change','#ToggleKSM', function (e) {
 if  ( e.currentTarget.id == 'ToggleKSM' ) {
        var status =$('#ToggleKSM').prop('checked')
        if ( status != window.ksm ) setKsm(status);
 }
});

// uploaa a simple node config
// Import labs
$(document).on('click', '.action-upload-node-config', function (e) {
    logger(1, 'DEBUG: action = import');
    printFormUploadNodeConfig($('#list-folders').attr('data-path'));
});

$(document).on('submit', '#form-upload-node-config', function (e) {
     e.preventDefault();
     var node_config = $('input[name="upload[file]"]')[0].files[0];
     logger( 1 , node_config ) ;
     var reader = new FileReader();
     reader.onload = function(){
          var text = reader.result;
          $('#nodeconfig').val(text) ;
     };
     reader.readAsText(node_config);
     $('.upload-modal').modal('hide');
});

// Generic Toggle Checknox
$(document).on('click','input[type=checkbox]', function (e) {
	if ( e.currentTarget.value == 0 ) {
		e.currentTarget.value = 1;
	} else {
		e.currentTarget.value = 0;
	}
});

$(document).on('click', '.configured-nodes-checkbox', function(e){
    var id = $(this).attr('data-path')
    setNodeData(id);
});

$(document).on('click','.action-nightmode', function(e){
  $('.action-nightmode').replaceWith('<a class="action-lightmode" href="javascript:void(0)" title="' + MESSAGES[236] + '"><i class="fas fa-sun"></i>'+MESSAGES[236]+'</a>')
  $('#lab-viewport').css('background-image','url(/build/editor/images/grid-dark.png)');
  $('.node_name').css('color','#b8c7ce')
  $('.network_name').css('color','#b8c7ce')
  $.cookie('topo', 'dark', {
      expires: 90
  });
});


$(document).on('click','.action-lightmode', function(e){
  $('.action-lightmode').replaceWith('<a class="action-nightmode" href="javascript:void(0)" title="' + MESSAGES[235] + '"><i class="fas fa-moon"></i>'+MESSAGES[235]+'</a>')
  $('#lab-viewport').css('background-image','url(/build/editor/images/grid.png)');
  $('.node_name').css('color','#333')
  $('.network_name').css('color','#333')
  $.cookie('topo', 'light', {
      expires: 90
  });
});
