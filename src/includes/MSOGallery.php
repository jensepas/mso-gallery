<?php
/**
 * Plugin Name: MSO Gallery
 * Description: WordPress plugin providing a simple photo gallery
 * Author: ms-only
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Text Domain: mso-gallery
 * Domain Path: /languages
 * License: GPL2+
 */

namespace MSO_Gallery;

if (!defined('ABSPATH')) {
    exit;
}

class MSOGallery {

    public static string $version = '1.0.0';
    private static ?self $instance = null;


    /**
     * Queue for gallery data to be output in the footer.
     * Key: Unique gallery instance ID. Value: Array of image data.
     * @var array<string, array>
     */
    public static array $gallery_data_queue = [];

    /**
     * Flag to ensure footer actions are added only once.
     * @var bool
     */
    public static bool $footer_actions_added = false;


    public static function init(): void {
        if (null === self::$instance) {
            self::$instance = new self();
        }
    }

    public function __construct() {

        (new GalleryRenderer())->register();
        (new GalleryAdmin())->register();
        (new GalleryBlock())->register();
    }

    /**
     * Registers WordPress hooks for admin functionality.
     *
     * Hooks methods from the Settings and MetaBox classes into the appropriate
     * WordPress actions (admin_menu, admin_init, add_meta_boxes, save_post).
     * Also hooks the method for enqueuing admin scripts.
     */
    public function register_hooks(): void
    {
//        add_action('add_meta_boxes', [$this->meta_box, 'add_meta_box']);
//        add_action('save_post', [$this->meta_box, 'save_meta_data']);
//        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
//        //add_action('admin_head', [$this, 'add_contextual_help']);
    }




    /**
     * Get gallery images
     *
     * @param array $atts Gallery attributes
     * @return array
     */
    public function get_gallery_images($atts): array {
        $post = get_post();

        // Process shortcode attributes
        $default_atts = [
            'order'      => 'ASC',
            'orderby' => 'post__in',
            'id'         => $post ? $post->ID : 0,
            'size'       => 'thumbnail',
            'include'    => '',
            'usedefault' => 0,
            'ids' => 0
        ];

        $atts = shortcode_atts($default_atts, $atts, 'gallery');

        // Handle IDs if specified
        if (!empty($atts['ids'])) {
            $atts['include'] = $atts['ids'];
            $atts['orderby'] = empty($atts['orderby']) ? 'post__in' : $atts['orderby'];
        }

        // Sanitize orderby
        if (isset($atts['orderby'])) {
            $atts['orderby'] = sanitize_sql_orderby($atts['orderby']);
            if (!$atts['orderby']) {
                unset($atts['orderby']);
            }
        }

        // Get attachments
        $attachments = $this->get_attachments($atts);

        if (empty($attachments)) {
            return [];
        }

        // Process attachments into image data array
        $images = [];
        foreach ($attachments as $id => $attachment) { // <-- Utiliser l'ID comme clé est bien
            $thumb = wp_get_attachment_image_src($id, 'thumbnail'); // Ou une taille personnalisée
            $full = wp_get_attachment_image_src($id, 'full');
            $alt_text = get_post_meta($id, '_wp_attachment_image_alt', true);
            $caption = wp_get_attachment_caption($id); // <-- Utiliser wp_get_attachment_caption

            // Utiliser la légende si elle existe, sinon le texte alt, sinon le titre
            $display_text = !empty($caption) ? $caption : (!empty($alt_text) ? $alt_text : get_the_title($id));

            if ($thumb && $full) {
                $images[] = [
                    'src'  => $thumb[0],
                    'full' => $full[0],
                    // Décoder les entités HTML pour l'affichage
                    'alt'  => html_entity_decode($display_text ?: '')
                ];
            }
        }

        return $images;
    }
    /**
     * Get attachments based on gallery attributes
     *
     * @param array $atts Gallery attributes
     * @return array
     */
    private function get_attachments($atts): array {
        $id = intval($atts['id']);
        $order = $atts['order'];
        $orderby = ($order === 'RAND') ? 'none' : $atts['orderby'];

        $common_args = [
            'post_status'      => 'inherit',
            'post_type'        => 'attachment',
            'post_mime_type'   => 'image',
            'order'            => $order,
            'orderby'          => $orderby
        ];

        // Get attachments based on include, exclude, or post parent
        if (!empty($atts['include'])) {
            $_attachments = get_posts(array_merge($common_args, [
                'include' => $atts['include']
            ]));

            $attachments = [];
            foreach ($_attachments as $key => $val) {
                $attachments[$val->ID] = $_attachments[$key];
            }

            return $attachments;
        }

        return get_children(array_merge($common_args, [
            'post_parent' => $id
        ]));
    }
}
