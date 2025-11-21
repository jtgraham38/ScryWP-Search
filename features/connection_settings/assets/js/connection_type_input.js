/**
 * Connection Type Input JavaScript
 * 
 * @package scry_ms_Search
 * @since 1.0.0
 */

(function () {
    'use strict';

    // Check if localized data is available
    if (typeof scrywpConnectionSettings === 'undefined') {
        return;
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Helper function to query selector
        function $(selector) {
            return document.querySelector(selector);
        }

        // Helper function to query all
        function $$(selector) {
            return document.querySelectorAll(selector);
        }

        // Show/hide sections based on connection type
        function toggleSectionsBasedOnConnectionType() {
            var connectionTypeInput = $('input[name="' + scrywpConnectionSettings.connectionTypeField + '"]:checked');
            var connectionType = connectionTypeInput ? connectionTypeInput.value : '';

            // Toggle "Get Connection Info" section (show for scrywp) with smooth animation
            var getConnectionInfoSection = $('.scrywp-managed-get-connection-info');
            if (getConnectionInfoSection) {
                if (connectionType === 'scrywp') {
                    // Show section with animation
                    getConnectionInfoSection.classList.add('scrywp-section-visible');
                } else {
                    // Hide section with animation
                    getConnectionInfoSection.classList.remove('scrywp-section-visible');
                }
            }

            // Toggle manual config fields (enable for manual, readonly for scrywp)
            var manualConfigFields = $$('.scrywp-manual-config-field');
            manualConfigFields.forEach(function (field) {
                var inputs = field.querySelectorAll('input');
                inputs.forEach(function (input) {
                    if (connectionType === 'manual') {
                        // Enable fields and make required for manual config
                        input.removeAttribute('readonly');
                        input.setAttribute('required', 'required');
                    } else {
                        // Make fields readonly (uneditable but still submits) and remove required for scrywp managed service
                        input.setAttribute('readonly', 'readonly');
                        input.removeAttribute('required');
                    }
                });
            });
        }

        // Initial check
        toggleSectionsBasedOnConnectionType();

        // Watch for changes to connection type
        var connectionTypeInputs = $$('input[name="' + scrywpConnectionSettings.connectionTypeField + '"]');
        connectionTypeInputs.forEach(function (input) {
            input.addEventListener('change', function () {
                toggleSectionsBasedOnConnectionType();
            });
        });

        // Test connection functionality
        var testButton = $('#scrywp-test-connection');
        if (testButton) {
            testButton.addEventListener('click', function () {
                var button = this;
                var result = $('#scrywp-connection-test-result');

                button.disabled = true;
                button.textContent = scrywpConnectionSettings.i18n.testing;
                if (result) {
                    result.style.display = 'none';
                }

                // Get form data using WordPress Settings API field names
                var connectionTypeInput = $('input[name="' + scrywpConnectionSettings.connectionTypeField + '"]:checked');
                var urlInput = $('input[name="' + scrywpConnectionSettings.urlField + '"]');
                var searchKeyInput = $('input[name="' + scrywpConnectionSettings.searchKeyField + '"]');
                var adminKeyInput = $('input[name="' + scrywpConnectionSettings.adminKeyField + '"]');

                var formData = new FormData();
                formData.append('action', scrywpConnectionSettings.testAction);
                formData.append('nonce', scrywpConnectionSettings.testNonce);
                formData.append('connection_type', connectionTypeInput ? connectionTypeInput.value : '');
                formData.append('meilisearch_url', urlInput ? urlInput.value : '');
                formData.append('meilisearch_search_key', searchKeyInput ? searchKeyInput.value : '');
                formData.append('meilisearch_admin_key', adminKeyInput ? adminKeyInput.value : '');

                fetch(scrywpConnectionSettings.ajaxUrl, {
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
                        if (result) {
                            result.classList.remove('success', 'error');

                            if (data.success) {
                                result.classList.add('success');
                                result.innerHTML = '<strong>' + scrywpConnectionSettings.i18n.success + '</strong> ' + (data.data && data.data.message ? data.data.message : '');
                            } else {
                                result.classList.add('error');
                                var errorMessage = (data.data && data.data.message) ? data.data.message : scrywpConnectionSettings.i18n.testFailed;
                                result.innerHTML = '<strong>' + scrywpConnectionSettings.i18n.error + '</strong> ' + errorMessage;
                            }
                            result.style.display = 'block';
                        }
                    })
                    .catch(function (error) {
                        if (result) {
                            result.classList.remove('success', 'error');
                            result.classList.add('error');
                            result.innerHTML = '<strong>' + scrywpConnectionSettings.i18n.error + '</strong> ' + scrywpConnectionSettings.i18n.testFailed;
                            result.style.display = 'block';
                        }
                    })
                    .finally(function () {
                        button.disabled = false;
                        button.textContent = scrywpConnectionSettings.i18n.testConnection;
                    });
            });
        }

        // Form validation
        var form = $('form.scrywp-connection-form');
        if (form) {
            form.addEventListener('submit', function (e) {
                var connectionTypeInput = $('input[name="' + scrywpConnectionSettings.connectionTypeField + '"]:checked');
                var connectionType = connectionTypeInput ? connectionTypeInput.value : '';

                if (!connectionType) {
                    alert(scrywpConnectionSettings.i18n.selectConnectionType);
                    e.preventDefault();
                    return false;
                }

                // Only validate manual config fields when manual is selected
                if (connectionType === 'manual') {
                    var urlInput = $('input[name="' + scrywpConnectionSettings.urlField + '"]');
                    var searchKeyInput = $('input[name="' + scrywpConnectionSettings.searchKeyField + '"]');
                    var adminKeyInput = $('input[name="' + scrywpConnectionSettings.adminKeyField + '"]');

                    var url = urlInput ? urlInput.value : '';
                    var searchKey = searchKeyInput ? searchKeyInput.value : '';
                    var adminKey = adminKeyInput ? adminKeyInput.value : '';

                    if (!url || !searchKey || !adminKey) {
                        alert(scrywpConnectionSettings.i18n.fillRequiredFields);
                        e.preventDefault();
                        return false;
                    }
                }
            });
        }
    });
})();
