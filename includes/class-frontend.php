<?php
/**
 * Frontend functionality for REVENTOR Calendar Appointment Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class REVENTORCAB_Frontend {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        add_action('wp_ajax_reventorcab_get_available_slots', [$this, 'get_available_slots']);
        add_action('wp_ajax_nopriv_reventorcab_get_available_slots', [$this, 'get_available_slots']);
        add_action('wp_ajax_reventorcab_book_appointment', [$this, 'book_appointment']);
        add_action('wp_ajax_nopriv_reventorcab_book_appointment', [$this, 'book_appointment']);
        add_action('wp_ajax_reventorcab_sync_calendar', [$this, 'sync_calendar']);
        add_action('wp_ajax_nopriv_reventorcab_sync_calendar', [$this, 'sync_calendar']);
    }
    
    public function enqueue_frontend_scripts(): void {
        if ($this->has_booking_shortcode()) {
            wp_enqueue_style('reventorcab-frontend-style', REVENTORCAB_PLUGIN_URL . 'assets/css/frontend.css', ['dashicons'], REVENTORCAB_VERSION);
            wp_enqueue_script('reventorcab-frontend-script', REVENTORCAB_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], REVENTORCAB_VERSION, true);
            
            $theme_color = get_option('reventorcab_theme_color', '#007cba');
            
            // Add inline CSS to apply theme color to success message
            $custom_css = "
                .reventorcab-success-icon {
                    color: {$theme_color} !important;
                }
                .reventorcab-success-content h4 {
                    color: {$theme_color} !important;
                }
                .reventorcab-step.completed .reventorcab-step-number {
                    background: {$theme_color} !important;
                }
            ";
            wp_add_inline_style('reventorcab-frontend-style', wp_strip_all_tags($custom_css));
            
            wp_localize_script('reventorcab-frontend-script', 'reventorcab_frontend', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('reventorcab_frontend_nonce'),
                'theme_color' => $theme_color,
                'settings' => [
                    'working_days' => get_option('reventorcab_working_days', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
                    'min_booking_advance' => get_option('reventorcab_min_booking_advance', '2h'),
                    'working_hours_start' => get_option('reventorcab_working_hours_start', '09:00'),
                    'working_hours_end' => get_option('reventorcab_working_hours_end', '17:00'),
                    'time_format' => get_option('reventorcab_time_format', '24h')
                ],
                'strings' => [
                    'loading' => __('Loading...', 'reventor-calendar-appointment-booking'),
            'no_slots' => __('No available time slots for this date.', 'reventor-calendar-appointment-booking'),
            'booking_success' => __('Appointment booked successfully!', 'reventor-calendar-appointment-booking'),
            'booking_error' => __('Error booking appointment. Please try again.', 'reventor-calendar-appointment-booking'),
            'required_fields' => __('Please fill in all required fields.', 'reventor-calendar-appointment-booking'),
            'invalid_email' => __('Please enter a valid email address.', 'reventor-calendar-appointment-booking'),
            'syncing_calendar' => __('Syncing with calendar...', 'reventor-calendar-appointment-booking'),
            'outside_working_hours' => __('This date is outside working hours.', 'reventor-calendar-appointment-booking'),
            'minimum_advance_required' => __('Please select a date that meets the minimum advance time requirement.', 'reventor-calendar-appointment-booking')
                ]
            ]);
            
            // Add dynamic CSS for theme color
            wp_add_inline_style('reventorcab-frontend-style', $this->get_dynamic_css($theme_color));
        }
    }
    
    private function has_booking_shortcode(): bool {
        global $post;
        return is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'reventor-booking');
    }
    
    private function get_dynamic_css(string $theme_color): string {
        $theme_color_rgb = $this->hex_to_rgb($theme_color);
        $theme_color_rgba_05 = "rgba({$theme_color_rgb['r']}, {$theme_color_rgb['g']}, {$theme_color_rgb['b']}, 0.05)";
        $theme_color_rgba_10 = "rgba({$theme_color_rgb['r']}, {$theme_color_rgb['g']}, {$theme_color_rgb['b']}, 0.1)";
        $theme_color_rgba_15 = "rgba({$theme_color_rgb['r']}, {$theme_color_rgb['g']}, {$theme_color_rgb['b']}, 0.15)";
        $theme_color_rgba_30 = "rgba({$theme_color_rgb['r']}, {$theme_color_rgb['g']}, {$theme_color_rgb['b']}, 0.3)";
        $darker_theme = $this->darken_color($theme_color, 15);
        $text_color = $this->get_contrast_text_color($theme_color);
        
        return "
            /* Form Header */
            .reventorcab-booking-form .reventorcab-form-header {
                background: linear-gradient(135deg, {$theme_color} 0%, {$darker_theme} 100%);
            }
            
            /* Progress Bar */
            .reventorcab-booking-form .reventorcab-progress-bar {
                background-color: #fff;
            }
            
            /* Steps Indicator */
            .reventorcab-booking-form .reventorcab-step.active:not(:last-child)::after,
            .reventorcab-booking-form .reventorcab-step.completed:not(:last-child)::after {
                background: {$theme_color};
            }
            
            .reventorcab-booking-form .reventorcab-step.active .reventorcab-step-number {
                background-color: {$theme_color};
            }
            
            .reventorcab-booking-form .reventorcab-step.completed .reventorcab-step-number {
                background-color: {$theme_color};
            }
            
            .reventorcab-booking-form .reventorcab-step.active .reventorcab-step-label {
                color: {$theme_color};
            }
            
            /* Service Selection */
            .reventorcab-booking-form .reventorcab-service-card::before {
                background: {$theme_color};
            }
            
            .reventorcab-booking-form .reventorcab-service-option:hover .reventorcab-service-card {
                border-color: {$theme_color};
                box-shadow: 0 8px 25px {$theme_color_rgba_15};
            }
            
            .reventorcab-booking-form .reventorcab-service-option input:checked + .reventorcab-service-card {
                border-color: {$theme_color};
                background: {$theme_color_rgba_05};
            }
            
            .reventorcab-booking-form .reventorcab-service-icon {
                color: {$theme_color};
            }
            
            /* Date & Time Selection */
            .reventorcab-booking-form .reventorcab-date-selection select:focus {
                border-color: {$theme_color};
                box-shadow: 0 0 0 3px {$theme_color_rgba_10};
            }
            
            .reventorcab-booking-form .reventorcab-time-slot:hover {
                border-color: {$theme_color};
                background: {$theme_color_rgba_05};
            }
            
            .reventorcab-booking-form .reventorcab-time-slot.selected {
                background-color: {$theme_color};
                border-color: {$theme_color};
            }
            
            /* Form Inputs */
            .reventorcab-booking-form .reventorcab-form-group input:focus,
            .reventorcab-booking-form .reventorcab-form-group textarea:focus {
                border-color: {$theme_color};
                box-shadow: 0 0 0 3px {$theme_color_rgba_10};
            }
            
            /* Confirmation */
            .reventorcab-booking-form .reventorcab-confirmation-header .dashicons {
                color: {$theme_color};
            }
            
            /* Buttons */
            .reventorcab-booking-form .reventorcab-btn-primary {
                background-color: {$theme_color};
                border-color: {$theme_color};
                color: {$text_color};
            }
            
            .reventorcab-booking-form .reventorcab-btn-primary:hover:not(:disabled) {
                background-color: {$darker_theme};
                box-shadow: 0 4px 12px {$theme_color_rgba_30};
                color: {$text_color};
            }
            
            /* Loading Spinner */
            .reventorcab-booking-form .reventorcab-spinner {
                border-top-color: {$theme_color};
            }
            
            /* Branding Link */
            .reventorcab-branding-link {
                color: {$theme_color} !important;
            }
            
            .reventorcab-branding-link:hover {
                color: {$darker_theme} !important;
            }
            
            .reventorcab-branding-link:hover::after {
                background: linear-gradient(90deg, {$theme_color}, {$darker_theme}) !important;
            }
        ";
    }
    
    private function darken_color($hex, $percent) {
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = max(0, min(255, $r - ($r * $percent / 100)));
        $g = max(0, min(255, $g - ($g * $percent / 100)));
        $b = max(0, min(255, $b - ($b * $percent / 100)));
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    
    private function hex_to_rgb(string $hex): array {
        $hex = str_replace('#', '', $hex);
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }
    
    private function get_contrast_text_color(string $hex): string {
        $rgb = $this->hex_to_rgb($hex);
        
        // Calculate relative luminance using WCAG formula
        $r = $rgb['r'] / 255;
        $g = $rgb['g'] / 255;
        $b = $rgb['b'] / 255;
        
        // Apply gamma correction
        $r = ($r <= 0.03928) ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = ($g <= 0.03928) ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = ($b <= 0.03928) ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);
        
        // Calculate luminance
        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
        
        // Return white for dark colors, black for light colors
        return $luminance > 0.5 ? '#000000' : '#ffffff';
    }
    
    public function get_available_slots(): void {
        // AJAX handler for getting available slots
        
        try {
            check_ajax_referer('reventorcab_frontend_nonce', 'nonce');
        } catch (Exception $e) {
            // Nonce verification failed
            wp_send_json_error(['message' => 'Security check failed.']);
            return;
        }
        
        try {
            $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';
            $appointment_type = isset($_POST['appointment_type']) ? sanitize_text_field(wp_unslash($_POST['appointment_type'])) : '';
            
            // Get user timezone information
            $user_timezone = isset($_POST['user_timezone']) ? sanitize_text_field(wp_unslash($_POST['user_timezone'])) : null;
            $user_timezone_offset = isset($_POST['user_timezone_offset']) ? intval(wp_unslash($_POST['user_timezone_offset'])) : null;
            
            // Debug log user timezone information
            $this->debug_log("=== User Timezone Information ===");
            $this->debug_log("User timezone: " . ($user_timezone ?: 'not provided'));
            $this->debug_log("User timezone offset: " . ($user_timezone_offset !== null ? $user_timezone_offset : 'not provided'));
        
        // Get duration from appointment type or use default
        $timeslot_duration = get_option('reventorcab_timeslot_duration', 30);
        if (isset($_POST['duration'])) {
            $timeslot_duration = intval(wp_unslash($_POST['duration']));
        } else {
            // Try to parse appointment type for duration (backward compatibility)
            $appointment_data = json_decode($appointment_type, true);
            if (is_array($appointment_data) && isset($appointment_data['duration'])) {
                $timeslot_duration = intval($appointment_data['duration']);
            }
        }
        
        // Debug logging
        // Debug information prepared
        
        // Check if date is within working days
        if (!$this->is_working_day($date)) {
            // Not a working day
            wp_send_json_success(['slots' => [], 'debug' => 'not_working_day']);
            return;
        }
        
        // Check minimum booking advance time (skip for admin preview)
        // Only consider it admin preview if explicitly set via admin_preview parameter
        $is_admin_preview = isset($_POST['admin_preview']) && sanitize_text_field(wp_unslash($_POST['admin_preview'])) === 'true';
        if (!$is_admin_preview && !$this->meets_minimum_advance($date)) {
            wp_send_json_success(['slots' => [], 'debug' => 'min_advance_not_met']);
            return;
        }
        
        $all_slots = $this->generate_time_slots($date, $timeslot_duration, $is_admin_preview, $user_timezone, $user_timezone_offset);
        
        // Get booked slots and CalDAV conflicts
        $booked_slots = $this->get_booked_slots($date);
        
        // Get CalDAV conflicts directly from server (no local storage)
        $caldav_conflicts = $this->get_caldav_conflicts($date, $all_slots, $timeslot_duration);
        
        // Debug logging for admin preview
        if ($is_admin_preview && defined('WP_DEBUG') && WP_DEBUG) {
            $this->debug_log('=== Admin Preview CalDAV Debug ===');
            $this->debug_log('Date: ' . $date);
            $this->debug_log('All slots: ' . implode(', ', $all_slots));
            $this->debug_log('Booked slots: ' . implode(', ', $booked_slots));
            $this->debug_log('CalDAV conflicts: ' . implode(', ', $caldav_conflicts));
        }
        
        // Calculate available slots
        $available_slots = array_diff($all_slots, $booked_slots, $caldav_conflicts);
        
        // For admin preview, return both available and unavailable slots with status
        if ($is_admin_preview) {
            $slot_data = [];
            
            // Get current time using UTC to avoid server timezone dependency
            try {
                $timezone_string = $this->get_timezone_string();
                $timezone = new DateTimeZone($timezone_string);
                // Always use UTC for 'now' to avoid server timezone dependency
                $now_datetime = new DateTime('now', new DateTimeZone('UTC'));
                $now = $now_datetime->getTimestamp();
            } catch (Exception $e) {
                // Log error and throw exception - no fallback to ensure consistent behavior
                $this->debug_log('REVENTORCAB Critical Error: Invalid timezone "' . $timezone_string . '" in admin preview: ' . $e->getMessage());
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('REVENTORCAB Critical Error: Invalid timezone "' . $timezone_string . '" in class-frontend.php admin preview: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                }
                throw new Exception('REVENTORCAB timezone configuration error: ' . esc_html($e->getMessage()));
            }
            $today_datetime = new DateTime('@' . $now);
            $today_datetime->setTimezone($timezone);
            $today_date = $today_datetime->format('Y-m-d');
            
            foreach ($all_slots as $slot) {
                $status = 'available';
                $reason = '';
                
                // Get timezone and working hours once
                try {
                    $timezone_string = $this->get_timezone_string();
                    $timezone = new DateTimeZone($timezone_string);
                } catch (Exception $e) {
                    $timezone = new DateTimeZone('UTC');
                }
                $working_hours_start = get_option('reventorcab_working_hours_start', '09:00');
                $working_hours_end = get_option('reventorcab_working_hours_end', '17:00');
                
                $slot_datetime = new DateTime($date . ' ' . $slot . ':00', $timezone);
                $slot_timestamp = $slot_datetime->getTimestamp();
                
                // Check if slot is in the past - this has highest priority
                if ($slot_timestamp <= $now) {
                    $status = 'past';
                    $reason = 'Past time';
                } else {
                    // Only check other statuses if not in the past
                    
                    // Check if slot is outside working hours
                    $working_start_datetime = new DateTime($date . ' ' . $working_hours_start . ':00', $timezone);
                    $working_end_datetime = new DateTime($date . ' ' . $working_hours_end . ':00', $timezone);
                    
                    if ($slot_timestamp < $working_start_datetime->getTimestamp() || $slot_timestamp >= $working_end_datetime->getTimestamp()) {
                        $status = 'outside_hours';
                        $reason = __('Outside working hours.', 'reventor-calendar-appointment-booking');
                    }
                    
                    // Override with booked status if applicable (includes CalDAV conflicts)
                    if (in_array($slot, $booked_slots) || in_array($slot, $caldav_conflicts)) {
                        $status = 'booked';
                        if (in_array($slot, $booked_slots)) {
                            $reason = __('Booked', 'reventor-calendar-appointment-booking');
                        } else {
                            $reason = 'Booked (CalDAV conflict)';
                        }
                    }
                }
                
                $slot_data[] = [
                    'time' => $slot,
                    'status' => $status,
                    'reason' => $reason
                ];
            }
            
            wp_send_json_success([
                'slots' => array_values($available_slots),
                'all_slots' => $slot_data,
                'admin_preview' => true
            ]);
        } else {
            wp_send_json_success(['slots' => array_values($available_slots)]);
        }
        
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'An error occurred while loading time slots.']);
        }
    }
    
    private function generate_time_slots(string $date, int $duration, bool $is_admin_preview = false, ?string $user_timezone = null, ?int $user_timezone_offset = null): array {
        $slots = [];
        $working_hours_start = get_option('reventorcab_working_hours_start', '09:00');
        $working_hours_end = get_option('reventorcab_working_hours_end', '17:00');
        
        // Get plugin's configured timezone for working hours calculation
        try {
            $timezone_string = $this->get_timezone_string();
            $plugin_timezone = new DateTimeZone($timezone_string);
        } catch (Exception $e) {
            $plugin_timezone = new DateTimeZone('UTC');
        }
        
        // Determine display timezone (user's timezone if available, otherwise plugin timezone)
        $display_timezone = $plugin_timezone;
        if ($user_timezone && !$is_admin_preview) {
            try {
                $display_timezone = new DateTimeZone($user_timezone);
                $this->debug_log("Using user timezone for display: " . $user_timezone);
            } catch (Exception $e) {
                $this->debug_log("Invalid user timezone '{$user_timezone}', falling back to plugin timezone");
                $display_timezone = $plugin_timezone;
            }
        }
        
        $this->debug_log("Plugin timezone: " . $plugin_timezone->getName() . ", Display timezone: " . $display_timezone->getName());
        
        // For admin preview, generate all slots regardless of time
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
        if ($is_admin_preview) {
            // For admin preview, generate slots within working hours but show all statuses
            $start_datetime = new DateTime($date . ' ' . $working_hours_start . ':00', $plugin_timezone);
            $end_datetime = new DateTime($date . ' ' . $working_hours_end . ':00', $plugin_timezone);
            
            $start_time = $start_datetime->getTimestamp();
            $end_time = $end_datetime->getTimestamp();
            
            // Generate slots every duration minutes within working hours
            // Use granularity setting instead of appointment duration for slot generation
            $granularity = get_option('reventorcab_timeslot_granularity', 15);
            
            for ($time = $start_time; $time < $end_time; $time += ($granularity * 60)) {
                $slot_datetime = new DateTime('@' . $time);
                $slot_datetime->setTimezone($plugin_timezone);
                $slots[] = $slot_datetime->format('H:i');
            }
        } else {
            // Create timezone-aware datetime objects for working hours (plugin timezone for calculations)
            $start_datetime_plugin = new DateTime($date . ' ' . $working_hours_start . ':00', $plugin_timezone);
            $end_datetime_plugin = new DateTime($date . ' ' . $working_hours_end . ':00', $plugin_timezone);
            
            // Always use plugin timezone boundaries for slot generation
            // The working hours should remain consistent regardless of user timezone
            $start_time = $start_datetime_plugin->getTimestamp();
            $end_time = $end_datetime_plugin->getTimestamp();
            
            $this->debug_log("Working hours in plugin timezone: " . $start_datetime_plugin->format('H:i') . " - " . $end_datetime_plugin->format('H:i') . " (" . $plugin_timezone->getName() . ")");
            if ($user_timezone && $display_timezone->getName() !== $plugin_timezone->getName()) {
                // Log how working hours appear in user timezone for reference
                $start_datetime_user = clone $start_datetime_plugin;
                $start_datetime_user->setTimezone($display_timezone);
                $end_datetime_user = clone $end_datetime_plugin;
                $end_datetime_user->setTimezone($display_timezone);
                $this->debug_log("Working hours displayed to user: " . $start_datetime_user->format('H:i') . " - " . $end_datetime_user->format('H:i') . " (" . $display_timezone->getName() . ")");
            }
            
            // For frontend booking, filter out past times and minimum advance
            $min_advance_time = $this->get_minimum_advance_timestamp();
            
            // Get current time using UTC to avoid server timezone dependency
            $now_datetime = new DateTime('now', new DateTimeZone('UTC'));
            $current_time = $now_datetime->getTimestamp();
            
            // Debug logging for time calculations
            $this->debug_log("=== Time Slot Generation Debug ===");
            $this->debug_log("Date: {$date}");
            $this->debug_log("Plugin timezone: " . $plugin_timezone->getName());
            $this->debug_log("Display timezone: " . $display_timezone->getName());
            $this->debug_log("Current time (plugin): " . $now_datetime->format('Y-m-d H:i:s T'));
            $this->debug_log("Current timestamp: {$current_time}");
            $this->debug_log("Min advance timestamp: {$min_advance_time}");
            $this->debug_log("Min advance setting: " . get_option('reventorcab_min_booking_advance', '2h'));
            $this->debug_log("Working hours: {$working_hours_start} - {$working_hours_end}");
            
            // Use granularity setting instead of appointment duration for slot generation
            $granularity = get_option('reventorcab_timeslot_granularity', 15);
            
            for ($time = $start_time; $time < $end_time; $time += ($granularity * 60)) {
                // Create slot datetime in display timezone for user display
                $slot_datetime_display = new DateTime('@' . $time);
                $slot_datetime_display->setTimezone($display_timezone);
                $slot_time_display = $slot_datetime_display->format('H:i');
                
                // Create slot datetime in plugin timezone for calculations (conflict checking, etc.)
                $slot_datetime_plugin = new DateTime('@' . $time);
                $slot_datetime_plugin->setTimezone($plugin_timezone);
                
                // Debug logging for time slot filtering
                $this->debug_log("Checking slot: {$slot_time_display} (display) / " . $slot_datetime_plugin->format('H:i') . " (plugin)");
                $this->debug_log("Slot timestamp: {$time}, Current time: {$current_time}, Min advance time: {$min_advance_time}");
                $this->debug_log("Time > current: " . ($time > $current_time ? 'true' : 'false'));
                $this->debug_log("Time >= min advance: " . ($time >= $min_advance_time ? 'true' : 'false'));
                
                // Skip slots that are in the past or don't meet minimum advance time (use plugin time for calculations)
                if ($time > $current_time && $time >= $min_advance_time) {
                    $slots[] = $slot_time_display; // Store display time for frontend
                    $this->debug_log("Slot {$slot_time_display} added to available slots");
                } else {
                    $this->debug_log("Slot {$slot_time_display} filtered out");
                }
            }
        }
        
        return $slots;
    }
    
    private function get_booked_slots($date) {
        // This method is now deprecated in favor of get_caldav_conflicts()
        // to avoid duplicate processing of the same CalDAV events.
        // CalDAV conflicts are handled by get_caldav_conflicts() method
        // which properly handles timezone conversion.
        
        // Return empty array since CalDAV conflicts are handled elsewhere
        return [];
    }
    
    private function get_caldav_conflicts(string $date, array $available_slots, int $duration): array {
        $conflicts = [];
        
        // Check if CalDAV is configured
        $caldav_url = get_option('reventorcab_caldav_url', '');
        $caldav_username = get_option('reventorcab_caldav_username', '');
        $caldav_password = get_option('reventorcab_caldav_password', '');
        
        if (empty($caldav_url) || empty($caldav_username) || empty($caldav_password)) {
            return $conflicts; // CalDAV not configured
        }
        
        // Convert display timezone slots to plugin timezone slots for CalDAV conflict checking
        $plugin_timezone_slots = [];
        $plugin_timezone = new DateTimeZone($this->get_timezone_string());
        $display_timezone = $this->get_user_timezone();
        
        foreach ($available_slots as $slot) {
            // Create datetime in display timezone
            $slot_datetime_display = new DateTime($date . ' ' . $slot, $display_timezone);
            // Convert to plugin timezone
            $slot_datetime_display->setTimezone($plugin_timezone);
            $plugin_timezone_slots[] = $slot_datetime_display->format('H:i');
        }
        
        $this->debug_log("Converting slots from display timezone to plugin timezone:");
        $this->debug_log("Display timezone slots: " . implode(', ', $available_slots));
        $this->debug_log("Plugin timezone slots: " . implode(', ', $plugin_timezone_slots));
        
        // Always fetch fresh data directly from CalDAV server
        $caldav = new REVENTORCAB_CalDAV();
        $caldav_conflicts_direct = $caldav->get_conflicts($date, $plugin_timezone_slots, $duration);
        
        // Convert conflicts back to display timezone for frontend
        $display_conflicts = [];
        foreach ($caldav_conflicts_direct as $conflict_slot) {
            // Find the original display timezone slot that corresponds to this plugin timezone conflict
            $conflict_datetime_plugin = new DateTime($date . ' ' . $conflict_slot, $plugin_timezone);
            $conflict_datetime_plugin->setTimezone($display_timezone);
            $display_conflict_slot = $conflict_datetime_plugin->format('H:i');
            
            // Find the matching display slot
            foreach ($available_slots as $display_slot) {
                $display_slot_datetime = new DateTime($date . ' ' . $display_slot, $display_timezone);
                if ($display_slot_datetime->format('H:i') === $display_conflict_slot) {
                    $display_conflicts[] = $display_slot;
                    break;
                }
            }
        }
        
        $this->debug_log("Plugin timezone conflicts: " . implode(', ', $caldav_conflicts_direct));
        $this->debug_log("Display timezone conflicts: " . implode(', ', $display_conflicts));
        
        return $display_conflicts;
    }
    
    /**
     * Get user timezone for display purposes
     * This method determines the timezone used for displaying times to the user
     */
    private function get_user_timezone(): DateTimeZone {
        // For calendar display, we need to determine the user's timezone
        // This is typically passed from the frontend JavaScript
        // For now, we'll use a simple approach based on the context
        
        // Check if we're in an AJAX request and have timezone data
        if (defined('DOING_AJAX') && DOING_AJAX) {
            // Verify nonce for AJAX requests
            if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'reventorcab_frontend_nonce')) {
                $user_timezone = isset($_POST['user_timezone']) ? sanitize_text_field(wp_unslash($_POST['user_timezone'])) : null;
                if (!empty($user_timezone)) {
                    try {
                        return new DateTimeZone($user_timezone);
                    } catch (Exception $e) {
                        // Invalid timezone, fall through to default
                    }
                }
            }
        }
        
        // Fallback to plugin timezone if no user timezone is available
        // This ensures consistent behavior when user timezone detection fails
        return new DateTimeZone($this->get_timezone_string());
    }
    

    
    private function is_working_day($date) {
        $working_days = get_option('reventorcab_working_days', array('monday', 'tuesday', 'wednesday', 'thursday', 'friday'));
        
        // Get timezone setting and calculate day of week in that timezone
        $timezone_string = $this->get_timezone_string();
        $timezone = new DateTimeZone($timezone_string);
        $date_obj = new DateTime($date, $timezone);
        $day_of_week = strtolower($date_obj->format('l'));
        
        $result = in_array($day_of_week, $working_days);
        
        return $result;
    }
    
    private function meets_minimum_advance($date) {
        $min_advance_time = $this->get_minimum_advance_timestamp();
        
        // For same-day bookings, check if any time slots in the day could meet the minimum advance
        // Instead of checking midnight (00:00:00), check the end of the day (23:59:59)
        $date_end_timestamp = strtotime($date . ' 23:59:59');
        
        return $date_end_timestamp >= $min_advance_time;
    }
    
    private function get_minimum_advance_timestamp() {
        $min_booking_advance = get_option('reventorcab_min_booking_advance', '2h');
        

        
        // Get timezone setting and current time in that timezone
        $timezone_string = $this->get_timezone_string();
        $timezone = new DateTimeZone($timezone_string);
        $now_datetime = new DateTime('now', $timezone);
        $now = $now_datetime->getTimestamp();
        
        // Debug logging for minimum advance calculation
        $this->debug_log("=== Minimum Advance Calculation ===");
        $this->debug_log("Min booking advance setting: {$min_booking_advance}");
        $this->debug_log("Current time: " . $now_datetime->format('Y-m-d H:i:s T'));
        $this->debug_log("Current timestamp: {$now}");
        
        switch ($min_booking_advance) {
            case '5min':
                $result = $now + (5 * 60); // 5 minutes for testing
                break;
            case '1h':
                $result = $now + (1 * 60 * 60);
                break;
            case '2h':
                $result = $now + (2 * 60 * 60);
                break;
            case '4h':
                $result = $now + (4 * 60 * 60);
                break;
            case 'next_day':
                $result = strtotime('tomorrow', $now);
                break;
            default:
                $result = $now + (2 * 60 * 60); // Default to 2 hours
                break;
        }
        
        $this->debug_log("Min advance timestamp: {$result}");
        $min_advance_datetime = new DateTime('@' . $result);
        $min_advance_datetime->setTimezone($timezone);
        $this->debug_log("Min advance time: " . $min_advance_datetime->format('Y-m-d H:i:s T'));
        
        return $result;
    }
    
    public function book_appointment(): void {
        check_ajax_referer('reventorcab_frontend_nonce', 'nonce');
        
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $appointment_type = isset($_POST['appointment_type']) ? sanitize_text_field(wp_unslash($_POST['appointment_type'])) : ''; // Now clean name only
        $appointment_duration = isset($_POST['appointment_duration']) ? intval(wp_unslash($_POST['appointment_duration'])) : get_option('reventorcab_timeslot_duration', 30);
        

        $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';
        $time = isset($_POST['time']) ? sanitize_text_field(wp_unslash($_POST['time'])) : '';
        $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';
        
        // Get user timezone information
        $user_timezone = isset($_POST['user_timezone']) ? sanitize_text_field(wp_unslash($_POST['user_timezone'])) : null;
        $user_timezone_offset = isset($_POST['user_timezone_offset']) ? intval(wp_unslash($_POST['user_timezone_offset'])) : null;
        
        // No need to parse JSON anymore - appointment_type is now clean
        $appointment_type_name = $appointment_type;
        
        // Validate required fields
        if (empty($name) || empty($email) || empty($appointment_type) || empty($date) || empty($time)) {
            wp_send_json_error(['message' => __('Please fill in all required fields.', 'reventor-calendar-appointment-booking')]);
        }
        
        // Validate email
        if (!is_email($email)) {
            wp_send_json_error(['message' => __('Please enter a valid email address.', 'reventor-calendar-appointment-booking')]);
        }
        
        // Check if user confirmed timezone usage
        $timezone_confirmed = isset($_POST['timezone_confirmed']) && sanitize_text_field(wp_unslash($_POST['timezone_confirmed']));
        
        // Validate timezone - user timezone is mandatory
        if (empty($user_timezone)) {
            // Check if plugin has a default timezone setting
            $plugin_timezone = get_option('reventorcab_timezone');
            if (empty($plugin_timezone)) {
                wp_send_json_error([
                    'message' => __('Timezone detection failed. Please ensure JavaScript is enabled and try again.', 'reventor-calendar-appointment-booking'),
                    'timezone_error' => true
                ]);
            } else {
                // Only show warning if not already confirmed
                if (!$timezone_confirmed) {
                    wp_send_json_error([
                        /* translators: %s: timezone name */
                        'message' => sprintf(__('Timezone detection failed. Times will be shown in %s timezone. Do you want to continue?', 'reventor-calendar-appointment-booking'), $plugin_timezone),
                        'timezone_warning' => true,
                        'fallback_timezone' => $plugin_timezone
                    ]);
                }
                // If confirmed, use the plugin timezone
                $user_timezone = $plugin_timezone;
            }
        }
        
        // Validate that the provided timezone is valid
        try {
            new DateTimeZone($user_timezone);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('Invalid timezone detected. Please refresh the page and try again.', 'reventor-calendar-appointment-booking'),
                'timezone_error' => true
            ]);
        }
        
        // Check if slot is still available - check both database and CalDAV conflicts
        $booked_slots = $this->get_booked_slots($date);
        if (in_array($time, $booked_slots)) {
            wp_send_json_error(['message' => __('This time slot is no longer available.', 'reventor-calendar-appointment-booking')]);
        }
        
        // Also check for CalDAV conflicts
        $all_slots = [$time]; // We only need to check this specific time slot
        $caldav_conflicts = $this->get_caldav_conflicts($date, $all_slots, $appointment_duration);
        if (in_array($time, $caldav_conflicts)) {
            wp_send_json_error(['message' => __('This time slot conflicts with an existing calendar event.', 'reventor-calendar-appointment-booking')]);
        }
        
        // Create CalDAV event (no database storage)
        $caldav_url = get_option('reventorcab_caldav_url', '');
        $caldav_username = get_option('reventorcab_caldav_username', '');
        $caldav_password = get_option('reventorcab_caldav_password', '');
        
        if (empty($caldav_url) || empty($caldav_username) || empty($caldav_password)) {
            wp_send_json_error(['message' => __('CalDAV is not configured. Appointments can only be stored in CalDAV calendar.', 'reventor-calendar-appointment-booking')]);
        }
        
        $caldav = new REVENTORCAB_CalDAV();
        
        // Generate appointment ID for tracking
        $appointment_id = uniqid('reventorcab_', true);
            
        // Generate Jitsi Meet room ID and URL
        $jitsi_room_id = reventorcab_generate_jitsi_room_id();
        $jitsi_url = 'https://meet.jit.si/' . $jitsi_room_id;
        
        // Prepare appointment data for CalDAV and action hook
        $appointment_data = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'appointment_type' => $appointment_type_name,
            'appointment_date' => $date,
            'appointment_time' => $time,
            'appointment_duration' => $appointment_duration,
            'notes' => $notes,
            'jitsi_url' => $jitsi_url,
            'user_timezone' => $user_timezone,
            'user_timezone_offset' => $user_timezone_offset
        ];
        
        // Create CalDAV event (required for booking)
        $caldav_result = $caldav->create_event($appointment_data);
        
        if ($caldav_result) {
            // Fire action hook for other plugins/themes to use
            do_action('reventorcab_appointment_booked', $appointment_id, $appointment_data);
            
            // Send confirmation email with ICS attachment
            if (function_exists('reventorcab_send_appointment_confirmation_email')) {
                $email_sent = reventorcab_send_appointment_confirmation_email($appointment_id, $appointment_data);
                if (!$email_sent) {
                    // Email sending failed but don't fail the booking
                }
            }
            
            wp_send_json_success(['message' => __('Appointment booked successfully!', 'reventor-calendar-appointment-booking')]);
        } else {
            wp_send_json_error(['message' => __('Error creating appointment in CalDAV calendar. Please try again.', 'reventor-calendar-appointment-booking')]);
        }
    }
    
    public function sync_calendar(): void {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'reventorcab_frontend_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'reventor-calendar-appointment-booking')]);
            return;
        }
        
        // Check if CalDAV is configured
        $caldav_url = get_option('reventorcab_caldav_url', '');
        $caldav_username = get_option('reventorcab_caldav_username', '');
        $caldav_password = get_option('reventorcab_caldav_password', '');
        
        if (empty($caldav_url) || empty($caldav_username) || empty($caldav_password)) {
            wp_send_json_success(['message' => __('CalDAV not configured, no sync needed.', 'reventor-calendar-appointment-booking')]);
            return;
        }
        
        // Initialize CalDAV class
        $caldav = new REVENTORCAB_CalDAV();
        
        try {
            // Test connection
            if (!$caldav->test_connection()) {
                wp_send_json_error(['message' => __('Failed to connect to CalDAV server.', 'reventor-calendar-appointment-booking')]);
                return;
            }
            
            // Since we fetch directly from CalDAV, no sync storage is needed
            wp_send_json_success(['message' => __('CalDAV connection verified. Real-time fetching is active.', 'reventor-calendar-appointment-booking')]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => __('CalDAV connection test failed: ', 'reventor-calendar-appointment-booking') . $e->getMessage()]);
        }
    }
    
    private function debug_log($message) {
        reventorcab_log($message);
    }
    
    /**
     * Get timezone string - SIMPLIFIED VERSION
     * Forces use of reventorcab_timezone only to ensure consistency across servers
     */
    private function get_timezone_string() {
        // ONLY use the custom timezone setting - no fallbacks
        $custom_timezone = get_option('reventorcab_timezone');
        
        // If reventorcab_timezone is set, use it exclusively
        if (!empty($custom_timezone)) {
            $this->debug_log("Using reventorcab_timezone (forced): " . $custom_timezone);
            return $custom_timezone;
        }
        
        // If not set, return UTC and log a warning
        $this->debug_log("REVENTORCAB Warning: reventorcab_timezone not set. Using UTC fallback.");
        return 'UTC';
    }
}