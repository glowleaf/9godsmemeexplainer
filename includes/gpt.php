<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Main function to call GPT-4 Vision API
function nine_gods_call_gpt_vision($image_url, $post_title = '', $post_excerpt = '') {
    $api_key = get_option('9gods_openai_api_key');
    
    if (empty($api_key)) {
        throw new Exception('OpenAI API key not configured');
    }
    
    // Construct the prompt
    $prompt = nine_gods_build_prompt($post_title, $post_excerpt);
    
    // Prepare the API request
    $payload = array(
        'model' => 'gpt-4o',
        'messages' => array(
            array(
                'role' => 'user',
                'content' => array(
                    array(
                        'type' => 'text',
                        'text' => $prompt
                    ),
                    array(
                        'type' => 'image_url',
                        'image_url' => array(
                            'url' => $image_url,
                            'detail' => 'high'
                        )
                    )
                )
            )
        ),
        'max_tokens' => 500,
        'temperature' => 0.7
    );
    
    // Make the API call
    $response = nine_gods_make_openai_request($payload, $api_key);
    
    if (is_wp_error($response)) {
        throw new Exception('API request failed: ' . $response->get_error_message());
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON response from OpenAI');
    }
    
    // Check for API errors
    if (isset($data['error'])) {
        throw new Exception('OpenAI API error: ' . $data['error']['message']);
    }
    
    // Extract the explanation
    if (isset($data['choices'][0]['message']['content'])) {
        return trim($data['choices'][0]['message']['content']);
    }
    
    throw new Exception('No content in API response');
}

// Build the custom prompt for Gorgocutie
function nine_gods_build_prompt($post_title = '', $post_excerpt = '') {
    $base_prompt = "You are Gorgocutie, a adorable and friendly Medusa who loves explaining internet memes and funny images! 🐍✨

Your personality:
- Cute, bubbly, and enthusiastic
- Uses snake-themed puns and emojis occasionally (but don't overdo it)
- Explains things in a fun, engaging way that anyone can understand
- Sometimes uses Gen Z/millennial internet slang naturally
- Always positive and wholesome

Please look at this image and explain:
1. What's happening in the image
2. What makes it funny or memeable
3. Any cultural references or context people might need
4. Why people might share this

Keep your explanation conversational, fun, and around 2-3 paragraphs. Make it feel like you're excitedly explaining a meme to a friend!";

    // Add context if available
    if (!empty($post_title)) {
        $base_prompt .= "\n\nPost title for context: \"" . $post_title . "\"";
    }
    
    if (!empty($post_excerpt)) {
        $base_prompt .= "\n\nPost excerpt for context: \"" . wp_strip_all_tags($post_excerpt) . "\"";
    }
    
    return $base_prompt;
}

// Make the actual HTTP request to OpenAI
function nine_gods_make_openai_request($payload, $api_key) {
    $headers = array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json',
        'User-Agent' => 'WordPress/9GodsMemExplainer'
    );
    
    $args = array(
        'method' => 'POST',
        'headers' => $headers,
        'body' => json_encode($payload),
        'timeout' => 60, // Increased timeout for vision API
        'sslverify' => true
    );
    
    return wp_remote_request('https://api.openai.com/v1/chat/completions', $args);
}

// Test function to verify API connectivity
function nine_gods_test_api_connection() {
    $api_key = get_option('9gods_openai_api_key');
    
    if (empty($api_key)) {
        return array(
            'success' => false,
            'message' => 'No API key configured'
        );
    }
    
    // Simple text completion test
    $payload = array(
        'model' => 'gpt-3.5-turbo',
        'messages' => array(
            array(
                'role' => 'user',
                'content' => 'Say "Hello from Gorgocutie!" if you can hear me.'
            )
        ),
        'max_tokens' => 50
    );
    
    try {
        $response = nine_gods_make_openai_request($payload, $api_key);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Request failed: ' . $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return array(
                'success' => false,
                'message' => 'API error: ' . $data['error']['message']
            );
        }
        
        if (isset($data['choices'][0]['message']['content'])) {
            return array(
                'success' => true,
                'message' => 'API connection successful!',
                'response' => trim($data['choices'][0]['message']['content'])
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Unexpected response format'
        );
        
    } catch (Exception $e) {
        return array(
            'success' => false,
            'message' => 'Exception: ' . $e->getMessage()
        );
    }
}

// Helper function to validate image URL
function nine_gods_validate_image_url($image_url) {
    // Check if URL is accessible
    $response = wp_remote_head($image_url, array('timeout' => 10));
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $content_type = wp_remote_retrieve_header($response, 'content-type');
    $valid_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp');
    
    return in_array($content_type, $valid_types);
}

// Add AJAX handler for testing API connection from admin
add_action('wp_ajax_9gods_test_api', 'nine_gods_ajax_test_api');

function nine_gods_ajax_test_api() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    check_ajax_referer('9gods_test_api', 'nonce');
    
    $result = nine_gods_test_api_connection();
    
    wp_send_json($result);
}