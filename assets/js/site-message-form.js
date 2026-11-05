import EasyMDE from 'easymde';
import 'easymde/dist/easymde.min.css';
import '../css/site-message-form.css';
import { emojiShortcodeToUnicode } from './components/Editor2/themes/default/js/emoji';

// Markdown editor for the site message form (new/edit). Mirrors the shared
// configuration used by the lab/subject editors so the preview behaves the
// same as the server-side rendering (GFM + :emoji: shortcodes).
function initSiteMessageEditor() {
    var textarea = document.getElementById('site_message_message');
    if (!textarea) {
        return;
    }

    var editor = new EasyMDE({
        element: textarea,
        minHeight: '200px',
        status: false,
        autosave: { enabled: false },
        forceSync: true,
        // Keep the side-by-side preview inside the message field instead of
        // turning the editor into a fullscreen overlay of the page.
        sideBySideFullscreen: false,
        toolbar: ['bold', 'italic', 'strikethrough', 'heading', '|', 'code', 'quote', '|',
                  'unordered-list', 'ordered-list', 'clean-block', '|', 'link', 'image',
                  'table', '|', 'preview', 'side-by-side'],
        // Interpret :emoji: shortcodes in the preview, on the raw markdown
        // and outside code blocks, before the usual marked rendering.
        previewRender: function (text, previewElement) {
            return this.parent.markdown(emojiShortcodeToUnicode(text));
        }
    });

    // The textarea is hidden by CodeMirror: HTML5 validation on the required
    // attribute would silently block the submission, so validate here instead.
    textarea.removeAttribute('required');

    // Scope the layout CSS (single-line toolbar, inline preview) to this editor.
    var wrapper = editor.codemirror.getWrapperElement();
    if (wrapper.parentNode) {
        wrapper.parentNode.classList.add('site-message-editor');
    }

    var form = textarea.form;
    if (form) {
        form.addEventListener('submit', function (event) {
            if (editor.value().trim() !== '') {
                return;
            }
            event.preventDefault();
            var container = textarea.parentNode || textarea.closest('.form-group');
            if (!container.querySelector('.site-message-required')) {
                var source = document.getElementById('site-message-required-text');
                var error = document.createElement('div');
                error.className = 'invalid-feedback d-block site-message-required';
                error.textContent = source ? source.textContent : 'This value should not be blank.';
                container.appendChild(error);
            }
        });
        editor.codemirror.on('change', function () {
            var error = form.querySelector('.site-message-required');
            if (error && editor.value().trim() !== '') {
                error.remove();
            }
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSiteMessageEditor);
} else {
    initSiteMessageEditor();
}
