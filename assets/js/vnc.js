import RFB from '@novnc/novnc/core/rfb';

var rfb;
var WindowObjectReference = null;

function openFullscreen() {
    if (WindowObjectReference == null || WindowObjectReference.closed) {
        WindowObjectReference = window.open(window.location.href + '?size=fullscreen',
            "OpenVNCFullscreen" + "{{device.name}}", "resizable=yes,scrollbars=no,status=no,location=no,menubar=no,toolbar=no,width="+rfb._fbWidth+",height="+rfb._fbHeight);

        if (WindowObjectReference && rfb) {
            rfb.disconnect();

            WindowObjectReference.onbeforeunload = function () {
                //console.log('Unload window')
                var userRating = document.querySelector('.js-user-rating');
                let protocol = userRating.dataset.protocol;
                let host = userRating.dataset.host;
                var port = userRating.dataset.port;
                var path = userRating.dataset.path;
                connectToVNC(protocol, host, port, path, {
                    scaleViewport: true,
                    clipViewport: true,
                });
            };
        }
    }
    else {
        WindowObjectReference.focus();
    };
}

function connectToVNC(protocol, host, port, path, options = {}) {
    const url = protocol + '://' + host + ':' + port + '/' + path;
    //console.log('Connecting to ' + url);
    rfb = new RFB(document.getElementById('noVNCScreen'), url, options);
    rfb.scaleViewport = true;
    rfb._fbWidth
    rfb.addEventListener('connect', function () {
        reconnectButton.setAttribute("disabled", "disabled");
        //console.log("Event: RFB connected");
    });
    rfb.addEventListener('disconnect', function () {
        //console.log("Event: RFB disconnect");
        rfb = null;
        reconnectButton.removeAttribute("disabled");
    });
}

var openFullscreenButton = document.getElementById('openFullscreenButton');
if (openFullscreenButton) {
    openFullscreenButton.onclick = () => {
        openFullscreen();
    }
}

var ctrlAltDelButton = document.getElementById('CtrlAltDelButton');
if (ctrlAltDelButton) {
    ctrlAltDelButton.onclick = () => {
        rfb.sendCtrlAltDel();
        //console.log("User action: sent Ctrl+Alt+Del");
    }
}

var reconnectButton = document.getElementById('ReconnectButton');
if (reconnectButton) {
    reconnectButton.onclick = () => {
        //console.log("User action: reconnect to VNC");
        reconnectButton.setAttribute("disabled", "disabled");
        let userRating = document.querySelector('.js-user-rating');
        let protocol = userRating.dataset.protocol;
        let host = userRating.dataset.host;
        let port = userRating.dataset.port;
        let path = userRating.dataset.path;
        //connectToVNC(protocol, host, port, path, {
        //    scaleViewport: true,
        //    clipViewport: true,
        //});
    }
}

var userRating = document.querySelector('.js-user-rating');
let protocol = userRating.dataset.protocol;
let host = userRating.dataset.host;
var port = userRating.dataset.port;
var path = userRating.dataset.path;
connectToVNC(protocol, host, port, path, {
    scaleViewport: true,
    clipViewport: true,
});