export default function(element, host, port) {
    return new RFB(element, host + ':' + port);
}