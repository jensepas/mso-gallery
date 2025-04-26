<?php

namespace MSO_Gallery;

class GalleryBlock {

    /**
     * Plugin basename
     *
     * @var string
     */
    private string $plugin_basename;


    /**
     * Constructor
     */
    public function __construct() {
        $this->plugin_basename = plugin_basename(__FILE__);
    }

    public function register(): void {
        add_action('init', [$this, 'register_gallery_block']);
    }

    public function _register_gallery_block(): void {
        register_block_type(__DIR__ . '/../block');
    }


    public function register_gallery_block(): void {
        // Enregistrer les scripts générés par @wordpress/scripts
        // Le handle 'mso-gallery-block-editor' doit correspondre à ce que @wordpress/scripts génère
        wp_register_script(
            'mso-gallery-block-editor',
            plugins_url('../assets/js/mso-gallery-gutenberg.js', __FILE__), // Chemin vers le JS compilé
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'], // Dépendances
            filemtime(plugin_dir_path(__FILE__) . '../assets/js/mso-gallery-gutenberg.js'),
            true
        );

        // Enregistrer les styles de l'éditeur (si vous en avez)
        wp_register_style(
            'mso-gallery-block-editor-style',
            plugins_url('../assets/css/mso-gallery-gutenberg.css', __FILE__), // Chemin vers le CSS compilé pour l'éditeur
            ['wp-edit-blocks'],
            filemtime(plugin_dir_path(__FILE__) . '../assets/css/mso-gallery-gutenberg.css')
        );

        // Enregistrer les styles du frontend (partagés avec le shortcode)
        wp_register_style(
            'mso-gallery-block-style',
            plugins_url('../assets/css/mso-gallery-main.css', __FILE__),
            [],
            MSO_Gallery::VERSION
        );

        register_block_type('mso/gallery', [
            'editor_script'   => 'mso-gallery-block-editor', // JS pour l'éditeur
            'editor_style'    => 'mso-gallery-block-editor-style', // CSS pour l'éditeur
            'style'           => 'mso-gallery-block-style', // CSS pour le frontend
            'attributes'      => [
                'ids' => [
                    'type' => 'array', // Stocker les IDs comme un tableau
                    'default' => [],
                ],
                // Ajoutez d'autres attributs si nécessaire (ordre, taille miniature, etc.)
            ],
            'render_callback' => [$this, 'render_gallery_block'], // Fonction PHP pour le rendu frontend
        ]);
    }
    /**
     * Render callback for the dynamic Gutenberg block.
     *
     * @param array $attributes Block attributes.
     * @return string HTML output.
     */
    public function render_gallery_block(array $attributes): string {
        $shortcode_atts = [
            'ids' => implode(',', $attributes['ids'] ?? []),
        ];
        return $this->generate_gallery_output($shortcode_atts);
    }
    /**
     * Generates the HTML for a gallery instance (thumbnails container)
     * and queues its data for the footer.
     *
     * @param array $atts Gallery attributes.
     * @return string HTML for the thumbnails' container.
     */
    private function generate_gallery_output(array $atts): string {
        // 1. Enqueue frontend assets if not already done (WP handles duplicates)
        $this->enqueue_assets();

        // 2. Get image data for this specific gallery
        $images = new MSOGallery();
        $images = $images->get_gallery_images($atts);

        // Return empty if no images
        if (empty($images)) {
            return '';
        }

        // 3. Generate a unique ID for this gallery instance
        $gallery_id = wp_unique_id('mso-gallery-');

        // 4. Add this gallery's data to the queue
        MSOGallery::$gallery_data_queue[$gallery_id] = $images;

        // 5. Add footer actions only once if there's data
        if (!MSOGallery::$footer_actions_added && !empty(MSOGallery::$gallery_data_queue)) {
            add_action('wp_footer', [$this, 'output_shared_footer_elements'], 20);
            MSOGallery::$footer_actions_added = true;
        }

        // 6. Return the unique container for this gallery's thumbnails
        return sprintf(
            '<div class="mso-gallery-thumbnails" data-gallery-id="%s"></div>',
            esc_attr($gallery_id)
        );
    }

    /**
     * Enqueue necessary scripts and styles for the frontend
     *
     * @return void
     */
    public function enqueue_assets(): void {
        $plugin_dir = dirname($this->plugin_basename);

        wp_enqueue_style(
            'mso-gallery',
            plugins_url("/{$plugin_dir}/assets/css/mso-gallery-main.css"),
            [],
            MSO_Gallery::VERSION
        );

        wp_enqueue_script(
            'mso-gallery',
            plugins_url("/{$plugin_dir}/assets/js/mso-gallery-main.js"),
            ['jquery'],
            MSO_Gallery::VERSION,
            true
        );
    }



    /**
     * Outputs shared HTML (overlay) and combined JS data in the footer.
     * Attached to wp_footer hook.
     *
     * @return void
     */
    public function output_shared_footer_elements(): void {
        // Don't output if no galleries were processed
        if (empty(MSOGallery::$gallery_data_queue)) {
            return;
        }

        // Output the single overlay structure
        echo wp_kses_post($this->get_overlay_html());

        // Output the combined data for all galleries
        ?>
        <script type="text/javascript" id="mso-galleries-data">
            /* <![CDATA[ */
            window.MSO_GALLERIES_DATA = <?php echo wp_json_encode(MSOGallery::$gallery_data_queue); ?>;
            /* ]]> */
        </script>
        <?php

        // Optional: Clear the queue after output
        MSOGallery::$gallery_data_queue = [];
    }
    /**
     * Get gallery HTML structure
     *
     * @return string
     */
    private function get_overlay_html(): string {
        // Using output buffering for cleaner HTML structure
        ob_start();
        ?>
        <div id="fullscreen-overlay">
            <div id="image-container">
                <button id="prev-btn" class="nav-button">&#x2B05;</button>
                <img id="fullscreen-image" alt="">
                <button id="next-btn" class="nav-button">&#x27A1;</button>
                <button id="close-btn" class="close-button">&times;</button>
                <div id="image-caption"></div>
            </div>
        </div>
        <div id="loading-indicator"><?php esc_html_e('Loading images...', 'mso-gallery'); ?></div>
        <div id="preload-cache"></div>
        <?php
        return ob_get_clean();
    }
}
