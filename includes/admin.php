<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
add_action('admin_menu', 'nine_gods_admin_menu');

// Enable custom fields in post editor
add_action('admin_init', 'nine_gods_enable_custom_fields');

function nine_gods_admin_menu() {
    add_options_page(
        '9 Gods Meme Explainer',
        '9 Gods Meme Explainer',
        'manage_options',
        '9gods-settings',
        'nine_gods_admin_page'
    );
}

function nine_gods_enable_custom_fields() {
    // Force custom fields to be visible in post editor
    add_meta_box('postcustom', __('Custom Fields'), 'post_custom_meta_box', 'post', 'normal', 'core');
}

// Admin page content
function nine_gods_admin_page() {
    // Handle form submission
    if (isset($_POST['submit']) && wp_verify_nonce($_POST['9gods_nonce'], '9gods_settings')) {
        update_option('9gods_openai_api_key', sanitize_text_field($_POST['openai_api_key']));
        
        // Handle cron interval setting
        $cron_interval = sanitize_text_field($_POST['cron_interval']);
        $old_interval = get_option('9gods_cron_interval', 'hourly');
        
        // Handle batch size setting
        $batch_size = intval($_POST['batch_size']);
        if ($batch_size < 1) $batch_size = 1;
        if ($batch_size > 20) $batch_size = 20;
        update_option('9gods_batch_size', $batch_size);
        
        if ($cron_interval !== $old_interval) {
            // Clear existing cron job
            wp_clear_scheduled_hook('9gods_cron_event');
            
            // Schedule new cron job with new interval
            if (!wp_next_scheduled('9gods_cron_event')) {
                wp_schedule_event(time(), $cron_interval, '9gods_cron_event');
            }
            
            update_option('9gods_cron_interval', $cron_interval);
        }
        
        // Handle avatar upload
        if (!empty($_FILES['gorgocutie_avatar']['name'])) {
            $uploaded_file = wp_handle_upload($_FILES['gorgocutie_avatar'], array('test_form' => false));
            if ($uploaded_file && !isset($uploaded_file['error'])) {
                update_option('9gods_gorgocutie_avatar', $uploaded_file['url']);
            }
        }
        
        echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
    }
    
    // Handle retry action
    if (isset($_GET['retry']) && wp_verify_nonce($_GET['_wpnonce'], 'retry_9gods_' . $_GET['retry'])) {
        $post_id = intval($_GET['retry']);
        update_post_meta($post_id, '_9gods_explanation_status', 'pending');
        echo '<div class="notice notice-success"><p>Post queued for retry!</p></div>';
    }
    
    $api_key = get_option('9gods_openai_api_key', '');
    $avatar_url = get_option('9gods_gorgocutie_avatar', '');
    $cron_interval = get_option('9gods_cron_interval', 'hourly');
    $batch_size = get_option('9gods_batch_size', 3);
    ?>
    <div class="wrap">
        <h1>🐍 9 Gods Meme Explainer Settings</h1>
        
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('9gods_settings', '9gods_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">OpenAI API Key</th>
                    <td>
                        <input type="password" name="openai_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                        <p class="description">Your OpenAI API key for GPT-4 Vision access.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Processing Frequency</th>
                    <td>
                        <select name="cron_interval" class="regular-text">
                            <option value="every_five_minutes" <?php selected($cron_interval, 'every_five_minutes'); ?>>Every 5 Minutes (Fast)</option>
                            <option value="every_fifteen_minutes" <?php selected($cron_interval, 'every_fifteen_minutes'); ?>>Every 15 Minutes</option>
                            <option value="thirtymin" <?php selected($cron_interval, 'thirtymin'); ?>>Every 30 Minutes</option>
                            <option value="hourly" <?php selected($cron_interval, 'hourly'); ?>>Every Hour (Default)</option>
                            <option value="every_three_hours" <?php selected($cron_interval, 'every_three_hours'); ?>>Every 3 Hours</option>
                            <option value="twicedaily" <?php selected($cron_interval, 'twicedaily'); ?>>Twice Daily</option>
                            <option value="daily" <?php selected($cron_interval, 'daily'); ?>>Daily (Slow)</option>
                        </select>
                        <p class="description">How often to automatically process new posts. Faster = more API costs.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Batch Size</th>
                    <td>
                        <input type="number" name="batch_size" value="<?php echo esc_attr($batch_size); ?>" min="1" max="20" class="small-text" />
                        <p class="description">How many posts to process at once. Lower = less timeouts. Higher = faster processing.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Gorgocutie Avatar</th>
                    <td>
                        <input type="file" name="gorgocutie_avatar" accept="image/*" />
                        <?php if ($avatar_url): ?>
                            <br><br>
                            <img src="<?php echo esc_url($avatar_url); ?>" alt="Current avatar" style="max-width: 100px; max-height: 100px; border-radius: 50%;">
                            <p class="description">Current avatar</p>
                        <?php endif; ?>
                        <p class="description">Upload an image to use as Gorgocutie's avatar.</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        
        <h2>Processing Status</h2>
        <p>
            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=9gods_manual_process'), '9gods_manual_process'); ?>" class="button button-primary">
                🐍 Process Batch Manually (for testing)
            </a>
            <button type="button" id="test-api-btn" class="button">
                🔧 Test API Connection
            </button>
        </p>
        <div id="api-test-result" style="margin: 10px 0;"></div>
        
        <script>
        document.getElementById('test-api-btn').addEventListener('click', function() {
            var btn = this;
            var result = document.getElementById('api-test-result');
            
            btn.disabled = true;
            btn.textContent = 'Testing...';
            result.innerHTML = '';
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=9gods_test_api&nonce=<?php echo wp_create_nonce('9gods_test_api'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.textContent = '🔧 Test API Connection';
                
                if (data.success) {
                    result.innerHTML = '<div style="color: green; padding: 10px; background: #f0f8f0; border: 1px solid #4CAF50; border-radius: 3px;"><strong>✅ Success!</strong> ' + data.message + (data.response ? '<br>Response: ' + data.response : '') + '</div>';
                } else {
                    result.innerHTML = '<div style="color: red; padding: 10px; background: #fdf0f0; border: 1px solid #f44336; border-radius: 3px;"><strong>❌ Failed!</strong> ' + data.message + '</div>';
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.textContent = '🔧 Test API Connection';
                result.innerHTML = '<div style="color: red; padding: 10px; background: #fdf0f0; border: 1px solid #f44336; border-radius: 3px;"><strong>❌ Error!</strong> ' + error.message + '</div>';
            });
        });
        </script>
        
        <script>
        // Function to show full explanation text
        function showExplanation(postId) {
            // Find the explanation text from the table
            var explanationData = {
                <?php foreach ($posts as $post): ?>
                    <?php if ($post->explanation_text): ?>
                        <?php echo $post->ID; ?>: <?php echo json_encode($post->explanation_text); ?>,
                    <?php endif; ?>
                <?php endforeach; ?>
            };
            
            if (explanationData[postId]) {
                // Create modal-like display
                var modal = document.createElement('div');
                modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;';
                
                var content = document.createElement('div');
                content.style.cssText = 'background: white; padding: 20px; border-radius: 5px; max-width: 80%; max-height: 80%; overflow-y: auto; position: relative;';
                
                content.innerHTML = '<h3>🐍 Gorgocutie\'s Explanation</h3>' +
                                  '<div style="line-height: 1.6; margin-bottom: 20px;">' + explanationData[postId].replace(/\n/g, '<br>') + '</div>' +
                                  '<button onclick="this.closest(\'div[style*=\"position: fixed\"]\').remove()" style="background: #0073aa; color: white; border: none; padding: 10px 20px; border-radius: 3px; cursor: pointer;">Close</button>';
                
                modal.appendChild(content);
                document.body.appendChild(modal);
                
                // Close on background click
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        modal.remove();
                    }
                });
            }
        }
        </script>
        
        <?php nine_gods_display_status_table(); ?>
    </div>
    <?php
}

// Display status table
function nine_gods_display_status_table() {
    global $wpdb;
    
    // Get posts with featured images
    $posts = $wpdb->get_results("
        SELECT p.ID, p.post_title, p.post_date, 
               pm1.meta_value as explanation_status,
               pm2.meta_value as explanation_text,
               pm3.meta_value as thumbnail_id
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '9gods_explanation_status'
        LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '9gods_explanation_text'
        LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_thumbnail_id'
        WHERE p.post_type = 'post' 
        AND p.post_status = 'publish'
        AND pm3.meta_value IS NOT NULL
        ORDER BY p.post_date DESC
        LIMIT 50
    ");
    
    if (empty($posts)) {
        echo '<p>No posts with featured images found.</p>';
        return;
    }
    ?>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Image</th>
                <th>Post Title</th>
                <th>Date</th>
                <th>Status</th>
                <th>Explanation Preview</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($posts as $post): ?>
                <?php
                $status = $post->explanation_status ?: 'pending';
                $status_class = '';
                $status_text = ucfirst($status);
                
                switch ($status) {
                    case 'done':
                        $status_class = 'success';
                        break;
                    case 'failed':
                        $status_class = 'error';
                        break;
                    case 'processing':
                        $status_class = 'warning';
                        break;
                    default:
                        $status_class = 'info';
                }
                
                $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'thumbnail');
                $explanation_preview = $post->explanation_text ? wp_trim_words($post->explanation_text, 15) : '';
                ?>
                <tr>
                    <td>
                        <?php if ($thumbnail_url): ?>
                            <img src="<?php echo esc_url($thumbnail_url); ?>" alt="Featured image" style="width: 50px; height: 50px; object-fit: cover; border-radius: 3px;">
                        <?php else: ?>
                            <span class="dashicons dashicons-format-image" style="font-size: 30px; color: #ccc;"></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><a href="<?php echo get_edit_post_link($post->ID); ?>" target="_blank"><?php echo esc_html($post->post_title); ?></a></strong>
                    </td>
                    <td><?php echo date('M j, Y', strtotime($post->post_date)); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $status_class; ?>" style="padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; text-transform: uppercase;">
                            <?php echo $status_text; ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($explanation_preview): ?>
                            <span title="<?php echo esc_attr($post->explanation_text); ?>"><?php echo esc_html($explanation_preview); ?></span>
                        <?php else: ?>
                            <em>No explanation yet</em>
                            <?php 
                            // Show error message if failed
                            if ($status === 'failed') {
                                $error_message = get_post_meta($post->ID, '_9gods_explanation_error', true);
                                if ($error_message) {
                                    echo '<br><small style="color: #dc3232;">Error: ' . esc_html($error_message) . '</small>';
                                }
                            }
                            ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($status === 'failed' || $status === 'done'): ?>
                            <a href="<?php echo wp_nonce_url(add_query_arg('retry', $post->ID), 'retry_9gods_' . $post->ID); ?>" class="button button-small">
                                Retry
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo get_permalink($post->ID); ?>" target="_blank" class="button button-small">View</a>
                        <?php if ($post->explanation_text): ?>
                            <button type="button" class="button button-small" onclick="showExplanation(<?php echo $post->ID; ?>)">Full Text</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <style>
    .status-success { background-color: #46b450; color: white; }
    .status-error { background-color: #dc3232; color: white; }
    .status-warning { background-color: #ffb900; color: white; }
    .status-info { background-color: #00a0d2; color: white; }
    </style>
    <?php
}