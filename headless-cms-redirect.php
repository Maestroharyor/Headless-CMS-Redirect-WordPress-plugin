<?php
/*
Plugin Name: Headless CMS Redirect
Description: Redirect WordPress posts and pages to a specified URL for headless CMS setup
Version: 1.1.0
Author: Ayomide Odewale
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class HeadlessCMSRedirect {
    private $option_group = 'headless_cms_redirect_options';
    private $option_name = 'headless_cms_redirect_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'create_plugin_settings_page']);
        add_action('admin_init', [$this, 'register_plugin_settings']);
        add_action('template_redirect', [$this, 'perform_redirect']);
    }

    public function create_plugin_settings_page() {
        add_options_page(
            'Headless CMS Redirect', 
            'Headless CMS Redirect', 
            'manage_options', 
            'headless-cms-redirect', 
            [$this, 'render_settings_page']
        );
    }

    public function register_plugin_settings() {
        register_setting($this->option_group, $this->option_name);

        add_settings_section(
            'headless_cms_redirect_main', 
            'Redirect Settings', 
            [$this, 'section_callback'], 
            'headless-cms-redirect'
        );

        // Posts Redirect Settings
        add_settings_field(
            'posts_redirect_enabled', 
            'Redirect All Posts', 
            [$this, 'render_posts_redirect_enabled_field'], 
            'headless-cms-redirect', 
            'headless_cms_redirect_main'
        );

        add_settings_field(
            'posts_redirect_url', 
            'Posts Redirect Base URL', 
            [$this, 'render_posts_redirect_url_field'], 
            'headless-cms-redirect', 
            'headless_cms_redirect_main'
        );

        // Pages Redirect Settings
        add_settings_field(
            'pages_redirect_enabled', 
            'Redirect All Pages and Posts', 
            [$this, 'render_pages_redirect_enabled_field'], 
            'headless-cms-redirect', 
            'headless_cms_redirect_main'
        );

        add_settings_field(
            'pages_redirect_url', 
            'Pages and Posts Redirect Base URL', 
            [$this, 'render_pages_redirect_url_field'], 
            'headless-cms-redirect', 
            'headless_cms_redirect_main'
        );

        // Excluded URLs
        add_settings_field(
            'excluded_urls', 
            'Excluded URLs', 
            [$this, 'render_excluded_urls_field'], 
            'headless-cms-redirect', 
            'headless_cms_redirect_main'
        );
    }

    public function section_callback() {
        echo '<p>Configure your headless CMS redirect settings for posts and pages.</p>';
    }

    public function render_posts_redirect_enabled_field() {
        $options = get_option($this->option_name);
        $checked = isset($options['posts_redirect_enabled']) ? checked($options['posts_redirect_enabled'], 1, false) : '';
        echo '<input type="checkbox" name="' . $this->option_name . '[posts_redirect_enabled]" value="1" ' . $checked . ' />';
    }

    public function render_posts_redirect_url_field() {
        $options = get_option($this->option_name);
        $value = isset($options['posts_redirect_url']) ? $options['posts_redirect_url'] : '';
        echo '<input type="url" name="' . $this->option_name . '[posts_redirect_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://newsite.com/posts/" />';
        echo '<p class="description">Base URL for redirecting posts. Include trailing slash.</p>';
    }

    public function render_pages_redirect_enabled_field() {
        $options = get_option($this->option_name);
        $checked = isset($options['pages_redirect_enabled']) ? checked($options['pages_redirect_enabled'], 1, false) : '';
        echo '<input type="checkbox" name="' . $this->option_name . '[pages_redirect_enabled]" value="1" ' . $checked . ' />';
    }

    public function render_pages_redirect_url_field() {
        $options = get_option($this->option_name);
        $value = isset($options['pages_redirect_url']) ? $options['pages_redirect_url'] : '';
        echo '<input type="url" name="' . $this->option_name . '[pages_redirect_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://newsite.com/pages/" />';
        echo '<p class="description">Base URL for redirecting pages. Include trailing slash.</p>';
    }

    public function render_excluded_urls_field() {
        $options = get_option($this->option_name);
        $value = isset($options['excluded_urls']) ? $options['excluded_urls'] : '';
        echo '<textarea name="' . $this->option_name . '[excluded_urls]" rows="5" class="large-text">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">Enter excluded URLs (one per line). Use relative paths without domain.</p>';
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Headless CMS Redirect Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields($this->option_group);
                do_settings_sections('headless-cms-redirect');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function perform_redirect() {
        // Skip admin pages
        if (is_admin()) {
            return;
        }

        $options = get_option($this->option_name);
        
        // Check excluded URLs
        $excluded_urls = isset($options['excluded_urls']) ? explode("\n", trim($options['excluded_urls'])) : [];
        $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Trim and clean excluded URLs
        $excluded_urls = array_map('trim', $excluded_urls);
        
        // Check if current path is in excluded URLs
        if (in_array($current_path, $excluded_urls)) {
            return;
        }

        // Handle Posts Redirect
        if (is_single() && isset($options['posts_redirect_enabled']) && $options['posts_redirect_enabled']) {
            $posts_redirect_url = isset($options['posts_redirect_url']) ? $options['posts_redirect_url'] : '';
            
            if (!empty($posts_redirect_url)) {
                $post = get_queried_object();
                $new_url = rtrim($posts_redirect_url, '/') . $current_path;
                wp_redirect($new_url, 301);
                exit;
            }
        }

        // Handle Pages Redirect
        if (is_page() && isset($options['pages_redirect_enabled']) && $options['pages_redirect_enabled']) {
            $pages_redirect_url = isset($options['pages_redirect_url']) ? $options['pages_redirect_url'] : '';
            
            if (!empty($pages_redirect_url)) {
                $new_url = rtrim($pages_redirect_url, '/') . $current_path;
                wp_redirect($new_url, 301);
                exit;
            }
        }
    }
}

// Initialize the plugin
function headless_cms_redirect_init() {
    new HeadlessCMSRedirect();
}
add_action('plugins_loaded', 'headless_cms_redirect_init');