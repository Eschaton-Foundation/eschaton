/**
 * PurioChat synthetic similarity benchmark.
 *
 * @package AI_Chat_Search
 */

(function($) {
    'use strict';

    var AIRS = window.AIRS || {};
    var i18n = window.listeo_ai_vector_benchmark_i18n || {};
    var counts = [1000, 5000, 10000];

    function setRowStatus($row, message, state) {
        var $status = $row.find('[data-benchmark-field="status"]');

        $status
            .removeClass('is-running is-complete is-error')
            .addClass(state ? 'is-' + state : '');
        $status.find('[data-benchmark-status-text]').text(message);
    }

    function resetRows() {
        $('.airs-vector-benchmark__table tbody tr').each(function() {
            var $row = $(this);

            $row.find('[data-benchmark-field="duration"]').text('—');
            $row.find('[data-benchmark-bar]')
                .removeClass('is-visible')
                .css('width', '0');
            setRowStatus($row, '—', '');
        });
    }

    function init() {
        var $button = $('#listeo-run-vector-benchmark');

        if (!$button.length || typeof AIRS.ajax !== 'function') {
            return;
        }

        var $status = $('#listeo-vector-benchmark-status');
        var originalHtml = $button.html();

        function finish(message, state) {
            $button.prop('disabled', false).html(originalHtml);
            $status
                .removeClass('is-running is-complete is-error')
                .addClass('is-' + state)
                .text(message);
        }

        function runBenchmark(index) {
            if (index >= counts.length) {
                finish(i18n.complete || 'Benchmark complete.', 'complete');
                return;
            }

            var count = counts[index];
            var $row = $('.airs-vector-benchmark__table tr[data-benchmark-count="' + count + '"]');
            var runningText = (i18n.running || 'Testing %s embeddings...')
                .replace('%s', count.toLocaleString());

            $button.html(
                '<span class="airs-spinner" style="margin-right: 5px;"></span>' + runningText
            );
            $status
                .removeClass('is-complete is-error')
                .addClass('is-running')
                .text(runningText);
            setRowStatus($row, runningText, 'running');

            AIRS.ajax({
                action: 'listeo_ai_run_vector_benchmark',
                data: {
                    embedding_count: count
                },
                success: function(response) {
                    if (!response.success) {
                        var message = response.data && response.data.message
                            ? response.data.message
                            : (i18n.failed || 'Benchmark failed.');

                        setRowStatus($row, message, 'error');
                        finish(message, 'error');
                        return;
                    }

                    var duration = Number(response.data.duration_ms);
                    var barWidth = Math.max(8, Math.round((count / 10000) * 82));

                    $row.find('[data-benchmark-field="duration"]')
                        .text(duration.toLocaleString(undefined, {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }) + ' ms');
                    $row.find('[data-benchmark-bar]')
                        .css('width', barWidth + '%')
                        .addClass('is-visible');
                    setRowStatus(
                        $row,
                        i18n.rowComplete || 'Complete',
                        'complete'
                    );

                    runBenchmark(index + 1);
                },
                error: function(xhr, status, error) {
                    var message = (i18n.connectionFailed || 'Connection failed:') + ' ' + error;

                    setRowStatus($row, message, 'error');
                    finish(message, 'error');
                }
            });
        }

        $button.on('click', function(event) {
            event.preventDefault();

            resetRows();
            $button.prop('disabled', true);
            runBenchmark(0);
        });
    }

    $(document).ready(init);

})(jQuery);
