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

