import RFB from '@novnc/novnc/core/rfb'

var userRating = document.querySelector('.js-user-rating');
var host = userRating.dataset.host;
var port = userRating.dataset.port;
var path = userRating.dataset.path;
console.log('Connecting to ' + host + ':' + port + '/' + path + '...');
var rfb = new RFB($('#noVNC_screen')[0], host + ':' + port + '/' + path, {
    scaleViewport: true,
    clipViewport: true,
});
rfb.scaleViewport = true;
console.log("in vnc.js");

    var el = document.getElementById('noVNC_button')
            clickerFn = function() {
                if (el.value=="CtrlAltDel") {
                    console.log("click "+el.value);
                    rfb.sendCtrlAltDel();
                }
                else console.log("Alert");
            }

        el.addEventListener('click', clickerFn);

