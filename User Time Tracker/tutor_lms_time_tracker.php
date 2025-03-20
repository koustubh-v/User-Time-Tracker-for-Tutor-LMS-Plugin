<?php
/**
 * Plugin Name: User Time Tracker
 * Description: Tracks time spent by each user on the website with daily refresh
 * Version: 1.0
 * Author: Koustubh Verma
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class User_Time_Tracker {
    
    public function __construct() {
        // Initialize the plugin
        add_action('init', array($this, 'init'));
        
        // Register shortcode
        add_shortcode('user_time_tracker', array($this, 'time_tracker_shortcode'));
        
        // Register AJAX actions
        add_action('wp_ajax_update_user_time', array($this, 'update_user_time'));
        add_action('wp_ajax_nopriv_update_user_time', array($this, 'update_user_time'));
        
        // Add cleanup cron job for daily refresh
        register_activation_hook(__FILE__, array($this, 'activate_cron'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate_cron'));
        add_action('user_time_daily_reset', array($this, 'reset_daily_time'));
    }
    
    public function init() {
        // Enqueue necessary scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('user-time-tracker', plugin_dir_url(__FILE__) . 'assets/js/user-time-tracker.js', array('jquery'), '1.0', true);
        
        // Pass AJAX URL to script
        wp_localize_script('user-time-tracker', 'user_time_tracker_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('user-time-tracker-nonce')
        ));
    }
    
    public function activate_cron() {
        // Schedule daily reset at midnight
        if (!wp_next_scheduled('user_time_daily_reset')) {
            wp_schedule_event(strtotime('today midnight'), 'daily', 'user_time_daily_reset');
        }
    }
    
    public function deactivate_cron() {
        wp_clear_scheduled_hook('user_time_daily_reset');
    }
    
    public function reset_daily_time() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_time_tracker';
        
        // Store in history table if needed
        // $wpdb->query("INSERT INTO {$wpdb->prefix}user_time_tracker_history SELECT * FROM $table_name");
        
        // Clear today's data
        $wpdb->query("TRUNCATE TABLE $table_name");
    }
    
    public function update_user_time() {
        // Verify nonce
        check_ajax_referer('user-time-tracker-nonce', 'nonce');
        
        $session_id = sanitize_text_field($_POST['session_id']);
        $time_spent = intval($_POST['time_spent']);
        
        if (!$session_id || $time_spent <= 0) {
            wp_send_json_error('Invalid data');
            return;
        }
        
        $this->store_user_time($session_id, $time_spent);
        wp_send_json_success('Time updated');
        wp_die();
    }
    
    private function store_user_time($session_id, $time_spent) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_time_tracker';
        
        // Create user_id variable
        $user_id = get_current_user_id();
        if ($user_id == 0) {
            $user_id = null; // For guests
        }
        
        // Check if session already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_id = %s",
            $session_id
        ));
        
        if ($existing) {
            // Update existing record
            $wpdb->update(
                $table_name,
                array('time_spent' => $time_spent, 'last_update' => current_time('mysql')),
                array('session_id' => $session_id),
                array('%d', '%s'),
                array('%s')
            );
        } else {
            // Insert new record
            $wpdb->insert(
                $table_name,
                array(
                    'session_id'  => $session_id,
                    'user_id'     => $user_id,
                    'time_spent'  => $time_spent,
                    'ip_address'  => $_SERVER['REMOTE_ADDR'],
                    'first_visit' => current_time('mysql'),
                    'last_update' => current_time('mysql')
                ),
                array('%s', '%d', '%d', '%s', '%s', '%s')
            );
        }
    }
    
    public function time_tracker_shortcode($atts) {
        $atts = shortcode_atts(array(
            'user_id' => '',
            'display' => 'current', // 'current', 'total', 'all'
        ), $atts, 'user_time_tracker');
        
        if ($atts['display'] == 'current') {
            // Show time for current session
            $output = '<div class="user-time-display" id="user-time-tracker-display">
                <span class="time-label">Time on site today: </span>
                <span class="time-value">00:00:00</span>
            </div>';
        } else {
            // Get time from database for this user or all users
            $output = $this->get_user_time_html($atts['user_id'], $atts['display']);
        }
        
        return $output;
    }
    
    public function get_user_time_html($user_id = '', $display = 'total') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_time_tracker';
        
        // If no user_id specified and user is logged in, use current user
        if (empty($user_id) && is_user_logged_in()) {
            $user_id = get_current_user_id();
        }
        
        $query = "SELECT SUM(time_spent) as total_time FROM $table_name";
        $params = array();
        
        if (!empty($user_id) && $display != 'all') {
            $query .= " WHERE user_id = %d";
            $params[] = $user_id;
        }
        
        if (!empty($params)) {
            $result = $wpdb->get_var($wpdb->prepare($query, $params));
        } else {
            $result = $wpdb->get_var($query);
        }
        
        $total_seconds = intval($result);
        $hours = floor($total_seconds / 3600);
        $minutes = floor(($total_seconds % 3600) / 60);
        $seconds = $total_seconds % 60;
        
        $formatted_time = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
        
        return '<div class="user-time-total">
            <span class="time-label">Total time spent today: </span>
            <span class="time-value">' . $formatted_time . '</span>
        </div>';
    }
    
    // Function to get user time programmatically
    public function get_user_time($user_id = null, $format = true) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_time_tracker';
        
        // If no user_id specified and user is logged in, use current user
        if (is_null($user_id) && is_user_logged_in()) {
            $user_id = get_current_user_id();
        }
        
        if (!is_null($user_id)) {
            $total_seconds = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(time_spent) FROM $table_name WHERE user_id = %d",
                $user_id
            ));
        } else {
            // Get current session time
            $session_id = isset($_COOKIE['wp_time_tracker_session']) ? $_COOKIE['wp_time_tracker_session'] : '';
            if (!empty($session_id)) {
                $total_seconds = $wpdb->get_var($wpdb->prepare(
                    "SELECT time_spent FROM $table_name WHERE session_id = %s",
                    $session_id
                ));
            } else {
                $total_seconds = 0;
            }
        }
        
        $total_seconds = intval($total_seconds);
        
        if ($format) {
            $hours = floor($total_seconds / 3600);
            $minutes = floor(($total_seconds % 3600) / 60);
            $seconds = $total_seconds % 60;
            return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
        }
        
        return $total_seconds;
    }
}

// Initialize the plugin
$user_time_tracker = new User_Time_Tracker();

// Create database tables on activation
function user_time_tracker_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    $table_name = $wpdb->prefix . 'user_time_tracker';
    
    // Create main tracking table
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        session_id varchar(32) NOT NULL,
        user_id bigint(20) NULL,
        time_spent int NOT NULL,
        ip_address varchar(100) NOT NULL,
        first_visit datetime NOT NULL,
        last_update datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY session_id (session_id),
        KEY user_id (user_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'user_time_tracker_activate');

// Create the JavaScript file on plugin activation
function user_time_tracker_create_js_file() {
    // Create the assets/js directory if it doesn't exist
    if (!file_exists(plugin_dir_path(__FILE__) . 'assets/js')) {
        mkdir(plugin_dir_path(__FILE__) . 'assets/js', 0755, true);
    }
    
    // JavaScript content
    $js_content = <<<EOT
jQuery(document).ready(function($) {
    // Generate a session ID if not exists
    if (!Cookies.get('wp_time_tracker_session')) {
        var sessionId = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        Cookies.set('wp_time_tracker_session', sessionId, { expires: 1 }); // Expires in 1 day
    }
    
    var sessionId = Cookies.get('wp_time_tracker_session');
    var startTime = new Date().getTime();
    var timeSpent = 0;
    var timerInterval;
    var isActive = true;
    var displayElement = $('#user-time-tracker-display .time-value');
    
    // Start timer
    function startTimer() {
        timerInterval = setInterval(function() {
            if (isActive) {
                timeSpent = Math.floor((new Date().getTime() - startTime) / 1000);
                updateTimeDisplay();
                
                // Update server every 30 seconds
                if (timeSpent % 30 === 0) {
                    updateServerTime();
                }
            }
        }, 1000);
    }
    
    // Format time for display
    function formatTime(seconds) {
        var hours = Math.floor(seconds / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        var secs = seconds % 60;
        
        return (hours < 10 ? "0" + hours : hours) + ":" +
               (minutes < 10 ? "0" + minutes : minutes) + ":" +
               (secs < 10 ? "0" + secs : secs);
    }
    
    // Update time display
    function updateTimeDisplay() {
        if (displayElement.length) {
            displayElement.text(formatTime(timeSpent));
        }
    }
    
    // Update time on server
    function updateServerTime() {
        $.ajax({
            url: user_time_tracker_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'update_user_time',
                nonce: user_time_tracker_ajax.nonce,
                session_id: sessionId,
                time_spent: timeSpent
            }
        });
    }
    
    // Detect user activity/inactivity
    $(window).on('blur', function() {
        isActive = false;
    });
    
    $(window).on('focus', function() {
        isActive = true;
    });
    
    // Update server on page unload
    $(window).on('beforeunload', function() {
        updateServerTime();
    });
    
    // Start tracking
    startTimer();
    
    // If the page doesn't have Cookie.js, add a simple implementation
    if (typeof Cookies === 'undefined') {
        // Simple cookie functions
        window.Cookies = {
            set: function(name, value, options) {
                var expires = "";
                if (options && options.expires) {
                    var date = new Date();
                    date.setTime(date.getTime() + (options.expires * 24 * 60 * 60 * 1000));
                    expires = "; expires=" + date.toUTCString();
                }
                document.cookie = name + "=" + (value || "") + expires + "; path=/";
            },
            get: function(name) {
                var nameEQ = name + "=";
                var ca = document.cookie.split(';');
                for(var i=0; i < ca.length; i++) {
                    var c = ca[i];
                    while (c.charAt(0) == ' ') c = c.substring(1, c.length);
                    if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
                }
                return null;
            }
        };
    }
});
EOT;
    
    // Write the JS file
    file_put_contents(plugin_dir_path(__FILE__) . 'assets/js/user-time-tracker.js', $js_content);
}
register_activation_hook(__FILE__, 'user_time_tracker_create_js_file');