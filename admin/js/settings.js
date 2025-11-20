jQuery(function ($) {
    var $webp = $('#ngio_enable_webp');
    var $avif = $('#ngio_enable_avif');

    if (!$webp.length || !$avif.length) {
        return;
    }

    function syncExclusive(source) {
        if (source === 'webp') {
            if ($webp.is(':checked')) {
                $avif.prop('checked', false);
            }
        } else if (source === 'avif') {
            if ($avif.is(':checked')) {
                $webp.prop('checked', false);
            }
        }
    }

    if ($webp.is(':checked') && $avif.is(':checked')) {
        $avif.prop('checked', false);
    }

    $webp.on('change', function () {
        syncExclusive('webp');
    });

    $avif.on('change', function () {
        syncExclusive('avif');
    });
});
