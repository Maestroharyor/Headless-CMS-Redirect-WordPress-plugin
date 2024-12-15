<?php
/*
Plugin Name: Headless CMS Redirect
Description: Redirect WordPress posts and pages to a specified URL for headless CMS setup
Version: 1.2.0
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
        add_action('template_redirect', [$this, 'perform_redirect'], 1);
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

        // Redirect Type Settings
        $redirect_types = [
            'homepage' => 'Homepage',
            'posts' => 'Posts',
            'pages' => 'Pages',
            'archives' => 'Archives',
            'categories' => 'Categories',
            'tags' => 'Tags'
        ];

        foreach ($redirect_types as $type => $label) {
            // Enabled Checkbox
            add_settings_field(
                $type . '_redirect_enabled', 
                'Redirect ' . $label, 
                [$this, 'render_redirect_enabled_field'], 
                'headless-cms-redirect', 
                'headless_cms_redirect_main',
                ['type' => $type]
            );

            // Redirect URL
            add_settings_field(
                $type . '_redirect_url', 
                $label . ' Redirect Base URL', 
                [$this, 'render_redirect_url_field'], 
                'headless-cms-redirect', 
                'headless_cms_redirect_main',
                ['type' => $type]
            );
        }

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
        echo '<p>Configure your headless CMS redirect settings for different content types.</p>';
    }

    public function render_redirect_enabled_field($args) {
        $options = get_option($this->option_name);
        $type = $args['type'];
        $checked = isset($options[$type . '_redirect_enabled']) ? checked($options[$type . '_redirect_enabled'], 1, false) : '';
        echo '<input type="checkbox" name="' . $this->option_name . '[' . $type . '_redirect_enabled]" value="1" ' . $checked . ' />';
    }

    public function render_redirect_url_field($args) {
        $options = get_option($this->option_name);
        $type = $args['type'];
        $value = isset($options[$type . '_redirect_url']) ? $options[$type . '_redirect_url'] : '';
        echo '<input type="url" name="' . $this->option_name . '[' . $type . '_redirect_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://newsite.com/' . $type . '/" />';
        echo '<p class="description">Base URL for redirecting ' . $type . '. Include trailing slash.</p>';
    }

public function render_excluded_urls_field() {
    $options = get_option($this->option_name);
    $value = isset($options['excluded_urls']) ? $options['excluded_urls'] : '';
    echo '<textarea name="' . $this->option_name . '[excluded_urls]" rows="8" class="large-text">' . esc_textarea($value) . '</textarea>';
    echo '<p class="description">Enter excluded URLs (one per line). Use relative paths without domain.</p>';
    echo '<p class="description"><strong>Examples:</strong></p>';
    echo '<ul class="description" style="list-style-type: disc; margin-left: 20px;">';
    echo '<li><code>/wp-admin/</code> - Excludes all WordPress admin pages</li>';
    echo '<li><code>/login/</code> - Excludes login page</li>';
    echo '<li><code>/my-special-page/</code> - Excludes a specific page from redirection</li>';
    echo '<li><code>/contact-us/</code> - Prevents redirection for contact page</li>';
    echo '<li><code>/search/</code> - Keeps search results on the current site</li>';
    echo '<li><code>/feed/</code> - Prevents redirection of RSS feeds</li>';
    echo '</ul>';
    echo '<p class="description"><strong>Quick Tips:</strong></p>';
    echo '<ul class="description" style="list-style-type: disc; margin-left: 20px;">';
    echo '<li>Use full path starting with "/"</li>';
    echo '<li>Do not include domain name</li>';
    echo '<li>Each URL should be on a new line</li>';
    echo '<li>Paths are matched exactly, so "/contact" and "/contact/" are different</li>';
    echo '</ul>';
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

        // Redirect mapping
        $redirect_types = [
            'homepage' => ['check' => 'is_home', 'path' => '/'],
            'posts' => ['check' => 'is_single', 'path' => $current_path],
            'pages' => ['check' => 'is_page', 'path' => $current_path],
            'archives' => ['check' => 'is_archive', 'path' => $current_path],
            'categories' => ['check' => 'is_category', 'path' => $current_path],
            'tags' => ['check' => 'is_tag', 'path' => $current_path]
        ];

        foreach ($redirect_types as $type => $config) {
            // Check if redirect is enabled for this type
            if (isset($options[$type . '_redirect_enabled']) && $options[$type . '_redirect_enabled']) {
                $redirect_url = isset($options[$type . '_redirect_url']) ? $options[$type . '_redirect_url'] : '';
                
                // Dynamically check page type using function from config
                $check_function = $config['check'];
                if (function_exists($check_function) && $check_function()) {
                    if (!empty($redirect_url)) {
                        // For homepage, use the base URL directly
                        $new_url = ($type === 'homepage') 
                            ? rtrim($redirect_url, '/') 
                            : rtrim($redirect_url, '/') . $config['path'];
                        
                        wp_redirect($new_url, 301);
                        exit;
                    }
                }
            }
        }
    }
}

// Initialize the plugin
function headless_cms_redirect_init() {
    new HeadlessCMSRedirect();
}
add_action('plugins_loaded', 'headless_cms_redirect_init');