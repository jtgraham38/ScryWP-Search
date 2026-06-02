document.addEventListener('DOMContentLoaded', function() {
    // The button only exists when there are older log entries available.
    var loadMoreButton = document.querySelector('.scrywp-logs-load-more a');
    var logViewer = document.querySelector('.scrywp-logs-viewer');
    var logCode = document.querySelector('.scrywp-logs-viewer code');

    if (!loadMoreButton || !logViewer || !logCode) {
        return;
    }

    loadMoreButton.addEventListener('click', function(event) {
        event.preventDefault();

        // Prevent repeated requests while one is already in progress.
        if (loadMoreButton.classList.contains('disabled')) {
            return;
        }

        // PHP will localize these values when the script is enqueued.
        if (!window.scrywpLogs || !window.scrywpLogs.ajaxUrl) {
            setButtonMessage('Log AJAX settings are missing.');
            return;
        }

        var logLevel = loadMoreButton.dataset.logLevel || 'error';
        var nextStart = loadMoreButton.dataset.nextStart || '0';
        var pageSize = window.scrywpLogs.pageSize || 100;
        var originalText = loadMoreButton.textContent;
        var previousScrollHeight = logViewer.scrollHeight;

        // Give immediate feedback while the admin-ajax request is running.
        loadMoreButton.classList.add('disabled');
        setButtonMessage(window.scrywpLogs.i18n.loading);

        var formData = new FormData();
        formData.append('action', window.scrywpLogs.action);
        formData.append('nonce', window.scrywpLogs.nonce);
        formData.append('level', logLevel);
        formData.append('start', nextStart);
        formData.append('lines', pageSize);

        fetch(window.scrywpLogs.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
            .then(function(response) {
                return response.json();
            })
            .then(function(response) {
                // WordPress AJAX responses use a { success, data } envelope.
                if (!response || !response.success || !response.data) {
                    throw new Error(getResponseMessage(response));
                }

                prependLines(response.data.lines || []);
                loadMoreButton.dataset.nextStart = response.data.next_start || nextStart;

                if (response.data.has_more) {
                    loadMoreButton.classList.remove('disabled');
                    setButtonMessage(originalText);
                } else {
                    replaceLoadMoreWithMessage(window.scrywpLogs.i18n.noMore);
                }

                // Keep the visible scroll position stable after prepending older lines.
                logViewer.scrollTop += logViewer.scrollHeight - previousScrollHeight;
            })
            .catch(function(error) {
                loadMoreButton.classList.remove('disabled');
                setButtonMessage(error.message || window.scrywpLogs.i18n.error);
            });
    });

    function prependLines(lines) {
        // Older log lines are inserted above the current visible chunk.
        if (!Array.isArray(lines) || lines.length === 0) {
            return;
        }

        var currentText = logCode.textContent.trim();
        var newText = lines.join('\n');

        logCode.textContent = currentText ? newText + '\n' + currentText : newText;
    }

    function setButtonMessage(message) {
        loadMoreButton.textContent = message || '';
    }

    function replaceLoadMoreWithMessage(message) {
        // Once there are no older entries, remove the dead button from the UI.
        var wrapper = loadMoreButton.parentElement;

        if (!wrapper) {
            return;
        }

        wrapper.innerHTML = '';

        var messageElement = document.createElement('span');
        messageElement.className = 'scrywp-logs-meta';
        messageElement.textContent = message || '';
        wrapper.appendChild(messageElement);
    }

    function getResponseMessage(response) {
        // Prefer the server-provided message, but keep a generic fallback.
        if (response && response.data && response.data.message) {
            return response.data.message;
        }

        return window.scrywpLogs.i18n.error;
    }
});
