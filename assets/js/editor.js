(function ($) {
    'use strict';

    const SIE_Editor = {
        config: null,
        container: null,
        sidebar: null,
        preview: null,
        is_admin_mode: false,

        init: function () {
            if (typeof sie_config === 'undefined' || !sie_config.raw_config) return;

            try {
                this.config = JSON.parse(sie_config.raw_config);
            } catch (e) {
                console.error('SIE: Invalid JSON configuration', e);
                return;
            }

            this.is_admin_mode = sie_config.is_admin_mode;
            this.container = $('#sie-editor-app');

            if (this.is_admin_mode) {
                $('body').addClass('sie-admin-mode');
            }

            this.renderLayout();
            this.renderLayers();
            this.loadFromLocalStorage();
            this.bindEvents();
            this.updateHiddenInput();
        },

        renderLayout: function () {
            this.container.html(`
                <div class="sie-sidebar"></div>
                <div class="sie-preview-container" style="
                    width: ${this.config.canvas.width}px; 
                    height: ${this.config.canvas.height}px; 
                    background-image: url('${this.config.canvas.bg_image}');
                    --sie-aspect-ratio: ${this.config.canvas.width} / ${this.config.canvas.height};
                "></div>
            `);

            this.sidebar = this.container.find('.sie-sidebar');
            this.preview = this.container.find('.sie-preview-container');
        },

        renderLayers: function () {
            this.config.layers.forEach(layer => {
                if (layer.type === 'text') {
                    this.addTextLayer(layer);
                }
            });
        },

        addTextLayer: function (layer) {
            // Create Sidebar Input
            const inputHtml = `
                <div class="sie-input-group" data-layer-id="${layer.id}">
                    <label>${layer.label}</label>
                    <input type="text" value="${layer.default_text}" class="sie-layer-input">
                </div>
            `;
            this.sidebar.append(inputHtml);

            // Create Preview Layer
            const $el = $('<div>', {
                class: 'sie-layer',
                id: `sie-layer-${layer.id}`,
                contenteditable: !this.is_admin_mode,
                text: layer.default_text
            }).css(layer.style);

            if (this.is_admin_mode) {
                this.makeDraggable($el, layer);
            }

            this.preview.append($el);
            this.applyShrinkToFit($el, layer.id);
        },

        bindEvents: function () {
            const self = this;

            // Sidebar Input -> Preview
            this.sidebar.on('input', '.sie-layer-input', function () {
                const $input = $(this);
                const layerId = $input.closest('.sie-input-group').data('layer-id');
                const text = $input.val();
                const $layer = $(`#sie-layer-${layerId}`);
                $layer.text(text);
                self.applyShrinkToFit($layer, layerId);
                self.updateHiddenInput();
            });



            // Preview -> Sidebar Input
            this.preview.on('input', '.sie-layer', function () {
                const $layer = $(this);
                const layerId = $layer.attr('id').replace('sie-layer-', '');
                const text = $layer.text();
                $(`.sie-input-group[data-layer-id="${layerId}"] input`).val(text);
                self.applyShrinkToFit($layer, layerId);
                self.updateHiddenInput();
            });

            // Highlight link
            this.preview.on('focus', '.sie-layer', function () {
                const layerId = $(this).attr('id').replace('sie-layer-', '');
                $('.sie-input-group').removeClass('active');
                $(`.sie-input-group[data-layer-id="${layerId}"]`).addClass('active')[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });

            this.sidebar.on('focus', '.sie-layer-input', function () {
                const layerId = $(this).closest('.sie-input-group').data('layer-id');
                $('.sie-layer').css('box-shadow', 'none');
                $(`#sie-layer-${layerId}`).css('box-shadow', '0 0 0 2px #d4af37');
            });

            // Add to Cart Button (using event delegation since modal is moved to body)
            $(document).on('click', '#sie-add-to-cart-btn', function () {
                // Update hidden input with latest data
                self.updateHiddenInput();

                // Get product ID from add to cart button
                const $addToCartBtn = $('button[name="add-to-cart"]');
                const productId = $addToCartBtn.val();

                if (!productId) {
                    console.error('SIE: Product ID not found');
                    return;
                }

                // Get customization data
                const customData = $('#sie-custom-data').val();

                // Prepare AJAX data
                const data = {
                    action: 'woocommerce_add_to_cart',
                    product_id: productId,
                    quantity: 1,
                    sie_custom_data: customData
                };

                // Add to cart via AJAX
                $.ajax({
                    url: wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'add_to_cart'),
                    type: 'POST',
                    data: data,
                    success: function (response) {
                        if (response.error) {
                            console.error('SIE: Add to cart failed', response);
                            alert('Sepete eklenirken bir hata oluştu.');
                        } else {
                            // Redirect to cart page
                            window.location.href = wc_add_to_cart_params.cart_url;
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('SIE: AJAX error', error);
                        alert('Sepete eklenirken bir hata oluştu.');
                    }
                });
            });
        },

        applyShrinkToFit: function ($el, layerId) {
            const layer = this.config.layers.find(l => l.id === layerId);
            if (!layer || !layer.style || !layer.style.fontSize) return;

            const originalSize = parseInt(layer.style.fontSize);
            const containerWidth = $el.width();
            const containerHeight = $el.height();

            let currentSize = originalSize;
            $el.css('font-size', currentSize + 'px');

            // Simple heuristic: if scrollWidth > width, reduce font size
            while (($el[0].scrollWidth > containerWidth || $el[0].scrollHeight > containerHeight) && currentSize > 8) {
                currentSize--;
                $el.css('font-size', currentSize + 'px');
            }
        },

        makeDraggable: function ($el, layer) {
            const self = this;
            let isDragging = false;
            let startX, startY, startLeft, startTop;

            $el.on('mousedown', function (e) {
                isDragging = true;
                startX = e.clientX;
                startY = e.clientY;
                startLeft = parseFloat($el.css('left'));
                startTop = parseFloat($el.css('top'));

                $(document).on('mousemove.sie-drag', function (e) {
                    if (!isDragging) return;

                    const dx = e.clientX - startX;
                    const dy = e.clientY - startY;

                    const newLeft = ((startLeft + dx) / self.preview.width() * 100).toFixed(2) + '%';
                    const newTop = ((startTop + dy) / self.preview.height() * 100).toFixed(2) + '%';

                    $el.css({ left: newLeft, top: newTop });

                    // Update the config object for export
                    layer.style.left = newLeft;
                    layer.style.top = newTop;
                    self.updateAdminExport();
                });

                $(document).on('mouseup.sie-drag', function () {
                    isDragging = false;
                    $(document).off('.sie-drag');
                });
            });
        },

        updateHiddenInput: function () {
            const data = {};
            this.config.layers.forEach(layer => {
                const $group = $(`.sie-input-group[data-layer-id="${layer.id}"]`);
                const text = $group.find('.sie-layer-input').val();

                data[layer.id] = {
                    label: layer.label,
                    text: text,
                    fontFamily: layer.style.fontFamily
                };
            });
            const jsonString = JSON.stringify(data);
            $('#sie-custom-data').val(jsonString);

            // Autosave to LocalStorage
            const productId = $('form.cart').find('button[name="add-to-cart"]').val() || window.location.pathname;
            localStorage.setItem('sie_autosave_' + productId, jsonString);
        },

        loadFromLocalStorage: function () {
            const productId = $('form.cart').find('button[name="add-to-cart"]').val() || window.location.pathname;
            const saved = localStorage.getItem('sie_autosave_' + productId);
            if (saved) {
                try {
                    const data = JSON.parse(saved);
                    Object.keys(data).forEach(layerId => {
                        const layerData = data[layerId];
                        const $group = $(`.sie-input-group[data-layer-id="${layerId}"]`);
                        const $layer = $(`#sie-layer-${layerId}`);

                        if (layerData.text !== undefined) {
                            $group.find('.sie-layer-input').val(layerData.text);
                            $layer.text(layerData.text);
                        }

                        this.applyShrinkToFit($layer, layerId);
                    });
                } catch (e) {
                    console.warn('SIE: Failed to load autosave', e);
                }
            }
        },

        updateAdminExport: function () {
            if (!this.is_admin_mode) return;

            // Console log the updated JSON for easy copying by admin
            console.log('UPDATED CONFIG:', JSON.stringify(this.config, null, 2));

            // Optionally, we could add a "Copy JSON" button in the UI
            if ($('#sie-admin-copy-json').length === 0) {
                this.sidebar.prepend('<button id="sie-admin-copy-json" style="margin-bottom: 10px;">Copy Updated JSON</button>');
                $('#sie-admin-copy-json').on('click', (e) => {
                    e.preventDefault();
                    const json = JSON.stringify(this.config, null, 2);
                    navigator.clipboard.writeText(json).then(() => {
                        alert('JSON copied to clipboard!');
                    });
                });
            }
        }
    };

    $(document).ready(function () {
        SIE_Editor.init();
    });

})(jQuery);
