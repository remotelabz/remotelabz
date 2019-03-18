import RFB from '@novnc/novnc/core/rfb'

var userRating = document.querySelector('.js-user-rating');
var host = userRating.dataset.host;
var port = userRating.dataset.port;
let rfb = new RFB($('#noVNC_screen')[0], host + ':' + port);