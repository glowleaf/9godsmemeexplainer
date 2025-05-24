<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Hook the cron event
add_action('9gods_cron_event', 'nine_gods_process_batch');

// Add custom cron interval if needed
add_filter('cron_schedules', 'nine_gods_add_cron_interval');

function nine_gods_add_cron_interval($schedules) {
    $schedules['every_five_minutes'] = array(
        'interval' => 300, // 5 minutes in seconds
        'display'  => esc_html__('Every 5 Minutes'),
    );
    $schedules['every_fifteen_minutes'] = array(
        'interval' => 900, // 15 minutes in seconds
        'display'  => esc_html__('Every 15 Minutes'),
    );
    $schedules['thirtymin'] = array(
        'interval' => 1800, // 30 minutes in seconds
        'display'  => esc_html__('Every 30 Minutes'),
    );
    $schedules['every_three_hours'] = array(
        'interval' => 10800, // 3 hours in seconds
        'display'  => esc_html__('Every 3 Hours'),
    );
    return $schedules;
}

// Main batch processing function
function nine_gods_process_batch() {
    // Check if API key is configured
    $api_key = get_option('9gods_openai_api_key');
    if (empty($api_key)) {
        error_log('9 Gods Meme Explainer: No API key configured');
        return;
    }
    
    // Get posts that need processing
    $posts_to_process = nine_gods_get_pending_posts();
    
    if (empty($posts_to_process)) {
        return; // Nothing to process
    }
    
    // Process up to X posts per batch to avoid timeouts (configurable)
    $batch_size = get_option('9gods_batch_size', 3);
    $processed = 0;
    
    foreach ($posts_to_process as $post_id) {
        if ($processed >= $batch_size) {
            break;
        }
        
        // Mark as processing to avoid duplicate processing
        update_post_meta($post_id, '9gods_explanation_status', 'processing');
        
        // Process the post
        $result = nine_gods_process_single_post($post_id);
        
        if ($result['success']) {
            update_post_meta($post_id, '9gods_explanation_text', $result['explanation']);
            update_post_meta($post_id, '9gods_explanation_status', 'done');
            error_log("9 Gods: Successfully processed post ID {$post_id}");
        } else {
            update_post_meta($post_id, '9gods_explanation_status', 'failed');
            update_post_meta($post_id, '_9gods_explanation_error', $result['error']);
            error_log("9 Gods: Failed to process post ID {$post_id}: " . $result['error']);
        }
        
        $processed++;
        
        // Longer delay to be respectful to the API and avoid timeouts
        sleep(2);
    }
    
    error_log("9 Gods: Processed {$processed} posts in this batch");
}

// Get posts that need processing
function nine_gods_get_pending_posts($limit = 20) {
    global $wpdb;
    
    // Get published posts with featured images that haven't been processed yet
    $sql = "
        SELECT DISTINCT p.ID 
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_thumb ON p.ID = pm_thumb.post_id 
            AND pm_thumb.meta_key = '_thumbnail_id' 
            AND pm_thumb.meta_value != ''
        LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id 
            AND pm_status.meta_key = '_9gods_explanation_status'
        WHERE p.post_type = 'post' 
            AND p.post_status = 'publish'
            AND (pm_status.meta_value IS NULL 
                 OR pm_status.meta_value = 'pending' 
                 OR pm_status.meta_value = 'failed')
        ORDER BY p.post_date DESC
        LIMIT %d
    ";
    
    $results = $wpdb->get_col($wpdb->prepare($sql, $limit));
    
    return array_map('intval', $results);
}

// Process a single post
function nine_gods_process_single_post($post_id) {
    // Get the featured image
    $thumbnail_id = get_post_thumbnail_id($post_id);
    if (!$thumbnail_id) {
        return array(
            'success' => false,
            'error' => 'No featured image found'
        );
    }
    
    $image_url = wp_get_attachment_image_url($thumbnail_id, 'large');
    if (!$image_url) {
        return array(
            'success' => false,
            'error' => 'Could not get image URL'
        );
    }
    
    // Get post context for better explanations
    $post = get_post($post_id);
    $post_title = $post->post_title;
    $post_excerpt = wp_trim_words($post->post_content, 50);
    
    // Call GPT-4 Vision API
    try {
        $explanation = nine_gods_call_gpt_vision($image_url, $post_title, $post_excerpt);
        
        if ($explanation) {
            return array(
                'success' => true,
                'explanation' => $explanation
            );
        } else {
            return array(
                'success' => false,
                'error' => 'Empty response from GPT-4 Vision'
            );
        }
    } catch (Exception $e) {
        return array(
            'success' => false,
            'error' => $e->getMessage()
        );
    }
}

// Manual trigger for testing (can be called via admin or CLI)
function nine_gods_manual_process($post_id = null) {
    if ($post_id) {
        // Process specific post
        $result = nine_gods_process_single_post($post_id);
        if ($result['success']) {
            update_post_meta($post_id, '9gods_explanation_text', $result['explanation']);
            update_post_meta($post_id, '9gods_explanation_status', 'done');
        } else {
            update_post_meta($post_id, '_9gods_explanation_status', 'failed');
            update_post_meta($post_id, '_9gods_explanation_error', $result['error']);
        }
        return $result;
    } else {
        // Process batch
        nine_gods_process_batch();
        return array('success' => true, 'message' => 'Batch processing triggered');
    }
}

// Add admin action for manual processing
add_action('admin_post_9gods_manual_process', 'nine_gods_handle_manual_process');

function nine_gods_handle_manual_process() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (!wp_verify_nonce($_GET['_wpnonce'], '9gods_manual_process')) {
        wp_die('Security check failed');
    }
    
    $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : null;
    $result = nine_gods_manual_process($post_id);
    
    $redirect_url = admin_url('options-general.php?page=9gods-settings');
    if ($result['success']) {
        $redirect_url = add_query_arg('message', 'processed', $redirect_url);
    } else {
        $redirect_url = add_query_arg('error', urlencode($result['error']), $redirect_url);
    }
    
    wp_redirect($redirect_url);
    exit;
}