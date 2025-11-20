<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NGIO_Admin {

    protected $option_name = 'ngio_settings';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        add_action( 'attachment_submitbox_misc_actions', array( $this, 'render_single_optimize_box' ) );

        add_action( 'wp_ajax_ngio_optimize_single', array( $this, 'ajax_optimize_single' ) );
        add_action( 'wp_ajax_ngio_restore_single', array( $this, 'ajax_restore_single' ) );

        add_filter( 'manage_upload_columns', array( $this, 'add_media_column' ) );
        add_action( 'manage_media_custom_column', array( $this, 'render_media_column' ), 10, 2 );
    }
function ngio_get_server_capabilities() {
    $converter = new NGIO_Converter();

    $webp_gd      = $converter->server_supports_webp_gd();
    $webp_imagick = $converter->server_supports_webp_imagick();
    $avif_gd      = $converter->server_supports_avif_gd();
    $avif_imagick = $converter->server_supports_avif_imagick();

    return array(
        'webp_gd'      => $webp_gd,
        'webp_imagick' => $webp_imagick,
        'avif_gd'      => $avif_gd,
        'avif_imagick' => $avif_imagick,
        'webp_overall' => ( $webp_gd || $webp_imagick ),
        'avif_overall' => ( $avif_gd || $avif_imagick ),
    );
}

    public function get_default_settings() {
        return array(
            'enable_webp'              => 1,
            'enable_avif'              => 1,
            'quality'                  => 82,
            'auto_on_upload'           => 1,
            'enable_picture'           => 1,
            'resize_enabled'           => 0,
            'resize_max_width'         => 2048,
            'strip_metadata'           => 1,
            'exclude_patterns_enabled' => 0,
            'exclude_patterns'         => '',
        );
    }

    public function get_settings() {
        $defaults = $this->get_default_settings();
        $saved    = get_option( $this->option_name, array() );

        if ( ! is_array( $saved ) ) {
            $saved = array();
        }

        $settings = wp_parse_args( $saved, $defaults );

        $settings['quality']          = max( 0, min( 100, (int) $settings['quality'] ) );
        $settings['resize_max_width'] = max( 320, min( 8000, (int) $settings['resize_max_width'] ) );

        $settings['enable_webp']              = (int) ! empty( $settings['enable_webp'] );
        $settings['enable_avif']              = (int) ! empty( $settings['enable_avif'] );
        $settings['auto_on_upload']           = (int) ! empty( $settings['auto_on_upload'] );
        $settings['enable_picture']           = (int) ! empty( $settings['enable_picture'] );
        $settings['resize_enabled']           = (int) ! empty( $settings['resize_enabled'] );
        $settings['strip_metadata']           = (int) ! empty( $settings['strip_metadata'] );
        $settings['exclude_patterns_enabled'] = (int) ! empty( $settings['exclude_patterns_enabled'] );
        $settings['exclude_patterns']         = is_string( $settings['exclude_patterns'] ) ? $settings['exclude_patterns'] : '';

        return $settings;
    }

    public function register_settings() {
        register_setting(
            'ngio_settings_group',
            $this->option_name,
            array( $this, 'sanitize_settings' )
        );
    }

    public function sanitize_settings( $input ) {
        $defaults = $this->get_default_settings();
        $output   = array();

        $output['enable_webp']    = isset( $input['enable_webp'] ) ? 1 : 0;
        $output['enable_avif']    = isset( $input['enable_avif'] ) ? 1 : 0;
        $output['auto_on_upload'] = isset( $input['auto_on_upload'] ) ? 1 : 0;
        $output['enable_picture'] = isset( $input['enable_picture'] ) ? 1 : 0;

        $quality = isset( $input['quality'] ) ? intval( $input['quality'] ) : $defaults['quality'];
        if ( $quality < 0 ) {
            $quality = 0;
        } elseif ( $quality > 100 ) {
            $quality = 100;
        }
        $output['quality'] = $quality;

        $output['resize_enabled'] = isset( $input['resize_enabled'] ) ? 1 : 0;

        $resize_max = isset( $input['resize_max_width'] ) ? intval( $input['resize_max_width'] ) : $defaults['resize_max_width'];
        if ( $resize_max < 320 ) {
            $resize_max = 320;
        } elseif ( $resize_max > 8000 ) {
            $resize_max = 8000;
        }
        $output['resize_max_width'] = $resize_max;

        $output['strip_metadata'] = isset( $input['strip_metadata'] ) ? 1 : 0;

        $output['exclude_patterns_enabled'] = isset( $input['exclude_patterns_enabled'] ) ? 1 : 0;

        if ( isset( $input['exclude_patterns'] ) ) {
            if ( function_exists( 'sanitize_textarea_field' ) ) {
                $patterns = sanitize_textarea_field( $input['exclude_patterns'] );
            } else {
                $patterns = wp_kses( (string) $input['exclude_patterns'], array() );
            }
        } else {
            $patterns = '';
        }

        $output['exclude_patterns'] = $patterns;

        return $output;
    }

    public function add_settings_page() {
        add_options_page(
            __( 'NextGen Image Optimizer', 'nextgen-image-optimizer' ),
            __( 'Image Optimizer', 'nextgen-image-optimizer' ),
            'manage_options',
            'ngio-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function enqueue_assets( $hook_suffix ) {

        if ( 'settings_page_ngio-settings' === $hook_suffix ) {

            wp_enqueue_style(
                'ngio-admin',
                NGIO_PLUGIN_URL . 'admin/css/admin.css',
                array(),
                NGIO_VERSION
            );

            wp_enqueue_script(
                'ngio-settings',
                NGIO_PLUGIN_URL . 'admin/js/settings.js',
                array( 'jquery' ),
                NGIO_VERSION,
                true
            );

            return;
        }

        if ( 'post.php' === $hook_suffix ) {
            $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

            if ( $screen && 'attachment' === $screen->post_type ) {

                wp_enqueue_style(
                    'ngio-admin',
                    NGIO_PLUGIN_URL . 'admin/css/admin.css',
                    array(),
                    NGIO_VERSION
                );

                wp_enqueue_script(
                    'ngio-single',
                    NGIO_PLUGIN_URL . 'admin/js/single.js',
                    array( 'jquery' ),
                    NGIO_VERSION,
                    true
                );

                wp_localize_script(
                    'ngio-single',
                    'ngioSingle',
                    array(
                        'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                        'nonce'       => wp_create_nonce( 'ngio_optimize_single' ),
                        'textWorking' => __( 'Re-optimizing this image…', 'nextgen-image-optimizer' ),
                        'textDone'    => __( 'Image optimization completed.', 'nextgen-image-optimizer' ),
                        'textError'   => __( 'Could not optimize this image.', 'nextgen-image-optimizer' ),
                    )
                );
            }
        }

        if ( 'upload.php' === $hook_suffix ) {

            wp_enqueue_style(
                'ngio-admin',
                NGIO_PLUGIN_URL . 'admin/css/admin.css',
                array(),
                NGIO_VERSION
            );

            wp_enqueue_script(
                'ngio-media',
                NGIO_PLUGIN_URL . 'admin/js/media.js',
                array( 'jquery' ),
                NGIO_VERSION,
                true
            );

            wp_localize_script(
                'ngio-media',
                'ngioMedia',
                array(
                    'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
                    'nonce'           => wp_create_nonce( 'ngio_optimize_single' ),
                    'textWorking'     => __( 'Optimizing image…', 'nextgen-image-optimizer' ),
                    'textDone'        => __( 'Image optimized.', 'nextgen-image-optimizer' ),
                    'textError'       => __( 'Optimization failed.', 'nextgen-image-optimizer' ),
                    'textDetailsOpen'  => __( 'View details', 'nextgen-image-optimizer' ),
                    'textDetailsClose' => __( 'Close details', 'nextgen-image-optimizer' ),
                    'textNotOptimized' => __( 'Not optimized yet', 'nextgen-image-optimizer' ),
                    'confirmRestore'   => __( 'Remove WebP/AVIF copies for this image and keep only the original?', 'nextgen-image-optimizer' ),
                )
            );
        }
    }

    public function render_toggle( $field, $value, $label ) {
        $field_id   = 'ngio_' . $field;
        $is_checked = ! empty( $value );
        ?>
        <label class="ngio-toggle" for="<?php echo esc_attr( $field_id ); ?>">
            <input type="checkbox"
                   id="<?php echo esc_attr( $field_id ); ?>"
                   name="<?php echo esc_attr( $this->option_name ); ?>[<?php echo esc_attr( $field ); ?>]"
                   value="1"
                <?php checked( $is_checked ); ?>
            />
            <span class="ngio-toggle-slider"></span>
            <span class="ngio-toggle-text"><?php echo esc_html( $label ); ?></span>
        </label>
        <?php
    }

    protected function get_media_overview() {
        $stats = array(
            'total_images'     => 0,
            'optimized_images' => 0,
            'original_bytes'   => 0,
            'nextgen_bytes'    => 0,
            'bytes_saved'      => 0,
            'saving_percent'   => 0,
            'original_human'   => '0 B',
            'optimized_human'  => '0 B',
            'saved_human'      => '0 B',
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
                'posts_per_page'         => 50,
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

        if ( function_exists( 'size_format' ) ) {
            $stats['original_human']  = size_format( $stats['original_bytes'], 2 );
            $stats['optimized_human'] = size_format( $stats['nextgen_bytes'], 2 );
            $stats['saved_human']     = size_format( $stats['bytes_saved'], 2 );
        }

        return $stats;
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = $this->get_settings();

        $caps = array(
            'webp_gd'      => false,
            'webp_imagick' => false,
            'avif_gd'      => false,
            'avif_imagick' => false,
            'webp_any'     => false,
            'avif_any'     => false,
        );
        if ( class_exists( 'NGIO_Converter' ) && method_exists( 'NGIO_Converter', 'get_server_capabilities' ) ) {
            $caps = NGIO_Converter::get_server_capabilities();
        }

        $media_overview = $this->get_media_overview();
        ?>
        <div class="wrap ngio-settings-wrap">
            <div class="ngio-hero">
                <div class="ngio-hero-left">
                    <div class="ngio-hero-logo">
                        <span>NGIO</span>
                    </div>
                    <div class="ngio-hero-text">
                        <h1><?php esc_html_e( 'NextGen Image Optimizer', 'nextgen-image-optimizer' ); ?></h1>
                        <p>
                            <?php esc_html_e( 'Convert your JPEG and PNG uploads to modern WebP/AVIF, automatically serve them on the frontend and track how much weight you save.', 'nextgen-image-optimizer' ); ?>
                        </p>
                        <div class="ngio-hero-badges">
                            <span class="ngio-hero-badge">
                                <span class="ngio-status-dot ngio-status-dot--ok"></span>
                                <strong><?php esc_html_e( 'Auto optimize', 'nextgen-image-optimizer' ); ?></strong>
                                <span><?php esc_html_e( 'on upload & in bulk', 'nextgen-image-optimizer' ); ?></span>
                            </span>
                            <span class="ngio-hero-badge">
                                <span class="dashicons dashicons-chart-pie"></span>
                                <strong><?php esc_html_e( 'Space saved', 'nextgen-image-optimizer' ); ?></strong>
                                <span>
                                    <?php
                                    printf(
                                        esc_html__( '%d%% (sampled)', 'nextgen-image-optimizer' ),
                                        (int) $media_overview['saving_percent']
                                    );
                                    ?>
                                </span>
                            </span>
                        </div><p class="ngio-hero-madeby">
    <?php
    printf(
        __( 'This plugin is built by %1$s and offered completely free. If you spot something missing or have ideas to improve it, feel free to email us at support@hedefhosting.com.tr', 'nextgen-image-optimizer' ),
        '<a href="https://hedefhosting.com.tr/" target="_blank" rel="noopener noreferrer">Hedef Hosting</a>'
    );
    ?>
</p>
                    </div>
                </div>
                <div class="ngio-hero-right">
                    <a href="<?php echo esc_url( admin_url( 'upload.php?page=ngio-bulk' ) ); ?>" class="button button-primary ngio-hero-button">
                        <?php esc_html_e( 'Open bulk optimizer', 'nextgen-image-optimizer' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="button button-secondary ngio-hero-button ngio-hero-button-secondary">
                        <?php esc_html_e( 'Go to Media Library', 'nextgen-image-optimizer' ); ?>
                    </a>
                </div>
            </div>

            <form method="post" action="options.php" class="ngio-settings-form">
                <?php
                settings_fields( 'ngio_settings_group' );
                ?>

                <div class="ngio-settings-grid">
                    <!-- Main config card -->
                    <section class="ngio-card ngio-card-main">
                        <header class="ngio-card-header">
                            <h2>
                                <span class="dashicons dashicons-admin-generic"></span>
                                <?php esc_html_e( 'Configuration', 'nextgen-image-optimizer' ); ?>
                            </h2>
                            <p class="ngio-card-subtitle">
                                <?php esc_html_e( 'Choose your output formats, automation rules and compression settings.', 'nextgen-image-optimizer' ); ?>
                            </p>
                        </header>

                        <div class="ngio-card-body">
                            <!-- Block 1: Output formats -->
                            <div class="ngio-settings-block ngio-settings-block--blue">
                                <div class="ngio-settings-block-header">
                                    <h3><?php esc_html_e( 'Output formats', 'nextgen-image-optimizer' ); ?></h3>
                                    <p><?php esc_html_e( 'Enable the formats you want to generate from JPEG/PNG uploads.', 'nextgen-image-optimizer' ); ?></p>
                                </div>
                                <div class="ngio-settings-block-body">
                                    <table class="ngio-options-table">
                                        <tbody>
                                        <tr>
                                            <th>
                                                <strong><?php esc_html_e( 'WebP', 'nextgen-image-optimizer' ); ?></strong>
                                                <span><?php esc_html_e( 'Excellent browser support and great compression for most websites.', 'nextgen-image-optimizer' ); ?></span>
                                            </th>
                                            <td>
                                                <?php
                                                $this->render_toggle(
                                                    'enable_webp',
                                                    isset( $settings['enable_webp'] ) ? $settings['enable_webp'] : 1,
                                                    __( 'Generate WebP versions', 'nextgen-image-optimizer' )
                                                );
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
                                                <strong><?php esc_html_e( 'AVIF', 'nextgen-image-optimizer' ); ?></strong>
                                                <span><?php esc_html_e( 'Even smaller files at similar quality, supported in modern browsers.', 'nextgen-image-optimizer' ); ?></span>
                                            </th>
                                            <td>
                                                <?php
                                                $this->render_toggle(
                                                    'enable_avif',
                                                    isset( $settings['enable_avif'] ) ? $settings['enable_avif'] : 1,
                                                    __( 'Generate AVIF versions', 'nextgen-image-optimizer' )
                                                );
                                                ?>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Block 2: Automation & delivery -->
                            <div class="ngio-settings-block ngio-settings-block--green">
                                <div class="ngio-settings-block-header">
                                    <h3><?php esc_html_e( 'Automation & delivery', 'nextgen-image-optimizer' ); ?></h3>
                                    <p><?php esc_html_e( 'Control when optimization runs and how next-gen formats are used on the frontend.', 'nextgen-image-optimizer' ); ?></p>
                                </div>
                                <div class="ngio-settings-block-body">
                                    <table class="ngio-options-table">
                                        <tbody>
                                        <tr>
                                            <th>
                                                <strong><?php esc_html_e( 'Optimize on upload', 'nextgen-image-optimizer' ); ?></strong>
                                                <span><?php esc_html_e( 'Automatically generate WebP/AVIF versions when new images are uploaded.', 'nextgen-image-optimizer' ); ?></span>
                                            </th>
                                            <td>
                                                <?php
                                                $this->render_toggle(
                                                    'auto_on_upload',
                                                    isset( $settings['auto_on_upload'] ) ? $settings['auto_on_upload'] : 1,
                                                    __( 'Run optimization on upload', 'nextgen-image-optimizer' )
                                                );
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
                                                <strong><?php esc_html_e( 'Serve via &lt;picture&gt;', 'nextgen-image-optimizer' ); ?></strong>
                                                <span><?php esc_html_e( 'Wrap images in &lt;picture&gt; with WebP/AVIF sources on the frontend.', 'nextgen-image-optimizer' ); ?></span>
                                            </th>
                                            <td>
                                                <?php
                                                $this->render_toggle(
                                                    'enable_picture',
                                                    isset( $settings['enable_picture'] ) ? $settings['enable_picture'] : 1,
                                                    __( 'Enable picture/srcset integration', 'nextgen-image-optimizer' )
                                                );
                                                ?>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Block 3: Compression & resizing -->
                            <div class="ngio-settings-block ngio-settings-block--purple">
                                <div class="ngio-settings-block-header">
                                    <h3><?php esc_html_e( 'Compression & resizing', 'nextgen-image-optimizer' ); ?></h3>
                                    <p><?php esc_html_e( 'Fine-tune the balance between visual quality and file size.', 'nextgen-image-optimizer' ); ?></p>
                                </div>
                                <div class="ngio-settings-block-body">
                                    <table class="ngio-options-table">
                                        <tbody>
                                        <tr class="ngio-options-table-row--quality">
                                            <th>
                                                <strong><?php esc_html_e( 'Quality level (0–100)', 'nextgen-image-optimizer' ); ?></strong>
                                                <span><?php esc_html_e( 'Higher values mean better visual quality but larger files. 80–85 works great for most sites.', 'nextgen-image-optimizer' ); ?></span>
                                            </th>
                                            <td>
                                                <div class="ngio-quality-range-wrap">
                                                    <input type="range"
                                                           min="0"
                                                           max="100"
                                                           step="1"
                                                           value="<?php echo esc_attr( $settings['quality'] ); ?>"
                                                           class="ngio-quality-range"
                                                           oninput="this.nextElementSibling.value = this.value">
                                                    <input type="number"
                                                           min="0"
                                                           max="100"
                                                           name="<?php echo esc_attr( $this->option_name ); ?>[quality]"
                                                           value="<?php echo esc_attr( $settings['quality'] ); ?>"
                                                           class="ngio-quality-number"
                                                           oninput="this.previousElementSibling.value = this.value">
                                                    <span class="ngio-quality-percent">%</span>
                                                </div>
                                                <p class="description">
                                                    <?php esc_html_e( 'Recommended range: 75–90. For photography-heavy sites stay slightly higher, for UI-heavy sites you can go lower.', 'nextgen-image-optimizer' ); ?>
                                                </p>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>
                                                <strong><?php esc_html_e( 'Resize large images', 'nextgen-image-optimizer' ); ?></strong>
                                                <span><?php esc_html_e( 'Automatically downscale next-gen copies when the original width is higher than the value below.', 'nextgen-image-optimizer' ); ?></span>
                                            </th>
                                            <td>
                                                <div class="ngio-quality-range-wrap">
                                                    <?php
                                                    $this->render_toggle(
                                                        'resize_enabled',
                                                        isset( $settings['resize_enabled'] ) ? $settings['resize_enabled'] : 0,
                                                        __( 'Resize next-gen copies', 'nextgen-image-optimizer' )
                                                    );
                                                    ?>
                                                    <input type="number"
                                                           min="320"
                                                           max="8000"
                                                           name="<?php echo esc_attr( $this->option_name ); ?>[resize_max_width]"
                                                           value="<?php echo esc_attr( $settings['resize_max_width'] ); ?>"
                                                           class="ngio-quality-number">
                                                    <span class="ngio-quality-percent">px</span>
                                                </div>
                                                <p class="description">
                                                    <?php esc_html_e( 'Example: 2048px keeps hero images sharp while avoiding super heavy files.', 'nextgen-image-optimizer' ); ?>
                                                </p>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>
                                                <strong><?php esc_html_e( 'Strip EXIF & metadata', 'nextgen-image-optimizer' ); ?></strong>
                                                <span><?php esc_html_e( 'Remove camera EXIF/IPTC data from next-gen copies for smaller files. Originals remain untouched.', 'nextgen-image-optimizer' ); ?></span>
                                            </th>
                                            <td>
                                                <?php
                                                $this->render_toggle(
                                                    'strip_metadata',
                                                    isset( $settings['strip_metadata'] ) ? $settings['strip_metadata'] : 1,
                                                    __( 'Strip metadata on convert', 'nextgen-image-optimizer' )
                                                );
                                                ?>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Block 4: Advanced rules -->
                            <div class="ngio-settings-block ngio-settings-block--gray">
                                <div class="ngio-settings-block-header">
                                    <h3><?php esc_html_e( 'Advanced rules', 'nextgen-image-optimizer' ); ?></h3>
                                    <p><?php esc_html_e( 'Exclude specific images from being converted based on their filenames or paths.', 'nextgen-image-optimizer' ); ?></p>
                                </div>
                                <div class="ngio-settings-block-body">
                                    <table class="ngio-options-table">
                                        <tbody>
                                        <tr>
                                            <th>
                                                <strong><?php esc_html_e( 'Enable exclusion rules', 'nextgen-image-optimizer' ); ?></strong>
                                                <span><?php esc_html_e( 'When enabled, images matching any of the patterns below will be skipped during optimization.', 'nextgen-image-optimizer' ); ?></span>
                                            </th>
                                            <td>
                                                <?php
                                                $this->render_toggle(
                                                    'exclude_patterns_enabled',
                                                    isset( $settings['exclude_patterns_enabled'] ) ? $settings['exclude_patterns_enabled'] : 0,
                                                    __( 'Apply exclusion patterns', 'nextgen-image-optimizer' )
                                                );
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
                                                <strong><?php esc_html_e( 'Exclusion patterns (one per line)', 'nextgen-image-optimizer' ); ?></strong>
                                                <span>
                                                    <?php esc_html_e( 'Any image whose path or filename contains one of these strings will be ignored. Example: logo, avatar-, /icons/, /uploads/2020/', 'nextgen-image-optimizer' ); ?>
                                                </span>
                                            </th>
                                            <td>
                                                <textarea
                                                    name="<?php echo esc_attr( $this->option_name ); ?>[exclude_patterns]"
                                                    rows="5"
                                                    class="large-text code"
                                                ><?php echo isset( $settings['exclude_patterns'] ) ? esc_textarea( $settings['exclude_patterns'] ) : ''; ?></textarea>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="ngio-submit-row">
                                <?php submit_button( __( 'Save settings', 'nextgen-image-optimizer' ), 'primary', 'submit', false ); ?>
                                <p class="ngio-submit-hint">
                                    <?php esc_html_e( 'After changing quality or resize, you can re-run the bulk optimizer to refresh existing images.', 'nextgen-image-optimizer' ); ?>
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Server support card -->
                    <section class="ngio-card ngio-card-server">
                        <header class="ngio-card-header">
                            <h2>
                                <span class="dashicons dashicons-admin-tools"></span>
                                <?php esc_html_e( 'Server support', 'nextgen-image-optimizer' ); ?>
                            </h2>
                            <p class="ngio-card-subtitle">
                                <?php esc_html_e( 'Detection of GD/Imagick capabilities for WebP and AVIF.', 'nextgen-image-optimizer' ); ?>
                            </p>
                        </header>
                        <div class="ngio-card-body">
                            <div class="ngio-inner-table">
                                <table class="ngio-server-table">
                                    <tbody>
                                    <tr>
                                        <th class="ngio-server-label">
                                            <span class="dashicons dashicons-admin-generic"></span>
                                            <span><?php esc_html_e( 'WebP via GD', 'nextgen-image-optimizer' ); ?></span>
                                        </th>
                                        <td>
                                            <?php $this->render_cap_badge( ! empty( $caps['webp_gd'] ) ); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="ngio-server-label">
                                            <span class="dashicons dashicons-admin-generic"></span>
                                            <span><?php esc_html_e( 'WebP via Imagick', 'nextgen-image-optimizer' ); ?></span>
                                        </th>
                                        <td>
                                            <?php $this->render_cap_badge( ! empty( $caps['webp_imagick'] ) ); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="ngio-server-label">
                                            <span class="dashicons dashicons-admin-generic"></span>
                                            <span><?php esc_html_e( 'AVIF via GD', 'nextgen-image-optimizer' ); ?></span>
                                        </th>
                                        <td>
                                            <?php $this->render_cap_badge( ! empty( $caps['avif_gd'] ) ); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="ngio-server-label">
                                            <span class="dashicons dashicons-admin-generic"></span>
                                            <span><?php esc_html_e( 'AVIF via Imagick', 'nextgen-image-optimizer' ); ?></span>
                                        </th>
                                        <td>
                                            <?php $this->render_cap_badge( ! empty( $caps['avif_imagick'] ) ); ?>
                                        </td>
                                    </tr>
                                    <tr class="ngio-server-summary-row">
                                        <th class="ngio-server-label">
                                            <span class="dashicons dashicons-yes"></span>
                                            <span><?php esc_html_e( 'Overall WebP support', 'nextgen-image-optimizer' ); ?></span>
                                        </th>
                                        <td>
                                            <?php $this->render_cap_badge( ! empty( $caps['webp_any'] ) ); ?>
                                        </td>
                                    </tr>
                                    <tr class="ngio-server-summary-row">
                                        <th class="ngio-server-label">
                                            <span class="dashicons dashicons-yes"></span>
                                            <span><?php esc_html_e( 'Overall AVIF support', 'nextgen-image-optimizer' ); ?></span>
                                        </th>
                                        <td>
                                            <?php $this->render_cap_badge( ! empty( $caps['avif_any'] ) ); ?>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <!-- Media overview card -->
                    <section class="ngio-card ngio-card-tools">
                        <header class="ngio-card-header">
                            <h2>
                                <span class="dashicons dashicons-chart-pie"></span>
                                <?php esc_html_e( 'Media overview', 'nextgen-image-optimizer' ); ?>
                            </h2>
                            <p class="ngio-card-subtitle">
                                <?php esc_html_e( 'Quick snapshot of how your media library benefits from NGIO.', 'nextgen-image-optimizer' ); ?>
                            </p>
                        </header>
                        <div class="ngio-card-body">
                            <div class="ngio-media-summary-grid">
                                <div class="ngio-media-summary-box">
                                    <span class="ngio-media-summary-label">
                                        <?php esc_html_e( 'Convertible images', 'nextgen-image-optimizer' ); ?>
                                    </span>
                                    <span class="ngio-media-summary-value">
                                        <?php echo esc_html( number_format_i18n( $media_overview['total_images'] ) ); ?>
                                    </span>
                                </div>
                                <div class="ngio-media-summary-box">
                                    <span class="ngio-media-summary-label">
                                        <?php esc_html_e( 'Already optimized', 'nextgen-image-optimizer' ); ?>
                                    </span>
                                    <span class="ngio-media-summary-value">
                                        <?php echo esc_html( number_format_i18n( $media_overview['optimized_images'] ) ); ?>
                                    </span>
                                </div>
                                <div class="ngio-media-summary-box">
                                    <span class="ngio-media-summary-label">
                                        <?php esc_html_e( 'Space saved (sample)', 'nextgen-image-optimizer' ); ?>
                                    </span>
                                    <span class="ngio-media-summary-value">
                                        <?php echo esc_html( $media_overview['saved_human'] ); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="ngio-media-summary-footer">
                                <a href="<?php echo esc_url( admin_url( 'upload.php?page=ngio-bulk' ) ); ?>" class="button button-secondary">
                                    <?php esc_html_e( 'Run bulk optimization', 'nextgen-image-optimizer' ); ?>
                                </a>
                            </div>
                        </div>
                    </section>
                </div>
            </form>
        </div>
        <?php
    }

    protected function render_cap_badge( $available ) {
        if ( $available ) {
            ?>
            <span class="ngio-cap ngio-cap--yes">
                <?php esc_html_e( 'Available', 'nextgen-image-optimizer' ); ?>
            </span>
            <?php
        } else {
            ?>
            <span class="ngio-cap ngio-cap--no">
                <?php esc_html_e( 'Not available', 'nextgen-image-optimizer' ); ?>
            </span>
            <?php
        }
    }

    public function render_single_optimize_box( $post ) {
        if ( ! $post instanceof WP_Post ) {
            return;
        }

        if ( 'attachment' !== $post->post_type ) {
            return;
        }

        $mime = get_post_mime_type( $post->ID );
        if ( ! in_array( $mime, array( 'image/jpeg', 'image/jpg', 'image/png' ), true ) ) {
            return;
        }
        ?>
        <div class="misc-pub-section misc-pub-ngio-optimize">
            <span class="ngio-single-optimize-label">
                <?php esc_html_e( 'NextGen Image Optimizer', 'nextgen-image-optimizer' ); ?>
            </span>
            <p>
                <button type="button"
                        class="button button-secondary"
                        id="ngio-optimize-single"
                        data-attachment-id="<?php echo esc_attr( $post->ID ); ?>">
                    <?php esc_html_e( 'Bu görseli yeniden optimize et', 'nextgen-image-optimizer' ); ?>
                </button>
                <span class="spinner" id="ngio-single-spinner"></span>
            </p>
            <p class="description" id="ngio-single-status">
                <?php esc_html_e( 'Rebuild WebP/AVIF copies for this image without touching the original file.', 'nextgen-image-optimizer' ); ?>
            </p>
        </div>
        <?php
    }

    public function ajax_optimize_single() {
        check_ajax_referer( 'ngio_optimize_single', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'You are not allowed to optimize this image.', 'nextgen-image-optimizer' ),
                )
            );
        }

        $attachment_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;

        if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Invalid attachment.', 'nextgen-image-optimizer' ),
                )
            );
        }

        $mime = get_post_mime_type( $attachment_id );
        if ( ! in_array( $mime, array( 'image/jpeg', 'image/jpg', 'image/png' ), true ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'This file type cannot be optimized.', 'nextgen-image-optimizer' ),
                )
            );
        }

        $meta = wp_get_attachment_metadata( $attachment_id );
        if ( empty( $meta ) || ! is_array( $meta ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'This file has no metadata to optimize.', 'nextgen-image-optimizer' ),
                )
            );
        }

        if ( ! class_exists( 'NGIO_Converter' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Converter is not available.', 'nextgen-image-optimizer' ),
                )
            );
        }

        $converter = new NGIO_Converter();
        $converter->set_force_convert( true );

        $new_meta = $converter->handle_attachment_metadata( $meta, $attachment_id );

        if ( $new_meta !== $meta ) {
            wp_update_attachment_metadata( $attachment_id, $new_meta );
        }

        $meta_after = wp_get_attachment_metadata( $attachment_id );
        $stats      = array(
            'new_filesize'   => '',
            'saving_percent' => 0,
        );

        if ( ! empty( $meta_after['ngio'] ) && is_array( $meta_after['ngio'] ) ) {
            $ngio_after = $meta_after['ngio'];
            $orig       = isset( $ngio_after['original_bytes'] ) ? (int) $ngio_after['original_bytes'] : 0;
            $next       = isset( $ngio_after['nextgen_bytes'] ) ? (int) $ngio_after['nextgen_bytes'] : 0;

            if ( $orig > 0 && $next > 0 ) {
                $saved_bytes             = max( 0, $orig - $next );
                $stats['saving_percent'] = (int) round( ( $saved_bytes / $orig ) * 100 );
                $stats['new_filesize']   = function_exists( 'size_format' ) ? size_format( $next, 2 ) : $next . ' B';
            }
        }

        wp_send_json_success(
            array(
                'message' => __( 'Image optimized successfully.', 'nextgen-image-optimizer' ),
                'stats'   => $stats,
            )
        );
    }

    public function ajax_restore_single() {
        check_ajax_referer( 'ngio_optimize_single', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'You are not allowed to restore this image.', 'nextgen-image-optimizer' ),
                )
            );
        }

        $attachment_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;

        if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Invalid attachment.', 'nextgen-image-optimizer' ),
                )
            );
        }

        $mime = get_post_mime_type( $attachment_id );
        if ( ! in_array( $mime, array( 'image/jpeg', 'image/jpg', 'image/png' ), true ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'This file type cannot be restored.', 'nextgen-image-optimizer' ),
                )
            );
        }

        if ( class_exists( 'NGIO_Converter' ) && method_exists( 'NGIO_Converter', 'remove_for_attachment' ) ) {
            NGIO_Converter::remove_for_attachment( $attachment_id );
        } else {
            $file = get_attached_file( $attachment_id );
            if ( $file && file_exists( $file ) ) {
                $info = pathinfo( $file );
                $base = trailingslashit( $info['dirname'] ) . $info['filename'];

                $patterns = array(
                    $base . '*.webp',
                    $base . '*.avif',
                );

                foreach ( $patterns as $pattern ) {
                    foreach ( glob( $pattern ) as $candidate ) {
                        if ( is_file( $candidate ) ) {
                            @unlink( $candidate );
                        }
                    }
                }
            }
        }

        $meta = wp_get_attachment_metadata( $attachment_id );
        if ( ! empty( $meta ) && is_array( $meta ) && isset( $meta['ngio'] ) ) {
            unset( $meta['ngio'] );
            wp_update_attachment_metadata( $attachment_id, $meta );
        }

        wp_send_json_success(
            array(
                'message' => __( 'Next-gen copies removed. Original image is now used.', 'nextgen-image-optimizer' ),
            )
        );
    }

    public function add_media_column( $columns ) {
        $new = array();

        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;

            if ( 'date' === $key ) {
                $new['ngio'] = __( 'NextGen', 'nextgen-image-optimizer' );
            }
        }

        if ( ! isset( $new['ngio'] ) ) {
            $new['ngio'] = __( 'NextGen', 'nextgen-image-optimizer' );
        }

        return $new;
    }

    public function render_media_column( $column_name, $post_id ) {
        if ( 'ngio' !== $column_name ) {
            return;
        }

        $post = get_post( $post_id );
        if ( ! $post || 'attachment' !== $post->post_type ) {
            return;
        }

        $mime = get_post_mime_type( $post_id );
        if ( ! in_array( $mime, array( 'image/jpeg', 'image/jpg', 'image/png' ), true ) ) {
            echo '<span class="ngio-media-col-notice">' . esc_html__( 'Not an optimizable image', 'nextgen-image-optimizer' ) . '</span>';
            return;
        }

        $settings = $this->get_settings();
        $quality  = isset( $settings['quality'] ) ? (int) $settings['quality'] : 82;

        if ( $quality >= 95 ) {
            $profile_label = __( 'Lossless', 'nextgen-image-optimizer' );
        } elseif ( $quality <= 70 ) {
            $profile_label = __( 'Aggressive', 'nextgen-image-optimizer' );
        } else {
            $profile_label = __( 'Smart', 'nextgen-image-optimizer' );
        }

        $meta = wp_get_attachment_metadata( $post_id );
        $ngio = ( ! empty( $meta['ngio'] ) && is_array( $meta['ngio'] ) ) ? $meta['ngio'] : array();

        $orig_bytes = isset( $ngio['original_bytes'] ) ? (int) $ngio['original_bytes'] : 0;
        $next_bytes = isset( $ngio['nextgen_bytes'] ) ? (int) $ngio['nextgen_bytes'] : 0;

        $optimized   = ( $orig_bytes > 0 && $next_bytes > 0 );
        $saved_pct   = 0;
        $orig_human  = '–';
        $next_human  = '–';
        $saved_label = '–';

        if ( $optimized ) {
            $saved_bytes = max( 0, $orig_bytes - $next_bytes );
            if ( $orig_bytes > 0 ) {
                $saved_pct = ( $saved_bytes / $orig_bytes ) * 100;
            }

            if ( function_exists( 'size_format' ) ) {
                $orig_human = size_format( $orig_bytes, 2 );
                $next_human = size_format( $next_bytes, 2 );
            } else {
                $orig_human = $orig_bytes . ' B';
                $next_human = $next_bytes . ' B';
            }

            $saved_label = sprintf( '%d%%', (int) round( $saved_pct ) );
        }

        $thumb_count = 0;
        if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
            $thumb_count = count( $meta['sizes'] );
        }

        $formats       = array();
        $original_path = get_attached_file( $post_id );
        if ( $original_path ) {
            $info = pathinfo( $original_path );
            $base = trailingslashit( $info['dirname'] ) . $info['filename'];

            $webp_path = $base . '.webp';
            $avif_path = $base . '.avif';

            if ( file_exists( $webp_path ) ) {
                $formats[] = 'WebP';
            }
            if ( file_exists( $avif_path ) ) {
                $formats[] = 'AVIF';
            }
        }

        if ( $optimized && $formats ) {
            $nextgen_status = sprintf(
                __( 'Yes (%s)', 'nextgen-image-optimizer' ),
                implode( ', ', $formats )
            );
        } elseif ( $optimized ) {
            $nextgen_status = __( 'Yes', 'nextgen-image-optimizer' );
        } else {
            $nextgen_status = __( 'No', 'nextgen-image-optimizer' );
        }

        $attachment_id_attr = (int) $post_id;

        echo '<div class="ngio-media-col" data-attachment-id="' . esc_attr( $attachment_id_attr ) . '">';

        echo '<div class="ngio-media-col-main">';

        if ( $optimized ) {
            echo '<div class="ngio-media-col-line">';
            echo '<span class="ngio-media-col-label">' . esc_html__( 'New filesize', 'nextgen-image-optimizer' ) . '</span>';
            echo '<span class="ngio-media-col-value ngio-media-col-value-size">' . esc_html( $next_human ) . '</span>';
            echo '</div>';

            echo '<div class="ngio-media-col-line">';
            echo '<span class="ngio-media-col-label">' . esc_html__( 'Original saving', 'nextgen-image-optimizer' ) . '</span>';
            echo '<span class="ngio-media-col-value ngio-media-col-saved ngio-media-col-value-saved">' . esc_html( $saved_label ) . '</span>';
            echo '</div>';
        } else {
            echo '<span class="ngio-media-col-notice">' . esc_html__( 'Not optimized yet', 'nextgen-image-optimizer' ) . '</span>';
        }

        echo '</div>';

        echo '<div class="ngio-media-col-actions">';
        echo '<button type="button" class="button-link ngio-media-reoptimize" data-attachment-id="' . esc_attr( $attachment_id_attr ) . '">';
        esc_html_e( 'Re-optimize', 'nextgen-image-optimizer' );
        echo '</button>';

        if ( $optimized ) {
            echo '<button type="button" class="button-link ngio-media-details-toggle" data-open="0">';
            esc_html_e( 'View details', 'nextgen-image-optimizer' );
            echo '</button>';

            echo '<button type="button" class="button-link ngio-media-restore" data-attachment-id="' . esc_attr( $attachment_id_attr ) . '">';
            esc_html_e( 'Restore original', 'nextgen-image-optimizer' );
            echo '</button>';
        }

        echo '<span class="spinner ngio-media-spinner"></span>';
        echo '</div>';

        if ( $optimized ) {
            echo '<div class="ngio-media-details">';
            echo '<div class="ngio-media-details-inner">';
            echo '<div class="ngio-media-details-row"><span class="ngio-media-details-label">' . esc_html__( 'Original filesize', 'nextgen-image-optimizer' ) . '</span><span class="ngio-media-details-value">' . esc_html( $orig_human ) . '</span></div>';
            echo '<div class="ngio-media-details-row"><span class="ngio-media-details-label">' . esc_html__( 'Level', 'nextgen-image-optimizer' ) . '</span><span class="ngio-media-details-value">' . esc_html( $profile_label ) . '</span></div>';
            echo '<div class="ngio-media-details-row"><span class="ngio-media-details-label">' . esc_html__( 'Next-gen generated', 'nextgen-image-optimizer' ) . '</span><span class="ngio-media-details-value">' . esc_html( $nextgen_status ) . '</span></div>';
            echo '<div class="ngio-media-details-row"><span class="ngio-media-details-label">' . esc_html__( 'Thumbnails optimized', 'nextgen-image-optimizer' ) . '</span><span class="ngio-media-details-value">' . esc_html( number_format_i18n( $thumb_count ) ) . '</span></div>';
            echo '<div class="ngio-media-details-row"><span class="ngio-media-details-label">' . esc_html__( 'Overall saving', 'nextgen-image-optimizer' ) . '</span><span class="ngio-media-details-value">' . esc_html( $saved_label ) . '</span></div>';
            echo '</div>';
            echo '</div>';
        }

        echo '<div class="ngio-media-col-status" id="ngio-media-status-' . esc_attr( $attachment_id_attr ) . '"></div>';

        echo '</div>';
    }
}
