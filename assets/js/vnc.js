import RFB from '@novnc/novnc/core/rfb'

var rfb;

function connectToVNC(host, port, path) {
    console.log('Connecting to ' + host + ':' + port + '/' + path + '...');
    rfb = new RFB($('#noVNC_screen')[0], host + ':' + port + '/' + path, {
        scaleViewport: true,
        clipViewport: true,
    });
    rfb.scaleViewport = true;
    rfb.addEventListener('connect', function () {
        reconnectButton.setAttribute("disabled", "disabled");
        console.log("Event: RFB connected");
    });
    rfb.addEventListener('disconnect', function () {
        console.log("Event: RFB disconnect");
        rfb = null;
        reconnectButton.removeAttribute("disabled");
    });
}

var el = document.getElementById('CtrlAltDelButton');
el.onclick = () => {
    rfb.sendCtrlAltDel();
    console.log("User action: sent Ctrl+Alt+Del");
}

var reconnectButton = document.getElementById('ReconnectButton');
reconnectButton.onclick = () => {
    console.log("User action: reconnect to VNC");
    reconnectButton.setAttribute("disabled", "disabled");
    var userRating = document.querySelector('.js-user-rating');
    var host = userRating.dataset.host;
    var port = userRating.dataset.port;
    var path = userRating.dataset.path;
    connectToVNC(host, port, path);
}

var userRating = document.querySelector('.js-user-rating');
var host = userRating.dataset.host;
var port = userRating.dataset.port;
var path = userRating.dataset.path;
connectToVNC(host, port, path);