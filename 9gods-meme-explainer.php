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
    // Clear any existing cron events first
    wp_clear_scheduled_hook('9gods_cron_event');
    
    // Get saved cron interval or default to hourly
    $cron_interval = get_option('9gods_cron_interval', 'hourly');
    
    // Schedule cron event with user-selected interval
    if (!wp_next_scheduled('9gods_cron_event')) {
        wp_schedule_event(time(), $cron_interval, '9gods_cron_event');
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
        $explanation_text = get_post_meta($post_id, '9gods_explanation_text', true);
        $status = get_post_meta($post_id, '9gods_explanation_status', true);
        
        if ($explanation_text && $status === 'done') {
            $avatar_url = get_option('9gods_gorgocutie_avatar', '');
            $avatar_html = '';
            
            if ($avatar_url) {
                $avatar_html = '<img src="' . esc_url($avatar_url) . '" alt="Gorgocutie" style="width: 50px; height: 50px; border-radius: 50%; margin-right: 10px; vertical-align: middle;">';
            }
            
            // Calculate difficulty rating based on explanation content
            $difficulty = nine_gods_calculate_difficulty($explanation_text);
            $difficulty_display = nine_gods_get_difficulty_display($difficulty);
            
            // Extract cultural references/tags
            $tags = nine_gods_extract_tags($explanation_text);
            
            $explanation_html = '<div class="nine-gods-explanation" style="margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-left: 4px solid #6c757d; border-radius: 5px;">';
            $explanation_html .= '<h3 style="margin-top: 0; color: #495057;">' . $avatar_html . '🐍 Gorgocutie Explains</h3>';
            $explanation_html .= '<div class="nine-gods-explained" style="line-height: 1.6;">' . wp_kses_post(nl2br($explanation_text)) . '</div>';
            
            // Add meta information
            $explanation_html .= '<div class="nine-gods-meta">';
            $explanation_html .= '<div class="nine-gods-difficulty">Difficulty: ' . $difficulty_display . '</div>';
            
            if (!empty($tags)) {
                $explanation_html .= '<div class="nine-gods-tags">';
                foreach ($tags as $tag) {
                    $explanation_html .= '<span class="nine-gods-tag">' . esc_html($tag) . '</span>';
                }
                $explanation_html .= '</div>';
            }
            
            // Add share buttons
            $post_url = get_permalink();
            $post_title = get_the_title();
            $explanation_html .= '<div class="nine-gods-share">';
            $explanation_html .= '<a href="https://twitter.com/intent/tweet?text=' . urlencode($post_title . ' - Explained by Gorgocutie 🐍') . '&url=' . urlencode($post_url) . '" class="nine-gods-share-btn" target="_blank">Share Explanation</a>';
            $explanation_html .= '<a href="#" onclick="navigator.clipboard.writeText(\'' . esc_js($post_url) . '\'); alert(\'Link copied!\'); return false;" class="nine-gods-share-btn">Copy Link</a>';
            $explanation_html .= '</div>';
            
            $explanation_html .= '</div>'; // Close meta div
            $explanation_html .= '</div>';
            
            $content .= $explanation_html;
        }
    }
    return $content;
}

// Function to calculate difficulty rating
function nine_gods_calculate_difficulty($text) {
    $difficulty_keywords = array(
        'easy' => array('funny', 'joke', 'simple', 'obvious', 'clear', 'basic'),
        'medium' => array('reference', 'culture', 'history', 'context', 'background', 'meaning'),
        'hard' => array('mythology', 'ancient', 'classical', 'philosophical', 'literary', 'obscure', 'academic', 'scholarly')
    );
    
    $text_lower = strtolower($text);
    $scores = array('easy' => 0, 'medium' => 0, 'hard' => 0);
    
    foreach ($difficulty_keywords as $level => $keywords) {
        foreach ($keywords as $keyword) {
            $scores[$level] += substr_count($text_lower, $keyword);
        }
    }
    
    // Determine difficulty based on scores
    if ($scores['hard'] > 2 || ($scores['hard'] > 0 && $scores['medium'] > 3)) {
        return 'hard';
    } elseif ($scores['medium'] > 2 || ($scores['medium'] > 0 && $scores['easy'] < 2)) {
        return 'medium';
    } else {
        return 'easy';
    }
}

// Function to display difficulty rating
function nine_gods_get_difficulty_display($difficulty) {
    switch ($difficulty) {
        case 'easy':
            return '🐍 Easy (Universal humor)';
        case 'medium':
            return '🐍🐍 Medium (Some cultural knowledge needed)';
        case 'hard':
            return '🐍🐍🐍 Hard (Deep cultural/historical knowledge required)';
        default:
            return '🐍 Easy';
    }
}

// Function to extract tags from explanation
function nine_gods_extract_tags($text) {
    $tag_patterns = array(
        'Medieval' => array('knight', 'armor', 'medieval', 'middle ages', 'chivalry'),
        'Fantasy' => array('fantasy', 'rpg', 'game', 'quest', 'adventure'),
        'Modern Life' => array('subway', 'commute', 'work', 'daily', 'routine'),
        'Wordplay' => array('pun', 'play on words', 'clever', 'twist'),
        'Pop Culture' => array('movie', 'film', 'tv', 'show', 'popular'),
        'History' => array('history', 'historical', 'ancient', 'past'),
        'Mythology' => array('myth', 'mythology', 'god', 'goddess', 'legend'),
        'Art' => array('art', 'painting', 'sculpture', 'museum', 'artistic')
    );
    
    $text_lower = strtolower($text);
    $found_tags = array();
    
    foreach ($tag_patterns as $tag => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($text_lower, $keyword) !== false) {
                $found_tags[] = $tag;
                break; // Only add each tag once
            }
        }
    }
    
    return array_unique($found_tags);
}

// Add CSS for better styling
add_action('wp_head', 'nine_gods_add_frontend_styles');

function nine_gods_add_frontend_styles() {
    echo '<style>
    .nine-gods-explanation {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        position: relative;
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
    .nine-gods-meta {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #dee2e6;
        font-size: 0.9em;
        color: #6c757d;
    }
    .nine-gods-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        margin-top: 10px;
    }
    .nine-gods-tag {
        background: #e9ecef;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        color: #495057;
    }
    .nine-gods-difficulty {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .nine-gods-share {
        margin-top: 10px;
    }
    .nine-gods-share-btn {
        display: inline-block;
        padding: 5px 10px;
        margin-right: 5px;
        background: #007cba;
        color: white;
        text-decoration: none;
        border-radius: 3px;
        font-size: 0.8em;
    }
    .nine-gods-share-btn:hover {
        background: #005a87;
        color: white;
    }
    @media (max-width: 768px) {
        .nine-gods-explanation {
            margin: 20px -15px 0;
            border-radius: 0;
        }
        .nine-gods-tags {
            justify-content: center;
        }
    }
    </style>';
}

// Add SEO enhancements
add_action('wp_head', 'nine_gods_add_seo_meta');

function nine_gods_add_seo_meta() {
    if (is_single()) {
        $post_id = get_the_ID();
        $explanation_text = get_post_meta($post_id, '9gods_explanation_text', true);
        $status = get_post_meta($post_id, '9gods_explanation_status', true);
        
        if ($explanation_text && $status === 'done') {
            // Create a shorter description for meta
            $short_explanation = wp_trim_words(strip_tags($explanation_text), 30, '...');
            $post_title = get_the_title();
            $featured_image = get_the_post_thumbnail_url($post_id, 'large');
            
            // Get difficulty and tags for enhanced meta
            $difficulty = nine_gods_calculate_difficulty($explanation_text);
            $tags = nine_gods_extract_tags($explanation_text);
            $difficulty_text = nine_gods_get_difficulty_display($difficulty);
            
            // Enhanced meta description with difficulty
            $meta_description = $short_explanation . ' | Difficulty: ' . strip_tags($difficulty_text);
            echo '<meta name="description" content="' . esc_attr($meta_description) . '">' . "\n";
            
            // Keywords meta tag with extracted tags
            if (!empty($tags)) {
                $keywords = implode(', ', array_merge($tags, ['meme explanation', 'Gorgocutie', '9 Gods', 'mythology memes']));
                echo '<meta name="keywords" content="' . esc_attr($keywords) . '">' . "\n";
            }
            
            // Open Graph tags for social sharing
            echo '<meta property="og:title" content="' . esc_attr($post_title) . ' - Explained by Gorgocutie">' . "\n";
            echo '<meta property="og:description" content="' . esc_attr($short_explanation) . '">' . "\n";
            if ($featured_image) {
                echo '<meta property="og:image" content="' . esc_url($featured_image) . '">' . "\n";
            }
            echo '<meta property="og:type" content="article">' . "\n";
            echo '<meta property="article:section" content="Meme Explanations">' . "\n";
            if (!empty($tags)) {
                foreach ($tags as $tag) {
                    echo '<meta property="article:tag" content="' . esc_attr($tag) . '">' . "\n";
                }
            }
            
            // Twitter Card tags
            echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr($post_title) . ' - Explained by Gorgocutie">' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr($short_explanation) . '">' . "\n";
            if ($featured_image) {
                echo '<meta name="twitter:image" content="' . esc_url($featured_image) . '">' . "\n";
            }
            
            // Enhanced Schema.org structured data
            $schema = array(
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $post_title,
                'description' => $short_explanation,
                'author' => array(
                    '@type' => 'Person',
                    'name' => 'Gorgocutie',
                    'description' => 'AI-powered meme explainer with a cute Medusa personality'
                ),
                'publisher' => array(
                    '@type' => 'Organization',
                    'name' => '9 Gods',
                    'url' => home_url(),
                    'description' => 'Mythology and History Memes with AI explanations'
                ),
                'datePublished' => get_the_date('c'),
                'dateModified' => get_the_modified_date('c'),
                'articleSection' => 'Meme Explanations',
                'genre' => 'Educational Entertainment',
                'educationalLevel' => $difficulty,
                'learningResourceType' => 'Explanation',
                'audience' => array(
                    '@type' => 'Audience',
                    'audienceType' => 'General Public'
                )
            );
            
            if ($featured_image) {
                $schema['image'] = array(
                    '@type' => 'ImageObject',
                    'url' => $featured_image,
                    'description' => $post_title . ' meme'
                );
            }
            
            if (!empty($tags)) {
                $schema['keywords'] = implode(', ', $tags);
                $schema['about'] = array();
                foreach ($tags as $tag) {
                    $schema['about'][] = array(
                        '@type' => 'Thing',
                        'name' => $tag
                    );
                }
            }
            
            // Add educational content schema
            $educational_schema = array(
                '@context' => 'https://schema.org',
                '@type' => 'LearningResource',
                'name' => $post_title . ' - Meme Explanation',
                'description' => $explanation_text,
                'educationalLevel' => $difficulty,
                'learningResourceType' => 'Explanation',
                'teaches' => 'Cultural literacy, humor analysis, visual communication',
                'author' => array(
                    '@type' => 'Person',
                    'name' => 'Gorgocutie'
                ),
                'publisher' => array(
                    '@type' => 'Organization',
                    'name' => '9 Gods'
                )
            );
            
            echo '<script type="application/ld+json">' . json_encode($schema) . '</script>' . "\n";
            echo '<script type="application/ld+json">' . json_encode($educational_schema) . '</script>' . "\n";
        }
    }
}