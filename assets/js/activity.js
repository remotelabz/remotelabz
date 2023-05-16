import SimpleMDE from 'simplemde';

var mde = new SimpleMDE({
    element: $('.mde')[0],
    forceSync: true
});

var originalContent = mde.value();

$("#activity_reset").click(function() {
    mde.value(originalContent);
});