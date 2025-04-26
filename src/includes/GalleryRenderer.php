<?php

namespace MSO_Gallery;
class GalleryRenderer {

    public function register(): void {
        add_shortcode('mso_gallery', [$this, 'register_gallery_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    /**
     * Gallery shortcode handler
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML output
     */
    public function register_gallery_shortcode($atts): string {
        $atts = is_array($atts) ? $atts : [];
        return $this->generate_gallery_output($atts);
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
       // $this->enqueue_assets();

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

    public function enqueue_assets(): void {
        wp_enqueue_style('mso-gallery-style', plugins_url('../assets/css/mso-gallery-main.css', __FILE__), [], MSO_Gallery::VERSION);
        wp_enqueue_script('mso-gallery-script', plugins_url('../assets/js/mso-gallery-main.js', __FILE__), [], MSO_Gallery::VERSION, true);
    }
}
