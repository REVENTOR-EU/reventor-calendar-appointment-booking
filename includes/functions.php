<?php
/**
 * Helper functions for REVENTOR Calendar Appointment Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get formatted appointment types for display
 */
function rcab_get_appointment_types(): array {
    $types = get_option('rcab_appointment_types', [__('General Consultation', 'reventor-calendar-appointment-booking')]);
    return is_array($types) ? $types : [__('General Consultation', 'reventor-calendar-appointment-booking')];
}

/**
 * Get plugin settings
 */
function rcab_get_settings(): array {
    return [
        'timeslot_duration' => get_option('rcab_timeslot_duration', 30),
        'booking_days_ahead' => get_option('rcab_booking_days_ahead', 7),
        'theme_color' => get_option('rcab_theme_color', '#007cba'),
        'appointment_types' => rcab_get_appointment_types(),
        'caldav_url' => get_option('rcab_caldav_url', ''),
        'caldav_username' => get_option('rcab_caldav_username', ''),
        'caldav_password' => get_option('rcab_caldav_password', '')
    ];
}

/**
 * Get the current time format setting
 */
function rcab_get_time_format(): string {
    return get_option('rcab_time_format', '24h');
}

/**
 * Get the current date format setting
 */
function rcab_get_date_format(): string {
    return get_option('rcab_date_format', 'DD.MM.YYYY');
}

/**
 * Format date according to the selected date format setting
 */
function rcab_format_date(string|int $date, ?string $format = null, ?string $timezone = null): string {
    $format ??= get_option('rcab_date_format', 'DD.MM.YYYY');
    $timezone ??= rcab_get_timezone_string();
    
    try {
        $tz = new DateTimeZone($timezone);
        if (is_numeric($date)) {
            // Always start from UTC timestamp to avoid server timezone dependency
            $datetime = new DateTime('@' . $date, new DateTimeZone('UTC'));
            $datetime->setTimezone($tz);
        } else {
            // Parse date string in UTC to avoid server timezone dependency
            $datetime = new DateTime($date, new DateTimeZone('UTC'));
            $datetime->setTimezone($tz);
        }
        
        return match($format) {
            'MM/DD/YYYY' => $datetime->format('m/d/Y'),
            'YYYY-MM-DD' => $datetime->format('Y-m-d'),
            'DD/MM/YYYY' => $datetime->format('d/m/Y'),
            'DD.MM.YYYY' => $datetime->format('d.m.Y'),
            default => $datetime->format('d.m.Y')
        };
    } catch (Exception $e) {
        // Fallback to UTC-based formatting to avoid server timezone dependency
        if (is_numeric($date)) {
            $datetime = new DateTime('@' . $date, new DateTimeZone('UTC'));
        } else {
            $datetime = new DateTime($date, new DateTimeZone('UTC'));
        }
        return match($format) {
            'MM/DD/YYYY' => $datetime->format('m/d/Y'),
            'YYYY-MM-DD' => $datetime->format('Y-m-d'),
            'DD/MM/YYYY' => $datetime->format('d/m/Y'),
            'DD.MM.YYYY' => $datetime->format('d.m.Y'),
            default => $datetime->format('d.m.Y')
        };
    }
}

/**
 * Format time according to the selected time format setting
 */
function rcab_format_time(string $time, ?string $format = null, ?string $timezone = null, ?string $date = null): string {
    $format ??= get_option('rcab_time_format', '24h');
    $timezone ??= rcab_get_timezone_string();
    
    try {
        $tz = new DateTimeZone($timezone);
        
        // If we have a date, combine it with time for proper timezone conversion
        if ($date) {
            // Parse in UTC first to avoid server timezone dependency
            $datetime = new DateTime($date . ' ' . $time, new DateTimeZone('UTC'));
            $datetime->setTimezone($tz);
        } else {
            // For time-only, assume today's date in UTC first
            $datetime = new DateTime('today ' . $time, new DateTimeZone('UTC'));
            $datetime->setTimezone($tz);
        }
        
        return match($format) {
            '12h' => $datetime->format('g:i A'),
            default => $datetime->format('H:i')
        };
    } catch (Exception $e) {
        // Fallback to UTC-based formatting to avoid server timezone dependency
        try {
            if ($date) {
                $datetime = new DateTime($date . ' ' . $time, new DateTimeZone('UTC'));
            } else {
                $datetime = new DateTime('today ' . $time, new DateTimeZone('UTC'));
            }
            return match($format) {
                '12h' => $datetime->format('g:i A'),
                default => $datetime->format('H:i')
            };
        } catch (Exception $e2) {
            // Final fallback - return time as-is
            return $time;
        }
    }
}

/**
 * Get available booking dates
 */
function rcab_get_available_dates() {
    $booking_days_ahead = get_option('rcab_booking_days_ahead', 7);
    $working_days = get_option('rcab_working_days', array('monday', 'tuesday', 'wednesday', 'thursday', 'friday'));
    $min_booking_advance = get_option('rcab_min_booking_advance', '2h');
    
    // Calculate minimum advance timestamp using UTC as base to avoid timezone dependency
    $timezone_string = rcab_get_timezone_string();
    try {
        $timezone = new DateTimeZone($timezone_string);
        // Always use UTC for 'now' to avoid server timezone dependency
        $now_datetime = new DateTime('now', new DateTimeZone('UTC'));
        $now = $now_datetime->getTimestamp();
    } catch (Exception $e) {
        // Log error and throw exception - no fallback to ensure consistent behavior
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EAB Critical Error: Invalid timezone "' . $timezone_string . '" in rcab_get_available_dates(): ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
        throw new Exception('EAB timezone configuration error: ' . esc_html($e->getMessage()));
    }
    
    switch ($min_booking_advance) {
        case '1h':
            $min_advance_time = $now + (1 * 60 * 60);
            break;
        case '2h':
            $min_advance_time = $now + (2 * 60 * 60);
            break;
        case '4h':
            $min_advance_time = $now + (4 * 60 * 60);
            break;
        case 'next_day':
            // Use UTC-based calculation to avoid server timezone dependency
            $tomorrow_utc = new DateTime('tomorrow', new DateTimeZone('UTC'));
            $min_advance_time = $tomorrow_utc->getTimestamp();
            break;
        default:
            $min_advance_time = $now + (2 * 60 * 60);
            break;
    }
    
    $dates = array();
    
    // Check only within the booking_days_ahead period - use plugin timezone for consistency
    for ($i = 0; $i < $booking_days_ahead; $i++) {
        // Create date in plugin timezone
        $date_datetime = clone $now_datetime;
        $date_datetime->modify("+{$i} days");
        $date = $date_datetime->format('Y-m-d');
        $date_timestamp = $date_datetime->getTimestamp();
        $day_of_week = strtolower($date_datetime->format('l'));
        
        // Check if any time slots in the day could meet the minimum advance time
        // Use UTC-based calculation to avoid server timezone dependency
        $date_end_utc = new DateTime($date . ' 23:59:59', new DateTimeZone('UTC'));
        $date_end_timestamp = $date_end_utc->getTimestamp();
        if ($date_end_timestamp < $min_advance_time) {
            continue; // Skip this date if no time slots would be available
        }
        
        // Check if date is a working day and has available time slots
        if (in_array($day_of_week, $working_days) && rcab_date_has_available_slots($date)) {
            $dates[] = array(
                'value' => $date,
                'label' => rcab_format_date($date)
            );
        }
    }
    
    return $dates;
}

/**
 * Validate appointment data
 */
function rcab_validate_appointment_data(array $data): array {
    $errors = [];
    
    // Required fields
    $required_fields = ['name', 'email', 'appointment_type', 'appointment_date', 'appointment_time'];
    
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            // translators: %s is the field name
            $errors[] = sprintf(__('The %s field is required.', 'reventor-calendar-appointment-booking'), $field);
        }
    }
    
    // Email validation
    if (!empty($data['email']) && !is_email($data['email'])) {
        $errors[] = __('Please enter a valid email address.', 'reventor-calendar-appointment-booking');
    }
    
    // Date validation
    if (!empty($data['appointment_date'])) {
        $date = strtotime($data['appointment_date']);
        $today = strtotime('today');
        $max_date = strtotime('+' . get_option('rcab_booking_days_ahead', 30) . ' days');
        
        if ($date <= $today) {
            $errors[] = __('Appointment date must be in the future.', 'reventor-calendar-appointment-booking');
        }
        
        if ($date > $max_date) {
            $errors[] = __('Appointment date is too far in the future.', 'reventor-calendar-appointment-booking');
        }
    }
    
    // Time validation
    if (!empty($data['appointment_time'])) {
        $time_parts = explode(':', $data['appointment_time']);
        if (count($time_parts) !== 2 || !is_numeric($time_parts[0]) || !is_numeric($time_parts[1])) {
            $errors[] = __('Invalid appointment time format.', 'reventor-calendar-appointment-booking');
        }
    }
    
    return $errors;
}

/**
 * Sanitize appointment data
 */
function rcab_sanitize_appointment_data($data) {
    $sanitized = array();
    
    $sanitized['name'] = sanitize_text_field($data['name'] ?? '');
    $sanitized['email'] = sanitize_email($data['email'] ?? '');
    $sanitized['phone'] = sanitize_text_field($data['phone'] ?? '');
    $sanitized['appointment_type'] = sanitize_text_field($data['appointment_type'] ?? '');
    $sanitized['appointment_date'] = sanitize_text_field($data['appointment_date'] ?? '');
    $sanitized['appointment_time'] = sanitize_text_field($data['appointment_time'] ?? '');
    $sanitized['notes'] = sanitize_textarea_field($data['notes'] ?? '');
    
    return $sanitized;
}

/**
 * Get appointment status options
 */
function rcab_get_status_options(): array {
    return [
        'confirmed' => __('Confirmed', 'reventor-calendar-appointment-booking'),
        'pending' => __('Pending', 'reventor-calendar-appointment-booking'),
        'cancelled' => __('Cancelled', 'reventor-calendar-appointment-booking'),
        'completed' => __('Completed', 'reventor-calendar-appointment-booking')
    ];
}

/**
 * Generate unique appointment ID
 */
function rcab_generate_appointment_id(): string {
    return 'EAB-' . strtoupper(wp_generate_password(8, false));
}

/**
 * Check if time slot is available
 */
function rcab_is_time_slot_available(string $date, string $time): bool {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'rcab_appointments';
    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `{$wpdb->prefix}rcab_appointments` WHERE appointment_date = %s AND appointment_time = %s AND status = 'confirmed'",
        $date,
        $time
    ));
    
    return $count == 0;
}

/**
 * Get business hours
 */
function rcab_get_business_hours(): array {
    return apply_filters('rcab_business_hours', [
        'start' => '09:00',
        'end' => '17:00',
        'days' => [1, 2, 3, 4, 5] // Monday to Friday
    ]);
}

/**
 * Check if date is a business day
 */
function rcab_is_business_day($date) {
    $business_hours = rcab_get_business_hours();
    $day_of_week = gmdate('w', strtotime($date));
    
    return in_array($day_of_week, $business_hours['days']);
}

/**
 * Get plugin version
 */
function rcab_get_version() {
    return RCAB_VERSION;
}

/**
 * Debug logging function - enabled for troubleshooting
 */
function rcab_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('EAB Debug: ' . $message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }
}

/**
 * Get timezone string - SIMPLIFIED VERSION
 * Forces use of rcab_timezone only to ensure consistency across servers
 */
function rcab_get_timezone_string() {
    // ONLY use the custom timezone setting - no fallbacks
    $custom_timezone = get_option('rcab_timezone');
    
    // If rcab_timezone is set, use it exclusively
    if (!empty($custom_timezone)) {
        return $custom_timezone;
    }
    
    // If not set, return UTC and log a warning
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('EAB Warning: rcab_timezone not set. Please configure it in plugin settings for consistent behavior across servers.'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }
    return 'UTC';
}

/**
 * Convert time to user timezone
 */
function rcab_convert_to_user_timezone($datetime, $format = 'Y-m-d H:i:s') {
    $timezone = new DateTimeZone(rcab_get_timezone_string());
    $date = new DateTime($datetime, new DateTimeZone('UTC'));
    $date->setTimezone($timezone);
    
    return $date->format($format);
}

/**
 * Get localized month names
 */
function rcab_get_month_names() {
    global $wp_locale;
    
    $months = array();
    for ($i = 1; $i <= 12; $i++) {
        $months[$i] = $wp_locale->get_month($i);
    }
    
    return $months;
}

/**
 * Get localized day names
 */
function rcab_get_day_names() {
    global $wp_locale;
    
    $days = array();
    for ($i = 0; $i <= 6; $i++) {
        $days[$i] = $wp_locale->get_weekday($i);
    }
    
    return $days;
}

/**
 * Check if a date has available time slots
 */
function rcab_date_has_available_slots($date) {
    // Get default appointment type duration
    $appointment_types = rcab_get_appointment_types();
    $duration = 30; // Default duration
    if (!empty($appointment_types) && isset($appointment_types[0]['duration'])) {
        $duration = intval($appointment_types[0]['duration']);
    }
    
    // Check if it's a working day (using timezone-aware calculation)
    $working_days = get_option('rcab_working_days', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);
    
    // Get timezone setting - use plugin timezone for consistency
    try {
        $timezone_string = rcab_get_timezone_string();
        $timezone = new DateTimeZone($timezone_string);
    } catch (Exception $e) {
        // Log error and throw exception - no fallback to ensure consistent behavior
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EAB Critical Error: Invalid timezone "' . $timezone_string . '" in rcab_date_has_available_slots(): ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
        throw new Exception('EAB timezone configuration error: ' . esc_html($e->getMessage()));
    }
    
    $date_obj = new DateTime($date, $timezone);
    $day_of_week = strtolower($date_obj->format('l'));
    
    if (!in_array($day_of_week, $working_days)) {
        return false;
    }
    
    // Check minimum advance time using UTC as base to avoid timezone dependency
    $min_advance = get_option('rcab_min_booking_advance', '2h');
    $timezone_string = rcab_get_timezone_string();
    try {
        $timezone = new DateTimeZone($timezone_string);
        // Always use UTC for 'now' to avoid server timezone dependency
        $now_datetime = new DateTime('now', new DateTimeZone('UTC'));
        $now = $now_datetime->getTimestamp();
    } catch (Exception $e) {
        // Log error and throw exception - no fallback to ensure consistent behavior
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EAB Critical Error: Invalid timezone "' . $timezone_string . '" in rcab_has_available_slots(): ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
        throw new Exception('EAB timezone configuration error: ' . esc_html($e->getMessage()));
    }

    switch ($min_advance) {
        case '1h':
            $min_advance_time = $now + (1 * 60 * 60);
            break;
        case '4h':
            $min_advance_time = $now + (4 * 60 * 60);
            break;
        case 'next_day':
            // Use UTC-based calculation to avoid server timezone dependency
            $tomorrow_utc = new DateTime('tomorrow', new DateTimeZone('UTC'));
            $min_advance_time = $tomorrow_utc->getTimestamp();
            break;
        case '2h':
        default:
            $min_advance_time = $now + (2 * 60 * 60);
            break;
    }
    
    // For today, check if there's enough time left based on minimum advance
    $date_utc = new DateTime($date, new DateTimeZone('UTC'));
    $date_timestamp = $date_utc->getTimestamp();
    // Always use UTC for 'now' to avoid server timezone dependency
    $today_datetime = new DateTime('now', new DateTimeZone('UTC'));
    $today_utc = new DateTime($today_datetime->format('Y-m-d'), new DateTimeZone('UTC'));
    $today = $today_utc->getTimestamp();
    
    // Check if any time slots in the day could meet the minimum advance time
    // Use UTC-based calculation to avoid server timezone dependency
    $date_end_utc = new DateTime($date . ' 23:59:59', new DateTimeZone('UTC'));
    $date_end_timestamp = $date_end_utc->getTimestamp();
    if ($date_end_timestamp < $min_advance_time) {
        return false; // No time slots would be available on this date
    }
    
    // Generate time slots for this date (using same logic as AJAX handler)
    $working_hours_start = get_option('rcab_working_hours_start', '09:00');
    $working_hours_end = get_option('rcab_working_hours_end', '17:00');
    
    // Create timezone-aware datetime objects for working hours
    $start_datetime = new DateTime($date . ' ' . $working_hours_start . ':00', $timezone);
    $end_datetime = new DateTime($date . ' ' . $working_hours_end . ':00', $timezone);
    
    $start_time = $start_datetime->getTimestamp();
    $end_time = $end_datetime->getTimestamp();
    
    // Get current time using UTC to avoid server timezone dependency
    $now_datetime = new DateTime('now', new DateTimeZone('UTC'));
    $current_time = $now_datetime->getTimestamp();
    
    $available_slots = [];
    
    // Generate time slots (matching AJAX handler logic exactly)
    for ($time = $start_time; $time < $end_time; $time += ($duration * 60)) {
        // Use timezone-aware formatting
        $slot_datetime = new DateTime('@' . $time);
        $slot_datetime->setTimezone($timezone);
        $slot_time = $slot_datetime->format('H:i');
        
        // Skip slots that are in the past or don't meet minimum advance time
        if ($time > $current_time && $time >= $min_advance_time) {
            $available_slots[] = $slot_time;
        }
    }
    
    if (empty($available_slots)) {
        return false;
    }
    
    // Check for booked slots
    global $wpdb;
    $table_name = $wpdb->prefix . 'rcab_appointments';
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query required
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- Real-time booking data needed
    $booked_slots = $wpdb->get_col($wpdb->prepare(
        "SELECT appointment_time FROM `{$wpdb->prefix}rcab_appointments` WHERE appointment_date = %s AND status = 'confirmed'",
        $date
    ));
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
    
    // Check for CalDAV conflicts if configured
    $caldav_conflicts = [];
    $caldav_url = get_option('rcab_caldav_url', '');
    $caldav_username = get_option('rcab_caldav_username', '');
    $caldav_password = get_option('rcab_caldav_password', '');
    
    if (!empty($caldav_url) && !empty($caldav_username) && !empty($caldav_password)) {
        // Always fetch fresh data directly from CalDAV server
        if (class_exists('RCAB_CalDAV')) {
            $caldav = new RCAB_CalDAV();
            $caldav_conflicts = $caldav->get_conflicts($date, $available_slots, $duration);
        }
    }
    
    // Calculate final available slots
    $final_available_slots = array_diff($available_slots, $booked_slots, $caldav_conflicts);
    
    return !empty($final_available_slots);
}