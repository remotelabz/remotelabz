import { render } from 'timeago.js';

const nodes = document.querySelectorAll('.timeago');

if (nodes.length > 0) {
    // use render method to render nodes in real time
    render(nodes);
}