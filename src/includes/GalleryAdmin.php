<?php

namespace MSO_Gallery;
use WP_User;

class GalleryAdmin {

    /**
     * Admin page hook suffix
     *
     * @var string|false
     */
    private $admin_page_hook = false;


    public function register(): void {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);


        add_action('media_buttons', [$this, 'add_mso_gallery_media_button'], 20); // Priorité
    }

    public function add_admin_menu(): void {
        $this->admin_page_hook =
            add_menu_page(
            __('MSO Gallery', 'mso-gallery'),
            __('MSO Gallery', 'mso-gallery'),
            'manage_options',
            'mso-gallery',
            [$this, 'render_admin_page'],
            'dashicons-format-gallery',
                25
        );

        // Hook the save handler to the load action for this specific page
        // This ensures it runs before headers are sent.
        if ($this->admin_page_hook) {
            add_action('load-' . $this->admin_page_hook, [$this, 'handle_editor_preference_save']);
        }
    }

    /**
     * Ajoute le bouton "Ajouter MSO Gallery" à côté du bouton "Ajouter un média".
     * S'affiche uniquement si l'éditeur classique est utilisé.
     *
     * @return void
     */
    public function add_mso_gallery_media_button(): void {
        // Vérifier si on est sur un écran d'édition de post/page
        // et si l'éditeur de blocs N'EST PAS actif
        if ( function_exists('get_current_screen') ) {
            $screen = get_current_screen();
            // Ne pas afficher si $screen n'est pas défini, si ce n'est pas un écran de post,
            if ( ! $screen || !in_array($screen->base, ['post']) || (method_exists($screen, 'is_block_editor') && $screen->is_block_editor()) ) {
                return;
            }
        } else {
        // Fallback si get_current_screen n'existe pas (très peu probable)
    return;
    }

    // ID unique pour le bouton
    $button_id = 'mso-insert-gallery-button';
    // Texte du bouton
    $button_text = esc_html__('Add MSO Gallery', 'mso-gallery');
    // Icône (optionnel)
    $icon = '<span class="dashicons dashicons-format-gallery" style="vertical-align: text-bottom; margin-right: 5px;"></span>';

    // Afficher le bouton HTML
    printf(
        '<button type="button" id="%s" class="button">%s%s</button>',
        esc_attr($button_id),
        $icon, // L'icône n'est pas échappée car c'est du HTML sûr génér$button_text)
        $button_text
    );
}



/**
     * Handles saving the user's editor preference.
     * Hooked to load-{page} action.
     *
     * @return void
     */
    public function handle_editor_preference_save(): void {
        // Check if the form was submitted
        if (!isset($_POST['mso_editor_preference_nonce'], $_POST['mso_editor_preference'])) {
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['mso_editor_preference_nonce'], 'mso_save_editor_preference')) {
            wp_die(__('Security check failed!', 'mso-gallery'));
        }

        // Check if user switching is allowed by the site admin
        if ('allow' !== get_option('classic-editor-allow-users', 'disallow')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . esc_html__('Site administrator does not allow users to switch editors.', 'mso-gallery') . '</p></div>';
            });
            return;
        }

        // Sanitize the input value
        $preference = sanitize_key($_POST['mso_editor_preference']);
        if (!in_array($preference, ['block', 'classic'])) {
            $preference = 'block'; // Default to block if invalid value
        }

        // Get current user ID
        $user_id = get_current_user_id();
        if (!$user_id) {
            return; // Should not happen on an admin page, but check anyway
        }

        // Update user meta
        $updated = update_user_meta($user_id, 'classic-editor-settings', $preference);

        // Add an admin notice on success or failure (optional)
        if ($updated) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Your editor preference has been saved.', 'mso-gallery') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Could not save editor preference or value unchanged.', 'mso-gallery') . '</p></div>';
            });
        }

        // Redirect back to the same page to prevent form resubmission on refresh
        // wp_safe_redirect(wp_get_referer()); // wp_get_referer can be unreliable
        wp_safe_redirect(remove_query_arg(['_wpnonce', 'action'], wp_unslash($_SERVER['REQUEST_URI']))); // More reliable redirect
        exit;
    }


    /**
     * Render the admin page content
     *
     * @return void
     */
    public function render_admin_page(): void
    {
        $current_user = wp_get_current_user();
        $allow_user_switch = ('allow' === get_option('classic-editor-allow-users', 'disallow'));
        $current_preference = $this->mso_get_user_or_site_editor_preference($current_user);
        $editor_to_use = str_replace('user-', '', $current_preference); // Get 'block' or 'classic'
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('MSO Gallery Shortcode Generator', 'mso-gallery'); ?></h1>
            <p><?php esc_html_e('Select images from the media library to generate the gallery shortcode.', 'mso-gallery'); ?></p>

            <?php $this->render_gallery_interface(); ?>

            <?php $this->render_editor_preference_notice($allow_user_switch, $current_preference, $current_user, $editor_to_use); ?>
        </div>
        <?php
    }

    /**
     * Render the gallery selection UI
     *
     * @return void
     */
    private function render_gallery_interface(): void
    {
        ?>
        <div id="mso-gallery-admin-container">
            <div id="mso-gallery-selected-images" style="margin-bottom: 20px; min-height: 100px; border: 1px dashed #ccc; padding: 10px; background: #f9f9f9;">
                <span class="placeholder" style="color: #aaa;"><?php esc_html_e('No images selected yet.', 'mso-gallery'); ?></span>
            </div>

            <button id="mso-select-images-button" class="button button-primary">
                <?php esc_html_e('Select Images', 'mso-gallery'); ?>
            </button>

            <div id="mso-gallery-shortcode-output" style="margin-top: 20px;">
                <h2><?php esc_html_e('Generated Shortcode', 'mso-gallery'); ?></h2>
                <p><?php esc_html_e('Copy and paste this shortcode into your post or page:', 'mso-gallery'); ?></p>
                <textarea id="mso-generated-shortcode" rows="2" style="width: 100%; background: #eee;" readonly></textarea>
            </div>
        </div>
        <?php
    }

    /**
     * Render the editor preference notice
     *
     * @param bool $allow_user_switch
     * @param string $current_preference
     * @param WP_User  $current_user
     * @param string $editor_to_use
     *
     * @return void
     */
    private function render_editor_preference_notice(bool $allow_user_switch, string $current_preference, WP_User $current_user, string $editor_to_use): void
    {
        if ( ! is_plugin_active('classic-editor/classic-editor.php') ) {
            $this->render_notice('warning', __('The "Classic Editor" plugin is not active. Editor preference settings require this plugin.', 'mso-gallery'));
            return;
        }

        if ( ! $allow_user_switch ) {
            ?>
            <div class="notice notice-info">
                <p><?php esc_html_e('The site administrator has not enabled the option for users to choose their editor.', 'mso-gallery'); ?></p>
                <p>
                    <?php
                    printf(
                        esc_html__('The site default editor is set to: %s', 'mso-gallery'),
                        '<strong>' . esc_html(
                            ($current_preference === 'classic')
                                ? __('the classic editor', 'mso-gallery')
                                : __('the block editor (Gutenberg)', 'mso-gallery')
                        ) . '</strong>'
                    );
                    ?>
                </p>
            </div>
            <?php
            return;
        }

        ?>
        <hr style="margin: 30px 0;">

        <h2><?php esc_html_e('Editor Preference', 'mso-gallery'); ?></h2>
        <p><?php esc_html_e('Choose the editor you prefer to use when creating or editing posts and pages.', 'mso-gallery'); ?></p>

        <form method="post" action="">
            <?php wp_nonce_field('mso_save_editor_preference', 'mso_editor_preference_nonce'); ?>
            <fieldset>
                <legend class="screen-reader-text">
                    <span><?php esc_html_e('Editor Preference', 'mso-gallery'); ?></span>
                </legend>
                <label>
                    <input type="radio" name="mso_editor_preference" value="block" <?php checked($editor_to_use, 'block'); ?>>
                    <?php esc_html_e('Block Editor (Gutenberg)', 'mso-gallery'); ?>
                </label>
                <br>
                <label>
                    <input type="radio" name="mso_editor_preference" value="classic" <?php checked($editor_to_use, 'classic'); ?>>
                    <?php esc_html_e('Classic Editor', 'mso-gallery'); ?>
                </label>
            </fieldset>
            <?php submit_button(__('Save Preference', 'mso-gallery')); ?>
        </form>

        <?php
        $this->render_user_editor_notice($current_preference, $current_user);
    }

    /**
     * Render a simple notice
     *
     * @param string $type
     * @param string $message
     *
     * @return void
     */
    private function render_notice(string $type, string $message): void
    {
        printf(
            '<div class="notice notice-%1$s"><p>%2$s</p></div>',
            esc_attr($type),
            esc_html($message)
        );
    }

    /**
     * Render the user-specific editor preference notice
     *
     * @param string $current_preference
     * @param WP_User $current_user
     *
     * @return void
     */
    private function render_user_editor_notice(string $current_preference, WP_User $current_user): void
    {
        $user_mane = $current_user->display_name;
        $message = match ($current_preference) {
            'user-block' => sprintf(esc_html__('The block editor (Gutenberg) is active for user %s.', 'mso-gallery'), $user_mane),
            'user-classic' => sprintf(esc_html__('The classic editor is active for user %s.', 'mso-gallery'), $user_mane),
            default => sprintf(
                __('The WordPress site uses %s by default.', 'mso-gallery'),
                ($current_preference === 'classic')
                    ? __('the classic editor', 'mso-gallery')
                    : __('the block editor (Gutenberg)', 'mso-gallery')
            ),
        };

        printf(
            '<div class="notice notice-info"><p>%s</p></div>',
            esc_html($message)
        );
    }

    /**
     * Enqueue scripts and styles for the admin page
     *
     * @param string $hook_suffix The current admin page hook.
     * @return void
     */
    public function enqueue_admin_assets(string $hook_suffix): void {
        // Only load on our specific admin page
        if ($this->admin_page_hook === $hook_suffix) {

            // Enqueue WordPress media scripts
            wp_enqueue_media();

            // Enqueue jQuery UI Sortable for reordering
            wp_enqueue_script('jquery-ui-sortable');

            // You can add a specific admin CSS file if needed
            wp_enqueue_style('mso-gallery-admin', plugins_url('../assets/css/mso-gallery-admin.css', __FILE__), [], MSO_Gallery::VERSION);

            // Enqueue a specific admin JS file
            wp_enqueue_script(
                'mso-gallery-admin',
                plugins_url('../assets/js/mso-gallery-admin.js', __FILE__),
                ['jquery', 'wp-mediaelement', 'jquery-ui-sortable'], // Dependencies
                MSO_Gallery::VERSION,
                true
            );

            // Localize script to pass translation strings or other data
            wp_localize_script('mso-gallery-admin', 'msoGalleryAdmin', [
                'title' => __('Select or Upload Gallery Images', 'mso-gallery'),
                'button' => __('Use these images', 'mso-gallery'),
            ]);
        }

        if (in_array($hook_suffix, ['post.php', 'post-new.php'])) {
            // Vérifier si l'éditeur de blocs n'est PAS actif
            if ( function_exists('get_current_screen') ) {
                $screen = get_current_screen();
                if ( !$screen || (method_exists($screen, 'is_block_editor') && $screen->is_block_editor()) ) {
                    return; // Ne pas charger si c'est l'éditeur de blocs
                }
            } else {
                return; // Ne pas charger si on ne peut pas vérifier
            }

            // On a besoin de wp.media pour ouvrir la médiathèque
            wp_enqueue_media();

            // Enqueue le script spécifique pour le bouton de l'éditeur classique
            wp_enqueue_script(
                'mso-gallery-classic-button',
                plugins_url('../assets/js/mso-gallery-classic-button.js', __FILE__),
                ['jquery', 'media-editor'], // media-editor inclut wp.media et les fonctions d'insertion
                MSO_Gallery::VERSION,
                true
            );

            // Localiser les textes pour ce script
            wp_localize_script('mso-gallery-classic-button', 'msoGalleryClassic', [
                'title' => __('Select Images for MSO Gallery', 'mso-gallery'),
                'button' => __('Insert MSO Gallery Shortcode', 'mso-gallery'),
            ]);
        }
    }

    /**
     * Tente de déterminer l'éditeur préféré d'un utilisateur ou le défaut du site.
     *
     * @param WP_User|null $current_user L'ID de l'utilisateur. Si null, utilise l'utilisateur courant.
     * @return string 'block', 'classic'.
     */
    public function mso_get_user_or_site_editor_preference(?WP_User $current_user = null): string {
        // D'abord, récupérer le défaut du site
        $user_id = $current_user->ID;
        $site_default = get_option('classic-editor-replace', 'block');
        // S'assurer que c'est 'block' ou 'classic'
        $site_default = ('classic' === $site_default) ? 'classic' : 'block';

        // Vérifier si les utilisateurs peuvent choisir leur propre éditeur
        $allow_users_to_switch = get_option('classic-editor-allow-users', 'disallow');

        if ('allow' !== $allow_users_to_switch) {
            // Les utilisateurs ne peuvent pas choisir, on retourne le défaut du site
            return $site_default;
        }

        // Obtenir l'ID de l'utilisateur courant si non fourni
        if (null === $user_id ) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            // Pas d'utilisateur connecté, retourner le défaut du site
            return $site_default;
        }

        // R préférence de l'utilisateur
        // La méta est 'classic-editor-settings', pas 'wp_classic-editor-settings'
        $user_preference = get_user_meta($user_id, 'classic-editor-settings', true);

        if ('block' === $user_preference) {
            return 'user-block';
        } elseif ('classic' === $user_preference) {
            return 'user-classic';
        } else {
            // L'utilisateur n'a pas défini de préférence, on retourne le défaut du site
            return $site_default;
        }
    }
}
