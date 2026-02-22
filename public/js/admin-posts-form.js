document.addEventListener('turbo:load', function () {
    const cfg = window.POSTS_FORM_CONFIG || {};

    // Initialize Quill Rich Text Editor
    const quill = new Quill('#quill-editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                [{ 'align': [] }],
                ['link', 'image'],
                [{ 'color': [] }, { 'background': [] }],
                ['clean']
            ]
        },
        placeholder: 'Schreiben Sie hier Ihren Text...'
    });

    const titleInput    = document.getElementById('post-title')    || document.getElementById('post_title');
    const subtitleInput = document.getElementById('post-subtitle') || document.getElementById('post_subtitle');
    const paragraphInput = document.getElementById('post-paragraph') || document.getElementById('post_paragraph');

    if (paragraphInput && paragraphInput.value) {
        quill.root.innerHTML = paragraphInput.value;
        // Sync back so preview always uses Quill's normalised HTML from the start
        paragraphInput.value = quill.root.innerHTML;
    }

    // Image rotation
    const currentImage  = document.getElementById('current-image');
    const rotationInput = document.getElementById('post_imageRotation');
    let currentRotation = cfg.imageRotation || 0;

    if (rotationInput) rotationInput.value = currentRotation;

    document.getElementById('rotate-left')?.addEventListener('click', function () {
        currentRotation = (currentRotation - 90) % 360;
        if (currentRotation < 0) currentRotation += 360;
        applyRotation();
    });

    document.getElementById('rotate-right')?.addEventListener('click', function () {
        currentRotation = (currentRotation + 90) % 360;
        applyRotation();
    });

    function applyRotation() {
        if (currentImage) currentImage.style.transform = 'rotate(' + currentRotation + 'deg)';
        if (rotationInput) rotationInput.value = currentRotation;
        updatePreviewPost();
    }

    const imageInput = document.getElementById('post-image') || document.getElementById('post_imageFile');

    if (imageInput) {
        imageInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            const reader = new FileReader();
            reader.onload = function (event) { openImageEditor(event.target.result, file.name, true); };
            reader.readAsDataURL(file);
        });
    }

    const layoutSelect    = document.getElementById('post-layout')  || document.getElementById('post_layout');
    const isMainCheckbox  = document.getElementById('post-is-main') || document.getElementById('post_isMain');
    const pageSelect      = document.getElementById('post-page')    || document.getElementById('post_page');
    const fullPagePreview = document.getElementById('full-page-preview');
    const titleCounter    = document.getElementById('title-counter');
    const subtitleCounter = document.getElementById('subtitle-counter');

    let currentPreviewImage = cfg.previewImageUrl || null;

    if (!titleInput || !subtitleInput || !paragraphInput || !imageInput || !layoutSelect || !isMainCheckbox || !pageSelect) {
        return;
    }

    updateCharCounter(titleInput, titleCounter);
    updateCharCounter(subtitleInput, subtitleCounter);

    setTimeout(loadPagePreview, 100);

    titleInput.addEventListener('input', function () {
        updateCharCounter(this, titleCounter);
        updatePreviewPost();
    });

    subtitleInput.addEventListener('input', function () {
        updateCharCounter(this, subtitleCounter);
        updatePreviewPost();
    });

    quill.on('text-change', function () {
        paragraphInput.value = quill.root.innerHTML;
        updatePreviewPost();
    });

    layoutSelect.addEventListener('change', updatePreviewPost);
    isMainCheckbox.addEventListener('change', updatePreviewPost);
    document.addEventListener('font:applied', updatePreviewPost);
    pageSelect.addEventListener('change', loadPagePreview);

    document.getElementById('post-form').addEventListener('submit', function () {
        paragraphInput.value = quill.root.innerHTML;
    });

    // ── Inline Image Editor ───────────────────────────────────────────────────
    let cropper = null;
    let cropOriginalFileName = '';
    let cropOriginalAspect = null;
    let updatingSize = false;
    let editorFromFileInput = false;
    let siblingOutputDims = null; // { w, h } of the sibling image, used as default output size

    const cropImage       = document.getElementById('crop-image');
    const widthInput      = document.getElementById('crop-output-width');
    const heightInput     = document.getElementById('crop-output-height');
    const keepRatio       = document.getElementById('crop-keep-ratio');
    const editorBody      = document.getElementById('image-editor-body');
    const editorHeader    = document.getElementById('editor-card-header');
    const previewHeader   = document.getElementById('preview-card-header');
    const previewContainer = document.getElementById('preview-container');

    function showEditor() {
        previewHeader.style.display  = 'none';
        previewContainer.style.display = 'none';
        editorHeader.style.display   = '';
        editorBody.style.display     = '';
    }

    function hideEditor() {
        editorHeader.style.display   = 'none';
        editorBody.style.display     = 'none';
        previewHeader.style.display  = '';
        previewContainer.style.display = '';
        if (cropper) { cropper.destroy(); cropper = null; }
    }

    function getSiblingImage() {
        const previewEl = fullPagePreview.querySelector('#' + previewPostId);
        if (!previewEl) return null;
        // Use DOM adjacency: previous sibling = we are second, next sibling = we are first
        const prevSib = previewEl.previousElementSibling;
        const nextSib = previewEl.nextElementSibling;
        const sibling = prevSib || nextSib;
        if (!sibling) return null;
        const img = sibling.querySelector('img');
        if (!img) return null;
        // position = 1-based index of the sibling in the row
        const position = prevSib ? 1 : 2;
        return { img, el: sibling, position };
    }

    /**
     * Measures how an image would be displayed on the real public page by
     * replicating its Bootstrap column structure in a hidden full-viewport element.
     * Since Bootstrap CSS is already loaded and media queries use viewport width,
     * this gives the exact same rendered size as on the real page.
     */
    function measureRealImageSize(sibResult, callback) {
        const { img, el } = sibResult;

        const wrap = document.createElement('div');
        wrap.style.cssText = 'position:fixed;left:-9999px;top:0;width:100vw;' +
                             'visibility:hidden;pointer-events:none;z-index:-9999;';

        const container = document.createElement('div');
        container.className = 'container';

        const row = document.createElement('div');
        row.className = 'row';

        const col = document.createElement('div');
        col.className = el.className; // e.g. 'col-md-6 mb-2'

        const testImg = document.createElement('img');
        testImg.className = img.className;    // e.g. 'img-fluid rounded'
        testImg.style.cssText = img.style.cssText; // copy inline style (max-width etc.)
        testImg.src = img.src;

        col.appendChild(testImg);
        row.appendChild(col);
        container.appendChild(row);
        wrap.appendChild(container);
        document.body.appendChild(wrap);

        function measure() {
            const w = testImg.offsetWidth  || img.naturalWidth;
            const h = testImg.offsetHeight || img.naturalHeight;
            callback(w, h);
            wrap.remove();
        }

        if (testImg.complete && testImg.naturalWidth) {
            // Already cached — wait 2 frames for layout to settle
            requestAnimationFrame(() => requestAnimationFrame(measure));
        } else {
            testImg.addEventListener('load', () => {
                requestAnimationFrame(() => requestAnimationFrame(measure));
            }, { once: true });
        }
    }

    function openImageEditor(dataUrl, fileName, fromFileInput) {
        if (!cropImage) return;
        cropOriginalFileName = fileName;
        editorFromFileInput  = !!fromFileInput;
        if (cropper) { cropper.destroy(); cropper = null; }
        document.querySelectorAll('[data-ratio]').forEach(b => b.classList.remove('active'));
        document.querySelector('[data-ratio="free"]')?.classList.add('active');

        // Show sibling image dimensions if available
        const sibInfoEl = document.getElementById('sibling-image-info');
        const sibDimsEl = document.getElementById('sibling-image-dims');
        const sibLabelEl = sibInfoEl ? sibInfoEl.querySelector('.small.text-muted') : null;
        const sibling   = getSiblingImage();
        siblingOutputDims = null;
        if (sibInfoEl && sibDimsEl) {
            if (sibling) {
                if (sibLabelEl) sibLabelEl.textContent = sibling.position + '. Bild:';
                sibInfoEl.style.display = 'none'; // hide until measured
                measureRealImageSize(sibling, (w, h) => {
                    sibDimsEl.textContent = w + ' × ' + h + ' px';
                    sibInfoEl.style.display = '';
                    siblingOutputDims = { w, h };
                    // Override output size fields with real page dimensions
                    if (widthInput)  widthInput.value  = w;
                    if (heightInput) heightInput.value = h;
                });
            } else {
                sibInfoEl.style.display = 'none';
            }
        }

        showEditor();
        cropImage.src = dataUrl;
        if (cropImage.complete && cropImage.naturalWidth) {
            setTimeout(initCropper, 50);
        } else {
            cropImage.onload = () => setTimeout(initCropper, 50);
        }
    }

    function initCropper() {
        if (cropper) { cropper.destroy(); }
        const naturalW = cropImage.naturalWidth;
        const naturalH = cropImage.naturalHeight;
        cropOriginalAspect = naturalW / naturalH;
        const origEl = document.getElementById('crop-original-size');
        if (origEl) origEl.textContent = '(' + naturalW + ' × ' + naturalH + ' px)';
        cropper = new Cropper(cropImage, {
            viewMode: 1,
            autoCropArea: 1,
            responsive: true,
            crop: function (e) {
                if (updatingSize) return;
                const w = Math.round(e.detail.width);
                const h = Math.round(e.detail.height);
                const selEl = document.getElementById('crop-selection-size');
                if (selEl) selEl.textContent = w + ' × ' + h + ' px';
                updatingSize = true;
                widthInput.value  = w;
                heightInput.value = h;
                updatingSize = false;
            }
        });
        widthInput.value  = siblingOutputDims ? siblingOutputDims.w : naturalW;
        heightInput.value = siblingOutputDims ? siblingOutputDims.h : naturalH;
    }

    // Copy sibling dimensions into the output size fields
    document.getElementById('use-sibling-size')?.addEventListener('click', function () {
        const sibling = getSiblingImage();
        if (!sibling) return;
        measureRealImageSize(sibling, (w, h) => {
            widthInput.value  = w;
            heightInput.value = h;
        });
    });

    // Rotation
    document.getElementById('editor-rotate-left')?.addEventListener('click', () => cropper?.rotate(-90));
    document.getElementById('editor-rotate-right')?.addEventListener('click', () => cropper?.rotate(90));

    // Aspect ratio
    document.querySelectorAll('[data-ratio]').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('[data-ratio]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            if (!cropper) return;
            const val = this.dataset.ratio;
            if (val === 'free')          cropper.setAspectRatio(NaN);
            else if (val === 'original') cropper.setAspectRatio(cropOriginalAspect);
            else                         cropper.setAspectRatio(parseFloat(val));
        });
    });

    // Linked width/height inputs
    widthInput?.addEventListener('input', function () {
        if (updatingSize || !keepRatio?.checked || !cropper) return;
        const w = parseInt(this.value); if (!w) return;
        const d = cropper.getData();
        updatingSize = true;
        heightInput.value = Math.round(w / (d.width / d.height));
        updatingSize = false;
    });

    heightInput?.addEventListener('input', function () {
        if (updatingSize || !keepRatio?.checked || !cropper) return;
        const h = parseInt(this.value); if (!h) return;
        const d = cropper.getData();
        updatingSize = true;
        widthInput.value = Math.round(h * (d.width / d.height));
        updatingSize = false;
    });

    // Open editor for the already-saved image
    document.getElementById('crop-existing-btn')?.addEventListener('click', function () {
        const url = cfg.previewImageUrl;
        if (!url) return;
        const fileName = url.split('/').pop() || 'image.jpg';
        fetch(url)
            .then(r => r.blob())
            .then(blob => {
                const reader = new FileReader();
                reader.onload = e => openImageEditor(e.target.result, fileName, false);
                reader.readAsDataURL(blob);
            });
    });

    // Cancel
    document.getElementById('crop-cancel-btn')?.addEventListener('click', function () {
        if (editorFromFileInput) imageInput.value = '';
        hideEditor();
    });

    // Apply
    document.getElementById('crop-apply-btn')?.addEventListener('click', function () {
        if (!cropper) return;
        const outWidth  = parseInt(widthInput.value)  || undefined;
        const outHeight = parseInt(heightInput.value) || undefined;
        const canvas = cropper.getCroppedCanvas({
            width: outWidth, height: outHeight,
            imageSmoothingEnabled: true, imageSmoothingQuality: 'high'
        });
        canvas.toBlob(function (blob) {
            const file = new File([blob], cropOriginalFileName, { type: 'image/jpeg', lastModified: Date.now() });
            const dt = new DataTransfer();
            dt.items.add(file);
            imageInput.files = dt.files;
            currentPreviewImage = canvas.toDataURL('image/jpeg', 0.92);
            currentRotation = 0;
            const sizeInMB = (blob.size / (1024 * 1024)).toFixed(2);
            document.getElementById('image-size').textContent = sizeInMB + ' MB';
            document.getElementById('image-dimensions').textContent =
                (outWidth || canvas.width) + ' × ' + (outHeight || canvas.height) + ' px';
            document.getElementById('image-info').style.display = 'block';
            hideEditor();
            updatePreviewPost();
        }, 'image/jpeg', 0.92);
    });
    // ─────────────────────────────────────────────────────────────────────────

    function updateCharCounter(input, counter) {
        const length = input.value.length;
        counter.textContent = length;
        if (length > 255) {
            counter.parentElement.classList.add('text-danger');
        } else {
            counter.parentElement.classList.remove('text-danger');
        }
    }

    function loadPagePreview() {
        const selectedPage = pageSelect.value;
        const pageMap = cfg.pageMap || {};
        const pageUrl = pageMap[selectedPage];

        if (!pageUrl) {
            fullPagePreview.innerHTML = '<div class="text-center text-muted py-5"><p>Keine Vorschau verfügbar für diese Seite</p></div>';
            return;
        }

        fullPagePreview.innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border" role="status"></div><p class="mt-2">Vorschau wird geladen...</p><button class="btn btn-sm btn-secondary mt-3" onclick="document.getElementById(\'full-page-preview\').innerHTML = \'<div class=\\\'text-center text-muted py-5\\\'><p>Vorschau übersprungen</p></div>\'; return false;">Vorschau überspringen</button></div>';

        const fetchWithTimeout = (url, timeout) => {
            timeout = timeout || 10000;
            return Promise.race([
                fetch(url),
                new Promise((_, reject) => setTimeout(() => reject(new Error('Timeout')), timeout))
            ]);
        };

        fetchWithTimeout(pageUrl)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                let mainContent = doc.querySelector('main') ||
                                  doc.querySelector('[role="main"]') ||
                                  doc.querySelector('.container .py-5') ||
                                  doc.querySelector('.container');

                if (!mainContent) {
                    fullPagePreview.innerHTML = '<div class="text-center text-muted py-5"><p>Hauptinhalt nicht gefunden</p></div>';
                    return;
                }

                const contentClone = mainContent.cloneNode(true);
                const nav = contentClone.querySelector('nav');
                if (nav) nav.remove();
                const header = contentClone.querySelector('header');
                if (header) header.remove();

                fullPagePreview.innerHTML = contentClone.innerHTML;

                if (!cfg.isNew && cfg.postTitle) {
                    const allPosts = fullPagePreview.querySelectorAll('[class*="col"]');
                    allPosts.forEach(post => {
                        const postTitle = post.querySelector('h3');
                        if (postTitle && postTitle.textContent.trim() === cfg.postTitle) {
                            post.id = previewPostId;
                        }
                    });
                }

                setTimeout(() => updatePreviewPost(), 100);
            })
            .catch(error => {
                let errorMessage = 'Fehler beim Laden der Vorschau';
                if (error.message === 'Timeout') {
                    errorMessage = 'Vorschau konnte nicht geladen werden (Zeitüberschreitung). Bitte Seite neu laden.';
                }
                fullPagePreview.innerHTML = `<div class="text-center text-danger py-5"><p>${errorMessage}</p><button class="btn btn-sm btn-primary mt-3" onclick="location.reload()">Seite neu laden</button></div>`;
            });
    }

    const previewPostId = 'preview-post-' + (cfg.postId || 'new');

    function updatePreviewPost() {
        const previewHtml   = createPreviewPostHtml();
        let previewPost     = fullPagePreview.querySelector('#' + previewPostId);

        if (!previewPost) {
            let postsContainer = fullPagePreview.querySelector('#posts-container') ||
                                 fullPagePreview.querySelector('.row.mt-2') ||
                                 fullPagePreview.querySelector('.row.mt-3') ||
                                 fullPagePreview.querySelector('.row.mt-4') ||
                                 fullPagePreview.querySelector('.row.mt-5') ||
                                 fullPagePreview.querySelector('.row') ||
                                 fullPagePreview.querySelector('[class*="post"]');

            if (!postsContainer) {
                const wrapper = document.createElement('div');
                wrapper.className = 'container py-5';
                wrapper.innerHTML = '<h2>Beiträge auf dieser Seite</h2><div class="row mt-4"></div>';
                fullPagePreview.appendChild(wrapper);
                postsContainer = wrapper.querySelector('.row');
            }

            if (postsContainer) {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = previewHtml;
                const previewElement = tempDiv.firstElementChild;
                previewElement.id = previewPostId;

                if (postsContainer.firstChild) {
                    postsContainer.insertBefore(previewElement, postsContainer.firstChild);
                } else {
                    postsContainer.appendChild(previewElement);
                }
            }
        } else {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = previewHtml;
            previewPost.innerHTML = tempDiv.firstElementChild.innerHTML;
        }

        // Align sibling columns: compute offset in CSS pixels (unaffected by scale transform)
        requestAnimationFrame(() => {
            const previewEl = fullPagePreview.querySelector('#' + previewPostId);
            if (!previewEl || !previewEl.parentElement) return;
            const badgeEl  = previewEl.firstElementChild;   // div.mb-1 (badge)
            const innerBox = previewEl.children[1];         // div with blue border
            if (!badgeEl || !innerBox) return;
            const badgeOuterHeight = badgeEl.offsetHeight + parseFloat(getComputedStyle(badgeEl).marginBottom || 0);
            const innerBoxOffset   = parseFloat(getComputedStyle(innerBox).borderTopWidth || 0)
                                   + parseFloat(getComputedStyle(innerBox).paddingTop || 0);
            const offset = badgeOuterHeight + innerBoxOffset;
            Array.from(previewEl.parentElement.children).forEach(sib => {
                if (sib !== previewEl) sib.style.paddingTop = offset + 'px';
            });
        });
    }

    function readFontStyle(fieldCap) {
        const font      = document.getElementById('post_font' + fieldCap)?.value;
        const size      = document.getElementById('post_font' + fieldCap + 'Size')?.value;
        const bold      = document.getElementById('post_font' + fieldCap + 'Bold')?.checked;
        const italic    = document.getElementById('post_font' + fieldCap + 'Italic')?.checked;
        const underline = document.getElementById('post_font' + fieldCap + 'Underline')?.checked;
        let style = '';
        if (font && font !== 'default') style += 'font-family: ' + font + ';';
        if (size)      style += ' font-size: ' + size + 'px;';
        if (bold)      style += ' font-weight: bold;';
        if (italic)    style += ' font-style: italic;';
        if (underline) style += ' text-decoration: underline;';
        return style.trim();
    }

    function createPreviewPostHtml() {
        const title    = titleInput.value || 'Titel wird hier angezeigt...';
        const subtitle = subtitleInput.value;
        const paragraph = paragraphInput.value;
        const isMain   = isMainCheckbox.checked;
        const layout   = layoutSelect.value;

        const colSize = layout === 'side_by_side' ? 'col-md-6' : 'col-12';
        let html = `<div class="${colSize} mb-4">`;

        // Badge outside the blue border
        html += '<div class="mb-1"><span class="badge bg-info">VORSCHAU - Ihr neuer Beitrag</span>';
        if (isMain) html += ' <span class="badge bg-warning">★ Hauptbeitrag</span>';
        html += '</div>';

        // Content inside the blue border
        html += '<div style="border: 3px solid #0d6efd; background-color: #e7f1ff; padding: 15px; border-radius: 8px;">';

        const titleStyle = readFontStyle('Title');
        html += `<h3${titleStyle ? ' style="' + titleStyle + '"' : ''}>${escapeHtml(title)}</h3>`;
        if (subtitle) {
            const subtitleStyle = readFontStyle('Subtitle');
            html += `<h5 class="text-muted"${subtitleStyle ? ' style="' + subtitleStyle + '"' : ''}>${escapeHtml(subtitle)}</h5>`;
        }

        if (currentPreviewImage) {
            html += `<div class="mt-3" style="width: 100%; display: flex; align-items: center; justify-content: center;">`;
            html += `<img src="${currentPreviewImage}" alt="${escapeHtml(title)}" class="img-fluid rounded" style="max-width: 100%; height: auto; object-fit: contain;${currentRotation ? ' transform: rotate(' + currentRotation + 'deg);' : ''}">`;
            html += `</div>`;
        }

        if (paragraph) html += `<div class="mt-3 post-paragraph">${paragraph}</div>`;

        html += '</div>'; // close blue border

        html += '</div>';
        return html;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // "Zur Seite" button
    const gotoPageBtn = document.getElementById('goto-page-btn');
    const pageMap     = cfg.pageMap || {};

    function updateGotoButton() {
        const selectedPage = pageSelect.value;
        if (pageMap[selectedPage]) {
            gotoPageBtn.href = pageMap[selectedPage];
            gotoPageBtn.style.display = 'inline-block';
        } else {
            gotoPageBtn.style.display = 'none';
        }
    }

    if (pageSelect && gotoPageBtn) {
        updateGotoButton();
        pageSelect.addEventListener('change', updateGotoButton);
    }
});
