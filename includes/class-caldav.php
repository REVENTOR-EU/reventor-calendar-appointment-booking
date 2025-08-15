<?php
/**
 * CalDAV integration for REVENTOR Calendar Appointment Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RCAB_CalDAV {
    
    private $caldav_url = '';
    private $username = '';
    private $password = '';
    
    public function __construct() {
        $this->caldav_url = get_option('rcab_caldav_url', '');
        $this->username = get_option('rcab_caldav_username', '');
        $this->password = get_option('rcab_caldav_password', '');
    }
    
    public function test_connection($url = null, $username = null, $password = null) {
        $test_url = $url ?: $this->caldav_url;
        $test_username = $username ?: $this->username;
        $test_password = $password ?: $this->password;
        
        if (empty($test_url) || empty($test_username) || empty($test_password)) {
            return array('success' => false, 'message' => 'Please fill in all CalDAV fields (URL, username, and password).');
        }
        
        $response = wp_remote_request($test_url, array(
            'method' => 'PROPFIND',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($test_username . ':' . $test_password),
                'Content-Type' => 'application/xml; charset=utf-8',
                'Depth' => '0'
            ),
            'body' => '<?xml version="1.0" encoding="utf-8" ?><D:propfind xmlns:D="DAV:"><D:prop><D:displayname/></D:prop></D:propfind>',
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => 'Connection failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if (in_array($response_code, array(200, 207))) {
            return array('success' => true, 'message' => 'CalDAV connection successful!');
        } elseif ($response_code === 404) {
            return array('success' => true, 'message' => 'CalDAV connection successful! (Calendar endpoint found)');
        } elseif ($response_code === 401) {
            return array('success' => false, 'message' => 'Authentication failed. Please check your username and password.');
        } elseif ($response_code === 403) {
            return array('success' => false, 'message' => 'Access forbidden. Please check your permissions.');
        } else {
            return array('success' => false, 'message' => 'Connection failed with HTTP status: ' . $response_code);
        }
    }
    
    public function get_conflicts($date, $time_slots, $duration) {
        if (empty($this->caldav_url) || empty($this->username) || empty($this->password)) {
            return array();
        }
        
        $conflicts = array();
        $events = $this->get_events_for_date($date);
        
        // Force logging for debugging
        rcab_log("CalDAV get_conflicts called for date: $date with " . count($time_slots) . " time slots");
        rcab_log("Time slots: " . implode(', ', $time_slots));
        rcab_log('Date: ' . $date);
        rcab_log('Found ' . count($events) . ' events');
        foreach ($events as $i => $event) {
            rcab_log('Event ' . $i . ': ' . gmdate('Y-m-d H:i:s', $event['start']) . ' to ' . gmdate('Y-m-d H:i:s', $event['end']) . ' (' . (isset($event['summary']) ? $event['summary'] : 'No summary') . ')');
        }
        
        if (empty($events)) {
            return $conflicts;
        }
        
        foreach ($time_slots as $slot) {
            // Use plugin timezone for consistent slot calculations
            $plugin_timezone = new DateTimeZone($this->get_timezone_string());
            $slot_datetime = new DateTime($date . ' ' . $slot, $plugin_timezone);
            $slot_start = $slot_datetime->getTimestamp();
            $slot_end = $slot_start + ($duration * 60);
            
            // Debug logging for specific slot
            if (defined('WP_DEBUG') && WP_DEBUG && $slot === '16:30') {
                $this->debug_log('Checking slot 16:30: ' . gmdate('Y-m-d H:i:s', $slot_start) . ' to ' . gmdate('Y-m-d H:i:s', $slot_end));
            }
            
            // Check for conflicts using configured granularity
            $has_conflict = false;
            $granularity = get_option('rcab_timeslot_granularity', 15);
            
            // Break the booking slot into granularity blocks and check each one
            $current_block = $slot_start;
            while ($current_block < $slot_end) {
                $block_end = $current_block + ($granularity * 60); // granularity block
                
                foreach ($events as $event) {
                    if ($this->times_overlap($current_block, $block_end, $event['start'], $event['end'])) {
                        $conflicts[] = $slot;
                        $has_conflict = true;
                        if (defined('WP_DEBUG') && WP_DEBUG && $slot === '16:30') {
                            $this->debug_log('CONFLICT FOUND for 16:30 with event: ' . gmdate('Y-m-d H:i:s', $event['start']) . ' to ' . gmdate('Y-m-d H:i:s', $event['end']) . ' in ' . $granularity . '-min block: ' . gmdate('H:i', $current_block) . '-' . gmdate('H:i', $block_end));
                        }
                        break 2; // Break out of both loops
                    }
                }
                
                $current_block += ($granularity * 60); // Move to next granularity block
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->debug_log('Total conflicts found: ' . count($conflicts) . ' (' . implode(', ', $conflicts) . ')');
        }
        
        return $conflicts;
    }
    
    private function get_events_for_date($date) {
        $start_date = $date . 'T00:00:00Z';
        $end_date = $date . 'T23:59:59Z';

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->debug_log('Fetching events for date range: ' . $start_date . ' to ' . $end_date);
            $this->debug_log('CalDAV URL: ' . $this->caldav_url);
        }

        $report_body = '<?xml version="1.0" encoding="utf-8" ?>
<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
  <D:prop>
    <D:getetag />
    <C:calendar-data />
  </D:prop>
  <C:filter>
    <C:comp-filter name="VCALENDAR">
      <C:comp-filter name="VEVENT">
        <C:time-range start="' . $start_date . '" end="' . $end_date . '"/>
      </C:comp-filter>
    </C:comp-filter>
  </C:filter>
</C:calendar-query>';

        $response = wp_remote_request($this->caldav_url, array(
            'method' => 'REPORT',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
                'Content-Type' => 'application/xml; charset=utf-8',
                'Depth' => '1'
            ),
            'body' => $report_body,
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $this->debug_log('Request failed: ' . $response->get_error_message());
            }
            return array();
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->debug_log('Response code: ' . $response_code);
        }
        
        if ($response_code !== 207) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $this->debug_log('Unexpected response code: ' . $response_code);
                $body = wp_remote_retrieve_body($response);
                $this->debug_log('Response body length: ' . strlen($body));
                $this->debug_log('Response body preview: ' . substr($body, 0, 500));
            }
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->debug_log('Response body length: ' . strlen($body));
            $this->debug_log('Response body preview: ' . substr($body, 0, 500));
        }
        
        return $this->parse_calendar_events($body, $date);
    }
    
    private function parse_calendar_events($xml_data, $date) {
        $events = array();
        
        // Simple XML parsing for CalDAV response
        if (empty($xml_data)) {
            return $events;
        }
        
        try {
            // Use DOMDocument for better XML parsing
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            
            // Suppress warnings and check if XML loading was successful
            if (!@$dom->loadXML($xml_data)) {
                $errors = libxml_get_errors();
                // XML parsing failed, continue silently
                libxml_clear_errors();
                return $events;
            }
            
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('D', 'DAV:');
            $xpath->registerNamespace('C', 'urn:ietf:params:xml:ns:caldav');
            
            $calendar_data_nodes = $xpath->query('//C:calendar-data');
            
            if ($calendar_data_nodes === false) {
                // XPath query failed, return empty events
                return $events;
            }
            
            foreach ($calendar_data_nodes as $node) {
                $ical_data = $node->textContent;
                $parsed_events = $this->parse_ical_events($ical_data, $date);
                $events = array_merge($events, $parsed_events);
            }
            
        } catch (Exception $e) {
            // Exception occurred, return current events
            return $events;
        }
        
        return $events;
    }
    
    private function parse_ical_events($ical_data, $date) {
        $events = array();
        
        if (empty($ical_data)) {
            return $events;
        }
        
        try {
            $lines = explode("\n", str_replace("\r\n", "\n", $ical_data));
            $in_event = false;
            $current_event = array();
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                if ($line === 'BEGIN:VEVENT') {
                    $in_event = true;
                    $current_event = array();
                } elseif ($line === 'END:VEVENT' && $in_event) {
                    if (!empty($current_event['start']) && !empty($current_event['end'])) {
                        $events[] = $current_event;
                    }
                    $in_event = false;
                } elseif ($in_event) {
                    if (strpos($line, 'DTSTART') === 0) {
                        $current_event['start'] = $this->parse_ical_datetime($line);
                    } elseif (strpos($line, 'DTEND') === 0) {
                        $current_event['end'] = $this->parse_ical_datetime($line);
                    } elseif (strpos($line, 'SUMMARY') === 0) {
                        $current_event['summary'] = substr($line, 8); // Remove 'SUMMARY:'
                    }
                }
            }
            
        } catch (Exception $e) {
            // Exception occurred, return current events
            return $events;
        }
        
        return $events;
    }
    
    private function parse_ical_datetime($line) {
        try {
            // Extract datetime value from iCal line
            $parts = explode(':', $line, 2);
            if (count($parts) < 2) {
                return null;
            }
            
            $datetime_str = trim($parts[1]);
            
            if (empty($datetime_str)) {
                return null;
            }
            
            $timestamp = null;
            $is_utc = false;
            
            // Handle different datetime formats - always use UTC to avoid server timezone dependency
            if (strlen($datetime_str) === 8) {
                // Date only format: YYYYMMDD
                $date_formatted = substr($datetime_str, 0, 4) . '-' . substr($datetime_str, 4, 2) . '-' . substr($datetime_str, 6, 2);
                $datetime_obj = new DateTime($date_formatted, new DateTimeZone('UTC'));
                $timestamp = $datetime_obj->getTimestamp();
            } elseif (strlen($datetime_str) === 15 && substr($datetime_str, -1) === 'Z') {
                // UTC format: YYYYMMDDTHHMMSSZ
                $date_part = substr($datetime_str, 0, 8);
                $time_part = substr($datetime_str, 9, 6);
                $formatted = substr($date_part, 0, 4) . '-' . substr($date_part, 4, 2) . '-' . substr($date_part, 6, 2) . ' ' .
                            substr($time_part, 0, 2) . ':' . substr($time_part, 2, 2) . ':' . substr($time_part, 4, 2);
                $datetime_obj = new DateTime($formatted, new DateTimeZone('UTC'));
                $timestamp = $datetime_obj->getTimestamp();
                $is_utc = true;
            } else {
                // Try to parse as-is in UTC
                try {
                    $datetime_obj = new DateTime($datetime_str, new DateTimeZone('UTC'));
                    $timestamp = $datetime_obj->getTimestamp();
                } catch (Exception $e) {
                    $timestamp = false;
                }
            }
            
            // Validate the timestamp
            if ($timestamp === false || $timestamp === -1) {
                // Failed to parse datetime, return null
                return null;
            }
            
            // For conflict detection, we need to compare times in the same timezone
            // Keep UTC timestamps for consistent comparison across all dates
            if ($is_utc) {
                $local_timezone = $this->get_timezone_string();
                rcab_log('CalDAV timezone conversion - Original UTC timestamp: ' . $timestamp . ' (' . gmdate('Y-m-d H:i:s', $timestamp) . ' UTC)');
                rcab_log('CalDAV timezone conversion - Target timezone: ' . $local_timezone);
                
                // For conflict detection, we convert the UTC time to local time format
                // but keep it as a timestamp for consistent comparison
                try {
                    $utc_datetime = new DateTime('@' . $timestamp);
                    $local_timezone_obj = new DateTimeZone($local_timezone);
                    $utc_datetime->setTimezone($local_timezone_obj);
                    
                    // Create a new timestamp that represents the local time
                    // This ensures consistent conflict detection across all dates
                    $local_time_str = $utc_datetime->format('Y-m-d H:i:s');
                    $local_datetime_obj = new DateTime($local_time_str, new DateTimeZone('UTC'));
                    $timestamp = $local_datetime_obj->getTimestamp();
                    
                    rcab_log('CalDAV timezone conversion - Local time: ' . $local_time_str);
                    rcab_log('CalDAV timezone conversion - Converted timestamp: ' . $timestamp . ' (' . gmdate('Y-m-d H:i:s', $timestamp) . ' UTC)');
                } catch (Exception $e) {
                    // If timezone conversion fails, keep original timestamp
                    rcab_log('CalDAV timezone conversion failed: ' . $e->getMessage());
                }
            }
            
            return $timestamp;
            
        } catch (Exception $e) {
            // Exception occurred, return null
            return null;
        }
    }
    
    private function times_overlap($start1, $end1, $start2, $end2) {
        return ($start1 < $end2) && ($end1 > $start2);
    }
    
    public function create_event($appointment_data) {
        if (empty($this->caldav_url) || empty($this->username) || empty($this->password)) {
            return false;
        }
        
        // Always use user timezone - no fallback to server timezone
        $user_timezone = isset($appointment_data['user_timezone']) ? $appointment_data['user_timezone'] : null;
        
        if (empty($user_timezone)) {
            // If no user timezone, use plugin setting as confirmed by user
            $user_timezone = get_option('rcab_timezone');
            if (empty($user_timezone)) {
                // This should not happen if frontend validation works correctly
                // No timezone available - appointment creation fails
                return false;
            }
        }
        
        try {
            // Create timezone object using user timezone
            $timezone = new DateTimeZone($user_timezone);
            
            // Create DateTime objects in the user's timezone
            $start_datetime_obj = new DateTime($appointment_data['appointment_date'] . ' ' . $appointment_data['appointment_time'], $timezone);
            $duration = isset($appointment_data['appointment_duration']) ? $appointment_data['appointment_duration'] : get_option('rcab_timeslot_duration', 30);
            
            // Calculate end time
            $end_datetime_obj = clone $start_datetime_obj;
            $end_datetime_obj->add(new DateInterval('PT' . $duration . 'M'));
            
            // Convert to UTC for CalDAV compatibility
            $start_datetime_obj->setTimezone(new DateTimeZone('UTC'));
            $end_datetime_obj->setTimezone(new DateTimeZone('UTC'));
            
            // Format as UTC timestamps
            $start_datetime = $start_datetime_obj->format('Ymd\THis\Z');
            $end_datetime = $end_datetime_obj->format('Ymd\THis\Z');
            $use_timezone = false;
        } catch (Exception $e) {
            // No fallback - if timezone fails, the appointment creation fails
            // Timezone processing failed - appointment creation fails
            return false;
        }
        
        // Event creation parameters prepared
        
        $uid = 'eab-' . md5($appointment_data['appointment_date'] . $appointment_data['appointment_time'] . $appointment_data['appointment_type']);
        $summary = $appointment_data['appointment_type'] . ' - ' . $appointment_data['name'];
        
        // Build description with same details as ICS file
        $description = 'Name: ' . $appointment_data['name'] . '\n';
        $description .= 'Email: ' . $appointment_data['email'] . '\n';
        $phone = !empty($appointment_data['phone']) ? $appointment_data['phone'] : '---';
        $description .= 'Phone: ' . $phone . '\n';
        if (!empty($appointment_data['jitsi_url'])) {
            $description .= 'Video Meeting: ' . $appointment_data['jitsi_url'] . '\n';
        }
        $description .= 'Type: ' . $appointment_data['appointment_type'] . '\n';
        $description .= 'Date: ' . $appointment_data['appointment_date'] . '\n';
        $description .= 'Time: ' . $appointment_data['appointment_time'];
        if (!empty($appointment_data['notes'])) {
            $description .= '\nNotes: ' . $appointment_data['notes'];
        }
        
        // Set location to Jitsi URL if available
        $location = !empty($appointment_data['jitsi_url']) ? $appointment_data['jitsi_url'] : 'Online Meeting';
        
        $ical_content = "BEGIN:VCALENDAR\r\n";
        $ical_content .= "VERSION:2.0\r\n";
        $ical_content .= "PRODID:-//REVENTOR.EU//REVENTOR Calendar Appointment Booking//EN\r\n";
        
        // No VTIMEZONE component - using UTC timestamps for maximum compatibility
        
        $ical_content .= "BEGIN:VEVENT\r\n";
        $ical_content .= "UID:" . $uid . "\r\n";
        
        // Always use UTC timestamps for CalDAV compatibility
        $ical_content .= "DTSTART:" . $start_datetime . "\r\n";
        $ical_content .= "DTEND:" . $end_datetime . "\r\n";
        
        $ical_content .= "SUMMARY:" . $summary . "\r\n";
        $ical_content .= "DESCRIPTION:" . $description . "\r\n";
        $ical_content .= "LOCATION:" . $location . "\r\n";
        if (!empty($appointment_data['jitsi_url'])) {
            $ical_content .= "URL:" . $appointment_data['jitsi_url'] . "\r\n";
        }
        $ical_content .= "STATUS:CONFIRMED\r\n";
        $ical_content .= "END:VEVENT\r\n";
        $ical_content .= "END:VCALENDAR\r\n";
        
        $event_url = rtrim($this->caldav_url, '/') . '/' . $uid . '.ics';
        
        $response = wp_remote_request($event_url, array(
            'method' => 'PUT',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
                'Content-Type' => 'text/calendar; charset=utf-8'
            ),
            'body' => $ical_content,
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        return in_array($response_code, array(200, 201, 204));
    }
    
    public function update_event($appointment_data, $old_appointment_data = null) {
        if (empty($this->caldav_url) || empty($this->username) || empty($this->password)) {
            return false;
        }
        
        // For updates, we need to delete the old event and create a new one
        // since we don't store the original UID
        if ($old_appointment_data) {
            $this->delete_event($old_appointment_data);
        }
        
        return $this->create_event($appointment_data);
    }
    
    public function delete_event($appointment_data) {
        if (empty($this->caldav_url) || empty($this->username) || empty($this->password)) {
            return false;
        }
        
        // Since we don't store the original UID, we need to find and delete the event
        // by searching for events on the appointment date and matching the details
        $events = $this->get_events_for_date($appointment_data['appointment_date']);
        
        foreach ($events as $event) {
            // Check if this event matches our appointment
            $event_time = gmdate('H:i', $event['start']);
            if ($event_time === $appointment_data['appointment_time'] && 
                strpos($event['summary'], $appointment_data['appointment_type']) !== false) {
                
                // Extract UID from the event (this is a simplified approach)
                $uid = 'eab-' . md5($appointment_data['appointment_date'] . $appointment_data['appointment_time'] . $appointment_data['appointment_type']);
                $event_url = rtrim($this->caldav_url, '/') . '/' . $uid . '.ics';
                
                $response = wp_remote_request($event_url, array(
                    'method' => 'DELETE',
                    'headers' => array(
                        'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password)
                    ),
                    'timeout' => 10
                ));
                
                if (!is_wp_error($response)) {
                    $response_code = wp_remote_retrieve_response_code($response);
                    return in_array($response_code, array(200, 204, 404)); // 404 is OK if already deleted
                }
            }
        }
        
        return false;
    }
    
    public function fetch_events($start_date = null, $end_date = null) {
        if (!$this->test_connection()) {
            return false;
        }
        
        // Default to current date and respect booking_days_ahead setting - use UTC
        if (!$start_date) {
            $start_date = gmdate('Y-m-d');
        }
        if (!$end_date) {
            $booking_days_ahead = get_option('rcab_booking_days_ahead', 7);
            $end_datetime = new DateTime("+{$booking_days_ahead} days", new DateTimeZone('UTC'));
            $end_date = $end_datetime->format('Y-m-d');
        }
        
        $events = array();
        
        try {
            // Use existing get_events_for_date method for each date in range
            $current_date = $start_date;
            while ($current_date <= $end_date) {
                $daily_events = $this->get_events_for_date($current_date);
                $events = array_merge($events, $daily_events);
                $current_datetime = new DateTime($current_date . ' +1 day', new DateTimeZone('UTC'));
                $current_date = $current_datetime->format('Y-m-d');
            }
        } catch (Exception $e) {
            return false;
        }
        
        return $events;
    }
    
    // Note: sync_events method removed - we now fetch directly from CalDAV server
    // This eliminates the need for local storage and ensures real-time conflict checking
    
    /**
     * Custom debug logging to plugin-specific log file
     * Function enabled for debugging
     */
    private function debug_log($message) {
        // Enable CalDAV debug logging temporarily
        rcab_log('CalDAV Debug: ' . $message);
    }
    
    /**
     * Get booked time slots for a specific date from CalDAV calendar
     */
    public function get_booked_slots_for_date($date) {
        $booked_slots = array();
        $events = $this->get_events_for_date($date);
        
        rcab_log("get_booked_slots_for_date called for date: $date with " . count($events) . " events");
        
        // Get the booking system's time slot interval from granularity setting
        $slot_interval = get_option('rcab_timeslot_granularity', 15);
        
        foreach ($events as $event) {
            // Convert event start and end times to local timezone
            $local_start = $this->convert_utc_to_local($event['start']);
            $local_end = $this->convert_utc_to_local($event['end']);
            
            rcab_log("Processing event: " . gmdate('Y-m-d H:i:s', $event['start']) . " to " . gmdate('Y-m-d H:i:s', $event['end']) . " UTC");
            
            // Create DateTime objects for proper timezone-aware formatting
            $local_start_dt = new DateTime('@' . $local_start);
            $local_end_dt = new DateTime('@' . $local_end);
            $timezone = new DateTimeZone($this->get_timezone_string());
            $local_start_dt->setTimezone($timezone);
            $local_end_dt->setTimezone($timezone);
            
            rcab_log("Local times: " . $local_start_dt->format('Y-m-d H:i:s') . " to " . $local_end_dt->format('Y-m-d H:i:s'));
            
            // Generate all time slots that this event overlaps
            $current_time = $local_start;
            $event_slots = array();
            while ($current_time < $local_end) {
                // Use DateTime for proper timezone-aware formatting
                $slot_dt = new DateTime('@' . $current_time);
                $slot_dt->setTimezone($timezone);
                $slot_time = $slot_dt->format('H:i');
                $booked_slots[] = $slot_time;
                $event_slots[] = $slot_time;
                $current_time += ($slot_interval * 60); // Move to next slot
            }
            
            rcab_log("Event blocks slots: " . implode(', ', $event_slots));
        }
        
        $unique_slots = array_unique($booked_slots);
        rcab_log("Total booked slots: " . implode(', ', $unique_slots));
        
        return $unique_slots;
    }
    
    /**
     * Convert UTC timestamp to local timezone
     */
    private function convert_utc_to_local($utc_timestamp) {
        $timezone_string = $this->get_timezone_string();
        
        try {
            $utc_date = new DateTime('@' . $utc_timestamp);
            $local_timezone = new DateTimeZone($timezone_string);
            $utc_date->setTimezone($local_timezone);
            return $utc_date->getTimestamp();
        } catch (Exception $e) {
            // If conversion fails, return original timestamp
            return $utc_timestamp;
        }
    }
    
    /**
     * Get timezone string - SIMPLIFIED VERSION
     * Forces use of rcab_timezone only to ensure consistency across servers
     */
    private function get_timezone_string() {
        // ONLY use the custom timezone setting - no fallbacks
        $custom_timezone = get_option('rcab_timezone');
        
        // If rcab_timezone is set, use it exclusively
        if (!empty($custom_timezone)) {
            return $custom_timezone;
        }
        
        // If not set, return UTC and log a warning
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EAB CalDAV Warning: rcab_timezone not set. Please configure it in plugin settings for consistent behavior across servers.'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
        return 'UTC';
    }
    
}