/**
 * ScryWP Search Admin JavaScript
 */

(function () {
    'use strict';

    // Check if scrywpTasks is available (localized script)
    if (typeof scrywpTasks === 'undefined') {
        return;
    }

    // Task Drawer State
    var taskDrawerState = {
        currentPage: 1,
        limit: 20,
        total: 0,
        from: 0,
        loading: false,
        totalPages: 0
    };

    // DOM Elements
    var drawerToggle = document.getElementById('scrywp-task-drawer-toggle');
    var drawer = document.getElementById('scrywp-task-drawer');
    var drawerOverlay = document.getElementById('scrywp-task-drawer-overlay');
    var drawerClose = document.getElementById('scrywp-task-drawer-close');
    var drawerLoading = document.getElementById('scrywp-task-drawer-loading');
    var drawerError = document.getElementById('scrywp-task-drawer-error');
    var drawerErrorMessage = document.getElementById('scrywp-task-drawer-error-message');
    var drawerEmpty = document.getElementById('scrywp-task-drawer-empty');
    var drawerList = document.getElementById('scrywp-task-drawer-list');
    var drawerPagination = document.getElementById('scrywp-task-drawer-pagination');
    var drawerPrev = document.getElementById('scrywp-task-drawer-prev');
    var drawerNext = document.getElementById('scrywp-task-drawer-next');
    var drawerPaginationInfo = document.getElementById('scrywp-task-drawer-pagination-info');
    var drawerPageInput = document.getElementById('scrywp-task-drawer-page-input');
    var drawerTotalPages = document.getElementById('scrywp-task-drawer-total-pages');

    /**
     * Open the drawer
     */
    function openDrawer() {
        drawer.classList.add('open');
        drawerOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';

        // Load tasks if drawer is empty
        if (drawerList.children.length === 0) {
            loadTasks(0);
        }
    }

    /**
     * Close the drawer
     */
    function closeDrawer() {
        drawer.classList.remove('open');
        drawerOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    /**
     * Format date/time string
     */
    function formatDateTime(dateString) {
        if (!dateString) {
            return '-';
        }

        try {
            var date = new Date(dateString);
            return date.toLocaleString();
        } catch (e) {
            return dateString;
        }
    }

    /**
     * Format duration
     */
    function formatDuration(duration) {
        if (!duration && duration !== 0) {
            return '-';
        }

        // Convert to number if it's a string
        var durationNum = typeof duration === 'string' ? parseFloat(duration) : duration;

        if (isNaN(durationNum)) {
            return duration; // Return as-is if not a number
        }

        // Duration is typically in milliseconds
        if (durationNum < 1000) {
            return durationNum + 'ms';
        } else if (durationNum < 60000) {
            return (durationNum / 1000).toFixed(2) + 's';
        } else {
            var minutes = Math.floor(durationNum / 60000);
            var seconds = Math.floor((durationNum % 60000) / 1000);
            return minutes + 'm ' + seconds + 's';
        }
    }

    /**
     * Render a single task item
     */
    function renderTask(task) {
        var statusClass = 'enqueued';
        if (task.status === 'succeeded') {
            statusClass = 'succeeded';
        } else if (task.status === 'failed') {
            statusClass = 'failed';
        } else if (task.status === 'processing') {
            statusClass = 'processing';
        }

        var html = '<div class="scrywp-task-item">';
        html += '<div class="scrywp-task-item-header">';
        html += '<h3 class="scrywp-task-item-title">' + esc_html(task.type || 'Unknown Task') + '</h3>';
        html += '<span class="scrywp-task-item-status ' + statusClass + '">' + esc_html(task.status || 'unknown') + '</span>';
        html += '</div>';

        html += '<div class="scrywp-task-item-body">';

        if (task.uid !== null) {
            html += '<div class="scrywp-task-item-row">';
            html += '<span class="scrywp-task-item-label">UID:</span>';
            html += '<span class="scrywp-task-item-value">' + esc_html(task.uid) + '</span>';
            html += '</div>';
        }

        if (task.indexUid) {
            html += '<div class="scrywp-task-item-row">';
            html += '<span class="scrywp-task-item-label">Index:</span>';
            html += '<span class="scrywp-task-item-value">' + esc_html(task.indexUid) + '</span>';
            html += '</div>';
        }

        html += '<div class="scrywp-task-item-row">';
        html += '<span class="scrywp-task-item-label">Enqueued:</span>';
        html += '<span class="scrywp-task-item-value">' + formatDateTime(task.enqueuedAt) + '</span>';
        html += '</div>';

        if (task.startedAt) {
            html += '<div class="scrywp-task-item-row">';
            html += '<span class="scrywp-task-item-label">Started:</span>';
            html += '<span class="scrywp-task-item-value">' + formatDateTime(task.startedAt) + '</span>';
            html += '</div>';
        }

        if (task.finishedAt) {
            html += '<div class="scrywp-task-item-row">';
            html += '<span class="scrywp-task-item-label">Finished:</span>';
            html += '<span class="scrywp-task-item-value">' + formatDateTime(task.finishedAt) + '</span>';
            html += '</div>';
        }

        if (task.duration !== null && task.duration !== '') {
            html += '<div class="scrywp-task-item-row">';
            html += '<span class="scrywp-task-item-label">Duration:</span>';
            html += '<span class="scrywp-task-item-value">' + formatDuration(task.duration) + '</span>';
            html += '</div>';
        }

        if (task.error) {
            html += '<div class="scrywp-task-item-error">';
            html += '<div class="scrywp-task-item-error-title">Error:</div>';
            html += '<div class="scrywp-task-item-error-message">';
            if (typeof task.error === 'string') {
                html += esc_html(task.error);
            } else if (task.error.message) {
                html += esc_html(task.error.message);
            } else {
                html += esc_html(JSON.stringify(task.error, null, 2));
            }
            html += '</div>';
            html += '</div>';
        }

        if (task.details && Object.keys(task.details).length > 0) {
            html += '<div class="scrywp-task-item-details">';
            html += '<strong>Details:</strong><br>';
            html += esc_html(JSON.stringify(task.details, null, 2));
            html += '</div>';
        }

        html += '</div>';
        html += '</div>';

        return html;
    }

    /**
     * Render tasks list
     */
    function renderTasks(tasks) {
        if (!tasks || tasks.length === 0) {
            drawerList.style.display = 'none';
            drawerEmpty.style.display = 'block';
            drawerPagination.style.display = 'none';
            return;
        }

        drawerEmpty.style.display = 'none';
        drawerList.style.display = 'block';

        var html = '';
        tasks.forEach(function (task) {
            html += renderTask(task);
        });

        drawerList.innerHTML = html;
    }

    /**
     * Update pagination controls
     */
    function updatePagination(data) {
        if (!data || data.total === 0) {
            drawerPagination.style.display = 'none';
            return;
        }

        drawerPagination.style.display = 'flex';

        var from = data.from || 0;
        var limit = data.limit || 20;
        var total = data.total || 0;
        var end = Math.min(from + limit, total);
        var start = from + 1;

        // Calculate current page (1-based)
        var currentPage = Math.floor(from / limit) + 1;
        var totalPages = Math.ceil(total / limit);

        // Update pagination info
        var infoText = 'Showing ' + start + '-' + end + ' of ' + total + ' tasks';
        drawerPaginationInfo.textContent = infoText;

        // Update page input
        if (drawerPageInput) {
            drawerPageInput.value = currentPage;
            drawerPageInput.max = totalPages;
        }

        // Update total pages display
        if (drawerTotalPages) {
            drawerTotalPages.textContent = 'of ' + totalPages;
        }

        // Update prev/next buttons
        drawerPrev.disabled = from === 0;
        drawerNext.disabled = !data.hasMore;

        // Update state
        taskDrawerState.from = from;
        taskDrawerState.total = total;
        taskDrawerState.hasMore = data.hasMore;
        taskDrawerState.currentPage = currentPage;
        taskDrawerState.totalPages = totalPages;
    }

    /**
     * Navigate to a specific page
     */
    function goToPage(pageNumber) {
        if (!pageNumber || pageNumber < 1) {
            pageNumber = 1;
        }

        var totalPages = taskDrawerState.totalPages || Math.ceil(taskDrawerState.total / taskDrawerState.limit);
        if (pageNumber > totalPages) {
            pageNumber = totalPages;
        }

        var from = (pageNumber - 1) * taskDrawerState.limit;
        loadTasks(from);
    }

    /**
     * Load tasks from server
     */
    function loadTasks(from) {
        if (taskDrawerState.loading) {
            return;
        }

        taskDrawerState.loading = true;
        taskDrawerState.from = from;

        // Show loading state
        drawerLoading.style.display = 'block';
        drawerError.style.display = 'none';
        drawerEmpty.style.display = 'none';
        drawerList.style.display = 'none';
        drawerPagination.style.display = 'none';

        // Build form data
        var formData = new FormData();
        formData.append('action', scrywpTasks.action);
        formData.append('nonce', scrywpTasks.nonce);
        formData.append('limit', taskDrawerState.limit);
        formData.append('from', from);

        fetch(scrywpTasks.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (response) {
                taskDrawerState.loading = false;
                drawerLoading.style.display = 'none';

                if (response.success && response.data) {
                    renderTasks(response.data.tasks);
                    updatePagination(response.data);
                } else {
                    showError(response.data && response.data.message
                        ? response.data.message
                        : scrywpTasks.i18n.error);
                }
            })
            .catch(function (error) {
                taskDrawerState.loading = false;
                drawerLoading.style.display = 'none';

                var errorMessage = scrywpTasks.i18n.error;
                showError(errorMessage);
            });
    }

    /**
     * Show error message
     */
    function showError(message) {
        drawerErrorMessage.textContent = message || scrywpTasks.i18n.error;
        drawerError.style.display = 'block';
        drawerList.style.display = 'none';
        drawerPagination.style.display = 'none';
    }

    /**
     * Escape HTML
     */
    function esc_html(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    /**
     * Event Handlers
     */

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        // Check if elements exist before attaching listeners
        if (!drawerToggle || !drawer || !drawerOverlay || !drawerClose) {
            return;
        }

        // Toggle drawer
        drawerToggle.addEventListener('click', function (e) {
            e.preventDefault();
            openDrawer();
        });

        // Close drawer
        drawerClose.addEventListener('click', function (e) {
            e.preventDefault();
            closeDrawer();
        });

        // Close drawer on overlay click
        drawerOverlay.addEventListener('click', function (e) {
            if (e.target === drawerOverlay) {
                closeDrawer();
            }
        });

        // Close drawer on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && drawer.classList.contains('open')) {
                closeDrawer();
            }
        });

        // Pagination
        if (drawerPrev) {
            drawerPrev.addEventListener('click', function (e) {
                e.preventDefault();
                if (!drawerPrev.disabled && taskDrawerState.from >= taskDrawerState.limit) {
                    var newFrom = Math.max(0, taskDrawerState.from - taskDrawerState.limit);
                    loadTasks(newFrom);
                }
            });
        }

        if (drawerNext) {
            drawerNext.addEventListener('click', function (e) {
                e.preventDefault();
                if (!drawerNext.disabled && taskDrawerState.hasMore) {
                    var newFrom = taskDrawerState.from + taskDrawerState.limit;
                    loadTasks(newFrom);
                }
            });
        }

        // Page input navigation
        if (drawerPageInput) {
            // Navigate on Enter key
            drawerPageInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    var pageNumber = parseInt(drawerPageInput.value, 10);
                    goToPage(pageNumber);
                }
            });

            // Navigate on blur (when user clicks away)
            drawerPageInput.addEventListener('blur', function (e) {
                var pageNumber = parseInt(drawerPageInput.value, 10);
                if (pageNumber && pageNumber !== taskDrawerState.currentPage) {
                    goToPage(pageNumber);
                } else {
                    // Reset to current page if invalid
                    drawerPageInput.value = taskDrawerState.currentPage;
                }
            });
        }
    }
})();
