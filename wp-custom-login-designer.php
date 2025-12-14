<?php
/**
 * Plugin Name: WP Custom Login Designer
 * Plugin URI: https://yourwebsite.com/
 * Description: Completely customize your WordPress login page with custom backgrounds, colors, logos, and styles
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com/
 * License: GPL v2 or later
 * Text Domain: wp-custom-login-designer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WCLD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WCLD_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WCLD_PLUGIN_VERSION', '1.0.0');

// Main plugin class
class WP_Custom_Login_Designer {
    
    private static $instance = null;
    private $options;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Login page hooks - FIXED: Load options in the method itself
        add_action('login_enqueue_scripts', array($this, 'custom_login_styles'));
        add_filter('login_headerurl', array($this, 'custom_login_logo_url'));
        add_filter('login_headertext', array($this, 'custom_login_logo_url_title'));
        
        // AJAX handlers
        add_action('wp_ajax_wcld_save_settings', array($this, 'ajax_save_settings'));
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Custom Login Designer',
            'Login Designer',
            'manage_options',
            'wp-custom-login-designer',
            array($this, 'admin_page'),
            'dashicons-art',
            100
        );
    }
    
    public function register_settings() {
        register_setting('wcld_settings_group', 'wcld_settings');
    }
    
    public function admin_enqueue_scripts($hook) {
        if ('toplevel_page_wp-custom-login-designer' !== $hook) {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        wp_localize_script('jquery', 'wcld_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcld_nonce')
        ));
    }
    
    public function admin_page() {
        // FIXED: Get fresh options
        $this->options = get_option('wcld_settings', array());
        ?>
        <div class="wrap wcld-admin-wrap">
            <h1><span class="dashicons dashicons-art"></span> WordPress Login Designer</h1>
            
            <div class="wcld-settings-container">
                <div class="wcld-tabs">
                    <ul class="wcld-tab-links">
                        <li class="active"><a href="#general" data-tab="general">General</a></li>
                        <li><a href="#background" data-tab="background">Background</a></li>
                        <li><a href="#form" data-tab="form">Form Styling</a></li>
                        <li><a href="#buttons" data-tab="buttons">Buttons</a></li>
                        <li><a href="#custom-css" data-tab="custom-css">Custom CSS</a></li>
                        <li><a href="#preview" data-tab="preview">Live Preview</a></li>
                    </ul>
                    
                    <form method="post" action="options.php" id="wcld-settings-form">
                        <?php settings_fields('wcld_settings_group'); ?>
                        
                        <!-- General Tab -->
                        <div id="general" class="wcld-tab-content active">
                            <h2>General Settings</h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Enable Custom Login</th>
                                    <td>
                                        <label class="wcld-switch">
                                            <input type="checkbox" name="wcld_settings[enable]" value="1" 
                                                <?php checked(isset($this->options['enable']) ? $this->options['enable'] : 0, 1); ?>>
                                            <span class="wcld-slider"></span>
                                        </label>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Custom Logo</th>
                                    <td>
                                        <div class="wcld-image-upload">
                                            <input type="text" name="wcld_settings[logo_url]" id="logo_url" class="regular-text"
                                                value="<?php echo isset($this->options['logo_url']) ? esc_attr($this->options['logo_url']) : ''; ?>">
                                            <button type="button" class="button wcld-upload-btn" data-target="logo_url">Upload Logo</button>
                                            <button type="button" class="button wcld-remove-btn" data-target="logo_url">Remove</button>
                                            <div class="wcld-preview-image" id="logo_url_preview">
                                                <?php if (!empty($this->options['logo_url'])): ?>
                                                    <img src="<?php echo esc_url($this->options['logo_url']); ?>" style="max-width: 200px;">
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Logo Width</th>
                                    <td>
                                        <input type="number" name="wcld_settings[logo_width]" 
                                            value="<?php echo isset($this->options['logo_width']) ? esc_attr($this->options['logo_width']) : '84'; ?>" min="50" max="500">
                                        <span class="description">px</span>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Logo Height</th>
                                    <td>
                                        <input type="number" name="wcld_settings[logo_height]" 
                                            value="<?php echo isset($this->options['logo_height']) ? esc_attr($this->options['logo_height']) : '84'; ?>" min="50" max="500">
                                        <span class="description">px</span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Background Tab -->
                        <div id="background" class="wcld-tab-content">
                            <h2>Background Settings</h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Background Type</th>
                                    <td>
                                        <select name="wcld_settings[bg_type]" id="bg_type">
                                            <option value="color" <?php selected(isset($this->options['bg_type']) ? $this->options['bg_type'] : 'color', 'color'); ?>>Solid Color</option>
                                            <option value="gradient" <?php selected(isset($this->options['bg_type']) ? $this->options['bg_type'] : '', 'gradient'); ?>>Gradient</option>
                                            <option value="image" <?php selected(isset($this->options['bg_type']) ? $this->options['bg_type'] : '', 'image'); ?>>Image</option>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr class="bg-color-row">
                                    <th scope="row">Background Color</th>
                                    <td>
                                        <input type="text" name="wcld_settings[bg_color]" class="wcld-color-picker" 
                                            value="<?php echo isset($this->options['bg_color']) ? esc_attr($this->options['bg_color']) : '#f1f1f1'; ?>">
                                    </td>
                                </tr>
                                
                                <tr class="bg-gradient-row" style="display:none;">
                                    <th scope="row">Gradient Start Color</th>
                                    <td>
                                        <input type="text" name="wcld_settings[gradient_start]" class="wcld-color-picker" 
                                            value="<?php echo isset($this->options['gradient_start']) ? esc_attr($this->options['gradient_start']) : '#667eea'; ?>">
                                    </td>
                                </tr>
                                
                                <tr class="bg-gradient-row" style="display:none;">
                                    <th scope="row">Gradient End Color</th>
                                    <td>
                                        <input type="text" name="wcld_settings[gradient_end]" class="wcld-color-picker" 
                                            value="<?php echo isset($this->options['gradient_end']) ? esc_attr($this->options['gradient_end']) : '#764ba2'; ?>">
                                    </td>
                                </tr>
                                
                                <tr class="bg-gradient-row" style="display:none;">
                                    <th scope="row">Gradient Direction</th>
                                    <td>
                                        <select name="wcld_settings[gradient_direction]">
                                            <option value="135deg" <?php selected(isset($this->options['gradient_direction']) ? $this->options['gradient_direction'] : '135deg', '135deg'); ?>>Diagonal</option>
                                            <option value="90deg" <?php selected(isset($this->options['gradient_direction']) ? $this->options['gradient_direction'] : '', '90deg'); ?>>Horizontal</option>
                                            <option value="180deg" <?php selected(isset($this->options['gradient_direction']) ? $this->options['gradient_direction'] : '', '180deg'); ?>>Vertical</option>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr class="bg-image-row" style="display:none;">
                                    <th scope="row">Background Image</th>
                                    <td>
                                        <div class="wcld-image-upload">
                                            <input type="text" name="wcld_settings[bg_image]" id="bg_image" class="regular-text"
                                                value="<?php echo isset($this->options['bg_image']) ? esc_attr($this->options['bg_image']) : ''; ?>">
                                            <button type="button" class="button wcld-upload-btn" data-target="bg_image">Upload Image</button>
                                            <button type="button" class="button wcld-remove-btn" data-target="bg_image">Remove</button>
                                            <div class="wcld-preview-image" id="bg_image_preview">
                                                <?php if (!empty($this->options['bg_image'])): ?>
                                                    <img src="<?php echo esc_url($this->options['bg_image']); ?>" style="max-width: 300px;">
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                
                                <tr class="bg-image-row" style="display:none;">
                                    <th scope="row">Background Size</th>
                                    <td>
                                        <select name="wcld_settings[bg_size]">
                                            <option value="cover" <?php selected(isset($this->options['bg_size']) ? $this->options['bg_size'] : 'cover', 'cover'); ?>>Cover</option>
                                            <option value="contain" <?php selected(isset($this->options['bg_size']) ? $this->options['bg_size'] : '', 'contain'); ?>>Contain</option>
                                            <option value="auto" <?php selected(isset($this->options['bg_size']) ? $this->options['bg_size'] : '', 'auto'); ?>>Auto</option>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr class="bg-image-row" style="display:none;">
                                    <th scope="row">Background Position</th>
                                    <td>
                                        <select name="wcld_settings[bg_position]">
                                            <option value="center center" <?php selected(isset($this->options['bg_position']) ? $this->options['bg_position'] : 'center center', 'center center'); ?>>Center</option>
                                            <option value="top center" <?php selected(isset($this->options['bg_position']) ? $this->options['bg_position'] : '', 'top center'); ?>>Top Center</option>
                                            <option value="bottom center" <?php selected(isset($this->options['bg_position']) ? $this->options['bg_position'] : '', 'bottom center'); ?>>Bottom Center</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Form Styling Tab -->
                        <div id="form" class="wcld-tab-content">
                            <h2>Form Styling</h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Form Background Color</th>
                                    <td>
                                        <input type="text" name="wcld_settings[form_bg]" class="wcld-color-picker" 
                                            value="<?php echo isset($this->options['form_bg']) ? esc_attr($this->options['form_bg']) : '#ffffff'; ?>">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Form Padding</th>
                                    <td>
                                        <input type="number" name="wcld_settings[form_padding]" 
                                            value="<?php echo isset($this->options['form_padding']) ? esc_attr($this->options['form_padding']) : '24'; ?>" min="0" max="100">
                                        <span class="description">px</span>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Form Border Radius</th>
                                    <td>
                                        <input type="number" name="wcld_settings[form_radius]" 
                                            value="<?php echo isset($this->options['form_radius']) ? esc_attr($this->options['form_radius']) : '4'; ?>" min="0" max="50">
                                        <span class="description">px</span>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Form Shadow</th>
                                    <td>
                                        <label class="wcld-switch">
                                            <input type="checkbox" name="wcld_settings[form_shadow]" value="1" 
                                                <?php checked(isset($this->options['form_shadow']) ? $this->options['form_shadow'] : 1, 1); ?>>
                                            <span class="wcld-slider"></span>
                                        </label>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Input Field Background</th>
                                    <td>
                                        <input type="text" name="wcld_settings[input_bg]" class="wcld-color-picker" 
                                            value="<?php echo isset($this->options['input_bg']) ? esc_attr($this->options['input_bg']) : '#ffffff'; ?>">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Input Text Color</th>
                                    <td>
                                        <input type="text" name="wcld_settings[input_text_color]" class="wcld-color-picker" 
                                            value="<?php echo isset($this->options['input_text_color']) ? esc_attr($this->options['input_text_color']) : '#333333'; ?>">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Input Border Color</th>
                                    <td>
                                        <input type="text" name="wcld_settings[input_border]" class="wcld-color-picker" 
                                            value="<?php echo isset($this->options['input_border']) ? esc_attr($this->options['input_border']) : '#dddddd'; ?>">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Label Color</th>
                                    <td>
                                        <input type="text" name="wcld_settings[label_color]" class="wcld-color-picker" 
                                            value="<?php echo isset($this->options['label_color']) ? esc_attr($this->options['label_color']) : '#444444'; ?>">
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Buttons Tab -->
                        <div id="buttons" class="wcld-tab-content">
                            <h2>Button Styling</h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Button Background Color</th>
                                    <td>
                                        <input type="text" name="wcld_settings[btn_bg]" class="wcld-color-picker" 
                                            value="<?php echo isset($this->options['btn_bg']) ? esc_attr($this->options['btn_bg']) : '#007cba'; ?>">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Button Text Color</th>
                                    <td>
                                        <input type="text" name="wcld_settings[btn_text]" class="wcld-color-picker" 
                                            value="<?php echo isset($this->options['btn_text']) ? esc_attr($this->options['btn_text']) : '#ffffff'; ?>">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Button Hover Background</th>
                                    <td>
                                        <input type="text" name="wcld_settings[btn_hover_bg]" class="wcld-color-picker" 
                                            value="<?php echo isset($this->options['btn_hover_bg']) ? esc_attr($this->options['btn_hover_bg']) : '#005a87'; ?>">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Button Border Radius</th>
                                    <td>
                                        <input type="number" name="wcld_settings[btn_radius]" 
                                            value="<?php echo isset($this->options['btn_radius']) ? esc_attr($this->options['btn_radius']) : '3'; ?>" min="0" max="50">
                                        <span class="description">px</span>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Link Color</th>
                                    <td>
                                        <input type="text" name="wcld_settings[link_color]" class="wcld-color-picker" 
                                            value="<?php echo isset($this->options['link_color']) ? esc_attr($this->options['link_color']) : '#007cba'; ?>">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Link Hover Color</th>
                                    <td>
                                        <input type="text" name="wcld_settings[link_hover]" class="wcld-color-picker" 
                                            value="<?php echo isset($this->options['link_hover']) ? esc_attr($this->options['link_hover']) : '#005a87'; ?>">
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Custom CSS Tab -->
                        <div id="custom-css" class="wcld-tab-content">
                            <h2>Custom CSS</h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Additional CSS</th>
                                    <td>
                                        <textarea name="wcld_settings[custom_css]" rows="15" cols="70" class="large-text code"><?php 
                                            echo isset($this->options['custom_css']) ? esc_textarea($this->options['custom_css']) : ''; 
                                        ?></textarea>
                                        <p class="description">Add your custom CSS here. It will be applied to the login page.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Preview Tab -->
                        <div id="preview" class="wcld-tab-content">
                            <h2>Live Preview</h2>
                            <div class="wcld-preview-container">
                                <iframe src="<?php echo wp_login_url(); ?>" class="wcld-preview-iframe"></iframe>
                                <p class="description">
                                    <a href="<?php echo wp_login_url(); ?>" target="_blank" class="button">Open Login Page in New Tab</a>
                                </p>
                            </div>
                        </div>
                        
                        <?php submit_button('Save Settings'); ?>
                    </form>
                </div>
            </div>
        </div>
        
        <style>
            .wcld-admin-wrap {
                max-width: 1200px;
                margin: 20px auto;
            }
            
            .wcld-admin-wrap h1 {
                font-size: 2em;
                margin-bottom: 30px;
            }
            
            .wcld-settings-container {
                background: #fff;
                border-radius: 5px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                padding: 20px;
            }
            
            .wcld-tabs {
                position: relative;
            }
            
            .wcld-tab-links {
                display: flex;
                list-style: none;
                margin: 0;
                padding: 0;
                border-bottom: 2px solid #ddd;
            }
            
            .wcld-tab-links li {
                margin: 0;
            }
            
            .wcld-tab-links a {
                display: block;
                padding: 15px 20px;
                text-decoration: none;
                color: #666;
                font-weight: 500;
                transition: all 0.3s;
                border-bottom: 3px solid transparent;
                margin-bottom: -2px;
            }
            
            .wcld-tab-links li.active a {
                color: #007cba;
                border-bottom-color: #007cba;
            }
            
            .wcld-tab-content {
                display: none;
                padding: 30px 0;
            }
            
            .wcld-tab-content.active {
                display: block;
            }
            
            .wcld-switch {
                position: relative;
                display: inline-block;
                width: 60px;
                height: 30px;
            }
            
            .wcld-switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }
            
            .wcld-slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #ccc;
                transition: .4s;
                border-radius: 30px;
            }
            
            .wcld-slider:before {
                position: absolute;
                content: "";
                height: 22px;
                width: 22px;
                left: 4px;
                bottom: 4px;
                background-color: white;
                transition: .4s;
                border-radius: 50%;
            }
            
            .wcld-switch input:checked + .wcld-slider {
                background-color: #007cba;
            }
            
            .wcld-switch input:checked + .wcld-slider:before {
                transform: translateX(30px);
            }
            
            .wcld-preview-image {
                margin-top: 10px;
            }
            
            .wcld-preview-image img {
                max-width: 100%;
                height: auto;
                border: 1px solid #ddd;
                padding: 5px;
                background: #fff;
                border-radius: 3px;
            }
            
            .wcld-preview-container {
                background: #f5f5f5;
                padding: 20px;
                border-radius: 5px;
                min-height: 600px;
                position: relative;
            }
            
            .wcld-preview-iframe {
                width: 100%;
                height: 600px;
                border: 1px solid #ddd;
                border-radius: 5px;
                background: #fff;
            }
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Initialize color pickers
            $('.wcld-color-picker').wpColorPicker();
            
            // Tab switching
            $('.wcld-tab-links a').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                
                $('.wcld-tab-links li').removeClass('active');
                $(this).parent().addClass('active');
                
                $('.wcld-tab-content').removeClass('active');
                $('#' + tab).addClass('active');
            });
            
            // Background type toggle
            $('#bg_type').on('change', function() {
                var type = $(this).val();
                $('.bg-color-row, .bg-gradient-row, .bg-image-row').hide();
                
                if (type === 'color') {
                    $('.bg-color-row').show();
                } else if (type === 'gradient') {
                    $('.bg-gradient-row').show();
                } else if (type === 'image') {
                    $('.bg-image-row').show();
                }
            }).trigger('change');
            
            // Media uploader
            $('.wcld-upload-btn').on('click', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var targetInput = $('#' + button.data('target'));
                var previewContainer = $('#' + button.data('target') + '_preview');
                
                var mediaUploader = wp.media({
                    title: 'Choose Image',
                    button: {
                        text: 'Use this image'
                    },
                    multiple: false
                }).on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    targetInput.val(attachment.url);
                    previewContainer.html('<img src="' + attachment.url + '" style="max-width: 300px;">');
                }).open();
            });
            
            // Remove image
            $('.wcld-remove-btn').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var targetInput = $('#' + button.data('target'));
                var previewContainer = $('#' + button.data('target') + '_preview');
                
                targetInput.val('');
                previewContainer.html('');
            });
        });
        </script>
        <?php
    }
    
    public function custom_login_styles() {
        // FIXED: Load options fresh
        $this->options = get_option('wcld_settings', array());
        
        // FIXED: Check if enabled, default to true if not set
        if (!isset($this->options['enable']) || !$this->options['enable']) {
            return;
        }
        
        ?>
        <style type="text/css">
            /* Background Styles */
            body.login {
                <?php if (isset($this->options['bg_type']) && $this->options['bg_type'] === 'color'): ?>
                    background-color: <?php echo esc_attr($this->options['bg_color'] ?? '#f1f1f1'); ?> !important;
                <?php elseif (isset($this->options['bg_type']) && $this->options['bg_type'] === 'gradient'): ?>
                    background: linear-gradient(<?php echo esc_attr($this->options['gradient_direction'] ?? '135deg'); ?>, 
                        <?php echo esc_attr($this->options['gradient_start'] ?? '#667eea'); ?>, 
                        <?php echo esc_attr($this->options['gradient_end'] ?? '#764ba2'); ?>) !important;
                <?php elseif (isset($this->options['bg_type']) && $this->options['bg_type'] === 'image' && !empty($this->options['bg_image'])): ?>
                    background-image: url('<?php echo esc_url($this->options['bg_image']); ?>') !important;
                    background-size: <?php echo esc_attr($this->options['bg_size'] ?? 'cover'); ?> !important;
                    background-position: <?php echo esc_attr($this->options['bg_position'] ?? 'center center'); ?> !important;
                    background-repeat: no-repeat !important;
                    background-attachment: fixed !important;
                <?php endif; ?>
            }
            
            /* Logo Styles */
            <?php if (!empty($this->options['logo_url'])): ?>
            .login h1 a {
                background-image: url('<?php echo esc_url($this->options['logo_url']); ?>') !important;
                background-size: contain !important;
                width: <?php echo esc_attr($this->options['logo_width'] ?? '84'); ?>px !important;
                height: <?php echo esc_attr($this->options['logo_height'] ?? '84'); ?>px !important;
            }
            <?php endif; ?>
            
            /* Form Styles */
            <?php if (!empty($this->options['form_bg'])): ?>
            .login form,
            #login form {
                background: <?php echo esc_attr($this->options['form_bg']); ?> !important;
                padding: <?php echo esc_attr($this->options['form_padding'] ?? '24'); ?>px !important;
                border-radius: <?php echo esc_attr($this->options['form_radius'] ?? '4'); ?>px !important;
                <?php if (isset($this->options['form_shadow']) && $this->options['form_shadow']): ?>
                box-shadow: 0 1px 3px rgba(0,0,0,0.13) !important;
                <?php else: ?>
                box-shadow: none !important;
                <?php endif; ?>
            }
            <?php endif; ?>
            
            /* Input Styles */
            <?php if (!empty($this->options['input_bg']) || !empty($this->options['input_text_color']) || !empty($this->options['input_border'])): ?>
            .login input[type="text"],
            .login input[type="password"],
            .login input[type="email"],
            #loginform input[type="text"],
            #loginform input[type="password"] {
                <?php if (!empty($this->options['input_bg'])): ?>
                background: <?php echo esc_attr($this->options['input_bg']); ?> !important;
                <?php endif; ?>
                <?php if (!empty($this->options['input_text_color'])): ?>
                color: <?php echo esc_attr($this->options['input_text_color']); ?> !important;
                <?php endif; ?>
                <?php if (!empty($this->options['input_border'])): ?>
                border-color: <?php echo esc_attr($this->options['input_border']); ?> !important;
                <?php endif; ?>
            }
            <?php endif; ?>
            
            <?php if (!empty($this->options['label_color'])): ?>
            .login label {
                color: <?php echo esc_attr($this->options['label_color']); ?> !important;
            }
            <?php endif; ?>
            
            /* Button Styles */
            <?php if (!empty($this->options['btn_bg']) || !empty($this->options['btn_text'])): ?>
            .login .button-primary,
            .login input[type="submit"],
            #wp-submit {
                <?php if (!empty($this->options['btn_bg'])): ?>
                background: <?php echo esc_attr($this->options['btn_bg']); ?> !important;
                border-color: <?php echo esc_attr($this->options['btn_bg']); ?> !important;
                <?php endif; ?>
                <?php if (!empty($this->options['btn_text'])): ?>
                color: <?php echo esc_attr($this->options['btn_text']); ?> !important;
                <?php endif; ?>
                <?php if (!empty($this->options['btn_radius'])): ?>
                border-radius: <?php echo esc_attr($this->options['btn_radius']); ?>px !important;
                <?php endif; ?>
                text-shadow: none !important;
                box-shadow: none !important;
            }
            <?php endif; ?>
            
            <?php if (!empty($this->options['btn_hover_bg'])): ?>
            .login .button-primary:hover,
            .login .button-primary:focus,
            .login input[type="submit"]:hover,
            .login input[type="submit"]:focus,
            #wp-submit:hover,
            #wp-submit:focus {
                background: <?php echo esc_attr($this->options['btn_hover_bg']); ?> !important;
                border-color: <?php echo esc_attr($this->options['btn_hover_bg']); ?> !important;
            }
            <?php endif; ?>
            
            /* Link Styles */
            <?php if (!empty($this->options['link_color'])): ?>
            .login #backtoblog a,
            .login #nav a,
            .login a {
                color: <?php echo esc_attr($this->options['link_color']); ?> !important;
            }
            <?php endif; ?>
            
            <?php if (!empty($this->options['link_hover'])): ?>
            .login #backtoblog a:hover,
            .login #nav a:hover,
            .login a:hover {
                color: <?php echo esc_attr($this->options['link_hover']); ?> !important;
            }
            <?php endif; ?>
            
            /* Custom CSS */
            <?php if (!empty($this->options['custom_css'])): ?>
                <?php echo wp_strip_all_tags($this->options['custom_css']); ?>
            <?php endif; ?>
        </style>
        <?php
    }
    
    public function custom_login_logo_url() {
        return home_url();
    }
    
    public function custom_login_logo_url_title() {
        return get_bloginfo('name');
    }
    
    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=wp-custom-login-designer">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Initialize the plugin
function wcld_init() {
    WP_Custom_Login_Designer::get_instance();
}
add_action('plugins_loaded', 'wcld_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    // Set default options
    $default_options = array(
        'enable' => 1,
        'bg_type' => 'gradient',
        'gradient_start' => '#667eea',
        'gradient_end' => '#764ba2',
        'gradient_direction' => '135deg',
        'form_bg' => '#ffffff',
        'form_padding' => 24,
        'form_radius' => 8,
        'form_shadow' => 1,
        'btn_bg' => '#667eea',
        'btn_text' => '#ffffff'
    );
    
    if (!get_option('wcld_settings')) {
        add_option('wcld_settings', $default_options);
    }
});