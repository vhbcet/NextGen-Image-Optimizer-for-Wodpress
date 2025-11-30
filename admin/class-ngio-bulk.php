<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NGIO_Bulk {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_bulk_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_ngio_bulk_optimize', array( $this, 'ajax_bulk_optimize' ) );
    }

    public function add_bulk_page() {
        add_media_page(
            __( 'Bulk Optimization (NGIO)', 'hedef-image-optimizer-webp-avif' ),
            __( 'Bulk Optimize (NGIO)', 'hedef-image-optimizer-webp-avif' ),
            'manage_options',
            'ngio-bulk',
            array( $this, 'render_page' )
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'media_page_ngio-bulk' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'ngio-admin',
            NGIO_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            NGIO_VERSION
        );

        wp_enqueue_script(
            'ngio-bulk',
            NGIO_PLUGIN_URL . 'admin/js/bulk.js',
            array( 'jquery' ),
            NGIO_VERSION,
            true
        );

        $overview = $this->get_overview_stats();

        wp_localize_script(
            'ngio-bulk',
            'ngioBulk',
            array(
                'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
                'nonce'          => wp_create_nonce( 'ngio_bulk_optimize' ),
                'estimatedTotal' => $overview['total_images'],
                'textStatus'     => __( 'Processed %processed% of %total% images (%percent%% of this run).', 'hedef-image-optimizer-webp-avif' ),
                'textStarting'   => __( 'Starting bulk optimization…', 'hedef-image-optimizer-webp-avif' ),
                'textDone'       => __( 'Bulk optimization finished.', 'hedef-image-optimizer-webp-avif' ),
                'textError'      => __( 'An error occurred during the bulk optimization.', 'hedef-image-optimizer-webp-avif' ),
                'textRestart'    => __( 'Run again', 'hedef-image-optimizer-webp-avif' ),
                'savingPercent'  => $overview['saving_percent'],
            )
        );
    }

    protected function get_overview_stats() {
        $stats = array(
            'total_images'      => 0,
            'optimized_images'  => 0,
            'remaining_images'  => 0,
            'original_bytes'    => 0,
            'nextgen_bytes'     => 0,
            'bytes_saved'       => 0,
            'saving_percent'    => 0,
            'original_human'    => '0 B',
            'optimized_human'   => '0 B',
            'saved_human'       => '0 B',
        );

        if ( function_exists( 'wp_count_attachments' ) ) {
            $counts = wp_count_attachments();

            $jpeg = 0;
            if ( isset( $counts->{'image/jpeg'} ) ) {
                $jpeg += (int) $counts->{'image/jpeg'};
            }
            if ( isset( $counts->{'image/jpg'} ) ) {
                $jpeg += (int) $counts->{'image/jpg'};
            }

            $png = isset( $counts->{'image/png'} ) ? (int) $counts->{'image/png'} : 0;

            $stats['total_images'] = $jpeg + $png;
        }

        $query = new WP_Query(
            array(
                'post_type'              => 'attachment',
                'post_mime_type'         => array( 'image/jpeg', 'image/jpg', 'image/png' ),
                'post_status'            => 'inherit',
                'posts_per_page'         => -1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => true,
                'update_post_term_cache' => false,
            )
        );

        if ( $query->have_posts() ) {
            foreach ( $query->posts as $attachment_id ) {
                $meta = wp_get_attachment_metadata( $attachment_id );
                if ( empty( $meta ) || ! is_array( $meta ) || empty( $meta['ngio'] ) || ! is_array( $meta['ngio'] ) ) {
                    continue;
                }

                $ngio = $meta['ngio'];

                $original = isset( $ngio['original_bytes'] ) ? (int) $ngio['original_bytes'] : 0;
                $nextgen  = isset( $ngio['nextgen_bytes'] ) ? (int) $ngio['nextgen_bytes'] : 0;

                if ( $original <= 0 || $nextgen <= 0 ) {
                    continue;
                }

                $stats['optimized_images']++;
                $stats['original_bytes'] += $original;
                $stats['nextgen_bytes']  += $nextgen;
            }
        }

        $stats['bytes_saved'] = max( 0, $stats['original_bytes'] - $stats['nextgen_bytes'] );

        if ( $stats['original_bytes'] > 0 ) {
            $stats['saving_percent'] = (int) round( ( $stats['bytes_saved'] / $stats['original_bytes'] ) * 100 );
        }

        $stats['remaining_images'] = max( 0, $stats['total_images'] - $stats['optimized_images'] );

        if ( function_exists( 'size_format' ) ) {
            $stats['original_human']  = size_format( $stats['original_bytes'], 2 );
            $stats['optimized_human'] = size_format( $stats['nextgen_bytes'], 2 );
            $stats['saved_human']     = size_format( $stats['bytes_saved'], 2 );
        }

        return $stats;
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $overview = $this->get_overview_stats();
        ?>
        <div class="wrap ngio-bulk-wrap">
            <div class="ngio-bulk-header">
                <div class="ngio-bulk-header-left">
                    <h1><?php esc_html_e( 'Bulk Optimization', 'hedef-image-optimizer-webp-avif' ); ?></h1>
                    <p><?php esc_html_e( 'Scan your media library, generate WebP/AVIF copies in batch and see exactly how much space you saved.', 'hedef-image-optimizer-webp-avif' ); ?></p>
                </div>
                <div class="ngio-bulk-header-right">
                    <a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="button">
                        <?php esc_html_e( 'Open Media Library', 'hedef-image-optimizer-webp-avif' ); ?>
                    </a>
                </div>
            </div>

            <div class="ngio-bulk-overview-grid">
                <section class="ngio-card ngio-bulk-overview-card">
                    <h2 class="ngio-bulk-card-title"><?php esc_html_e( 'Overview', 'hedef-image-optimizer-webp-avif' ); ?></h2>

                    <div class="ngio-bulk-overview-main">
                        <div class="ngio-bulk-ring-wrap">
                            <div
                                class="ngio-bulk-ring"
                                id="ngio-bulk-ring"
                                style="--ngio-progress: <?php echo (int) $overview['saving_percent']; ?>%;"
                            >
                                <span class="ngio-bulk-ring-value" id="ngio-bulk-ring-value">
                                    <?php echo (int) $overview['saving_percent']; ?>%
                                </span>
                            </div>
                        </div>

                        <div class="ngio-bulk-overview-stats">
                            <div class="ngio-bulk-overview-stat">
                                <span class="ngio-bulk-overview-stat-label">
                                    <?php esc_html_e( 'Total convertible images', 'hedef-image-optimizer-webp-avif' ); ?>
                                </span>
                                <span class="ngio-bulk-overview-stat-value">
                                    <?php echo esc_html( number_format_i18n( $overview['total_images'] ) ); ?>
                                </span>
                            </div>

                            <div class="ngio-bulk-overview-stat">
                                <span class="ngio-bulk-overview-stat-label">
                                    <?php esc_html_e( 'Optimized images', 'hedef-image-optimizer-webp-avif' ); ?>
                                </span>
                                <span class="ngio-bulk-overview-stat-value">
                                    <?php echo esc_html( number_format_i18n( $overview['optimized_images'] ) ); ?>
                                </span>
                            </div>

                            <div class="ngio-bulk-overview-stat">
                                <span class="ngio-bulk-overview-stat-label">
                                    <?php esc_html_e( 'Images to optimize', 'hedef-image-optimizer-webp-avif' ); ?>
                                </span>
                                <span class="ngio-bulk-overview-stat-value">
                                    <?php echo esc_html( number_format_i18n( $overview['remaining_images'] ) ); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <p class="ngio-bulk-overview-note">
                        <?php
                        /* translators: 1: total space saved (human readable), 2: saving percentage, 3: original total size (human readable). */
                            printf(
                            esc_html__( 'You\'ve already saved %1$s (%2$d%% of %3$s) across optimized images.', 'hedef-image-optimizer-webp-avif' ),
                            esc_html( $overview['saved_human'] ),
                            (int) $overview['saving_percent'],
                            esc_html( $overview['original_human'] )
                            );
                            ?>
                    </p>
                </section>

                <aside class="ngio-card ngio-bulk-side-card">
                    <h2><?php esc_html_e( 'Tips', 'hedef-image-optimizer-webp-avif' ); ?></h2>
                    <ul class="ngio-bulk-tips">
                        <li><?php esc_html_e( 'Run the bulk optimizer after importing a large batch of photos or a new theme demo.', 'hedef-image-optimizer-webp-avif' ); ?></li>
                        <li><?php esc_html_e( 'Keep “Optimize on upload” enabled so new uploads are always covered.', 'hedef-image-optimizer-webp-avif' ); ?></li>
                        <li><?php esc_html_e( 'If you change the quality or resize settings, run a new bulk pass to refresh existing copies.', 'hedef-image-optimizer-webp-avif' ); ?></li>
                    </ul>
                </aside>
            </div>

            <section class="ngio-bulk-card">
                <h2 class="ngio-bulk-card-title">
                    <?php esc_html_e( 'Optimize your media files', 'hedef-image-optimizer-webp-avif' ); ?>
                </h2>
                <p class="description">
                    <?php esc_html_e( 'Click the button below to start scanning your media library and generating missing WebP/AVIF versions. You can safely navigate away; the process runs in small batches.', 'hedef-image-optimizer-webp-avif' ); ?>
                </p>

                <div class="ngio-bulk-controls">
                    <button type="button" class="button button-primary" id="ngio-bulk-start">
                        <?php esc_html_e( 'Optimize all images', 'hedef-image-optimizer-webp-avif' ); ?>
                    </button>

                    <span class="spinner" id="ngio-bulk-spinner"></span>
                </div>

                <div class="ngio-bulk-progress" id="ngio-bulk-progress" style="display:none;">
                    <div class="ngio-bulk-progress-bar">
                        <div class="ngio-bulk-progress-bar-inner"></div>
                    </div>
                    <p id="ngio-bulk-status"></p>
                </div>

                <div class="ngio-bulk-activity" id="ngio-bulk-activity"></div>
            </section>
        </div>
        <?php
    }

    public function ajax_bulk_optimize() {
    check_ajax_referer( 'ngio_bulk_optimize', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'You are not allowed to run bulk optimization.', 'hedef-image-optimizer-webp-avif' ),
            )
        );
    }

    $page = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;

    $per_page = (int) apply_filters( 'ngio_bulk_batch_size', 4 );
    if ( $per_page < 1 ) {
        $per_page = 4;
    }

    $query = new WP_Query(
        array(
            'post_type'      => 'attachment',
            'post_mime_type' => array( 'image/jpeg', 'image/jpg', 'image/png' ),
            'post_status'    => 'inherit',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
        )
    );

    if ( ! $query->have_posts() ) {
        wp_send_json_success(
            array(
                'processed' => 0,
                'items'     => array(),
                'finished'  => true,
            )
        );
    }

    $converter = new NGIO_Converter();
    $converter->set_force_convert( true );

    $processed = 0;
    $items     = array();

    foreach ( $query->posts as $attachment_id ) {
        $meta = wp_get_attachment_metadata( $attachment_id );
        if ( empty( $meta ) || ! is_array( $meta ) ) {
            continue;
        }

        $new_meta = $converter->handle_attachment_metadata( $meta, $attachment_id );

        if ( $new_meta !== $meta ) {
            wp_update_attachment_metadata( $attachment_id, $new_meta );
        }

        $processed++;
        $items[] = array(
            'id'    => $attachment_id,
            'title' => get_the_title( $attachment_id ),
        );
    }

    $finished = ( $page >= $query->max_num_pages );

    wp_send_json_success(
        array(
            'processed' => $processed,
            'items'     => $items,
            'finished'  => $finished,
        )
    );
}
}
