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


/**
 * Autoload function registered with spl_autoload_register.
 *
 * This function is called by PHP whenever a class or interface from the
 * MSO_Gallery namespace is used for the first time and hasn't
 * been loaded yet. It maps the namespace structure to the directory structure
 * within the 'includes' folder.
 *
 * @param string $class The fully qualified class name.
 */
spl_autoload_register(function ($class) {
    $prefix = 'MSO_Gallery\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $base_dir = __DIR__ .'\includes' . DIRECTORY_SEPARATOR;
    $file = $base_dir . str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';

    if (file_exists($file) && is_readable($file)) {
        require_once $file;
    }
});


/**
 * Main plugin class (MSO_Gallery).
 *
 * Initializes and coordinates different plugin components like Admin, Frontend, AJAX, Settings, etc.
 * Implements the Singleton pattern to ensure only one instance exists.
 *
 * @package MSO_Gallery
 * @since   1.0.0
 */
final class MSO_Gallery
{
    /** Plugin version number. Used for cache busting scripts/styles. */
    public const string VERSION = '1.0.0';

    /** Text domain for localization (internationalization). Must match plugin header and .pot file. */
    public const string TEXT_DOMAIN = 'mso-gallery';

    /** Holds the single instance of this class (Singleton pattern). */
    private static ?MSO_Gallery $instance = null;


    /** Instance of the MSOGallery class, handling admin-specific functionality. */
    private MSOGallery $gallery;

    /**
     * Private constructor to prevent direct instantiation (Singleton pattern).
     * Use `get_instance()` to get the object.
     */
    private function __construct()
    {

    }

    /**
     * Get the singleton instance of the plugin.
     *
     * Creates the instance on the first call and runs the setup method.
     * Subsequent calls return the existing instance.
     *
     * @return MSO_Gallery The single instance of the main plugin class.
     */
    public static function get_instance(): MSO_Gallery
    {
        if (null === self::$instance) {
            self::$instance = new self();
            self::$instance->setup();
        }

        return self::$instance;
    }

    /**
     * Set up the plugin: load dependencies, instantiate components, register hooks.
     * This method is called only once when the singleton instance is created.
     */
    private function setup(): void
    {
        $this->instantiate_components();
    }

    /**
     * Instantiate plugin components (classes).
     * Creates objects for handling different parts of the plugin's functionality.
     */
    private function instantiate_components(): void
    {
        $this->gallery = new MSOGallery();
    }
}

/**
 * Begins execution of the plugin.
 *
 * This function simply retrieves the singleton instance of the main plugin class.
 * The `get_instance()` method handles the actual setup and hook registration
 * if it's the first time it's being called.
 */
function mso_gallery_run(): void
{
    MSO_Gallery::get_instance();
}

mso_gallery_run();

//add_action('plugins_loaded', [MSO_Gallery::class, 'init']);