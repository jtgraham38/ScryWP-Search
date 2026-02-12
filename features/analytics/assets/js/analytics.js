(function ($) {
    'use strict';

    var config = window.scrywpAnalytics;
    if (!config) return;

    var topTermsChart = null;
    var trendChart = null;

    // =========================================================================
    // Summary Cards
    // =========================================================================

    function loadSummaryCards() {
        $.post(config.ajaxUrl, {
            action: config.actions.getSummary,
            nonce: config.nonces.getSummary
        }, function (response) {
            if (!response.success) return;

            var data = response.data;
            var cards = document.querySelectorAll('.scry-summary-card-value');
            cards.forEach(function (el) {
                var key = el.getAttribute('data-key');
                if (data[key] !== undefined) {
                    el.textContent = data[key].toLocaleString !== undefined
                        ? Number(data[key]).toLocaleString()
                        : data[key];
                }
            });
        });
    }

    // =========================================================================
    // Top Terms Bar Chart
    // =========================================================================

    function loadTopTermsChart() {
        var dateFrom = document.getElementById('scry-top-terms-from');
        var dateTo = document.getElementById('scry-top-terms-to');

        var postData = {
            action: config.actions.getTopTerms,
            nonce: config.nonces.getTopTerms,
            limit: 15
        };

        if (dateFrom && dateFrom.value) postData.date_from = dateFrom.value;
        if (dateTo && dateTo.value) postData.date_to = dateTo.value;

        $.post(config.ajaxUrl, postData, function (response) {
            if (!response.success) return;

            var terms = response.data.terms || [];
            var emptyEl = document.getElementById('scry-top-terms-empty');
            var canvasEl = document.getElementById('scry-top-terms-chart');

            if (!terms.length) {
                if (canvasEl) canvasEl.style.display = 'none';
                if (emptyEl) emptyEl.style.display = 'block';
                return;
            }

            if (canvasEl) canvasEl.style.display = 'block';
            if (emptyEl) emptyEl.style.display = 'none';

            var labels = terms.map(function (t) { return t.search_term; });
            var counts = terms.map(function (t) { return parseInt(t.count, 10); });

            if (topTermsChart) topTermsChart.destroy();

            topTermsChart = new Chart(canvasEl, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: config.i18n.searches,
                        data: counts,
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    }
                }
            });

            // Populate the trend term filter dropdown
            populateTrendTermFilter(labels);
        });
    }

    // =========================================================================
    // Trend Line Chart
    // =========================================================================

    function populateTrendTermFilter(terms) {
        var select = document.getElementById('scry-trend-term-filter');
        if (!select) return;

        // Preserve current selection
        var current = select.value;

        // Clear all but the first "All" option
        while (select.options.length > 1) {
            select.remove(1);
        }

        terms.forEach(function (term) {
            var option = document.createElement('option');
            option.value = term;
            option.textContent = term;
            select.appendChild(option);
        });

        // Restore selection if still present
        if (current) {
            select.value = current;
        }
    }

    function loadTrendChart() {
        var select = document.getElementById('scry-trend-term-filter');
        var selectedTerm = select ? select.value : '';

        var postData = {
            action: config.actions.getTermTrend,
            nonce: config.nonces.getTermTrend,
            days: 30
        };

        if (selectedTerm) {
            postData.term = selectedTerm;
        }

        $.post(config.ajaxUrl, postData, function (response) {
            if (!response.success) return;

            var trend = response.data.trend || [];
            var emptyEl = document.getElementById('scry-trend-empty');
            var canvasEl = document.getElementById('scry-trend-chart');

            var hasData = trend.some(function (d) { return d.count > 0; });

            if (!hasData) {
                if (canvasEl) canvasEl.style.display = 'none';
                if (emptyEl) emptyEl.style.display = 'block';
                return;
            }

            if (canvasEl) canvasEl.style.display = 'block';
            if (emptyEl) emptyEl.style.display = 'none';

            var labels = trend.map(function (d) { return d.date; });
            var counts = trend.map(function (d) { return parseInt(d.count, 10); });

            var chartLabel = selectedTerm
                ? selectedTerm
                : config.i18n.searchVolume;

            if (trendChart) trendChart.destroy();

            trendChart = new Chart(canvasEl, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: chartLabel,
                        data: counts,
                        fill: true,
                        tension: 0.3,
                        borderWidth: 2,
                        pointRadius: 2,
                        pointHoverRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true }
                    },
                    scales: {
                        x: {
                            ticks: {
                                maxTicksLimit: 10
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    }
                }
            });
        });
    }

    // =========================================================================
    // Event Listeners
    // =========================================================================

    $(document).ready(function () {
        loadSummaryCards();
        loadTopTermsChart();
        loadTrendChart();

        // Re-load bar chart when date filters change
        $('#scry-top-terms-from, #scry-top-terms-to').on('change', function () {
            loadTopTermsChart();
        });

        // Re-load line chart when term filter changes
        $('#scry-trend-term-filter').on('change', function () {
            loadTrendChart();
        });
    });

})(jQuery);
