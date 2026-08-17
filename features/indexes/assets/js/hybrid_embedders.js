/**
 * Load Meilisearch embedders for the open Configure Index dialog (this index only).
 */
(function () {
    'use strict';

    if (typeof scrywpHybridEmbedders === 'undefined') {
        return;
    }

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
        if (!listEl) {
            return;
        }

        listEl.innerHTML = '';
        var names = Object.keys(embedders || {});
        names.sort();

        fillSelect(select, names, select ? select.getAttribute('data-selected') : '');

        if (names.length === 0) {
            var empty = document.createElement('p');
            empty.className = 'description';
            empty.textContent = scrywpHybridEmbedders.i18n.none;
            listEl.appendChild(empty);
            return;
        }

        names.forEach(function (name) {
            var config = embedders[name] || {};
            var row = document.createElement('div');
            row.className = 'scrywp-hy-embedder-row';

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

            row.appendChild(title);
            row.appendChild(detail);
            listEl.appendChild(row);
        });
    }

    function loadEmbedders(indexName) {
        var section = findSection(indexName);
        if (!section || !indexName) {
            return;
        }

        var listEl = section.querySelector('.scrywp-hy-embedders-list');
        if (listEl) {
            listEl.innerHTML = '';
            var loading = document.createElement('p');
            loading.className = 'description scrywp-hy-embedders-loading';
            loading.textContent = scrywpHybridEmbedders.i18n.loading;
            listEl.appendChild(loading);
        }
        setStatus(section, '', 'info');

        var body = new FormData();
        body.set('action', scrywpHybridEmbedders.action);
        body.set('nonce', scrywpHybridEmbedders.nonce);
        body.set('index_name', indexName);

        fetch(scrywpHybridEmbedders.ajaxUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
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

    ready(function () {
        document.addEventListener(
            'click',
            function (event) {
                var button = event.target && event.target.closest
                    ? event.target.closest('.scrywp-configure-index-button')
                    : null;
                if (!button) {
                    return;
                }
                var indexName = button.getAttribute('data-index-name');
                if (indexName) {
                    loadEmbedders(indexName);
                }
            },
            true
        );
    });
})();
