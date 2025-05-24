<?php
/*
Plugin Name: 9 Gods Meme Explainer
Description: Uses GPT-4 Vision to explain memes via Gorgocutie, a cute Medusa.
Version: 1.0
Author: Glowleaf
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NINE_GODS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NINE_GODS_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Include required files
require_once NINE_GODS_PLUGIN_PATH . 'includes/admin.php';
require_once NINE_GODS_PLUGIN_PATH . 'includes/cron.php';
require_once NINE_GODS_PLUGIN_PATH . 'includes/gpt.php';

// Plugin activation and deactivation hooks
register_activation_hook(__FILE__, 'nine_gods_activate');
register_deactivation_hook(__FILE__, 'nine_gods_deactivate');

function nine_gods_activate() {
    // Schedule cron event
    if (!wp_next_scheduled('9gods_cron_event')) {
        wp_schedule_event(time(), 'hourly', '9gods_cron_event');
    }
}

function nine_gods_deactivate() {
    // Clear scheduled cron event
    wp_clear_scheduled_hook('9gods_cron_event');
}

// Add explanation to post content
add_filter('the_content', 'nine_gods_add_explanation');

function nine_gods_add_explanation($content) {
    if (is_single() && in_the_loop() && is_main_query()) {
        $post_id = get_the_ID();
        $explanation_text = get_post_meta($post_id, '_9gods_explanation_text', true);
        $status = get_post_meta($post_id, '_9gods_explanation_status', true);
        
        if ($explanation_text && $status === 'done') {
            $avatar_url = get_option('9gods_gorgocutie_avatar', '');
            $avatar_html = '';
            
            if ($avatar_url) {
                $avatar_html = '<img src="' . esc_url($avatar_url) . '" alt="Gorgocutie" style="width: 50px; height: 50px; border-radius: 50%; margin-right: 10px; vertical-align: middle;">';
            }
            
            $explanation_html = '<div class="nine-gods-explanation" style="margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-left: 4px solid #6c757d; border-radius: 5px;">';
            $explanation_html .= '<h3 style="margin-top: 0; color: #495057;">' . $avatar_html . '🐍 Gorgocutie Explains</h3>';
            $explanation_html .= '<div class="nine-gods-explained" style="line-height: 1.6;">' . wp_kses_post(nl2br($explanation_text)) . '</div>';
            $explanation_html .= '</div>';
            
            $content .= $explanation_html;
        }
    }
    return $content;
}

// Add CSS for better styling
add_action('wp_head', 'nine_gods_add_frontend_styles');

function nine_gods_add_frontend_styles() {
    echo '<style>
    .nine-gods-explanation {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    .nine-gods-explanation h3 {
        display: flex;
        align-items: center;
        font-size: 1.2em;
        margin-bottom: 15px;
    }
    .nine-gods-explained {
        color: #333;
    }
    </style>';
}