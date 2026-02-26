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
                    'Are you sure you want to wipe the index? All documents will be deleted. The index will be recreated automatically.'
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
                                resultsHTML += '<div class="scrywp-index-dialog-result">';
                                resultsHTML += '<h4 class="scrywp-index-dialog-result-title">' + (result.title || untitledLabel) + '</h4>';

                                if (result.excerpt) {
                                    resultsHTML += '<p class="scrywp-index-dialog-result-excerpt">' + result.excerpt + '</p>';
                                }

                                resultsHTML += '<div class="scrywp-index-dialog-result-meta">';
                                resultsHTML += '<span>' + result.ID + '</span>';
                                resultsHTML += '<span>' + result.post_type + '</span>';
                                resultsHTML += '<span>' + result.post_status + '</span>';
                                if (result.post_date) {
                                    resultsHTML += '<span>' + result.post_date + '</span>';
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
            var fieldsTree = dialog.querySelector('.scrywp-searchable-fields-tree');
            var rankingRulesInputsContainer = dialog.querySelector('.scrywp-ranking-rules-hidden-inputs');
            var loadingDiv = dialog.querySelector('.scrywp-index-settings-loading');
            var loadedDiv = dialog.querySelector('.scrywp-index-settings-loaded');
            var settingsForm = dialog.querySelector('.scrywp-index-settings-form');
            var errorDiv = dialog.querySelector('.scrywp-index-settings-error');
            var saveButton = dialog.querySelector('.scrywp-save-index-settings-button');
            var saveErrorDiv = dialog.querySelector('.scrywp-index-settings-save-error');
            var saveErrorMessage = dialog.querySelector('.scrywp-index-settings-save-error-message');

            var currentRankingRules = [];

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
                    setupSearchableFieldsInteractions();

                    loadingDiv.style.display = 'none';
                    loadedDiv.style.display = 'block';

                    // Reset button state after successful load
                    resetSaveButton();
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
            function renderRankingRules() {
                if (!rulesList) return;

                rulesList.innerHTML = '';

                currentRankingRules.forEach(function (rule, index) {
                    var li = document.createElement('li');
                    li.className = 'scrywp-ranking-rule-item';
                    li.draggable = true;
                    li.dataset.rule = rule;
                    li.dataset.index = index;

                    var handle = document.createElement('span');
                    handle.className = 'scrywp-ranking-rule-handle';
                    handle.textContent = '☰';
                    handle.setAttribute('aria-label', scrywpIndexes.i18n.dragToReorder);

                    var label = document.createElement('span');
                    label.className = 'scrywp-ranking-rule-label';
                    label.textContent = rule;

                    li.appendChild(handle);
                    li.appendChild(label);
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
            function setupSearchableFieldsInteractions() {
                if (!fieldsTree) return;

                var groups = fieldsTree.querySelectorAll('.scrywp-searchable-field-group');
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
            }

            // Save settings
            if (saveButton) {
                if (settingsForm) {
                    settingsForm.addEventListener('submit', function (e) {
                        e.preventDefault();
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
