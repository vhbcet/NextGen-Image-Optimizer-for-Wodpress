jQuery(function ($) {
    var running = false;
    var currentPage = 1;
    var processedTotal = 0;
    var total = ngioBulk.estimatedTotal || 0;

    var $progressWrap = $('#ngio-bulk-progress');
    var $progressInner = $('.ngio-bulk-progress-bar-inner');
    var $status = $('#ngio-bulk-status');
    var $spinner = $('#ngio-bulk-spinner');
    var $startBtn = $('#ngio-bulk-start');
    var $activity = $('#ngio-bulk-activity');

    var $ring = $('#ngio-bulk-ring');
    var $ringValue = $('#ngio-bulk-ring-value');

    function setRingBackground(percent) {
        if (!$ring.length) {
            return;
        }
        var el = $ring.get(0);
        if (el && el.style) {
            el.style.background = 'conic-gradient(#22c55e ' + percent + '%, #e5e7eb 0)';
        }
    }

    (function initialiseRingFromMeta() {
        var initial = parseInt(ngioBulk.savingPercent || 0, 10);
        if (isNaN(initial) || initial < 0) {
            initial = 0;
        }
        if (initial > 100) {
            initial = 100;
        }
        setRingBackground(initial);
        if ($ringValue.length) {
            $ringValue.text(initial + '%');
        }
    })();

    function updateProgress() {
        if (!total) {
            if ($progressInner.length) {
                $progressInner.css('width', '0%');
            }
            if ($status.length) {
                $status.text(
                    ngioBulk.textStatus
                        .replace('%processed%', processedTotal)
                        .replace('%total%', total)
                        .replace('%percent%', 0)
                );
            }
            return;
        }

        var percent = Math.min(100, Math.round((processedTotal / total) * 100));

        if ($progressInner.length) {
            $progressInner.css('width', percent + '%');
        }

        if ($status.length) {
            $status.text(
                ngioBulk.textStatus
                    .replace('%processed%', processedTotal)
                    .replace('%total%', total)
                    .replace('%percent%', percent)
            );
        }

    }

    function appendActivity(items) {
        if (!items || !items.length) {
            return;
        }

        items.forEach(function (item) {
            var title = item.title || ('#' + item.id);
            var $row = $('<div class="ngio-bulk-activity-item"></div>');
            $row.text('Optimized: ' + title);
            $activity.append($row);
        });

        var el = $activity.get(0);
        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    }

    function resetUI() {
        processedTotal = 0;
        currentPage = 1;

        if ($progressInner.length) {
            $progressInner.css('width', '0%');
        }
        if ($status.length) {
            $status.text('');
        }
        if ($activity.length) {
            $activity.empty();
        }

    }

    function runBatch() {
        if (!running) {
            return;
        }

        $spinner.addClass('is-active');

        $.post(
            ngioBulk.ajaxUrl,
            {
                action: 'ngio_bulk_optimize',
                nonce: ngioBulk.nonce,
                page: currentPage
            }
        )
            .done(function (response) {
                if (!response || !response.success) {
                    var msg = (response && response.data && response.data.message)
                        ? response.data.message
                        : ngioBulk.textError;

                    if ($status.length) {
                        $status.text(msg);
                    }
                    running = false;
                    $spinner.removeClass('is-active');
                    $startBtn.prop('disabled', false);
                    return;
                }

                var data = response.data;

                processedTotal += data.processed || 0;
                updateProgress();
                appendActivity(data.items || []);

                if (data.finished || !data.processed) {
                    running = false;
                    $spinner.removeClass('is-active');
                    $startBtn
                        .prop('disabled', false)
                        .text(ngioBulk.textRestart);

                    if ($status.length) {
                        $status.append(' ' + ngioBulk.textDone);
                    }
                    return;
                }

                currentPage++;
                runBatch();
            })
            .fail(function () {
                if ($status.length) {
                    $status.text(ngioBulk.textError);
                }
                running = false;
                $spinner.removeClass('is-active');
                $startBtn.prop('disabled', false);
            });
    }

    $startBtn.on('click', function (e) {
        e.preventDefault();

        if (running) {
            return;
        }

        running = true;
        resetUI();

        $startBtn.prop('disabled', true);
        $progressWrap.show();
        $spinner.addClass('is-active');

        if ($status.length) {
            $status.text(ngioBulk.textStarting);
        }

        runBatch();
    });
});
