(function ($) {
    'use strict';

    function parseJSONScript(wrapper) {
        const node = wrapper.querySelector('.custom-wvb__data');
        if (!node) {
            return null;
        }

        try {
            return JSON.parse(node.textContent);
        } catch (error) {
            return null;
        }
    }

    function parseConfig(wrapper) {
        try {
            return JSON.parse(wrapper.getAttribute('data-config') || '{}');
        } catch (error) {
            return {};
        }
    }

    function init(wrapper) {
        // Elementor fires element_ready once per widget on the page, so the same
        // wrapper can reach init() many times. Without this guard every listener
        // gets bound again and a single click fires the handler N times.
        if (wrapper.dataset.wvbReady) {
            return;
        }
        wrapper.dataset.wvbReady = '1';

        const data = parseJSONScript(wrapper);
        const config = parseConfig(wrapper);

        if (!data || !Array.isArray(data.variations) || !Array.isArray(data.order)) {
            return;
        }

        const containers = Array.from(wrapper.querySelectorAll('.custom-wvb__attribute'));
        const addButton = wrapper.querySelector('.custom-wvb__add');
        const qtyInput = wrapper.querySelector('.custom-wvb__qty');
        const priceEl = wrapper.querySelector('.custom-wvb__price');
        const priceRow = wrapper.querySelector('.custom-wvb__price-row');
        const messageEl = wrapper.querySelector('.custom-wvb__message');

        if (!containers.length || !addButton) {
            return;
        }

        function t(key, fallback) {
            return (config.i18n && config.i18n[key]) || fallback;
        }

        const attributeOrder = data.order.slice();
        const variations = data.variations.filter(function (variation) {
            return variation && variation.id;
        });

        const valueIndex = new Map();

        function addToValueIndex(attributeName, value, variation) {
            if (!valueIndex.has(attributeName)) {
                valueIndex.set(attributeName, new Map());
            }

            const attrMap = valueIndex.get(attributeName);

            if (!attrMap.has(value)) {
                attrMap.set(value, []);
            }

            attrMap.get(value).push(variation);
        }

        variations.forEach(function (variation) {
            attributeOrder.forEach(function (attributeName) {
                const value = variation.attributes[attributeName] || '';
                addToValueIndex(attributeName, value, variation);
            });
        });

        function getSelections() {
            const selections = {};

            containers.forEach(function (container) {
                const select = container.querySelector('.custom-wvb__select');
                const attributeName = container.dataset.attributeName;

                if (select) {
                    selections[attributeName] = select.value || '';
                    return;
                }

                const selectedButton = container.querySelector('.custom-wvb__option.is-selected');
                selections[attributeName] = selectedButton ? (selectedButton.dataset.value || '') : '';
            });

            return selections;
        }

        function selectionIsComplete(selections) {
            return attributeOrder.every(function (attributeName) {
                return !!selections[attributeName];
            });
        }

        function isUsableVariation(variation) {
            return !!(
                variation.active &&
                variation.visible &&
                variation.purchasable
            );
        }

        function matchesSelection(variation, selections, excludedAttribute) {
            return attributeOrder.every(function (attributeName) {
                if (attributeName === excludedAttribute) {
                    return true;
                }

                const selectedValue = selections[attributeName];
                if (!selectedValue) {
                    return true;
                }

                const variationValue = variation.attributes[attributeName] || '';

                // Empty WooCommerce variation attribute means "any value".
                return !variationValue || variationValue === selectedValue;
            });
        }

        function getCandidateVariations(selections, excludedAttribute) {
            let candidateSets = [];

            attributeOrder.forEach(function (attributeName) {
                if (attributeName === excludedAttribute) {
                    return;
                }

                const selectedValue = selections[attributeName];
                if (!selectedValue) {
                    return;
                }

                const attrMap = valueIndex.get(attributeName);
                if (!attrMap) {
                    candidateSets.push([]);
                    return;
                }

                // Include exact values plus variations where this attribute is "any".
                const exact = attrMap.get(selectedValue) || [];
                const any = attrMap.get('') || [];
                candidateSets.push(exact.concat(any));
            });

            if (!candidateSets.length) {
                return variations.slice();
            }

            candidateSets.sort(function (a, b) {
                return a.length - b.length;
            });

            // De-duplicate because a variation can appear in multiple candidate sets.
            const seen = new Set();
            const result = [];

            candidateSets[0].forEach(function (variation) {
                if (!seen.has(variation.id)) {
                    seen.add(variation.id);
                    result.push(variation);
                }
            });

            return result;
        }

        function findMatchingVariation(selections) {
            const candidates = getCandidateVariations(selections, null);

            for (let i = 0; i < candidates.length; i += 1) {
                const variation = candidates[i];

                if (!isUsableVariation(variation)) {
                    continue;
                }

                if (matchesSelection(variation, selections, null)) {
                    return variation;
                }
            }

            return null;
        }

        function optionAvailable(attributeName, optionValue, selections) {
            const attrMap = valueIndex.get(attributeName);
            if (!attrMap) {
                return false;
            }

            const exact = attrMap.get(optionValue) || [];
            const any = attrMap.get('') || [];
            const candidates = exact.concat(any);

            for (let i = 0; i < candidates.length; i += 1) {
                const variation = candidates[i];

                if (!isUsableVariation(variation)) {
                    continue;
                }

                if (matchesSelection(variation, selections, attributeName)) {
                    return true;
                }
            }

            return false;
        }

        function clearAttributesAfter(index) {
            for (let i = index + 1; i < containers.length; i += 1) {
                const container = containers[i];
                const select = container.querySelector('.custom-wvb__select');

                if (select) {
                    select.value = '';
                }

                container.querySelectorAll('.custom-wvb__option').forEach(function (button) {
                    button.classList.remove('is-selected');
                    button.setAttribute('aria-pressed', 'false');
                });
            }
        }

        function updateAvailability() {
            const selections = getSelections();

            containers.forEach(function (container) {
                const attributeName = container.dataset.attributeName;
                const selectedValue = selections[attributeName] || '';

                container.querySelectorAll('.custom-wvb__option').forEach(function (button) {
                    const value = button.dataset.value || '';
                    const available = optionAvailable(attributeName, value, selections);

                    button.disabled = !available;

                    if (value === selectedValue) {
                        button.disabled = false;
                    }
                });

                const select = container.querySelector('.custom-wvb__select');
                if (select) {
                    Array.from(select.options).forEach(function (option) {
                        if (!option.value) {
                            option.disabled = false;
                            return;
                        }

                        const available = optionAvailable(attributeName, option.value, selections);
                        option.disabled = option.value !== selectedValue && !available;
                    });
                }
            });
        }

        function setMessage(message) {
            if (messageEl) {
                messageEl.textContent = message || '';
            }
        }

        function setPrice(html) {
            if (priceEl) {
                priceEl.innerHTML = html || '';
            }

            // Keeps a price prefix like "Cena:" from showing without a price.
            if (priceRow) {
                priceRow.hidden = !html;
            }
        }

        /**
         * CSS cannot express "a select that has a value", so the selected state of
         * the closed field is a class. The open option list is drawn by the OS on
         * macOS/iOS, which is why the field itself carries the styling.
         */
        function syncSelectStates() {
            containers.forEach(function (container) {
                const select = container.querySelector('.custom-wvb__select');

                if (select) {
                    select.classList.toggle('is-selected', !!select.value);
                }
            });
        }

        /**
         * Returns { variation, complete }. `complete` tells the caller whether the
         * user picked every attribute, so an unusable-but-complete selection keeps
         * its own explanation instead of being told to pick more options.
         */
        function updateVariationState() {
            const selections = getSelections();

            addButton.disabled = true;
            setPrice('');
            setMessage('');

            updateAvailability();
            syncSelectStates();

            if (!selectionIsComplete(selections)) {
                return { variation: null, complete: false };
            }

            const variation = findMatchingVariation(selections);

            if (!variation) {
                setMessage(t('unavailable', 'Wybrana kombinacja jest niedostępna.'));
                return { variation: null, complete: true };
            }

            if (!variation.in_stock) {
                setMessage(t('outOfStock', 'Ten wariant jest obecnie niedostępny (brak w magazynie).'));
                return { variation: null, complete: true };
            }

            setPrice(variation.price_html);
            addButton.disabled = false;

            if (qtyInput) {
                if (variation.max_qty > 0) {
                    qtyInput.max = String(variation.max_qty);
                } else {
                    qtyInput.removeAttribute('max');
                }
            }

            return { variation: variation, complete: true };
        }

        function getQuantity(variation) {
            const parsed = parseFloat(qtyInput ? qtyInput.value : '1');
            let quantity = isFinite(parsed) && parsed > 0 ? parsed : 1;

            if (variation.max_qty > 0) {
                quantity = Math.min(quantity, variation.max_qty);
            }

            if (qtyInput) {
                qtyInput.value = String(quantity);
            }

            return quantity;
        }

        function resetButtonState() {
            addButton.disabled = true;
            addButton.textContent = t('addToCart', addButton.textContent);
        }

        containers.forEach(function (container, index) {
            const select = container.querySelector('.custom-wvb__select');

            if (select) {
                select.addEventListener('change', function () {
                    clearAttributesAfter(index);
                    resetButtonState();
                    updateVariationState();
                });
            }

            container.querySelectorAll('.custom-wvb__option').forEach(function (button) {
                button.addEventListener('click', function () {
                    if (button.disabled) {
                        return;
                    }

                    container.querySelectorAll('.custom-wvb__option').forEach(function (item) {
                        item.classList.remove('is-selected');
                        item.setAttribute('aria-pressed', 'false');
                    });

                    button.classList.add('is-selected');
                    button.setAttribute('aria-pressed', 'true');

                    clearAttributesAfter(index);
                    resetButtonState();
                    updateVariationState();
                });
            });
        });

        addButton.addEventListener('click', function () {
            const state = updateVariationState();

            if (!state.variation) {
                // A complete selection already got a specific message above.
                if (!state.complete) {
                    setMessage(t('selectAll', 'Wybierz wszystkie opcje produktu.'));
                }

                return;
            }

            /*
             * The chosen values have to travel with the request. WooCommerce
             * stores an empty string for an attribute a variation leaves as
             * "Any", so a variation ID on its own cannot describe what the
             * customer picked, and the cart rejects it as a missing field.
             */
            const payload = {
                product_id: config.productId,
                variation_id: state.variation.id,
                quantity: getQuantity(state.variation),
                attributes: getSelections()
            };

            const originalText = addButton.textContent;
            addButton.disabled = true;
            addButton.textContent = t('adding', 'Dodawanie...');
            setMessage('');

            $.ajax({
                type: 'POST',
                url: config.ajaxUrl,
                data: payload,
                dataType: 'json'
            })
                .done(function (response) {
                    if (!response || response.error) {
                        // WooCommerce explains the refusal better than we can.
                        setMessage(
                            (response && response.message) ||
                            t('error', 'Nie udało się dodać tego wariantu do koszyka. Spróbuj ponownie.')
                        );

                        if (response && response.product_url) {
                            window.location.href = response.product_url;
                            return;
                        }

                        addButton.disabled = false;
                        addButton.textContent = originalText;
                        return;
                    }

                    if (response.fragments) {
                        Object.keys(response.fragments).forEach(function (selector) {
                            $(selector).replaceWith(response.fragments[selector]);
                        });
                    }

                    $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, addButton]);

                    addButton.textContent = t('added', 'Dodano do koszyka');
                    setMessage('');

                    window.setTimeout(function () {
                        addButton.disabled = false;
                        addButton.textContent = originalText;
                    }, 1600);
                })
                .fail(function () {
                    setMessage(t('error', 'Nie udało się dodać tego wariantu do koszyka. Spróbuj ponownie.'));
                    addButton.disabled = false;
                    addButton.textContent = originalText;
                });
        });

        updateVariationState();
    }

    /*
     * An exception thrown from a jQuery ready callback aborts the remaining
     * callbacks in that queue, so one broken widget could stop other plugins'
     * scripts from initialising. Contain the damage to this widget.
     */
    function safeInit(wrapper) {
        try {
            init(wrapper);
        } catch (error) {
            if (window.console && window.console.error) {
                window.console.error('custom-wvb:', error);
            }
        }
    }

    function initWithin(root) {
        if (!root) {
            return;
        }

        if (root.classList && root.classList.contains('custom-wvb')) {
            safeInit(root);
        }

        root.querySelectorAll('.custom-wvb').forEach(safeInit);
    }

    function boot() {
        initWithin(document);
    }

    $(boot);

    // Elementor can render/re-render widgets dynamically in the editor.
    $(document).on('elementor/frontend/init', function () {
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function ($scope) {
                // Scope the scan to the widget that just became ready instead of
                // re-scanning the whole document for every element on the page.
                initWithin($scope && $scope.length ? $scope[0] : document);
            });
        }
    });
})(jQuery);