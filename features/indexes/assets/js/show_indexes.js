/**
 * Scry Search for Meilisearch Show Indexes JavaScript
 */

(function () {
    'use strict';

    // Check if scrywpIndexes is available (localized script)
    if (typeof scrywpIndexes === 'undefined') {
        return;
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Handle index posts button clicks
        var indexPostsButtons = document.querySelectorAll('.scrywp-index-posts-button');

        indexPostsButtons.forEach(function (button) {
            button.addEventListener('click', function (e) {
                e.preventDefault();

                var button = this;
                var postType = button.getAttribute('data-post-type');
                var indexDisplay = button.getAttribute('data-index-display');

                // Request confirmation
                var confirmed = confirm(
                    'Are you sure you want to index all posts of type "' + indexDisplay + '"? This will add or update all posts in the index.'
                );

                if (!confirmed) {
                    return;
                }

                // Disable button and show loading state
                button.disabled = true;
                var originalText = button.textContent;
                button.textContent = scrywpIndexes.i18n.indexing;

                // Prepare AJAX request
                var formData = new FormData();
                formData.append('action', scrywpIndexes.actions.indexPosts);
                formData.append('nonce', scrywpIndexes.nonces.indexPosts);
                formData.append('post_type', postType);

                // Send AJAX request
                fetch(scrywpIndexes.ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(function (data) {
                        if (data.success) {
                            // Show success message
                            alert(data.data.message || scrywpIndexes.i18n.postsIndexedSuccessfully);
                            // Reload page to refresh the index list
                            setTimeout(function () {
                                window.location.reload();
                            }, 500);
                        } else {
                            // Show error message
                            alert(scrywpIndexes.i18n.error + ' ' + (data.data && data.data.message ? data.data.message : scrywpIndexes.i18n.failedToIndexPosts));
                            button.disabled = false;
                            button.textContent = originalText;
                        }
                    })
                    .catch(function (error) {
                        // Show error message
                        alert(scrywpIndexes.i18n.error + ' ' + scrywpIndexes.i18n.failedToIndexPosts);
                        button.disabled = false;
                        button.textContent = originalText;
                    });
            });
        });

        // Handle index all posts button click
        var indexAllButton = document.querySelector('.scrywp-index-all-posts-button');
        if (indexAllButton) {
            indexAllButton.addEventListener('click', function (e) {
                e.preventDefault();

                var button = this;

                // Get all index post buttons (excluding ones with errors)
                var allIndexButtons = Array.from(document.querySelectorAll('.scrywp-index-posts-button'));
                var validIndexButtons = allIndexButtons.filter(function (btn) {
                    // Check if the button's parent card has an error
                    var card = btn.closest('.scrywp-index-card');
                    return card && !card.classList.contains('scrywp-index-card-error');
                });

                if (validIndexButtons.length === 0) {
                    alert(scrywpIndexes.i18n.noValidIndexesToIndex);
                    return;
                }

                // Request confirmation
                var postTypes = validIndexButtons.map(function (btn) {
                    return btn.getAttribute('data-index-display');
                }).join(', ');

                var confirmed = confirm(
                    'Are you sure you want to index all post types? This will index:\n\n' + postTypes + '\n\nThis may take a while.'
                );

                if (!confirmed) {
                    return;
                }

                // Disable button and show loading state
                button.disabled = true;
                var originalText = button.textContent;
                button.textContent = scrywpIndexes.i18n.indexingAll;

                // Disable all individual index buttons
                validIndexButtons.forEach(function (btn) {
                    btn.disabled = true;
                });

                // Process each index sequentially
                var processIndex = function (index) {
                    if (index >= validIndexButtons.length) {
                        // All done, reload page
                        alert(scrywpIndexes.i18n.allPostTypesIndexedSuccessfully);
                        setTimeout(function () {
                            window.location.reload();
                        }, 500);
                        return;
                    }

                    var currentButton = validIndexButtons[index];
                    var postType = currentButton.getAttribute('data-post-type');
                    var indexDisplay = currentButton.getAttribute('data-index-display');

                    // Update button text to show current progress
                    button.textContent = scrywpIndexes.i18n.indexing + ': ' + indexDisplay + ' (' + (index + 1) + '/' + validIndexButtons.length + ')';

                    // Prepare AJAX request
                    var formData = new FormData();
                    formData.append('action', scrywpIndexes.actions.indexPosts);
                    formData.append('nonce', scrywpIndexes.nonces.indexPosts);
                    formData.append('post_type', postType);

                    // Send AJAX request
                    fetch(scrywpIndexes.ajaxUrl, {
                        method: 'POST',
                        body: formData
                    })
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(function (data) {
                            if (data.success) {
                                // Move to next index
                                processIndex(index + 1);
                            } else {
                                // Show error but continue with next index
                                var errorMsg = data.data && data.data.message ? data.data.message : scrywpIndexes.i18n.failedToIndex;
                                console.error('Failed to index ' + indexDisplay + ': ' + errorMsg);
                                // Continue with next index anyway
                                processIndex(index + 1);
                            }
                        })
                        .catch(function (error) {
                            // Show error but continue with next index
                            console.error('Error indexing ' + indexDisplay + ':', error);
                            // Continue with next index anyway
                            processIndex(index + 1);
                        });
                };

                // Start processing
                processIndex(0);
            });
        }

        // Handle wipe index button clicks
        var wipeButtons = document.querySelectorAll('.scrywp-wipe-index-button');

        wipeButtons.forEach(function (button) {
            button.addEventListener('click', function (e) {
                e.preventDefault();

                var button = this;
                var indexName = button.getAttribute('data-index-name');
                var indexDisplay = button.getAttribute('data-index-display');

                // Request confirmation
                var confirmed = confirm(
                    'Are you sure you want to wipe the index? All documents will be deleted, and index settings will be lost. The index will be recreated automatically.'
                );

                if (!confirmed) {
                    return;
                }

                // Disable button and show loading state
                button.disabled = true;
                var originalText = button.textContent;
                button.textContent = scrywpIndexes.i18n.wiping;

                // Prepare AJAX request
                var formData = new FormData();
                formData.append('action', scrywpIndexes.actions.wipeIndex);
                formData.append('nonce', scrywpIndexes.nonces.wipeIndex);
                formData.append('index_name', indexName);

                // Send AJAX request
                fetch(scrywpIndexes.ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(function (data) {
                        if (data.success) {
                            // Show success message and reload page after a short delay
                            alert(data.data.message || scrywpIndexes.i18n.indexWipedSuccessfully);

                            // Reload page to refresh the index list
                            setTimeout(function () {
                                window.location.reload();
                            }, 500);
                        } else {
                            // Show error message
                            alert(scrywpIndexes.i18n.error + ' ' + (data.data && data.data.message ? data.data.message : scrywpIndexes.i18n.failedToWipeIndex));
                            button.disabled = false;
                            button.textContent = originalText;
                        }
                    })
                    .catch(function (error) {
                        // Show error message
                        alert(scrywpIndexes.i18n.error + ' ' + scrywpIndexes.i18n.failedToWipeIndex);
                        button.disabled = false;
                        button.textContent = originalText;
                    });
            });
        });

        // Handle search index input changes (instant search)
        var searchInputs = document.querySelectorAll('.scrywp-index-dialog-search-input');
        var searchTimeouts = {};

        searchInputs.forEach(function (searchInput) {
            var form = searchInput.closest('.scrywp-index-dialog-search-form');
            if (!form) return;

            var indexName = form.getAttribute('data-index-name');
            if (!indexName) return;

            var dialog = form.closest('.scrywp-index-dialog');
            if (!dialog) return;

            var resultsContainer = dialog.querySelector('.scrywp-index-dialog-results');
            if (!resultsContainer) return;

            var inputId = indexName; // Use index name as unique ID for timeout

            // Search function
            var performSearch = function () {
                var searchQuery = searchInput.value.trim();

                // If query is empty, show initial message
                if (!searchQuery) {
                    resultsContainer.innerHTML = '<div class="scrywp-index-dialog-results-message">' + scrywpIndexes.i18n.enterSearchQuery + '</div>';
                    return;
                }

                // Show loading state
                resultsContainer.innerHTML = '<div class="scrywp-index-dialog-loading">' + scrywpIndexes.i18n.searching + '</div>';

                // Prepare AJAX request
                var formData = new FormData();
                formData.append('action', scrywpIndexes.actions.searchIndex);
                formData.append('nonce', scrywpIndexes.nonces.searchIndex);
                formData.append('index_name', indexName);
                formData.append('search_query', searchQuery);

                // Send AJAX request
                fetch(scrywpIndexes.ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(function (data) {
                        if (data.success) {
                            var results = data.data.results || [];

                            if (results.length === 0) {
                                resultsContainer.innerHTML = '<div class="scrywp-index-dialog-no-results">' + scrywpIndexes.i18n.noResultsFound + '</div>';
                                return;
                            }

                            // Helper function to escape HTML for JSON display
                            function escapeHtml(text) {
                                var map = {
                                    '&': '&amp;',
                                    '<': '&lt;',
                                    '>': '&gt;',
                                    '"': '&quot;',
                                    "'": '&#039;'
                                };
                                return text.replace(/[&<>"']/g, function (m) { return map[m]; });
                            }

                            // Build results HTML
                            var resultsHTML = '';
                            var viewPostLabel = scrywpIndexes.i18n.viewPost;
                            var editPostLabel = scrywpIndexes.i18n.editPost;
                            var untitledLabel = scrywpIndexes.i18n.untitled;

                            results.forEach(function (result) {
                                var displayTitle = result.post_title || result.title || untitledLabel;
                                var displayExcerpt = result.post_excerpt || result.excerpt || '';

                                resultsHTML += '<div class="scrywp-index-dialog-result">';
                                resultsHTML += '<h4 class="scrywp-index-dialog-result-title">' + escapeHtml(String(displayTitle)) + '</h4>';

                                if (displayExcerpt) {
                                    resultsHTML += '<p class="scrywp-index-dialog-result-excerpt">' + escapeHtml(String(displayExcerpt)) + '</p>';
                                }

                                resultsHTML += '<div class="scrywp-index-dialog-result-meta">';
                                resultsHTML += '<span>' + escapeHtml(String(result.ID || '')) + '</span>';
                                resultsHTML += '<span>' + escapeHtml(String(result.post_type || '')) + '</span>';
                                resultsHTML += '<span>' + escapeHtml(String(result.post_status || '')) + '</span>';
                                if (result.post_date) {
                                    resultsHTML += '<span>' + escapeHtml(String(result.post_date)) + '</span>';
                                }
                                resultsHTML += '</div>';

                                // Add raw JSON dropdown
                                resultsHTML += '<details class="scrywp-index-dialog-result-json">';
                                resultsHTML += '<summary class="scrywp-index-dialog-result-json-toggle">' + scrywpIndexes.i18n.viewRawJson + '</summary>';
                                resultsHTML += '<pre class="scrywp-index-dialog-result-json-content">' + escapeHtml(JSON.stringify(result, null, 2)) + '</pre>';
                                resultsHTML += '</details>';

                                resultsHTML += '<div class="scrywp-index-dialog-result-actions">';
                                if (result.permalink) {
                                    resultsHTML += '<a href="' + result.permalink + '" target="_blank" class="scrywp-index-dialog-result-link" aria-label="' + viewPostLabel + '">' + viewPostLabel + '</a>';
                                }
                                if (result.edit_link) {
                                    resultsHTML += '<a href="' + result.edit_link + '" target="_blank" class="scrywp-index-dialog-result-edit-link" aria-label="' + editPostLabel + '">' + editPostLabel + '</a>';
                                }
                                resultsHTML += '</div>';

                                resultsHTML += '</div>';
                            });

                            resultsContainer.innerHTML = resultsHTML;
                        } else {
                            var errorMessage = data.data && data.data.message ? data.data.message : scrywpIndexes.i18n.searchFailed;
                            resultsContainer.innerHTML = '<div class="scrywp-index-dialog-no-results">' + scrywpIndexes.i18n.error + ' ' + errorMessage + '</div>';
                        }
                    })
                    .catch(function (error) {
                        resultsContainer.innerHTML = '<div class="scrywp-index-dialog-no-results">' + scrywpIndexes.i18n.errorFailedToSearchIndex + '</div>';
                    });
            };

            // Handle input events with debouncing (300ms delay)
            searchInput.addEventListener('input', function () {
                // Clear existing timeout
                if (searchTimeouts[inputId]) {
                    clearTimeout(searchTimeouts[inputId]);
                }

                // Set new timeout
                searchTimeouts[inputId] = setTimeout(performSearch, 300);
            });

            // Also handle form submission (in case user presses Enter)
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                // Clear timeout and perform search immediately
                if (searchTimeouts[inputId]) {
                    clearTimeout(searchTimeouts[inputId]);
                }
                performSearch();
            });
        });

        // Handle index settings dialog
        var configureButtons = document.querySelectorAll('.scrywp-configure-index-button');

        configureButtons.forEach(function (configureButton) {
            var indexName = configureButton.getAttribute('data-index-name');
            if (!indexName) return;

            var dialog = document.getElementById(indexName + '_settings_dialog');
            if (!dialog) return;

            var rulesList = dialog.querySelector('.scrywp-ranking-rules-list');
            var rankingRulesInputsContainer = dialog.querySelector('.scrywp-ranking-rules-hidden-inputs');
            var rankingRulesSection = dialog.querySelector('.scrywp-index-settings-section[data-setting-key="ranking_rules"]');
            var rankingRuleAttributeInput = dialog.querySelector('.scrywp-ranking-rule-attribute-input');
            var rankingRuleDirectionSelect = dialog.querySelector('.scrywp-ranking-rule-direction-select');
            var rankingRuleAddButton = dialog.querySelector('.scrywp-ranking-rule-add-button');
            var rankingRulesAddError = dialog.querySelector('.scrywp-ranking-rules-add-error');
            var synonymsEditor = dialog.querySelector('.scrywp-synonyms-editor');
            var synonymsEntriesContainer = dialog.querySelector('.scrywp-synonyms-entries');
            var synonymsHiddenInputsContainer = dialog.querySelector('.scrywp-synonyms-hidden-inputs');
            var stopWordsEditor = dialog.querySelector('.scrywp-stopwords-editor');
            var stopWordsChipsContainer = dialog.querySelector('.scrywp-stopwords-chips');
            var stopWordsInput = dialog.querySelector('.scrywp-stopwords-chip-input');
            var stopWordsHiddenInputsContainer = dialog.querySelector('.scrywp-stopwords-hidden-inputs');
            var dictionaryEditor = dialog.querySelector('.scrywp-dictionary-editor');
            var dictionaryChipsContainer = dialog.querySelector('.scrywp-dictionary-chips');
            var dictionaryInput = dialog.querySelector('.scrywp-dictionary-chip-input');
            var dictionaryHiddenInputsContainer = dialog.querySelector('.scrywp-dictionary-hidden-inputs');
            var typoToleranceEditor = dialog.querySelector('.scrywp-typo-tolerance-editor');
            var typoEnabledCheckbox = dialog.querySelector('.scrywp-typo-tolerance-enabled');
            var typoOneTypoInput = dialog.querySelector('.scrywp-typo-tolerance-one-typo');
            var typoTwoTyposInput = dialog.querySelector('.scrywp-typo-tolerance-two-typos');
            var typoDisableNumbersCheckbox = dialog.querySelector('.scrywp-typo-tolerance-disable-numbers');
            var typoDisableWordsChipsContainer = dialog.querySelector('.scrywp-typo-disable-words-chips');
            var typoDisableWordsInput = dialog.querySelector('.scrywp-typo-disable-words-chip-input');
            var typoDisableWordsHiddenInputsContainer = dialog.querySelector('.scrywp-typo-disable-words-hidden-inputs');
            var typoDisableAttributesChipsContainer = dialog.querySelector('.scrywp-typo-disable-attributes-chips');
            var typoDisableAttributesInput = dialog.querySelector('.scrywp-typo-disable-attributes-chip-input');
            var typoDisableAttributesHiddenInputsContainer = dialog.querySelector('.scrywp-typo-disable-attributes-hidden-inputs');
            var loadingDiv = dialog.querySelector('.scrywp-index-settings-loading');
            var loadedDiv = dialog.querySelector('.scrywp-index-settings-loaded');
            var settingsForm = dialog.querySelector('.scrywp-index-settings-form');
            var errorDiv = dialog.querySelector('.scrywp-index-settings-error');
            var saveButton = dialog.querySelector('.scrywp-save-index-settings-button');
            var saveErrorDiv = dialog.querySelector('.scrywp-index-settings-save-error');
            var saveErrorMessage = dialog.querySelector('.scrywp-index-settings-save-error-message');

            var currentRankingRules = [];

            function slugifyTabId(label, fallback) {
                var slug = (label || '')
                    .toString()
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');
                return slug || fallback;
            }

            function initSettingsTabs() {
                if (!settingsForm) return;

                var tabsNav = settingsForm.querySelector('.scrywp-index-settings-tabs');
                var panels = settingsForm.querySelector('.scrywp-index-settings-panels');
                if (!tabsNav || !panels) return;

                var sections = Array.from(panels.children).filter(function (el) {
                    return el.classList && el.classList.contains('scrywp-index-settings-section');
                });
                if (!sections.length) return;

                // Rebuild so plugin-injected sections are always included.
                tabsNav.innerHTML = '';

                var usedIds = {};
                var tabButtons = [];

                sections.forEach(function (section, index) {
                    var titleEl = section.querySelector('.scrywp-index-settings-section-header h4, h4');
                    var label = titleEl ? titleEl.textContent.trim() : '';
                    var baseId = section.getAttribute('data-tab') || slugifyTabId(label, 'section-' + (index + 1));
                    var tabId = baseId;
                    var suffix = 2;
                    while (usedIds[tabId]) {
                        tabId = baseId + '-' + suffix;
                        suffix += 1;
                    }
                    usedIds[tabId] = true;

                    var panelId = indexName + '-settings-panel-' + tabId;
                    section.id = panelId;
                    section.setAttribute('role', 'tabpanel');
                    section.setAttribute('aria-labelledby', indexName + '-settings-tab-' + tabId);
                    section.hidden = index !== 0;

                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'scrywp-index-settings-tab' + (index === 0 ? ' is-active' : '');
                    button.id = indexName + '-settings-tab-' + tabId;
                    button.setAttribute('role', 'tab');
                    button.setAttribute('aria-selected', index === 0 ? 'true' : 'false');
                    button.setAttribute('aria-controls', panelId);
                    button.setAttribute('tabindex', index === 0 ? '0' : '-1');
                    button.textContent = label || ('Section ' + (index + 1));
                    button.addEventListener('click', function () {
                        activateSettingsTab(index);
                    });

                    tabsNav.appendChild(button);
                    tabButtons.push(button);
                });

                function activateSettingsTab(activeIndex) {
                    sections.forEach(function (section, index) {
                        var isActive = index === activeIndex;
                        section.hidden = !isActive;
                        if (tabButtons[index]) {
                            tabButtons[index].classList.toggle('is-active', isActive);
                            tabButtons[index].setAttribute('aria-selected', isActive ? 'true' : 'false');
                            tabButtons[index].setAttribute('tabindex', isActive ? '0' : '-1');
                        }
                    });
                }

                tabsNav.addEventListener('keydown', function (event) {
                    var currentIndex = tabButtons.findIndex(function (btn) {
                        return btn.getAttribute('aria-selected') === 'true';
                    });
                    if (currentIndex < 0) return;

                    var nextIndex = currentIndex;
                    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                        nextIndex = (currentIndex + 1) % tabButtons.length;
                    } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                        nextIndex = (currentIndex - 1 + tabButtons.length) % tabButtons.length;
                    } else if (event.key === 'Home') {
                        nextIndex = 0;
                    } else if (event.key === 'End') {
                        nextIndex = tabButtons.length - 1;
                    } else {
                        return;
                    }

                    event.preventDefault();
                    activateSettingsTab(nextIndex);
                    tabButtons[nextIndex].focus();
                });
            }

            initSettingsTabs();

            // Store original button text
            var originalSaveButtonText = saveButton ? saveButton.textContent : '';

            // Reset button state function
            function resetSaveButton() {
                if (saveButton) {
                    saveButton.disabled = false;
                    saveButton.textContent = originalSaveButtonText || scrywpIndexes.i18n.saveSettings;
                }
                // Hide save error message
                if (saveErrorDiv) {
                    saveErrorDiv.style.display = 'none';
                }
            }

            // Show save error function
            function showSaveError(message) {
                if (saveErrorDiv && saveErrorMessage) {
                    saveErrorMessage.textContent = message;
                    saveErrorDiv.style.display = 'block';
                }
            }

            // Load settings when dialog opens
            configureButton.addEventListener('click', function () {
                // Reset button state when dialog opens
                resetSaveButton();

                // Small delay to ensure dialog is open
                setTimeout(function () {
                    loadIndexSettings(indexName);
                }, 100);
            });

            function normalizeTerm(term) {
                return (term || '').toString().trim();
            }

            function clearSynonymsUi() {
                if (synonymsEntriesContainer) {
                    synonymsEntriesContainer.innerHTML = '';
                }
                if (synonymsHiddenInputsContainer) {
                    synonymsHiddenInputsContainer.innerHTML = '';
                }
            }

            function syncSynonymsHiddenInputs() {
                if (!synonymsHiddenInputsContainer || !synonymsEntriesContainer) return;

                synonymsHiddenInputsContainer.innerHTML = '';

                var entryEls = Array.from(synonymsEntriesContainer.querySelectorAll('.scrywp-synonyms-entry'));
                entryEls.forEach(function (entryEl) {
                    var baseInput = entryEl.querySelector('.scrywp-synonyms-base-input');
                    var base = normalizeTerm(baseInput ? baseInput.value : '');
                    if (!base) return;

                    var chipEls = Array.from(entryEl.querySelectorAll('.scrywp-synonyms-chip'));
                    var seen = new Set();
                    chipEls.forEach(function (chipEl) {
                        var value = normalizeTerm(chipEl.getAttribute('data-value') || chipEl.textContent);
                        if (!value) return;
                        if (seen.has(value)) return;
                        seen.add(value);

                        var hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'synonyms[' + base + '][]';
                        hidden.value = value;
                        synonymsHiddenInputsContainer.appendChild(hidden);
                    });
                });
            }

            /** Commit text still in synonym / stop-word / dictionary / typo chip inputs into chips (must run before save). */
            function flushPendingSynonymAndStopWordInputs() {
                if (synonymsEntriesContainer) {
                    var entryEls = synonymsEntriesContainer.querySelectorAll('.scrywp-synonyms-entry');
                    entryEls.forEach(function (entryEl) {
                        var input = entryEl.querySelector('.scrywp-synonyms-chip-input');
                        if (!input) return;
                        var v = normalizeTerm(input.value);
                        if (v) {
                            addChip(entryEl, v);
                            input.value = '';
                        }
                    });
                }
                if (stopWordsInput) {
                    var sw = normalizeTerm(stopWordsInput.value);
                    if (sw) {
                        addStopWordChip(sw);
                        stopWordsInput.value = '';
                    }
                }
                if (dictionaryInput) {
                    var dw = normalizeTerm(dictionaryInput.value);
                    if (dw) {
                        addDictionaryChip(dw);
                        dictionaryInput.value = '';
                    }
                }
                if (typoDisableWordsInput) {
                    var tw = normalizeTerm(typoDisableWordsInput.value);
                    if (tw) {
                        addTypoDisableWordChip(tw);
                        typoDisableWordsInput.value = '';
                    }
                }
                if (typoDisableAttributesInput) {
                    var ta = normalizeTerm(typoDisableAttributesInput.value);
                    if (ta) {
                        addTypoDisableAttributeChip(ta);
                        typoDisableAttributesInput.value = '';
                    }
                }
            }

            function addChip(entryEl, term) {
                var value = normalizeTerm(term);
                if (!value) return;

                var chipsContainer = entryEl.querySelector('.scrywp-synonyms-chips');
                if (!chipsContainer) return;

                // Prevent duplicates within an entry
                var existing = Array.from(chipsContainer.querySelectorAll('.scrywp-synonyms-chip')).some(function (chipEl) {
                    return normalizeTerm(chipEl.getAttribute('data-value')) === value;
                });
                if (existing) return;

                var chip = document.createElement('span');
                chip.className = 'scrywp-synonyms-chip';
                chip.setAttribute('data-value', value);

                var label = document.createElement('span');
                label.className = 'scrywp-synonyms-chip-label';
                label.textContent = value;

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'scrywp-synonyms-chip-remove';
                removeBtn.setAttribute('aria-label', 'Remove synonym');
                removeBtn.textContent = '×';
                removeBtn.addEventListener('click', function () {
                    chip.remove();
                    syncSynonymsHiddenInputs();
                });

                chip.appendChild(label);
                chip.appendChild(removeBtn);
                chipsContainer.appendChild(chip);
                syncSynonymsHiddenInputs();
            }

            function addSynonymsEntry(baseValue, synonymsArray) {
                if (!synonymsEntriesContainer) return;

                var entry = document.createElement('div');
                entry.className = 'scrywp-synonyms-entry';

                var baseRow = document.createElement('div');
                baseRow.className = 'scrywp-synonyms-base-row';

                var baseLabel = document.createElement('label');
                baseLabel.className = 'scrywp-synonyms-base-label';
                baseLabel.textContent = 'Base term';

                var baseInput = document.createElement('input');
                baseInput.type = 'text';
                baseInput.className = 'regular-text scrywp-synonyms-base-input';
                baseInput.placeholder = 'e.g. car';
                if (baseValue) {
                    baseInput.value = baseValue;
                }
                baseInput.addEventListener('input', function () {
                    syncSynonymsHiddenInputs();
                });

                var removeEntryBtn = document.createElement('button');
                removeEntryBtn.type = 'button';
                removeEntryBtn.className = 'button-link-delete scrywp-synonyms-remove-entry';
                removeEntryBtn.textContent = 'Remove';
                removeEntryBtn.addEventListener('click', function () {
                    entry.remove();
                    syncSynonymsHiddenInputs();
                });

                baseRow.appendChild(baseLabel);
                baseRow.appendChild(baseInput);
                baseRow.appendChild(removeEntryBtn);

                var synonymsRow = document.createElement('div');
                synonymsRow.className = 'scrywp-synonyms-synonyms-row';

                var chipsContainer = document.createElement('div');
                chipsContainer.className = 'scrywp-synonyms-chips';

                var synonymInput = document.createElement('input');
                synonymInput.type = 'text';
                synonymInput.className = 'regular-text scrywp-synonyms-chip-input';
                synonymInput.placeholder = 'Type a synonym and press Enter';

                synonymInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ',') {
                        e.preventDefault();
                        addChip(entry, synonymInput.value);
                        synonymInput.value = '';
                        return;
                    }

                    if (e.key === 'Backspace' && normalizeTerm(synonymInput.value) === '') {
                        var chips = chipsContainer.querySelectorAll('.scrywp-synonyms-chip');
                        if (chips.length > 0) {
                            chips[chips.length - 1].remove();
                            syncSynonymsHiddenInputs();
                        }
                    }
                });

                synonymsRow.appendChild(chipsContainer);
                synonymsRow.appendChild(synonymInput);

                entry.appendChild(baseRow);
                entry.appendChild(synonymsRow);

                synonymsEntriesContainer.appendChild(entry);

                if (Array.isArray(synonymsArray)) {
                    synonymsArray.forEach(function (term) {
                        addChip(entry, term);
                    });
                }

                syncSynonymsHiddenInputs();
            }

            function setupSynonymsInteractions() {
                if (!synonymsEditor) return;
                if (synonymsEditor.dataset.synonymsListenersAttached === '1') return;
                synonymsEditor.dataset.synonymsListenersAttached = '1';

                var addEntryBtn = synonymsEditor.querySelector('.scrywp-synonyms-add-entry');
                if (addEntryBtn) {
                    addEntryBtn.addEventListener('click', function () {
                        addSynonymsEntry('', []);
                    });
                }
            }

            function hydrateSynonymsFromObject(synonymsObj) {
                clearSynonymsUi();
                setupSynonymsInteractions();

                if (!synonymsObj || typeof synonymsObj !== 'object') {
                    syncSynonymsHiddenInputs();
                    return;
                }

                Object.keys(synonymsObj).forEach(function (base) {
                    var list = synonymsObj[base];
                    if (!Array.isArray(list)) {
                        list = [];
                    }
                    addSynonymsEntry(base, list);
                });

                syncSynonymsHiddenInputs();
            }

            function clearStopWordsUi() {
                if (stopWordsChipsContainer) {
                    stopWordsChipsContainer.innerHTML = '';
                }
                if (stopWordsHiddenInputsContainer) {
                    stopWordsHiddenInputsContainer.innerHTML = '';
                }
                if (stopWordsInput) {
                    stopWordsInput.value = '';
                }
            }

            function syncStopWordsHiddenInputs() {
                if (!stopWordsHiddenInputsContainer || !stopWordsChipsContainer) return;
                stopWordsHiddenInputsContainer.innerHTML = '';

                var chips = Array.from(stopWordsChipsContainer.querySelectorAll('.scrywp-stopwords-chip'));
                var seen = new Set();
                chips.forEach(function (chipEl) {
                    var value = normalizeTerm(chipEl.getAttribute('data-value') || chipEl.textContent);
                    if (!value) return;
                    if (seen.has(value)) return;
                    seen.add(value);

                    var hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'stop_words[]';
                    hidden.value = value;
                    stopWordsHiddenInputsContainer.appendChild(hidden);
                });
            }

            function addStopWordChip(term) {
                var value = normalizeTerm(term);
                if (!value || !stopWordsChipsContainer) return;

                var exists = Array.from(stopWordsChipsContainer.querySelectorAll('.scrywp-stopwords-chip')).some(function (chipEl) {
                    return normalizeTerm(chipEl.getAttribute('data-value')) === value;
                });
                if (exists) return;

                var chip = document.createElement('span');
                chip.className = 'scrywp-stopwords-chip';
                chip.setAttribute('data-value', value);

                var label = document.createElement('span');
                label.className = 'scrywp-stopwords-chip-label';
                label.textContent = value;

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'scrywp-stopwords-chip-remove';
                removeBtn.setAttribute('aria-label', 'Remove stop word');
                removeBtn.textContent = '×';
                removeBtn.addEventListener('click', function () {
                    chip.remove();
                    syncStopWordsHiddenInputs();
                });

                chip.appendChild(label);
                chip.appendChild(removeBtn);
                stopWordsChipsContainer.appendChild(chip);
                syncStopWordsHiddenInputs();
            }

            function setupStopWordsInteractions() {
                if (!stopWordsEditor) return;
                if (stopWordsEditor.dataset.stopWordsListenersAttached === '1') return;
                stopWordsEditor.dataset.stopWordsListenersAttached = '1';

                if (stopWordsInput) {
                    stopWordsInput.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter' || e.key === ',') {
                            e.preventDefault();
                            addStopWordChip(stopWordsInput.value);
                            stopWordsInput.value = '';
                            return;
                        }

                        if (e.key === 'Backspace' && normalizeTerm(stopWordsInput.value) === '') {
                            var chips = stopWordsChipsContainer ? stopWordsChipsContainer.querySelectorAll('.scrywp-stopwords-chip') : [];
                            if (chips.length > 0) {
                                chips[chips.length - 1].remove();
                                syncStopWordsHiddenInputs();
                            }
                        }
                    });
                }
            }

            function hydrateStopWordsFromArray(stopWordsArr) {
                clearStopWordsUi();
                setupStopWordsInteractions();

                if (!Array.isArray(stopWordsArr)) {
                    stopWordsArr = [];
                }
                stopWordsArr.forEach(function (word) {
                    addStopWordChip(word);
                });
                syncStopWordsHiddenInputs();
            }

            function createChipListHelpers(config) {
                var chipsContainer = config.chipsContainer;
                var hiddenContainer = config.hiddenContainer;
                var input = config.input;
                var editor = config.editor;
                var hiddenName = config.hiddenName;
                var chipClass = config.chipClass;
                var labelClass = config.labelClass;
                var removeClass = config.removeClass;
                var removeLabel = config.removeLabel || 'Remove';
                var attachedFlag = config.attachedFlag;

                function clearUi() {
                    if (chipsContainer) chipsContainer.innerHTML = '';
                    if (hiddenContainer) hiddenContainer.innerHTML = '';
                    if (input) input.value = '';
                }

                function syncHiddenInputs() {
                    if (!hiddenContainer || !chipsContainer) return;
                    hiddenContainer.innerHTML = '';
                    var seen = new Set();
                    Array.from(chipsContainer.querySelectorAll('.' + chipClass)).forEach(function (chipEl) {
                        var value = normalizeTerm(chipEl.getAttribute('data-value') || chipEl.textContent);
                        if (!value || seen.has(value)) return;
                        seen.add(value);
                        var hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = hiddenName;
                        hidden.value = value;
                        hiddenContainer.appendChild(hidden);
                    });
                }

                function addChipValue(term) {
                    var value = normalizeTerm(term);
                    if (!value || !chipsContainer) return;
                    var exists = Array.from(chipsContainer.querySelectorAll('.' + chipClass)).some(function (chipEl) {
                        return normalizeTerm(chipEl.getAttribute('data-value')) === value;
                    });
                    if (exists) return;

                    var chip = document.createElement('span');
                    chip.className = chipClass;
                    chip.setAttribute('data-value', value);

                    var label = document.createElement('span');
                    label.className = labelClass;
                    label.textContent = value;

                    var removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = removeClass;
                    removeBtn.setAttribute('aria-label', removeLabel);
                    removeBtn.textContent = '×';
                    removeBtn.addEventListener('click', function () {
                        chip.remove();
                        syncHiddenInputs();
                    });

                    chip.appendChild(label);
                    chip.appendChild(removeBtn);
                    chipsContainer.appendChild(chip);
                    syncHiddenInputs();
                }

                function setupInteractions() {
                    if (!editor && !input) return;
                    var target = editor || input;
                    if (target.dataset[attachedFlag] === '1') return;
                    target.dataset[attachedFlag] = '1';

                    if (!input) return;
                    input.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter' || e.key === ',') {
                            e.preventDefault();
                            addChipValue(input.value);
                            input.value = '';
                            return;
                        }
                        if (e.key === 'Backspace' && normalizeTerm(input.value) === '') {
                            var chips = chipsContainer ? chipsContainer.querySelectorAll('.' + chipClass) : [];
                            if (chips.length > 0) {
                                chips[chips.length - 1].remove();
                                syncHiddenInputs();
                            }
                        }
                    });
                }

                function hydrateFromArray(values) {
                    clearUi();
                    setupInteractions();
                    if (!Array.isArray(values)) values = [];
                    values.forEach(function (value) {
                        addChipValue(value);
                    });
                    syncHiddenInputs();
                }

                return {
                    clearUi: clearUi,
                    syncHiddenInputs: syncHiddenInputs,
                    addChipValue: addChipValue,
                    setupInteractions: setupInteractions,
                    hydrateFromArray: hydrateFromArray
                };
            }

            var dictionaryChips = createChipListHelpers({
                chipsContainer: dictionaryChipsContainer,
                hiddenContainer: dictionaryHiddenInputsContainer,
                input: dictionaryInput,
                editor: dictionaryEditor,
                hiddenName: 'dictionary[]',
                chipClass: 'scrywp-dictionary-chip',
                labelClass: 'scrywp-dictionary-chip-label',
                removeClass: 'scrywp-dictionary-chip-remove',
                removeLabel: 'Remove dictionary word',
                attachedFlag: 'dictionaryListenersAttached'
            });

            function addDictionaryChip(term) {
                dictionaryChips.addChipValue(term);
            }

            function setupDictionaryInteractions() {
                dictionaryChips.setupInteractions();
            }

            function hydrateDictionaryFromArray(dictionaryArr) {
                dictionaryChips.hydrateFromArray(dictionaryArr);
            }

            var typoDisableWordsChips = createChipListHelpers({
                chipsContainer: typoDisableWordsChipsContainer,
                hiddenContainer: typoDisableWordsHiddenInputsContainer,
                input: typoDisableWordsInput,
                editor: dialog.querySelector('.scrywp-typo-disable-words-editor'),
                hiddenName: 'typo_tolerance[disableOnWords][]',
                chipClass: 'scrywp-typo-chip',
                labelClass: 'scrywp-typo-chip-label',
                removeClass: 'scrywp-typo-chip-remove',
                removeLabel: 'Remove word',
                attachedFlag: 'typoDisableWordsListenersAttached'
            });

            var typoDisableAttributesChips = createChipListHelpers({
                chipsContainer: typoDisableAttributesChipsContainer,
                hiddenContainer: typoDisableAttributesHiddenInputsContainer,
                input: typoDisableAttributesInput,
                editor: dialog.querySelector('.scrywp-typo-disable-attributes-editor'),
                hiddenName: 'typo_tolerance[disableOnAttributes][]',
                chipClass: 'scrywp-typo-chip',
                labelClass: 'scrywp-typo-chip-label',
                removeClass: 'scrywp-typo-chip-remove',
                removeLabel: 'Remove attribute',
                attachedFlag: 'typoDisableAttributesListenersAttached'
            });

            function addTypoDisableWordChip(term) {
                typoDisableWordsChips.addChipValue(term);
            }

            function addTypoDisableAttributeChip(term) {
                typoDisableAttributesChips.addChipValue(term);
            }

            function setupTypoToleranceInteractions() {
                typoDisableWordsChips.setupInteractions();
                typoDisableAttributesChips.setupInteractions();
            }

            function hydrateTypoToleranceFromObject(typoObj) {
                setupTypoToleranceInteractions();
                if (!typoObj || typeof typoObj !== 'object') {
                    typoObj = {};
                }

                if (typoEnabledCheckbox) {
                    typoEnabledCheckbox.checked = typoObj.enabled !== false;
                }
                if (typoDisableNumbersCheckbox) {
                    typoDisableNumbersCheckbox.checked = !!typoObj.disableOnNumbers;
                }

                var minSizes = typoObj.minWordSizeForTypos && typeof typoObj.minWordSizeForTypos === 'object'
                    ? typoObj.minWordSizeForTypos
                    : {};
                if (typoOneTypoInput) {
                    typoOneTypoInput.value = minSizes.oneTypo != null ? minSizes.oneTypo : 5;
                }
                if (typoTwoTyposInput) {
                    typoTwoTyposInput.value = minSizes.twoTypos != null ? minSizes.twoTypos : 9;
                }

                typoDisableWordsChips.hydrateFromArray(Array.isArray(typoObj.disableOnWords) ? typoObj.disableOnWords : []);
                typoDisableAttributesChips.hydrateFromArray(Array.isArray(typoObj.disableOnAttributes) ? typoObj.disableOnAttributes : []);
            }

            // Function to initialize settings from server-rendered form controls
            function loadIndexSettings(indexName) {
                loadingDiv.style.display = 'block';
                loadedDiv.style.display = 'none';
                errorDiv.style.display = 'none';

                // Reset button state when loading
                resetSaveButton();

                try {
                    hydrateRankingRulesFromDom();
                    syncRankingRulesInputs();
                    setupDragAndDrop();
                    setupCustomRankingRulesInteractions();
                    setupSearchableFieldsInteractions();
                    setupSynonymsInteractions();
                    setupStopWordsInteractions();
                    setupDictionaryInteractions();
                    setupTypoToleranceInteractions();

                    // Fetch latest settings (including synonyms) from server/Meilisearch.
                    var settingsFormData = new FormData();
                    settingsFormData.set('action', scrywpIndexes.actions.getIndexSettings);
                    settingsFormData.set('nonce', scrywpIndexes.nonces.getIndexSettings);
                    settingsFormData.set('index_name', indexName);

                    fetch(scrywpIndexes.ajaxUrl, {
                        method: 'POST',
                        body: settingsFormData
                    })
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(function (data) {
                            if (!data.success) {
                                var msg = data.data && data.data.message ? data.data.message : scrywpIndexes.i18n.errorFailedToLoadSettings;
                                showSettingsError(msg);
                                return;
                            }

                            if (data.data && data.data.synonyms != null && typeof data.data.synonyms === 'object') {
                                hydrateSynonymsFromObject(data.data.synonyms);
                            } else {
                                hydrateSynonymsFromObject({});
                            }

                            if (data.data && data.data.stop_words) {
                                hydrateStopWordsFromArray(data.data.stop_words);
                            } else {
                                hydrateStopWordsFromArray([]);
                            }

                            if (data.data && data.data.dictionary) {
                                hydrateDictionaryFromArray(data.data.dictionary);
                            } else {
                                hydrateDictionaryFromArray([]);
                            }

                            if (data.data && data.data.typo_tolerance) {
                                hydrateTypoToleranceFromObject(data.data.typo_tolerance);
                            } else {
                                hydrateTypoToleranceFromObject({});
                            }

                            if (data.data && Array.isArray(data.data.searchable_attributes)) {
                                hydrateSearchableAttributesFromArray(data.data.searchable_attributes);
                            }

                            updateSettingsRawJsonPanels(data.data || {});

                            loadingDiv.style.display = 'none';
                            loadedDiv.style.display = 'flex';
                            resetSaveButton();
                        })
                        .catch(function () {
                            showSettingsError(scrywpIndexes.i18n.errorFailedToLoadSettings);
                        });
                } catch (error) {
                    showSettingsError(scrywpIndexes.i18n.errorFailedToLoadSettings);
                }
            }

            // Function to show error
            function showSettingsError(message) {
                loadingDiv.style.display = 'none';
                loadedDiv.style.display = 'none';
                errorDiv.style.display = 'block';
                errorDiv.querySelector('.scrywp-index-settings-error-message').textContent = message;
            }

            function ensureSettingsRawJsonPanel(section) {
                if (!section) return null;

                var existing = section.querySelector('.scrywp-index-settings-raw-json');
                if (existing) {
                    return existing.querySelector('.scrywp-index-settings-raw-json-content') || existing.querySelector('pre');
                }

                var details = document.createElement('details');
                details.className = 'scrywp-index-dialog-result-json scrywp-index-settings-raw-json';

                var summary = document.createElement('summary');
                summary.className = 'scrywp-index-dialog-result-json-toggle';
                summary.textContent = (scrywpIndexes.i18n && scrywpIndexes.i18n.viewRawJson) ? scrywpIndexes.i18n.viewRawJson : 'View Raw JSON';

                var pre = document.createElement('pre');
                pre.className = 'scrywp-index-dialog-result-json-content scrywp-index-settings-raw-json-content';
                pre.textContent = 'null';

                details.appendChild(summary);
                details.appendChild(pre);
                section.appendChild(details);

                return pre;
            }

            function updateSettingsRawJsonPanels(settings) {
                if (!settingsForm || !settings || typeof settings !== 'object') return;

                var sections = settingsForm.querySelectorAll('.scrywp-index-settings-section[data-setting-key]');
                sections.forEach(function (section) {
                    var key = section.getAttribute('data-setting-key');
                    if (!key || !Object.prototype.hasOwnProperty.call(settings, key)) {
                        return;
                    }

                    var pre = ensureSettingsRawJsonPanel(section);
                    if (!pre) return;

                    try {
                        pre.textContent = JSON.stringify(settings[key], null, 2);
                    } catch (e) {
                        pre.textContent = String(settings[key]);
                    }
                });
            }

            function hydrateRankingRulesFromDom() {
                if (!rulesList) {
                    currentRankingRules = [];
                    return;
                }
                currentRankingRules = Array.from(rulesList.querySelectorAll('.scrywp-ranking-rule-item'))
                    .map(function (item) {
                        if (item.dataset.rule) {
                            return item.dataset.rule;
                        }
                        var label = item.querySelector('.scrywp-ranking-rule-label');
                        return label ? label.textContent.trim() : '';
                    })
                    .filter(function (rule) {
                        return !!rule;
                    });
            }

            // Function to render ranking rules
            function isCustomRankingRule(rule) {
                return /^.+:(asc|desc)$/i.test(rule || '');
            }

            function showRankingRulesAddError(message) {
                if (!rankingRulesAddError) return;
                if (!message) {
                    rankingRulesAddError.style.display = 'none';
                    rankingRulesAddError.textContent = '';
                    return;
                }
                rankingRulesAddError.textContent = message;
                rankingRulesAddError.style.display = 'block';
            }

            function normalizeCustomRankingAttribute(attribute) {
                return (attribute || '')
                    .toString()
                    .trim()
                    .replace(/\s+/g, '')
                    .replace(/:(asc|desc)$/i, '');
            }

            function addCustomRankingRule() {
                var attribute = normalizeCustomRankingAttribute(
                    rankingRuleAttributeInput ? rankingRuleAttributeInput.value : ''
                );
                var direction = rankingRuleDirectionSelect ? rankingRuleDirectionSelect.value : 'desc';
                if (direction !== 'asc' && direction !== 'desc') {
                    direction = 'desc';
                }

                if (!attribute) {
                    showRankingRulesAddError(
                        (scrywpIndexes.i18n && scrywpIndexes.i18n.customRankingAttributeRequired)
                            ? scrywpIndexes.i18n.customRankingAttributeRequired
                            : 'Please enter an attribute name.'
                    );
                    return;
                }

                if (!/^[A-Za-z0-9_./-]+$/.test(attribute)) {
                    showRankingRulesAddError(
                        (scrywpIndexes.i18n && scrywpIndexes.i18n.customRankingAttributeInvalid)
                            ? scrywpIndexes.i18n.customRankingAttributeInvalid
                            : 'Attribute names can only contain letters, numbers, underscores, dots, slashes, and hyphens.'
                    );
                    return;
                }

                var rule = attribute + ':' + direction;
                if (currentRankingRules.indexOf(rule) !== -1) {
                    showRankingRulesAddError(
                        (scrywpIndexes.i18n && scrywpIndexes.i18n.customRankingRuleExists)
                            ? scrywpIndexes.i18n.customRankingRuleExists
                            : 'That custom ranking rule is already in the list.'
                    );
                    return;
                }

                showRankingRulesAddError('');
                currentRankingRules.push(rule);
                if (rankingRuleAttributeInput) {
                    rankingRuleAttributeInput.value = '';
                    rankingRuleAttributeInput.focus();
                }
                renderRankingRules();
            }

            function setupCustomRankingRulesInteractions() {
                if (!rankingRulesSection) return;
                if (rankingRulesSection.dataset.customRankingListenersAttached === '1') return;
                rankingRulesSection.dataset.customRankingListenersAttached = '1';

                if (rankingRuleAddButton) {
                    rankingRuleAddButton.addEventListener('click', function (e) {
                        e.preventDefault();
                        addCustomRankingRule();
                    });
                }

                if (rankingRuleAttributeInput) {
                    rankingRuleAttributeInput.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            addCustomRankingRule();
                        }
                    });
                }

                if (rulesList) {
                    rulesList.addEventListener('click', function (e) {
                        var removeBtn = e.target.closest('.scrywp-ranking-rule-remove');
                        if (!removeBtn) return;
                        e.preventDefault();
                        var item = removeBtn.closest('.scrywp-ranking-rule-item');
                        if (!item) return;
                        var rule = item.dataset.rule || '';
                        currentRankingRules = currentRankingRules.filter(function (existing) {
                            return existing !== rule;
                        });
                        renderRankingRules();
                    });
                }
            }

            function renderRankingRules() {
                if (!rulesList) return;

                rulesList.innerHTML = '';

                currentRankingRules.forEach(function (rule, index) {
                    var li = document.createElement('li');
                    var custom = isCustomRankingRule(rule);
                    li.className = 'scrywp-ranking-rule-item' + (custom ? ' scrywp-ranking-rule-item-custom' : '');
                    li.draggable = true;
                    li.dataset.rule = rule;
                    li.dataset.index = index;
                    li.dataset.custom = custom ? '1' : '0';

                    var handle = document.createElement('span');
                    handle.className = 'scrywp-ranking-rule-handle';
                    handle.textContent = '☰';
                    handle.setAttribute('aria-label', scrywpIndexes.i18n.dragToReorder);

                    var label = document.createElement('span');
                    label.className = 'scrywp-ranking-rule-label';
                    label.textContent = rule;

                    li.appendChild(handle);
                    li.appendChild(label);

                    if (custom) {
                        var removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'scrywp-ranking-rule-remove';
                        removeBtn.setAttribute(
                            'aria-label',
                            (scrywpIndexes.i18n && scrywpIndexes.i18n.removeCustomRankingRule)
                                ? scrywpIndexes.i18n.removeCustomRankingRule
                                : 'Remove custom ranking rule'
                        );
                        removeBtn.textContent = '×';
                        li.appendChild(removeBtn);
                    }

                    rulesList.appendChild(li);
                });

                syncRankingRulesInputs();

                // Setup drag and drop
                setupDragAndDrop();
            }

            // Keep ranking rules serialized as ordered hidden form inputs.
            function syncRankingRulesInputs() {
                if (!settingsForm || !rankingRulesInputsContainer) return;

                rankingRulesInputsContainer.innerHTML = '';

                currentRankingRules.forEach(function (rule) {
                    var hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'ranking_rules[]';
                    hiddenInput.value = rule;
                    rankingRulesInputsContainer.appendChild(hiddenInput);
                });
            }

            // Function to setup drag and drop
            function setupDragAndDrop() {
                var items = rulesList.querySelectorAll('.scrywp-ranking-rule-item');
                var draggedElement = null;

                items.forEach(function (item) {
                    if (item.dataset.dragListenersAttached === '1') {
                        return;
                    }
                    item.dataset.dragListenersAttached = '1';

                    item.addEventListener('dragstart', function (e) {
                        draggedElement = this;
                        this.style.opacity = '0.5';
                        e.dataTransfer.effectAllowed = 'move';
                    });

                    item.addEventListener('dragend', function (e) {
                        this.style.opacity = '1';
                        items.forEach(function (it) {
                            it.classList.remove('scrywp-ranking-rule-drag-over');
                        });
                    });

                    item.addEventListener('dragover', function (e) {
                        if (e.preventDefault) {
                            e.preventDefault();
                        }
                        e.dataTransfer.dropEffect = 'move';
                        if (this !== draggedElement) {
                            this.classList.add('scrywp-ranking-rule-drag-over');
                        }
                        return false;
                    });

                    item.addEventListener('dragleave', function (e) {
                        this.classList.remove('scrywp-ranking-rule-drag-over');
                    });

                    item.addEventListener('drop', function (e) {
                        if (e.stopPropagation) {
                            e.stopPropagation();
                        }

                        if (draggedElement !== this) {
                            var allItems = Array.from(rulesList.querySelectorAll('.scrywp-ranking-rule-item'));
                            var draggedIndex = allItems.indexOf(draggedElement);
                            var targetIndex = allItems.indexOf(this);

                            // Reorder array
                            var rule = currentRankingRules[draggedIndex];
                            currentRankingRules.splice(draggedIndex, 1);
                            currentRankingRules.splice(targetIndex, 0, rule);

                            // Re-render
                            renderRankingRules();
                        }

                        return false;
                    });
                });
            }
            function getSearchableFieldsTree() {
                return dialog.querySelector('.scrywp-searchable-fields-tree[data-fields-type="searchable"]');
            }

            function getSearchableDraggablePath(el) {
                if (!el) return '';
                if (el.getAttribute('data-field-path')) {
                    return el.getAttribute('data-field-path');
                }
                var checkbox = el.querySelector('.scrywp-searchable-field-checkbox');
                return checkbox ? (checkbox.getAttribute('data-field-path') || checkbox.value || '') : '';
            }

            function getSearchableDraggableRank(el, selected) {
                var path = getSearchableDraggablePath(el);
                var rank = selected.indexOf(path);
                if (rank !== -1) {
                    return rank;
                }
                if (el.classList.contains('scrywp-searchable-field-group')) {
                    var childBoxes = el.querySelectorAll('.scrywp-searchable-field-children .scrywp-searchable-field-checkbox');
                    var best = -1;
                    childBoxes.forEach(function (checkbox) {
                        var childPath = checkbox.getAttribute('data-field-path') || checkbox.value;
                        var childRank = selected.indexOf(childPath);
                        if (childRank !== -1 && (best === -1 || childRank < best)) {
                            best = childRank;
                        }
                    });
                    if (best !== -1) {
                        return best;
                    }
                }
                return Number.MAX_SAFE_INTEGER;
            }

            function reorderSearchableDraggables(container, selected) {
                if (!container) return;
                var items = Array.from(container.children).filter(function (child) {
                    return child.classList && child.classList.contains('scrywp-searchable-field-draggable');
                });
                items.sort(function (a, b) {
                    var rankA = getSearchableDraggableRank(a, selected);
                    var rankB = getSearchableDraggableRank(b, selected);
                    if (rankA === rankB) {
                        return items.indexOf(a) - items.indexOf(b);
                    }
                    return rankA - rankB;
                });
                items.forEach(function (item) {
                    container.appendChild(item);
                });
            }

            function hydrateSearchableAttributesFromArray(attrs) {
                var tree = getSearchableFieldsTree();
                if (!tree) return;

                var selected = Array.isArray(attrs)
                    ? attrs.filter(function (attr) { return typeof attr === 'string' && attr !== ''; })
                    : [];
                var checkboxes = tree.querySelectorAll('.scrywp-searchable-field-checkbox');
                var wildcard = selected.indexOf('*') !== -1;
                if (wildcard) {
                    selected = Array.from(checkboxes).map(function (checkbox) {
                        return checkbox.getAttribute('data-field-path') || checkbox.value;
                    }).filter(function (path) {
                        return !!path;
                    });
                }
                var selectedSet = {};
                selected.forEach(function (attr) {
                    selectedSet[attr] = true;
                });

                checkboxes.forEach(function (checkbox) {
                    var path = checkbox.getAttribute('data-field-path') || checkbox.value;
                    checkbox.checked = wildcard || !!selectedSet[path];
                });

                reorderSearchableDraggables(tree, selected);
                tree.querySelectorAll('.scrywp-searchable-field-group').forEach(function (group) {
                    var childrenDiv = group.querySelector('.scrywp-searchable-field-children');
                    reorderSearchableDraggables(childrenDiv, selected);
                    if (!childrenDiv) return;
                    var hasCheckedChild = !!childrenDiv.querySelector('.scrywp-searchable-field-checkbox:checked');
                    var expandButton = group.querySelector('.scrywp-searchable-field-expand');
                    if (hasCheckedChild) {
                        childrenDiv.style.display = 'block';
                        if (expandButton) {
                            expandButton.textContent = '▼';
                        }
                    }
                });
            }

            function setupSearchableFieldsDragAndDrop(tree) {
                if (!tree || tree.dataset.dragListenersAttached === '1') {
                    return;
                }
                tree.dataset.dragListenersAttached = '1';

                var draggedElement = null;

                function getDragListItem(fromEl, dragged) {
                    var list = dragged.parentNode;
                    var el = fromEl && fromEl.nodeType === 1 ? fromEl : (fromEl && fromEl.parentElement);
                    while (el && el !== list) {
                        if (el.parentNode === list && el.classList.contains('scrywp-searchable-field-draggable')) {
                            return el;
                        }
                        el = el.parentNode;
                    }
                    return null;
                }

                tree.addEventListener('dragstart', function (e) {
                    if (e.target.closest('input, button, a')) {
                        e.preventDefault();
                        return;
                    }
                    var item = e.target.closest('.scrywp-searchable-field-draggable');
                    if (!item || !tree.contains(item)) {
                        return;
                    }
                    draggedElement = item;
                    item.classList.add('scrywp-searchable-field-dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    try {
                        e.dataTransfer.setData('text/plain', getSearchableDraggablePath(item));
                    } catch (err) {
                        // Some browsers require setData for the drag to start.
                    }
                    e.stopPropagation();
                });

                tree.addEventListener('dragend', function () {
                    if (draggedElement) {
                        draggedElement.classList.remove('scrywp-searchable-field-dragging');
                    }
                    tree.querySelectorAll('.scrywp-searchable-field-drag-over').forEach(function (el) {
                        el.classList.remove('scrywp-searchable-field-drag-over');
                    });
                    draggedElement = null;
                });

                tree.addEventListener('dragover', function (e) {
                    if (!draggedElement) {
                        return;
                    }
                    var list = draggedElement.parentNode;
                    if (!list || !list.contains(e.target)) {
                        return;
                    }
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';

                    var item = getDragListItem(e.target, draggedElement);
                    if (!item || item === draggedElement) {
                        return;
                    }

                    var rect = item.getBoundingClientRect();
                    if (e.clientY < rect.top + (rect.height / 2)) {
                        list.insertBefore(draggedElement, item);
                    } else {
                        list.insertBefore(draggedElement, item.nextSibling);
                    }
                });

                tree.addEventListener('drop', function (e) {
                    if (!draggedElement) {
                        return;
                    }
                    e.preventDefault();
                    e.stopPropagation();
                });
            }

            function setupFieldTreeInteractions(tree) {
                if (!tree) return;

                var groups = tree.querySelectorAll('.scrywp-searchable-field-group');
                groups.forEach(function (group) {
                    if (group.dataset.groupListenersAttached === '1') {
                        return;
                    }
                    group.dataset.groupListenersAttached = '1';

                    var groupCheckbox = group.querySelector('.scrywp-searchable-field-group-label .scrywp-searchable-field-checkbox');
                    var expandButton = group.querySelector('.scrywp-searchable-field-expand');
                    var childrenDiv = group.querySelector('.scrywp-searchable-field-children');

                    if (expandButton && childrenDiv) {
                        expandButton.addEventListener('click', function () {
                            var isExpanded = childrenDiv.style.display !== 'none';
                            childrenDiv.style.display = isExpanded ? 'none' : 'block';
                            expandButton.textContent = isExpanded ? '▶' : '▼';
                        });
                    }

                    if (groupCheckbox && childrenDiv) {
                        groupCheckbox.addEventListener('change', function () {
                            var children = childrenDiv.querySelectorAll('.scrywp-searchable-field-checkbox');
                            children.forEach(function (child) {
                                child.checked = groupCheckbox.checked;
                            });
                        });
                    }
                });

                if (tree.getAttribute('data-fields-type') === 'searchable') {
                    setupSearchableFieldsDragAndDrop(tree);
                }
            }

            function setupSearchableFieldsInteractions() {
                var fieldTrees = dialog.querySelectorAll('.scrywp-searchable-fields-tree');
                fieldTrees.forEach(function (tree) {
                    setupFieldTreeInteractions(tree);
                });
            }

            // Save settings
            if (saveButton) {
                if (settingsForm) {
                    settingsForm.addEventListener('submit', function (e) {
                        e.preventDefault();
                        flushPendingSynonymAndStopWordInputs();
                        syncSynonymsHiddenInputs();
                        syncStopWordsHiddenInputs();
                        dictionaryChips.syncHiddenInputs();
                        typoDisableWordsChips.syncHiddenInputs();
                        typoDisableAttributesChips.syncHiddenInputs();
                        saveButton.click();
                    });
                }

                saveButton.addEventListener('click', function () {
                    var button = this;

                    // Hide any previous error
                    if (saveErrorDiv) {
                        saveErrorDiv.style.display = 'none';
                    }

                    button.disabled = true;
                    button.textContent = scrywpIndexes.i18n.saving;

                    // Start with full form serialization so hook-injected inputs are included.
                    flushPendingSynonymAndStopWordInputs();
                    syncSynonymsHiddenInputs();
                    syncStopWordsHiddenInputs();
                    dictionaryChips.syncHiddenInputs();
                    typoDisableWordsChips.syncHiddenInputs();
                    typoDisableAttributesChips.syncHiddenInputs();

                    // Hybrid cannot stay enabled with no embedder. Uncheck before FormData
                    // so the checkbox is omitted from POST (PHP treats missing as off).
                    var hybridEnabled = settingsForm
                        ? settingsForm.querySelector('.scrywp-hy-hybrid-section input[name="hybrid_enabled"]')
                        : null;
                    var hybridEmbedder = settingsForm
                        ? settingsForm.querySelector('.scrywp-hy-hybrid-section select[name="hybrid_embedder"]')
                        : null;
                    if (hybridEnabled && hybridEmbedder && hybridEnabled.checked && !hybridEmbedder.value) {
                        hybridEnabled.checked = false;
                    }

                    var formData = settingsForm ? new FormData(settingsForm) : new FormData();
                    formData.set('action', scrywpIndexes.actions.updateIndexSettings);
                    formData.set('nonce', scrywpIndexes.nonces.updateIndexSettings);
                    formData.set('index_name', indexName);

                    fetch(scrywpIndexes.ajaxUrl, {
                        method: 'POST',
                        body: formData
                    })
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(function (data) {
                            if (data.success) {
                                // Hide any previous error
                                if (saveErrorDiv) {
                                    saveErrorDiv.style.display = 'none';
                                }
                                alert(data.data.message || scrywpIndexes.i18n.settingsSavedSuccessfully);
                                // Reset button state before closing
                                resetSaveButton();
                                dialog.close();
                            } else {
                                var errorMessage = data.data && data.data.message ? data.data.message : scrywpIndexes.i18n.failedToSaveSettings;
                                // Reset button state but don't hide error
                                if (saveButton) {
                                    saveButton.disabled = false;
                                    saveButton.textContent = originalSaveButtonText || scrywpIndexes.i18n.saveSettings;
                                }
                                showSaveError(scrywpIndexes.i18n.error + ' ' + errorMessage);
                            }
                        })
                        .catch(function (error) {
                            var errorMessage = scrywpIndexes.i18n.error + ' ' + scrywpIndexes.i18n.failedToSaveSettings;
                            if (error && error.message) {
                                errorMessage += ' (' + error.message + ')';
                            }
                            // Reset button state but don't hide error
                            if (saveButton) {
                                saveButton.disabled = false;
                                saveButton.textContent = originalSaveButtonText || scrywpIndexes.i18n.saveSettings;
                            }
                            showSaveError(errorMessage);
                        });
                });
            }
        });
    });
})();
