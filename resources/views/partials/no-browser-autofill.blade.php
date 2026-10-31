{{--
    Keep the browser's address book / password manager out of pharmacy fields.

    The application has its own item search and its own names. A saved login
    appearing over "Drug name" or the till search is never useful, and on a
    shared shop counter it can expose a password. Every ordinary form is marked
    autocomplete=off, and the fields also carry the hints password managers
    look for. The login page does not include this layout: it keeps
    autocomplete="username" / "current-password" so signing in still works.

    Dynamic purchase/sale lines are included through the MutationObserver, and
    focusin covers browsers that fill a field after the page has loaded.
--}}
<script data-no-browser-autofill>
(function () {
    'use strict';

    var skipped = {
        hidden: 1, checkbox: 1, radio: 1, submit: 1, button: 1, reset: 1,
        file: 1, color: 1, range: 1, date: 1, time: 1, 'datetime-local': 1,
        month: 1, week: 1
    };
    var textLike = {
        text: 1, search: 1, email: 1, tel: 1, url: 1, number: 1
    };

    function harden(input) {
        if (!input || input.tagName !== 'INPUT') return;
        if (input.closest && input.closest('[data-allow-autofill]')) return;

        var type = (input.getAttribute('type') || 'text').toLowerCase();

        // New-password fields may offer a strong password; they must never
        // offer the signed-in user's current one.
        if (type === 'password') {
            if (!input.getAttribute('autocomplete') || input.getAttribute('autocomplete') === 'off') {
                input.setAttribute('autocomplete', 'new-password');
            }

            return;
        }

        if (skipped[type] || !textLike[type]) return;

        if (!input.getAttribute('autocomplete') || input.getAttribute('autocomplete') === 'on') {
            input.setAttribute('autocomplete', 'off');
        }

        input.setAttribute('autocorrect', 'off');
        input.setAttribute('autocapitalize', 'off');
        input.setAttribute('spellcheck', 'false');

        input.setAttribute('data-lpignore', 'true');
        input.setAttribute('data-1p-ignore', 'true');
        input.setAttribute('data-bwignore', 'true');
        input.setAttribute('data-dashlane-ignore', 'true');
        input.setAttribute('data-protonpass-ignore', 'true');

        if (!input.getAttribute('data-form-type')) {
            input.setAttribute('data-form-type', 'other');
        }
    }

    function hardenForm(form) {
        if (!form || form.tagName !== 'FORM') return;
        if (form.hasAttribute('data-allow-autofill')) return;

        if (!form.getAttribute('autocomplete')) {
            form.setAttribute('autocomplete', 'off');
        }

        if (!form.getAttribute('data-form-type')) {
            form.setAttribute('data-form-type', 'other');
        }

        [].forEach.call(form.querySelectorAll('input'), harden);
    }

    function hardenTree(root) {
        if (!root || root.nodeType !== 1) return;

        if (root.tagName === 'INPUT') harden(root);
        if (root.tagName === 'FORM') hardenForm(root);

        if (!root.querySelectorAll) return;

        [].forEach.call(root.querySelectorAll('form'), hardenForm);
        [].forEach.call(root.querySelectorAll('input'), harden);
    }

    function hardenDocument() {
        hardenTree(document.body || document.documentElement);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hardenDocument);
    } else {
        hardenDocument();
    }

    // The till and purchase screens clone rows after load.
    if (window.MutationObserver && document.body) {
        new MutationObserver(function (mutations) {
            [].forEach.call(mutations, function (mutation) {
                [].forEach.call(mutation.addedNodes || [], hardenTree);
            });
        }).observe(document.body, { childList: true, subtree: true });
    }

    // Also runs before the browser can offer a suggestion on focus.
    document.addEventListener('focusin', function (event) {
        harden(event.target);
    }, true);
})();
</script>
