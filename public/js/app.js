// pageLoad is defined inline in base.html.twig <head> so it is always available
// before any page-specific script runs. This guard prevents accidental re-definition.
if (!window.pageLoad) {
    (function () {
        var _fired = false;
        document.addEventListener('turbo:before-visit', function () { _fired = false; });
        document.addEventListener('turbo:load',         function () { _fired = true;  });
        window.pageLoad = function (fn) {
            document.addEventListener('turbo:load', fn);
            if (_fired) fn();
        };
    }());
}

function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('svg');
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = '<path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7 7 0 0 0-2.79.588l.77.771A6 6 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486z"/><path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829"/><path d="M3.35 5.47q-.27.24-.518.487A13 13 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7 7 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709z"/><path d="m14.854 14.146-13-13-.708.708 13 13z"/>';
    } else {
        input.type = 'password';
        icon.innerHTML = '<path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>';
    }
}

// Lightbox for post images
pageLoad(function () {
    let overlay = document.getElementById('lightbox-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'lightbox-overlay';
        overlay.innerHTML = '<img id="lightbox-img" src="" alt="">';
        document.body.appendChild(overlay);
        overlay.addEventListener('click', function () {
            overlay.classList.remove('active');
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') overlay.classList.remove('active');
        });
    }

    document.querySelectorAll('#posts-container img').forEach(function (img) {
        img.style.cursor = 'zoom-in';
        img.addEventListener('click', function () {
            document.getElementById('lightbox-img').src = img.src;
            document.getElementById('lightbox-img').alt = img.alt;
            overlay.classList.add('active');
        });
    });
});

// Unsaved changes guard
(function () {
    var trackedForm = null;
    var initialSnapshot = '';
    var bypassCheck = false;
    var pendingUrl = null;
    var bsModal = null;

    function getSnapshot(form) {
        var pairs = [];
        Array.from(form.elements).forEach(function (el) {
            if (!el.name || el.disabled) return;
            if (el.type === 'file' || el.type === 'submit' || el.type === 'button') return;
            if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
            pairs.push(el.name + '=' + el.value);
        });
        return pairs.join('&');
    }

    function isDirty() {
        if (!trackedForm) return false;
        if (window.unsavedChangesForced) return true;
        return getSnapshot(trackedForm) !== initialSnapshot;
    }

    function ensureModal() {
        var existing = document.getElementById('unsaved-modal');
        if (existing) return existing;
        var div = document.createElement('div');
        div.innerHTML =
            '<div class="modal fade" id="unsaved-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">' +
                '<div class="modal-dialog modal-dialog-centered">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header border-0 pb-0">' +
                            '<h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Ungespeicherte Änderungen</h5>' +
                        '</div>' +
                        '<div class="modal-body">Es gibt ungespeicherte Änderungen. Möchtest du die Seite wirklich verlassen?</div>' +
                        '<div class="modal-footer border-0">' +
                            '<button type="button" class="btn btn-secondary" id="unsaved-stay">Auf der Seite bleiben</button>' +
                            '<button type="button" class="btn btn-danger" id="unsaved-leave">Trotzdem verlassen</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
        document.body.appendChild(div.firstElementChild);
        return document.getElementById('unsaved-modal');
    }

    // Global listeners — added once, survive Turbo navigations
    document.addEventListener('turbo:before-visit', function (e) {
        if (bypassCheck || !isDirty()) return;
        e.preventDefault();
        pendingUrl = e.detail.url;
        if (bsModal) bsModal.show();
    });

    window.addEventListener('beforeunload', function (e) {
        if (bypassCheck || !isDirty()) return;
        e.preventDefault();
        e.returnValue = '';
    });

    function getSaveButtons(form) {
        var btns = [];
        Array.from(form.querySelectorAll('button[type=submit]')).forEach(function (b) { btns.push(b); });
        if (form.id) {
            Array.from(document.querySelectorAll('button[form="' + form.id + '"]')).forEach(function (b) { btns.push(b); });
        }
        return btns;
    }

    function updateActionButtons(saveButtons, cancelLinks) {
        var dirty = isDirty();
        saveButtons.forEach(function (btn) {
            btn.disabled = !dirty;
        });
        cancelLinks.forEach(function (link) {
            link.classList.toggle('disabled', !dirty);
            link.setAttribute('aria-disabled', dirty ? 'false' : 'true');
            link.setAttribute('tabindex', dirty ? '0' : '-1');
        });
    }

    // Allow page-specific JS to mark the form as dirty (e.g. Konzert song picker)
    window.markFormDirty = function () {
        window.unsavedChangesForced = true;
        if (trackedForm) updateActionButtons(_saveButtons, _cancelLinks);
    };

    var _saveButtons = [];
    var _cancelLinks = [];

    pageLoad(function () {
        // Reset per-page state on each navigation
        trackedForm = null;
        initialSnapshot = '';
        bypassCheck = false;
        window.unsavedChangesForced = false;
        pendingUrl = null;
        bsModal = null;
        _saveButtons = [];
        _cancelLinks = [];

        var form = document.querySelector('form[data-unsaved-check]');
        if (!form) return;

        var modalEl = ensureModal();
        bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        trackedForm = form;
        initialSnapshot = getSnapshot(form);

        _saveButtons = getSaveButtons(form);
        _cancelLinks = Array.from(document.querySelectorAll('[data-unsaved-cancel]'));

        // Disable save/cancel initially (no changes yet)
        updateActionButtons(_saveButtons, _cancelLinks);

        // Re-evaluate on any input event within the form
        form.addEventListener('input',  function () { updateActionButtons(_saveButtons, _cancelLinks); });
        form.addEventListener('change', function () { updateActionButtons(_saveButtons, _cancelLinks); });

        document.getElementById('unsaved-stay').onclick = function () {
            bsModal.hide();
            pendingUrl = null;
        };

        document.getElementById('unsaved-leave').onclick = function () {
            var url = pendingUrl;
            bsModal.hide();
            bypassCheck = true;
            trackedForm = null;
            pendingUrl = null;
            if (url) window.location.href = url;
        };

        form.addEventListener('submit', function () {
            bypassCheck = true;
            trackedForm = null;
        });
    });
}());

// Re-initialize Bootstrap dropdowns after each Turbo navigation.
// Turbo replaces the <body> on each visit, so new DOM elements have no
// Bootstrap Dropdown instance yet; lazy initialization on first click
// is unreliable under Turbo and causes intermittent failures.
pageLoad(function () {
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function (el) {
        bootstrap.Dropdown.getOrCreateInstance(el);
    });
});
