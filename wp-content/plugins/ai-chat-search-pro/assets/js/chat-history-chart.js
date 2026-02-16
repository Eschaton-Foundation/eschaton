/**
 * AI Chat Search Pro - Chat History Chart
 *
 * Initializes and renders the chat history activity chart
 * using Chart.js library.
 *
 * @package AI_Chat_Search_Pro
 * @since 1.7.5
 */

(function($) {
    'use strict';

    // Store chart instance for resize handling
    var chatHistoryChart = null;

    /**
     * Initialize the chat history chart
     */
    function initChatHistoryChart() {
        var canvas = document.getElementById('airs-chat-history-chart');

        if (!canvas) {
            return;
        }

        if (typeof Chart === 'undefined') {
            console.log('AI Chat: Chart.js not loaded');
            return;
        }

        if (typeof aiChatHistoryChartData === 'undefined') {
            console.log('AI Chat: Chart data not available');
            return;
        }

        var ctx = canvas.getContext('2d');
        var data = aiChatHistoryChartData;

        // Build datasets array
        // Colors: Conversations=Blue, Messages=Green, Emails=Purple
        var datasets = [
            {
                label: data.strings.conversations,
                data: data.conversations,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3,
                pointRadius: 3,
                pointHoverRadius: 5,
                pointBackgroundColor: '#3b82f6',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2
            },
            {
                label: data.strings.messages,
                data: data.messages,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3,
                pointRadius: 3,
                pointHoverRadius: 5,
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2
            }
        ];

        // Add emails dataset only if showEmails is true (total > 0)
        if (data.showEmails && data.emails) {
            datasets.push({
                label: data.strings.emails,
                data: data.emails,
                borderColor: '#8b5cf6',
                backgroundColor: 'rgba(139, 92, 246, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3,
                pointRadius: 3,
                pointHoverRadius: 5,
                pointBackgroundColor: '#8b5cf6',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2
            });
        }

        // Chart configuration
        var chartConfig = {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: true,
                        boxWidth: 12,
                        boxHeight: 12,
                        boxPadding: 4,
                        callbacks: {
                            title: function(context) {
                                return context[0].label;
                            },
                            label: function(context) {
                                var label = context.dataset.label || '';
                                var value = context.parsed.y;
                                return ' ' + label + ': ' + value;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6b7280',
                            font: {
                                size: 11
                            },
                            maxRotation: 45,
                            minRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 15
                        },
                        border: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#6b7280',
                            font: {
                                size: 11
                            },
                            stepSize: 1,
                            callback: function(value) {
                                if (Number.isInteger(value)) {
                                    return value;
                                }
                                return null;
                            }
                        },
                        border: {
                            display: false
                        }
                    }
                }
            }
        };

        // Create the chart with error handling
        try {
            // Destroy existing chart if it exists (for re-initialization)
            if (chatHistoryChart) {
                chatHistoryChart.destroy();
            }
            chatHistoryChart = new Chart(ctx, chartConfig);
        } catch (error) {
            console.error('Chat History Chart initialization failed:', error);
        }
    }

    // Debounce function for resize handling
    function debounce(func, wait) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }

    // Handle window resize
    var handleResize = debounce(function() {
        if (chatHistoryChart) {
            chatHistoryChart.resize();
        }
    }, 250);

    // Initialize when DOM is ready
    $(document).ready(function() {
        initChatHistoryChart();

        // Add resize listener
        $(window).on('resize', handleResize);
    });

})(jQuery);
