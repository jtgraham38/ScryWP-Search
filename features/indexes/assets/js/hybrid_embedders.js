/**
 * Hybrid embedder CRUD for the open Configure Index dialog (this index only).
 *
 * The browser never talks to Meilisearch. post() hits admin-ajax.php; PHP holds the admin key
 * and GET/PATCHes /indexes/{uid}/settings/embedders.
 *
 * Wrapped in an IIFE so these helpers are not global. scrywpHybridEmbedders comes from
 * wp_localize_script (ajax URL, action names, nonces, i18n strings).
 */
(function () {
    'use strict';

    if (typeof scrywpHybridEmbedders === 'undefined') {
        return;
    }

    // Last GET per index uid, used by Edit so we do not need a second fetch.
    var embeddersCache = {};

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    function findSection(indexName) {
        var sections = document.querySelectorAll('.scrywp-hy-hybrid-section');
        for (var i = 0; i < sections.length; i++) {
            if (sections[i].getAttribute('data-index-name') === indexName) {
                return sections[i];
            }
        }
        return null;
    }

    function setStatus(section, message, type) {
        var statusEl = section.querySelector('.scrywp-hy-embedders-status');
        if (!statusEl) {
            return;
        }
        statusEl.innerHTML = '';
        if (!message) {
            return;
        }
        var notice = document.createElement('div');
        notice.className = 'notice notice-' + (type || 'info') + ' inline';
        var p = document.createElement('p');
        p.textContent = message;
        notice.appendChild(p);
        statusEl.appendChild(notice);
    }

    // POST to WordPress admin-ajax.php (not Meilisearch). timeoutMs aborts hung Ollama/Meili waits.
    function post(action, nonce, fields, timeoutMs) {
        var body = new FormData();
        body.set('action', action);
        body.set('nonce', nonce);
        Object.keys(fields || {}).forEach(function (key) {
            body.set(key, fields[key]);
        });

        var controller = null;
        var timer = null;
        var opts = {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        };
        if (timeoutMs && typeof AbortController !== 'undefined') {
            controller = new AbortController();
            opts.signal = controller.signal;
            timer = setTimeout(function () {
                controller.abort();
            }, timeoutMs);
        }

        return fetch(scrywpHybridEmbedders.ajaxUrl, opts)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') {
                    throw new Error(scrywpHybridEmbedders.i18n.saveTimeout);
                }
                throw err;
            })
            .finally(function () {
                if (timer) {
                    clearTimeout(timer);
                }
            });
    }

    // Show/hide URL, dimensions, API key, model, template based on source. Scoped to this dialog.
    function toggleSourceFields(section) {
        var sourceSelect = section.querySelector('.scrywp-hy-embedder-source');
        var source = sourceSelect ? sourceSelect.value : 'openAi';
        var urlRow = section.querySelector('.scrywp-hy-field-url');
        var dimsRow = section.querySelector('.scrywp-hy-field-dimensions');
        var templateRow = section.querySelector('.scrywp-hy-field-template');
        var apiKeyRow = section.querySelector('.scrywp-hy-field-api-key');
        var modelRow = section.querySelector('.scrywp-hy-field-model');

        if (urlRow) {
            urlRow.hidden = source !== 'ollama';
        }
        if (dimsRow) {
            dimsRow.hidden = source !== 'userProvided';
        }
        if (templateRow) {
            templateRow.hidden = source === 'userProvided';
        }
        if (apiKeyRow) {
            apiKeyRow.hidden = source === 'userProvided';
        }
        if (modelRow) {
            modelRow.hidden = source === 'userProvided';
        }
    }

    function resetSaveButton(section) {
        var saveBtn = section.querySelector('.scrywp-hy-embedder-save');
        if (!saveBtn) {
            return;
        }
        saveBtn.disabled = false;
        saveBtn.textContent = scrywpHybridEmbedders.i18n.saveEmbedder;
    }

    function resetForm(section) {
        var nameInput = section.querySelector('.scrywp-hy-embedder-name');
        var sourceSelect = section.querySelector('.scrywp-hy-embedder-source');
        var modelInput = section.querySelector('.scrywp-hy-embedder-model');
        var apiKeyInput = section.querySelector('.scrywp-hy-embedder-api-key');
        var urlInput = section.querySelector('.scrywp-hy-embedder-url');
        var dimsInput = section.querySelector('.scrywp-hy-embedder-dimensions');
        var template = section.querySelector('.scrywp-hy-embedder-template');

        if (nameInput) {
            nameInput.value = '';
            nameInput.readOnly = false;
        }
        if (sourceSelect) {
            sourceSelect.value = 'openAi';
        }
        if (modelInput) {
            modelInput.value = '';
        }
        if (apiKeyInput) {
            apiKeyInput.value = '';
        }
        if (urlInput) {
            urlInput.value = '';
        }
        if (dimsInput) {
            dimsInput.value = '';
        }
        if (template) {
            template.value = template.defaultValue || '';
        }

        resetSaveButton(section);
        toggleSourceFields(section);
    }

    function fillForm(section, name, config) {
        if (!config) {
            return;
        }
        var nameInput = section.querySelector('.scrywp-hy-embedder-name');
        var sourceSelect = section.querySelector('.scrywp-hy-embedder-source');
        var modelInput = section.querySelector('.scrywp-hy-embedder-model');
        var apiKeyInput = section.querySelector('.scrywp-hy-embedder-api-key');
        var urlInput = section.querySelector('.scrywp-hy-embedder-url');
        var dimsInput = section.querySelector('.scrywp-hy-embedder-dimensions');
        var template = section.querySelector('.scrywp-hy-embedder-template');

        if (nameInput) {
            nameInput.value = name;
            nameInput.readOnly = true; // Meili keys embedders by name; renaming would create a second embedder.
        }
        if (sourceSelect) {
            sourceSelect.value = config.source || 'openAi';
        }
        if (modelInput) {
            modelInput.value = config.model || '';
        }
        if (apiKeyInput) {
            apiKeyInput.value = ''; // Never put the key in the DOM; blank means keep existing on save.
        }
        if (urlInput) {
            urlInput.value = config.url || '';
        }
        if (dimsInput) {
            dimsInput.value = config.dimensions || '';
        }
        if (template) {
            template.value = config.documentTemplate || template.defaultValue || '';
        }

        toggleSourceFields(section);
        setStatus(section, config.has_api_key ? scrywpHybridEmbedders.i18n.keepKey : '', 'info');
    }

    function fillSelect(select, names, selected) {
        if (!select) {
            return;
        }
        var current = selected || select.value || select.getAttribute('data-selected') || '';
        select.innerHTML = '';

        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = scrywpHybridEmbedders.i18n.selectEmbedder;
        select.appendChild(placeholder);

        names.forEach(function (name) {
            var option = document.createElement('option');
            option.value = name;
            option.textContent = name;
            select.appendChild(option);
        });

        if (current && names.indexOf(current) === -1) {
            // WP backup still has a name Meili no longer has — keep it selectable until Save Settings.
            var extra = document.createElement('option');
            extra.value = current;
            extra.textContent = current;
            select.appendChild(extra);
        }

        select.value = current;
        if (select.value !== current) {
            select.value = '';
        }
    }

    function renderList(section, embedders) {
        var listEl = section.querySelector('.scrywp-hy-embedders-list');
        var select = section.querySelector('select[name="hybrid_embedder"]');
        var indexName = section.getAttribute('data-index-name') || '';
        if (!listEl) {
            return;
        }

        embeddersCache[indexName] = embedders || {};
        listEl.innerHTML = '';
        var names = Object.keys(embeddersCache[indexName]);
        names.sort();

        var currentSelected = select ? select.value : '';
        fillSelect(
            select,
            names,
            currentSelected || (select ? select.getAttribute('data-selected') : '')
        );

        if (names.length === 0) {
            var empty = document.createElement('p');
            empty.className = 'description';
            empty.textContent = scrywpHybridEmbedders.i18n.none;
            listEl.appendChild(empty);
            return;
        }

        names.forEach(function (name) {
            var config = embeddersCache[indexName][name] || {};
            var row = document.createElement('div');
            row.className = 'scrywp-hy-embedder-row';

            var meta = document.createElement('div');
            meta.className = 'scrywp-hy-embedder-row-meta';

            var title = document.createElement('span');
            title.className = 'scrywp-hy-embedder-row-name';
            title.textContent = name;

            var detail = document.createElement('span');
            detail.className = 'scrywp-hy-embedder-row-detail';
            var parts = [];
            if (config.source) {
                parts.push(config.source);
            }
            if (config.model) {
                parts.push(config.model);
            }
            parts.push(config.has_api_key ? scrywpHybridEmbedders.i18n.hasKey : scrywpHybridEmbedders.i18n.noKey);
            detail.textContent = parts.join(' · ');

            meta.appendChild(title);
            meta.appendChild(detail);

            var actions = document.createElement('div');
            actions.className = 'scrywp-hy-embedder-row-actions';

            var editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'button button-small scrywp-hy-embedder-edit';
            editBtn.textContent = scrywpHybridEmbedders.i18n.edit;
            editBtn.addEventListener('click', function () {
                fillForm(section, name, config);
            });

            var deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'button button-small scrywp-hy-embedder-delete';
            deleteBtn.textContent = scrywpHybridEmbedders.i18n.delete;
            deleteBtn.addEventListener('click', function () {
                deleteEmbedder(section, name, deleteBtn);
            });

            actions.appendChild(editBtn);
            actions.appendChild(deleteBtn);
            row.appendChild(meta);
            row.appendChild(actions);
            listEl.appendChild(row);
        });
    }

    function loadEmbedders(indexName, options) {
        options = options || {};
        var section = findSection(indexName);
        if (!section || !indexName) {
            return Promise.resolve();
        }

        var listEl = section.querySelector('.scrywp-hy-embedders-list');
        if (listEl) {
            listEl.innerHTML = '';
            var loading = document.createElement('p');
            loading.className = 'description scrywp-hy-embedders-loading';
            loading.textContent = scrywpHybridEmbedders.i18n.loading;
            listEl.appendChild(loading);
        }
        if (!options.keepStatus) {
            setStatus(section, '', 'info');
        }

        // List this index only — index_name is required by PHP.
        return post(
            scrywpHybridEmbedders.actions.list,
            scrywpHybridEmbedders.nonces.list,
            { index_name: indexName }
        )
            .then(function (data) {
                if (!data.success) {
                    var msg = data.data && data.data.message ? data.data.message : scrywpHybridEmbedders.i18n.loadFailed;
                    setStatus(section, msg, 'error');
                    if (listEl) {
                        listEl.innerHTML = '';
                    }
                    return;
                }
                renderList(section, (data.data && data.data.embedders) || {});
            })
            .catch(function () {
                setStatus(section, scrywpHybridEmbedders.i18n.loadFailed, 'error');
                if (listEl) {
                    listEl.innerHTML = '';
                }
            });
    }

    function saveEmbedder(section) {
        var indexName = section.getAttribute('data-index-name') || '';
        var saveBtn = section.querySelector('.scrywp-hy-embedder-save');
        var nameInput = section.querySelector('.scrywp-hy-embedder-name');
        var name = nameInput ? nameInput.value.trim() : '';

        if (!indexName || !name) {
            setStatus(section, scrywpHybridEmbedders.i18n.nameRequired, 'error');
            return;
        }

        var fields = {
            index_name: indexName, // PHP PATCHes only this uid.
            name: name,
            source: (section.querySelector('.scrywp-hy-embedder-source') || {}).value || '',
            model: ((section.querySelector('.scrywp-hy-embedder-model') || {}).value || '').trim(),
            api_key: (section.querySelector('.scrywp-hy-embedder-api-key') || {}).value || '',
            url: ((section.querySelector('.scrywp-hy-embedder-url') || {}).value || '').trim(),
            dimensions: (section.querySelector('.scrywp-hy-embedder-dimensions') || {}).value || '',
            document_template: (section.querySelector('.scrywp-hy-embedder-template') || {}).value || ''
        };

        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = scrywpHybridEmbedders.i18n.saving;
        }

        // PATCH is async in Meili — a GET right after save still misses the new name.
        var cache = embeddersCache[indexName] || {};
        var prev = cache[name] || {};
        cache[name] = {
            source: fields.source,
            model: fields.model,
            has_api_key: !!fields.api_key || !!prev.has_api_key
        };
        renderList(section, cache);

        post(
            scrywpHybridEmbedders.actions.save,
            scrywpHybridEmbedders.nonces.save,
            fields,
            30000
        )
            .then(function (data) {
                if (!data.success) {
                    throw new Error(
                        (data.data && data.data.message) || scrywpHybridEmbedders.i18n.saveFailed
                    );
                }
                resetForm(section);
                setStatus(
                    section,
                    (data.data && data.data.message) || scrywpHybridEmbedders.i18n.saveEmbedder,
                    'success'
                );
            })
            .catch(function (err) {
                setStatus(section, (err && err.message) || scrywpHybridEmbedders.i18n.saveFailed, 'error');
                return loadEmbedders(indexName, { keepStatus: true });
            })
            .finally(function () {
                resetSaveButton(section);
            });
    }

    function deleteEmbedder(section, name, deleteBtn) {
        var indexName = section.getAttribute('data-index-name') || '';
        if (!indexName || !name) {
            return;
        }
        if (!window.confirm(scrywpHybridEmbedders.i18n.confirmDelete)) {
            return;
        }

        var row = deleteBtn && deleteBtn.closest('.scrywp-hy-embedder-row');
        if (row && row.parentNode) {
            row.parentNode.removeChild(row);
        }

        post(
            scrywpHybridEmbedders.actions.delete,
            scrywpHybridEmbedders.nonces.delete,
            {
                index_name: indexName,
                name: name
            },
            30000
        )
            .then(function (data) {
                if (!data.success) {
                    throw new Error(
                        (data.data && data.data.message) || scrywpHybridEmbedders.i18n.deleteFailed
                    );
                }

                var select = section.querySelector('select[name="hybrid_embedder"]');
                var enabled = section.querySelector('input[name="hybrid_enabled"]');
                var nameInput = section.querySelector('.scrywp-hy-embedder-name');
                var cleared = !!(data.data && data.data.cleared_selection);

                if (select && (select.value === name || (select.getAttribute('data-selected') || '') === name || cleared)) {
                    select.setAttribute('data-selected', '');
                    select.value = '';
                    if (enabled) {
                        enabled.checked = false;
                    }
                }

                if (nameInput && nameInput.value === name) {
                    resetForm(section);
                }

                setStatus(
                    section,
                    (data.data && data.data.message) || scrywpHybridEmbedders.i18n.delete,
                    'success'
                );
            })
            .catch(function (err) {
                setStatus(section, (err && err.message) || scrywpHybridEmbedders.i18n.deleteFailed, 'error');
                return loadEmbedders(indexName, { keepStatus: true });
            });
    }

    ready(function () {
        document.querySelectorAll('.scrywp-hy-hybrid-section').forEach(function (section) {
            toggleSourceFields(section);
        });

        document.addEventListener(
            'click',
            function (event) {
                // Capture phase: start the GET as soon as Configure Index is clicked.
                var configure = event.target && event.target.closest
                    ? event.target.closest('.scrywp-configure-index-button')
                    : null;
                if (configure) {
                    var indexName = configure.getAttribute('data-index-name');
                    if (indexName) {
                        loadEmbedders(indexName);
                    }
                    return;
                }

                var saveBtn = event.target && event.target.closest
                    ? event.target.closest('.scrywp-hy-embedder-save')
                    : null;
                if (saveBtn) {
                    var saveSection = saveBtn.closest('.scrywp-hy-hybrid-section');
                    if (saveSection) {
                        saveEmbedder(saveSection);
                    }
                    return;
                }

                var resetBtn = event.target && event.target.closest
                    ? event.target.closest('.scrywp-hy-embedder-reset')
                    : null;
                if (resetBtn) {
                    var resetSection = resetBtn.closest('.scrywp-hy-hybrid-section');
                    if (resetSection) {
                        resetForm(resetSection);
                        setStatus(resetSection, '', 'info');
                    }
                }
            },
            true
        );

        document.addEventListener('change', function (event) {
            if (!event.target || !event.target.classList.contains('scrywp-hy-embedder-source')) {
                return;
            }
            var section = event.target.closest('.scrywp-hy-hybrid-section');
            if (section) {
                toggleSourceFields(section);
            }
        });
    });
})();
