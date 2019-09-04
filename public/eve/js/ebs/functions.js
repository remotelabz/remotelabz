
function setStartupData(id, set_data = false, config = null, name = null) {
    var lab_filename = $('#lab-viewport').attr('data-path');
    var form_data = form2ArrayByRow('node', id);

    var promises = [];
    logger(1, 'DEBUG: posting form-node-edit form.');
    var url = '/api/labs' + lab_filename + '/nodes/' + id;
    var type = 'PUT';
    form_data['id'] = id;
    form_data['count'] = 1;
    form_data['postfix'] = 0;
    for (var i = 0; i < form_data['count']; i++)
    {
        if(set_data) {
            form_data['config'] = config;
            form_data['name'] = name;
        }

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
                    addMessage(data['status'], data['message']);
                    // Close the modal
                    if(set_data) {
                        $("#node" + id + " .node_name").html('<i class="node' + id + '_status glyphicon glyphicon-stop"></i>' + form_data['name'])
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
        promises.push(request);
    }

    $.when.apply(null, promises).done(function () {
        logger(1,"data is sent");
    });
    return false;
}

function initializeEditor(theme = 'cobalt', mode = 'cisco_ios', font_size = '12px', value = null)
{
    $('#editor').css('font-size', font_size)
    var editor = ace.edit("editor");
    editor.setTheme("ace/theme/" + theme)
    editor.getSession().setMode("ace/mode/" + mode)
    editor.setHighlightActiveLine(true);
    editor.setOptions({
        enableBasicAutocompletion: true,
        enableLiveAutocompletion: false
    });
    editor.$blockScrolling = Infinity;
    if(value != null) {
        editor.setValue(value, 1)
    }
    editor.focus();
    editor.gotoLine(Infinity, editor.getSession().getValue().split("\n").length);
}

function initEditor () {
    var mode = readCookie('ace_mode') ? readCookie('ace_mode') : 'cisco_ios';
    var theme = readCookie('ace_theme') ? readCookie('ace_theme') : 'cobalt';
    var font_size = readCookie('ace_font_size') ? readCookie('ace_font_size') : '12px';
    var editor = $('#editor');
    var textarea = $('#nodeconfig');
    var ace_conf_panel = $('#ace-conf-panel');


    editor.show();
    console.log(textarea.val())
    textarea.hide();
    ace_conf_panel.show();
    $('#editor').css('height', $('#nodeconfig').height());
    initializeEditor(theme, mode, font_size, textarea.val());
    createCookie("editor", "ace");
}

function initTextarea() {
    var editor = $('#editor');
    var textarea = $('#nodeconfig');
    var ace_conf_panel = $('#ace-conf-panel');

    textarea.show().val(ace.edit("editor").getValue());
    textarea.focus();
    destroyEditor();
    ace_conf_panel.hide();
    editor.empty().hide().removeAttr('class');
    eraseCookie("editor");
}

function destroyEditor()
{
    var editor = ace.edit("editor");
    editor.destroy();
}

function createCookie(name, value, days = 30)
{
    var expires = "";
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days*24*60*60*1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + value + expires + "; path=/";
}

function readCookie(name)
{
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');

    for(var i=0;i < ca.length;i++)
    {
        var c = ca[i];
        while (c.charAt(0)==' ')
        {
            c = c.substring(1, c.length);
        }
        if (c.indexOf(nameEQ) == 0)
        {
            return c.substring(nameEQ.length,c.length);
        }
    }
    return null;
}

function eraseCookie(name)
{
    createCookie(name,"",-1);
}

function saveEditorLab(formId = 'form-node-config', bool)
{
    if($('#toggle_editor').is(':checked')) {
        var editor_data = ace.edit('editor').getValue();
        var data = $(document).find('#nodeconfig').val(editor_data);
    } else {
        var data = $(document).find('#nodeconfig');
    }
        var all_devices_list = $('.action-configget');
        all_devices_list.each(function () {
            // must rework it
            var el = $(this).parent('li');
            if($(this).hasClass('selected')) {
                if(data.val().length > 0) { //make blue
                    $(this).find('img').removeClass('grayscale');
                    $(this).parent('li').find('input.change_config_status').removeAttr("disabled");
                } else {// make gray
                    $(this).find('img').addClass('grayscale');
                    el.find('.absolute-right').find('span.align-flash').html('')
                    el.find('.absolute-right').find('input.change_config_status').attr("checked", false)
                    el.find('.absolute-right').find('input.change_config_status').attr("disabled", true);
                    $(this).parent('li').find('.absolute-right').find('.checkbox-switch').find('.checkbox-slider-on').addClass('checkbox-slider-off');
                    $(this).parent('li').find('.absolute-right').find('.checkbox-switch').find('.checkbox-slider-off').removeClass('checkbox-slider-on');
                }
            }
        })
    
    var lab_filename = $('#lab-viewport').attr('data-path');
    var form_data = form2Array('config');
    var url = '/api/labs' + lab_filename + '/configs/' + form_data['id'];
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
}

function zoomToDefault(instance){
    var zoom=1;
    //setZoom(zoom,lab_topology,[ 0.0, 0.0 ]);
    var viewport = $('#lab-viewport');
    viewport.width(($(window).width()-40)/zoom);
    viewport.height($(window).height()/zoom);
    $('#lab-viewport').css({'top': 0 ,'left': 40, 'position': 'absolute'});

    setZoom(zoom,instance,[0.0,0.0]);
    $('#zoomslide').slider({ value:100 });
}

function adjustZoom(lab_topology, scroll_top = null, scroll_left = null)
{
    var zoomvalue = $('#zoomslide').slider("value")/100;
    var viewport =  $('#lab-viewport');
    if(zoomvalue) {
        setZoom(zoomvalue, lab_topology, [0.0, 0.0], $('#context-menu')[0])
        viewport.scrollTop(scroll_top || $('#lab-viewport').scrollTop());
        viewport.scrollLeft(scroll_left || $('#lab-viewport').scrollLeft());
    }
    delete window.scroll_left;
    delete window.scroll_top;
}

function hideContextmenu()
{
    if(contextMenuOpen){
        $("#context-menu").remove();
        adjustZoom(lab_topology, $('#lab-viewport').scrollTop(), $('#lab-viewport').scrollLeft());
        logger(1, "DEBUG: Drop Down menu closed");
    }
    contextMenuOpen = false;
}

function resolveZoom(value, scroll)
{
    window.scroll_left = $('#lab-viewport').scrollLeft();
    window.scroll_top = $('#lab-viewport').scrollTop();

    if(scroll == 'left') {
        return Math.round(parseInt(value) * (100/$('#zoomslide').slider("value"))) + window.scroll_left;
    } else if(scroll == 'top') {
        return Math.round(parseInt(value) * (100/$('#zoomslide').slider("value"))) + window.scroll_top;
    }

    return Math.round(parseInt(value) * (100/$('#zoomslide').slider("value")))
}
