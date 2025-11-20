jQuery(function ($) {

    if (typeof ngioMedia === 'undefined') {
        return;
    }

    var cfg = ngioMedia;

    $(document).on('click', '.ngio-media-reoptimize', function (e) {
        e.preventDefault();

        var $btn      = $(this);
        var id        = $btn.data('attachment-id');
        var $col      = $btn.closest('.ngio-media-col');
        var $status   = $col.find('.ngio-media-col-status');
        var $spinner  = $col.find('.ngio-media-spinner');

        if (!id) {
            return;
        }

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        if ($status.length && cfg.textWorking) {
            $status
                .text(cfg.textWorking)
                .removeClass('ngio-media-col-status--ok ngio-media-col-status--error');
        }

        $.post(
            cfg.ajaxUrl,
            {
                action: 'ngio_optimize_single',
                nonce: cfg.nonce,
                attachment_id: id
            }
        )
            .done(function (response) {
                if (!response || !response.success) {
                    var msg = (response && response.data && response.data.message)
                        ? response.data.message
                        : cfg.textError;

                    if ($status.length) {
                        $status
                            .text(msg)
                            .addClass('ngio-media-col-status--error');
                    }
                    return;
                }

                var data = response.data || {};
                var msg  = data.message || cfg.textDone;

                if ($status.length) {
                    $status
                        .text(msg)
                        .addClass('ngio-media-col-status--ok');
                }

                if (data.stats) {
                    var stats = data.stats;
                    if (stats.new_filesize) {
                        $col.find('.ngio-media-col-value-size').text(stats.new_filesize);
                    }
                    if (typeof stats.saving_percent !== 'undefined') {
                        $col.find('.ngio-media-col-value-saved').text(stats.saving_percent + '%');
                    }
                }
            })
            .fail(function () {
                if ($status.length) {
                    $status
                        .text(cfg.textError)
                        .addClass('ngio-media-col-status--error');
                }
            })
            .always(function () {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false);
            });
    });
    $(document).on('click', '.ngio-media-details-toggle', function (e) {
        e.preventDefault();

        var $btn     = $(this);
        var $col     = $btn.closest('.ngio-media-col');
        var $details = $col.find('.ngio-media-details');

        if (!$details.length) {
            return;
        }

        $details.slideToggle(150);

        var isOpen = $btn.data('open') === 1 || $btn.data('open') === '1';

        if (isOpen) {
            $btn.data('open', 0).text(cfg.textDetailsOpen);
        } else {
            $btn.data('open', 1).text(cfg.textDetailsClose);
        }
    });

    $(document).on('click', '.ngio-media-restore', function (e) {
        e.preventDefault();

        if (!window.confirm(cfg.confirmRestore)) {
            return;
        }

        var $btn      = $(this);
        var id        = $btn.data('attachment-id');
        var $col      = $btn.closest('.ngio-media-col');
        var $status   = $col.find('.ngio-media-col-status');
        var $spinner  = $col.find('.ngio-media-spinner');

        if (!id) {
            return;
        }

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.post(
            cfg.ajaxUrl,
            {
                action: 'ngio_restore_single',
                nonce: cfg.nonce,
                attachment_id: id
            }
        )
            .done(function (response) {
                if (!response || !response.success) {
                    var msg = (response && response.data && response.data.message)
                        ? response.data.message
                        : cfg.textError;

                    if ($status.length) {
                        $status
                            .text(msg)
                            .addClass('ngio-media-col-status--error');
                    }
                    return;
                }

                var data = response.data || {};
                var msg  = data.message || cfg.textDone;

                $col.find('.ngio-media-col-main').html(
                    '<span class="ngio-media-col-notice">' + cfg.textNotOptimized + '</span>'
                );

                $col.find('.ngio-media-details').slideUp(150);
                $col.find('.ngio-media-details-toggle').remove();
                $btn.remove();

                if ($status.length) {
                    $status
                        .text(msg)
                        .removeClass('ngio-media-col-status--error')
                        .addClass('ngio-media-col-status--ok');
                }
            })
            .fail(function () {
                if ($status.length) {
                    $status
                        .text(cfg.textError)
                        .addClass('ngio-media-col-status--error');
                }
            })
            .always(function () {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false);
            });
    });

});
