function initFontModal(fieldCap, field, modalId) {
    const fonts = [
        { name: 'Standard', value: 'default', category: 'System' },
        { name: 'Arial', value: 'Arial, sans-serif', category: 'System' },
        { name: 'Georgia', value: 'Georgia, serif', category: 'System' },
        { name: 'Times New Roman', value: 'Times New Roman, serif', category: 'System' },
        { name: 'Verdana', value: 'Verdana, sans-serif', category: 'System' },
        { name: 'Roboto', value: "'Roboto', sans-serif", google: true },
        { name: 'Open Sans', value: "'Open Sans', sans-serif", google: true },
        { name: 'Lato', value: "'Lato', sans-serif", google: true },
        { name: 'Montserrat', value: "'Montserrat', sans-serif", google: true },
        { name: 'Oswald', value: "'Oswald', sans-serif", google: true },
        { name: 'Raleway', value: "'Raleway', sans-serif", google: true },
        { name: 'Poppins', value: "'Poppins', sans-serif", google: true },
        { name: 'Playfair Display', value: "'Playfair Display', serif", google: true },
        { name: 'Merriweather', value: "'Merriweather', serif", google: true },
        { name: 'PT Sans', value: "'PT Sans', sans-serif", google: true },
        { name: 'Ubuntu', value: "'Ubuntu', sans-serif", google: true },
        { name: 'Noto Sans', value: "'Noto Sans', sans-serif", google: true }
    ];

    let selectedFont = 'default';
    let selectedSize = null;
    let selectedBold = false;
    let selectedItalic = false;
    let selectedUnderline = false;
    let fieldInput;

    // Remove any previously registered handler for this modal to prevent accumulation
    var _prevHandler = window['_fmHandler_' + modalId];
    if (_prevHandler) document.removeEventListener('turbo:load', _prevHandler);
    var _handler = function () {
        const fontList           = document.getElementById('fontList' + fieldCap);
        const fontSearch         = document.getElementById('fontSearch' + fieldCap);
        const sizeSlider         = document.getElementById('fontSizeSlider' + fieldCap);
        const sizeDisplay        = document.getElementById('fontSizeDisplay' + fieldCap);
        const preview            = document.getElementById('preview' + fieldCap);
        const selectedFontDisplay = document.getElementById('selectedFont' + fieldCap);
        const boldCheckbox       = document.getElementById('fontBold' + fieldCap);
        const italicCheckbox     = document.getElementById('fontItalic' + fieldCap);
        const underlineCheckbox  = document.getElementById('fontUnderline' + fieldCap);

        fieldInput = document.getElementById('post-' + field) || document.getElementById('post_' + field);

        // Load Google Fonts
        const googleFonts = fonts.filter(f => f.google).map(f => f.name.replace(/'/g, '')).join('|');
        if (googleFonts) {
            const link = document.createElement('link');
            link.href = `https://fonts.googleapis.com/css2?family=${googleFonts.replace(/ /g, '+')}:wght@400;700&display=swap`;
            link.rel = 'stylesheet';
            document.head.appendChild(link);
        }

        function renderFonts(filter) {
            filter = filter || '';
            const filtered = fonts.filter(f => f.name.toLowerCase().includes(filter.toLowerCase()));

            fontList.innerHTML = filtered.map(font => `
                <button type="button" class="list-group-item list-group-item-action font-item"
                        data-font="${font.value}"
                        style="font-family: ${font.value}; border: none;">
                    ${font.name} ${font.category ? '<small class="text-muted">(' + font.category + ')</small>' : ''}
                </button>
            `).join('');

            fontList.querySelectorAll('.font-item').forEach(item => {
                item.addEventListener('click', function () {
                    fontList.querySelectorAll('.font-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    selectedFont = this.dataset.font;
                    const fontName = this.textContent.trim().split('(')[0].trim();
                    selectedFontDisplay.textContent = fontName;
                    updatePreview();
                });
            });
        }

        renderFonts();

        fontSearch.addEventListener('input', function () {
            renderFonts(this.value);
        });

        sizeSlider.addEventListener('input', function () {
            sizeDisplay.textContent = this.value;
            selectedSize = this.value;
            updatePreview();
        });

        boldCheckbox.addEventListener('change', function () {
            selectedBold = this.checked;
            updatePreview();
        });

        italicCheckbox.addEventListener('change', function () {
            selectedItalic = this.checked;
            updatePreview();
        });

        underlineCheckbox.addEventListener('change', function () {
            selectedUnderline = this.checked;
            updatePreview();
        });

        function updatePreview() {
            if (!fieldInput) {
                fieldInput = document.getElementById('post-' + field) || document.getElementById('post_' + field);
            }

            let previewText = 'Vorschau Text';
            if (fieldInput) {
                const value = fieldInput.value ? fieldInput.value.trim() : '';
                if (value) previewText = value;
            }
            preview.textContent = previewText;

            let style = '';
            if (selectedFont && selectedFont !== 'default') style += 'font-family: ' + selectedFont + ';';
            if (selectedSize)      style += ' font-size: ' + selectedSize + 'px;';
            if (selectedBold)      style += ' font-weight: bold;';
            if (selectedItalic)    style += ' font-style: italic;';
            if (selectedUnderline) style += ' text-decoration: underline;';
            preview.setAttribute('style', style);
        }

        if (fieldInput) {
            fieldInput.addEventListener('input', function () {
                const modal = document.getElementById(modalId);
                if (modal.classList.contains('show')) updatePreview();
            });
        }

        document.getElementById(modalId).addEventListener('show.bs.modal', function () {
            const currentFont      = document.getElementById('post_font' + fieldCap).value;
            const currentSize      = document.getElementById('post_font' + fieldCap + 'Size').value;
            const currentBold      = document.getElementById('post_font' + fieldCap + 'Bold').checked;
            const currentItalic    = document.getElementById('post_font' + fieldCap + 'Italic').checked;
            const currentUnderline = document.getElementById('post_font' + fieldCap + 'Underline').checked;

            selectedFont      = currentFont || 'default';
            selectedSize      = currentSize || 16;
            selectedBold      = currentBold;
            selectedItalic    = currentItalic;
            selectedUnderline = currentUnderline;

            sizeSlider.value        = selectedSize;
            sizeDisplay.textContent = selectedSize;
            boldCheckbox.checked      = selectedBold;
            italicCheckbox.checked    = selectedItalic;
            underlineCheckbox.checked = selectedUnderline;

            setTimeout(() => {
                fontList.querySelectorAll('.font-item').forEach(item => {
                    if (item.dataset.font === selectedFont) {
                        item.classList.add('active');
                        item.scrollIntoView({ block: 'center' });
                    }
                });
                updatePreview();
            }, 100);
        });
    };
    window['_fmHandler_' + modalId] = _handler;
    pageLoad(_handler);

    window['applyFont' + fieldCap] = function () {
        document.getElementById('post_font' + fieldCap).value           = selectedFont;
        document.getElementById('post_font' + fieldCap + 'Size').value  = selectedSize;
        document.getElementById('post_font' + fieldCap + 'Bold').checked      = selectedBold;
        document.getElementById('post_font' + fieldCap + 'Italic').checked    = selectedItalic;
        document.getElementById('post_font' + fieldCap + 'Underline').checked = selectedUnderline;

        bootstrap.Modal.getInstance(document.getElementById(modalId)).hide();

        if (window.markFormDirty) window.markFormDirty();
        // Notify the page preview to re-render with new font settings
        document.dispatchEvent(new CustomEvent('font:applied'));
    };
}
