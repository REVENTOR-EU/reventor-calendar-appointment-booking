<?php
/**
 * Admin page template for REVENTOR Calendar Appointment Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$appointment_types = get_option('reventorcab_appointment_types', array(array('name' => __('General Consultation', 'reventor-calendar-appointment-booking'), 'duration' => 30)));
?>

<div class="wrap eab-admin-wrap">
    <h1><?php esc_html_e('REVENTOR Calendar Appointment Booking', 'reventor-calendar-appointment-booking'); ?></h1>
    
    <div class="eab-admin-content">
        <form id="eab-settings-form" method="post">
            <?php wp_nonce_field('reventorcab_admin_nonce', 'reventorcab_nonce'); ?>
            
            <div class="eab-save-header">
                <button type="button" id="eab-save-all" class="eab-floating-save">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e('Save Settings', 'reventor-calendar-appointment-booking'); ?>
                </button>
            </div>
            
            <div class="eab-settings-grid">
                <!-- Import/Export Settings -->
                <div class="eab-settings-section">
                    <h3><?php esc_html_e('Import/Export Settings', 'reventor-calendar-appointment-booking'); ?></h3>
                    
                    <div class="eab-field-group">
                        <div class="eab-export-import-buttons">
                            <button type="button" id="eab-export-settings" class="button button-secondary">
                                <span class="dashicons dashicons-download"></span>
                                <?php esc_html_e('Export Settings', 'reventor-calendar-appointment-booking'); ?>
                            </button>
                            <button type="button" id="eab-import-settings" class="button button-secondary">
                                <span class="dashicons dashicons-upload"></span>
                                <?php esc_html_e('Import Settings', 'reventor-calendar-appointment-booking'); ?>
                            </button>
                            <input type="file" id="eab-import-file" accept=".json" style="display: none;" />
                        </div>
                        <p class="description"><?php esc_html_e('Export your current settings to a JSON file or import settings from a previously exported file.', 'reventor-calendar-appointment-booking'); ?></p>
                    </div>
                </div>
                
                <!-- Quick Setup Guide -->
                <div class="eab-settings-section">
                    <h3><?php esc_html_e('Quick Setup Guide', 'reventor-calendar-appointment-booking'); ?></h3>
                    
                    <div class="eab-field-group">
                        <ol class="eab-setup-guide">
                            <li><?php esc_html_e('Configure your time slot duration and booking period', 'reventor-calendar-appointment-booking'); ?></li>
            <li><?php esc_html_e('Set up your appointment types', 'reventor-calendar-appointment-booking'); ?></li>
            <li><?php esc_html_e('Choose your theme color', 'reventor-calendar-appointment-booking'); ?></li>
            <li><?php esc_html_e('Configure email notifications', 'reventor-calendar-appointment-booking'); ?></li>
            <li><?php esc_html_e('Configure CalDAV integration', 'reventor-calendar-appointment-booking'); ?></li>
            <li><?php esc_html_e('Copy the shortcode and paste it on any page', 'reventor-calendar-appointment-booking'); ?></li>
                        </ol>
                    </div>
                </div>
                
                <!-- General Settings -->
                <div class="eab-settings-section">
                    <h3><?php esc_html_e('General Settings', 'reventor-calendar-appointment-booking'); ?></h3>
                    

                    <div class="eab-field-group">
                        <label for="booking_days_ahead"><?php esc_html_e('Booking Days Ahead', 'reventor-calendar-appointment-booking'); ?></label>
                        <input type="number" id="booking_days_ahead" name="booking_days_ahead" value="<?php echo esc_attr($booking_days_ahead); ?>" min="1" max="365" />
                        <p class="description"><?php esc_html_e('How many days in advance customers can book appointments.', 'reventor-calendar-appointment-booking'); ?></p>
                    </div>
                    
                    <div class="eab-field-group">
                        <label for="theme_color"><?php esc_html_e('Theme Color', 'reventor-calendar-appointment-booking'); ?></label>
                        <div class="eab-color-picker-wrapper">
                            <input type="color" id="theme_color" name="theme_color" value="<?php echo esc_attr($theme_color); ?>" />
                            <input type="text" id="theme_color_text" value="<?php echo esc_attr($theme_color); ?>" />
                        </div>
                        <p class="description"><?php esc_html_e('Primary color for buttons and form elements.', 'reventor-calendar-appointment-booking'); ?></p>
                    </div>
                    
                    <div class="eab-field-group">
                        <label for="timeslot_granularity"><?php esc_html_e('Timeslot Granularity', 'reventor-calendar-appointment-booking'); ?></label>
                        <select id="timeslot_granularity" name="timeslot_granularity">
                            <option value="15" <?php selected($timeslot_granularity, 15); ?>>15 <?php esc_html_e('minutes', 'reventor-calendar-appointment-booking'); ?></option>
                            <option value="30" <?php selected($timeslot_granularity, 30); ?>>30 <?php esc_html_e('minutes', 'reventor-calendar-appointment-booking'); ?></option>
                            <option value="60" <?php selected($timeslot_granularity, 60); ?>>60 <?php esc_html_e('minutes', 'reventor-calendar-appointment-booking'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Time interval between available booking slots (e.g., 15min allows 8:00, 8:15, 8:30; 30min allows 8:00, 8:30; 60min allows 8:00, 9:00).', 'reventor-calendar-appointment-booking'); ?></p>
                    </div>
                    
                    <div class="eab-field-group">
                        <label for="timezone"><?php esc_html_e('Timezone', 'reventor-calendar-appointment-booking'); ?></label>
                        <select id="timezone" name="timezone">
                            <?php
                            $timezones = timezone_identifiers_list();
                            $current_timezone = esc_attr($timezone);
                            
                            // Create user-friendly timezone options
                            $timezone_options = array();
                            foreach ($timezones as $tz) {
                                // Extract city name from timezone identifier
                                $parts = explode('/', $tz);
                                $city = end($parts);
                                $city = str_replace('_', ' ', $city);
                                
                                // Create display name: "City - Continent"
                                if (count($parts) >= 2) {
                                    $continent = $parts[0];
                                    $display_name = $city . ' - ' . $continent;
                                } else {
                                    $display_name = $tz;
                                }
                                
                                $timezone_options[] = array(
                                    'value' => $tz,
                                    'display' => $display_name,
                                    'city' => strtolower($city)
                                );
                            }
                            
                            // Sort by city name for easier searching
                            usort($timezone_options, function($a, $b) {
                                return strcmp($a['city'], $b['city']);
                            });
                            
                            foreach ($timezone_options as $option) {
                                $selected = ($option['value'] === $current_timezone) ? 'selected' : '';
                                echo '<option value="' . esc_attr($option['value']) . '" ' . esc_attr($selected) . '>' . esc_html($option['display']) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description"><?php esc_html_e('Select the timezone for your booking system. This affects appointment times and calendar synchronization.', 'reventor-calendar-appointment-booking'); ?><br>
                            <strong><?php esc_html_e('Important:', 'reventor-calendar-appointment-booking'); ?></strong> <?php esc_html_e('Setting this timezone ensures consistent time display across different servers. If you run the same plugin on multiple servers (development, staging, production), make sure they all use the same timezone setting here.', 'reventor-calendar-appointment-booking'); ?></p>
                    </div>
                    
                    <div class="eab-field-group">
                        <label for="time_format"><?php esc_html_e('Time Format', 'reventor-calendar-appointment-booking'); ?></label>
                        <select id="time_format" name="time_format">
                            <option value="24h" <?php selected($time_format, '24h'); ?>>24-hour (14:30)</option>
                            <option value="12h" <?php selected($time_format, '12h'); ?>>12-hour (2:30 PM)</option>
                        </select>
                        <p class="description"><?php esc_html_e('Choose how time should be displayed throughout the booking system.', 'reventor-calendar-appointment-booking'); ?></p>
                    </div>
                    
                    <div class="eab-field-group">
                        <label for="date_format"><?php esc_html_e('Date Format', 'reventor-calendar-appointment-booking'); ?></label>
                        <select id="date_format" name="date_format">
                            <option value="DD.MM.YYYY" <?php selected($date_format, 'DD.MM.YYYY'); ?>>DD.MM.YYYY (31.12.2024)</option>
                            <option value="MM/DD/YYYY" <?php selected($date_format, 'MM/DD/YYYY'); ?>>MM/DD/YYYY (12/31/2024)</option>
                            <option value="YYYY-MM-DD" <?php selected($date_format, 'YYYY-MM-DD'); ?>>YYYY-MM-DD (2024-12-31)</option>
                            <option value="DD/MM/YYYY" <?php selected($date_format, 'DD/MM/YYYY'); ?>>DD/MM/YYYY (31/12/2024)</option>
                        </select>
                        <p class="description"><?php esc_html_e('Choose how dates should be displayed throughout the booking system.', 'reventor-calendar-appointment-booking'); ?></p>
                    </div>

                </div>
                
                <!-- Booking Restrictions -->
                <div class="eab-settings-section">
                    <h3><?php esc_html_e('Booking Restrictions', 'reventor-calendar-appointment-booking'); ?></h3>
                    
                    <div class="eab-field-group">
                        <label for="min_booking_advance"><?php esc_html_e('Minimum Booking Advance Time', 'reventor-calendar-appointment-booking'); ?></label>
                        <select id="min_booking_advance" name="min_booking_advance">
                            <option value="1h" <?php selected($min_booking_advance, '1h'); ?>>1 <?php esc_html_e('hour', 'reventor-calendar-appointment-booking'); ?></option>
                            <option value="2h" <?php selected($min_booking_advance, '2h'); ?>>2 <?php esc_html_e('hours', 'reventor-calendar-appointment-booking'); ?></option>
                            <option value="4h" <?php selected($min_booking_advance, '4h'); ?>>4 <?php esc_html_e('hours', 'reventor-calendar-appointment-booking'); ?></option>
                            <option value="next_day" <?php selected($min_booking_advance, 'next_day'); ?>><?php esc_html_e('Next day', 'reventor-calendar-appointment-booking'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Minimum time required between now and the earliest possible appointment.', 'reventor-calendar-appointment-booking'); ?></p>
                    </div>
                    

                </div>
                
                <!-- Working Hours -->
                <div class="eab-settings-section">
                    <h3><?php esc_html_e('Working Hours', 'reventor-calendar-appointment-booking'); ?></h3>
                    
                    <div class="eab-field-group">
                        <label for="working_hours_start"><?php esc_html_e('Start Time', 'reventor-calendar-appointment-booking'); ?></label>
                        <input type="time" id="working_hours_start" name="working_hours_start" value="<?php echo esc_attr($working_hours_start); ?>" />
                        <p class="description"><?php esc_html_e('Daily working hours start time.', 'reventor-calendar-appointment-booking'); ?></p>
                    </div>
                    
                    <div class="eab-field-group">
                        <label for="working_hours_end"><?php esc_html_e('End Time', 'reventor-calendar-appointment-booking'); ?></label>
                        <input type="time" id="working_hours_end" name="working_hours_end" value="<?php echo esc_attr($working_hours_end); ?>" />
                        <p class="description"><?php esc_html_e('Daily working hours end time.', 'reventor-calendar-appointment-booking'); ?></p>
                    </div>
                    
                    <div class="eab-field-group">
                        <label><?php esc_html_e('Working Days', 'reventor-calendar-appointment-booking'); ?></label>
                        <div class="eab-working-days">
                            <?php 
                            $days = array(
                                'monday' => __('Monday', 'reventor-calendar-appointment-booking'),
                                'tuesday' => __('Tuesday', 'reventor-calendar-appointment-booking'),
                                'wednesday' => __('Wednesday', 'reventor-calendar-appointment-booking'),
                                'thursday' => __('Thursday', 'reventor-calendar-appointment-booking'),
                                'friday' => __('Friday', 'reventor-calendar-appointment-booking'),
                                'saturday' => __('Saturday', 'reventor-calendar-appointment-booking'),
                                'sunday' => __('Sunday', 'reventor-calendar-appointment-booking')
                            );
                            foreach ($days as $day_key => $day_label): 
                            ?>
                            <label class="eab-checkbox-label">
                                <input type="checkbox" name="working_days[]" value="<?php echo esc_attr($day_key); ?>" <?php checked(in_array($day_key, $working_days)); ?> />
                                <?php echo esc_html($day_label); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="description"><?php esc_html_e('Select the days when appointments can be booked.', 'reventor-calendar-appointment-booking'); ?></p>
                    </div>
                    

                </div>
                
                <!-- Appointment Types -->
                <div class="eab-settings-section">
                    <h3><?php esc_html_e('Appointment Types', 'reventor-calendar-appointment-booking'); ?></h3>
                    
                    <div class="eab-field-group">
                        <label><?php esc_html_e('Available Appointment Types', 'reventor-calendar-appointment-booking'); ?></label>
                        <div id="appointment-types-container">
                            <?php 
                            // Ensure $appointment_types is always an array
                            if (!is_array($appointment_types)) {
                                $appointment_types = array(array('name' => __('General Consultation', 'reventor-calendar-appointment-booking'), 'duration' => 30));
                            }
                            
                            // Convert old format to new format if needed
                            if (!empty($appointment_types) && isset($appointment_types[0]) && is_string($appointment_types[0])) {
                                $converted_types = array();
                                foreach ($appointment_types as $type) {
                                    $converted_types[] = array(
                                        'name' => $type,
                                        'duration' => 30
                                    );
                                }
                                $appointment_types = $converted_types;
                            }
                            
                            // Handle new format (array of arrays)
                            foreach ($appointment_types as $index => $type): 
                                $type_name = isset($type['name']) ? $type['name'] : '';
                                $type_duration = isset($type['duration']) ? $type['duration'] : 30;
                            ?>
                            <div class="eab-appointment-type-row">
                                <input type="text" name="appointment_types[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($type_name); ?>" placeholder="<?php esc_attr_e('Appointment type name', 'reventor-calendar-appointment-booking'); ?>" required />
                                <select name="appointment_types[<?php echo esc_attr($index); ?>][duration]" class="eab-duration-select" required>
                                    <option value="15" <?php selected($type_duration, 15); ?>>15 <?php esc_html_e('min', 'reventor-calendar-appointment-booking'); ?></option>
                                    <option value="30" <?php selected($type_duration, 30); ?>>30 <?php esc_html_e('min', 'reventor-calendar-appointment-booking'); ?></option>
                                    <option value="45" <?php selected($type_duration, 45); ?>>45 <?php esc_html_e('min', 'reventor-calendar-appointment-booking'); ?></option>
                                    <option value="60" <?php selected($type_duration, 60); ?>>60 <?php esc_html_e('min', 'reventor-calendar-appointment-booking'); ?></option>
                                    <option value="90" <?php selected($type_duration, 90); ?>>90 <?php esc_html_e('min', 'reventor-calendar-appointment-booking'); ?></option>
                                    <option value="120" <?php selected($type_duration, 120); ?>>120 <?php esc_html_e('min', 'reventor-calendar-appointment-booking'); ?></option>
                                </select>
                                <button type="button" class="eab-remove-type" title="<?php esc_attr_e('Remove this appointment type', 'reventor-calendar-appointment-booking'); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="eab-appointment-types-actions">
                            <button type="button" id="add-appointment-type" class="button">
                                <span class="dashicons dashicons-plus-alt"></span>
                                <?php esc_html_e('Add Type', 'reventor-calendar-appointment-booking'); ?>
                            </button>
                        </div>
                        <p class="description"><?php esc_html_e('Define the types of appointments customers can book. Use the main "Save Settings" button to save all changes.', 'reventor-calendar-appointment-booking'); ?></p>
                    </div>
                    

                </div>
                
                <!-- Email Notifications -->
                <div class="eab-settings-section">
                    <h3><?php esc_html_e('Email Notifications', 'reventor-calendar-appointment-booking'); ?></h3>
                    
                    
                    <div class="eab-field-group">
                        <label for="email_sender_name"><?php esc_html_e('Sender Name', 'reventor-calendar-appointment-booking'); ?></label>
                        <input type="text" id="email_sender_name" name="email_sender_name" value="<?php echo esc_attr(get_option('reventorcab_email_sender_name', get_bloginfo('name'))); ?>" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>" />
                        <p class="description"><?php esc_html_e('Name that will appear in the From field of notification emails.', 'reventor-calendar-appointment-booking'); ?></p>
                    </div>
                    
                    <div class="eab-field-group">
                        <label for="email_sender_email"><?php esc_html_e('Sender Email', 'reventor-calendar-appointment-booking'); ?></label>
                        <input type="email" id="email_sender_email" name="email_sender_email" value="<?php echo esc_attr(get_option('reventorcab_email_sender_email', get_option('admin_email'))); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>" />
                        <p class="description"><?php esc_html_e('Email address that will be used to send notification emails.', 'reventor-calendar-appointment-booking'); ?></p>
                    </div>
                    

                </div>
                
                <!-- CalDAV Integration -->
                <div class="eab-settings-section">
                    <h3><?php esc_html_e('CalDAV Integration', 'reventor-calendar-appointment-booking'); ?></h3>
                    
                    <div class="eab-caldav-container">
                        <div class="eab-caldav-settings">
                            <div class="eab-field-group">
                                <label for="caldav_url"><?php esc_html_e('CalDAV URL', 'reventor-calendar-appointment-booking'); ?></label>
                                <input type="url" id="caldav_url" name="caldav_url" value="<?php echo esc_attr($caldav_url); ?>" placeholder="https://example.com/caldav/calendar/" />
                                <p class="description"><?php esc_html_e('URL to your CalDAV calendar (e.g., Google Calendar, iCloud, etc.).', 'reventor-calendar-appointment-booking'); ?></p>
                            </div>
                            
                            <div class="eab-field-group">
                                <label for="caldav_username"><?php esc_html_e('Username', 'reventor-calendar-appointment-booking'); ?></label>
                                <input type="text" id="caldav_username" name="caldav_username" value="<?php echo esc_attr($caldav_username); ?>" autocomplete="username" />
                                <p class="description"><?php esc_html_e('Your CalDAV username or email address.', 'reventor-calendar-appointment-booking'); ?></p>
                            </div>
                            
                            <div class="eab-field-group">
                                <label for="caldav_password"><?php esc_html_e('Password/App Password', 'reventor-calendar-appointment-booking'); ?></label>
                                <input type="password" id="caldav_password" name="caldav_password" value="<?php echo esc_attr($caldav_password); ?>" autocomplete="current-password" />
                                <p class="description"><?php esc_html_e('Your CalDAV password or app-specific password.', 'reventor-calendar-appointment-booking'); ?></p>
                            </div>
                            
                            <div class="eab-field-group">
                                <button type="button" id="test-caldav-connection" class="button button-secondary">
                                    <span class="dashicons dashicons-admin-tools"></span>
                                    <?php esc_html_e('Test Connection', 'reventor-calendar-appointment-booking'); ?>
                                </button>
                                <div id="caldav-test-result"></div>
                            </div>
                        </div>
                        
                        <div class="eab-calendar-preview">
                            <h4><?php esc_html_e('Today\'s Schedule Preview', 'reventor-calendar-appointment-booking'); ?></h4>
                            <div class="eab-calendar-date">
                                <span class="eab-date-display"><?php echo esc_html(date_i18n('l, F j, Y')); ?></span>
                            </div>
                            <div class="eab-time-slots-preview" id="eab-time-slots-preview">
                                <div class="eab-loading"><?php esc_html_e('Loading time slots...', 'reventor-calendar-appointment-booking'); ?></div>
                            </div>
                            <div class="eab-legend">
                                <div class="eab-legend-item">
                                    <span class="eab-legend-color eab-slot-available"></span>
                                    <?php esc_html_e('Available', 'reventor-calendar-appointment-booking'); ?>
                                </div>
                                <div class="eab-legend-item">
                                    <span class="eab-legend-color eab-slot-past"></span>
                                    <?php esc_html_e('Past Time', 'reventor-calendar-appointment-booking'); ?>
                                </div>
                                <div class="eab-legend-item">
                                    <span class="eab-legend-color eab-slot-booked"></span>
                                    <?php esc_html_e('Booked', 'reventor-calendar-appointment-booking'); ?>
                                </div>
                                <div class="eab-legend-item">
                                    <span class="eab-legend-color eab-slot-outside_hours"></span>
                                    <?php esc_html_e('Outside Hours', 'reventor-calendar-appointment-booking'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    

                </div>
                
                <!-- Shortcode -->
                <div class="eab-settings-section">
                    <h3><?php esc_html_e('Shortcode', 'reventor-calendar-appointment-booking'); ?></h3>
                    
                    <div class="eab-field-group">
                        <div class="eab-shortcode-box">
                            <code>[reventor-booking]</code>
                            <button type="button" class="eab-copy-shortcode" title="<?php esc_attr_e('Copy to clipboard', 'reventor-calendar-appointment-booking'); ?>">
                                <span class="dashicons dashicons-admin-page"></span>
                            </button>
                        </div>
                        <p class="description"><?php esc_html_e('Use this shortcode to display the booking form on any page or post.', 'reventor-calendar-appointment-booking'); ?></p>
                    </div>
                </div>
                
                <!-- Support -->
                <div class="eab-settings-section">
                    <h3><?php esc_html_e('Support', 'reventor-calendar-appointment-booking'); ?></h3>
                    
                    <div class="eab-field-group">
                        <label>
                            <input type="checkbox" id="show_credits" name="show_credits" value="1" <?php checked($show_credits, 1); ?> />
                            <?php esc_html_e('Help support the future development of this plugin by displaying a small credit note below the booking form.', 'reventor-calendar-appointment-booking'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('This option allows you to show a small "Powered by" credit link below the booking form to help support the plugin development.', 'reventor-calendar-appointment-booking'); ?></p>
                    </div>
                    
                    <div class="eab-field-group">
                        <p><?php esc_html_e('Need help, want to report a bug, or have an improvement idea?', 'reventor-calendar-appointment-booking'); ?></p>
                        <p><?php 
                            printf(
                                /* translators: %s: WordPress plugin page URL */
                                esc_html__('Please visit our %s to get support, report issues, or share your suggestions.', 'reventor-calendar-appointment-booking'),
                                '<a href="https://wordpress.org/plugins/reventor-calendar-appointment-booking/" target="_blank" rel="noopener noreferrer">' . esc_html__('WordPress plugin page', 'reventor-calendar-appointment-booking') . '</a>'
                            );
                        ?></p>
                        <div class="eab-support-buttons">
                            <a href="https://wordpress.org/support/plugin/reventor-calendar-appointment-booking/" target="_blank" rel="noopener noreferrer" class="button button-secondary">
                                <span class="dashicons dashicons-sos"></span>
                                <?php esc_html_e('Get Support', 'reventor-calendar-appointment-booking'); ?>
                            </a>
                            <a href="https://wordpress.org/plugins/reventor-calendar-appointment-booking/#reviews" target="_blank" rel="noopener noreferrer" class="button button-secondary">
                                <span class="dashicons dashicons-star-filled"></span>
                                <?php esc_html_e('Leave a Review', 'reventor-calendar-appointment-booking'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                

                
                <!-- Plugin Information -->
                <div class="eab-settings-section">
                    <h3><?php esc_html_e('Plugin Information', 'reventor-calendar-appointment-booking'); ?></h3>
                    
                    <div class="eab-field-group">
                        <p><strong><?php esc_html_e('Version:', 'reventor-calendar-appointment-booking'); ?></strong> <?php echo esc_html(REVENTORCAB_VERSION); ?></p>
                        <p><strong><?php esc_html_e('User Device Timezone:', 'reventor-calendar-appointment-booking'); ?></strong> <span id="eab-user-timezone"><?php esc_html_e('Detecting...', 'reventor-calendar-appointment-booking'); ?></span> - <span id="eab-user-time"><?php esc_html_e('Loading...', 'reventor-calendar-appointment-booking'); ?></span></p>
                        <p><strong><?php esc_html_e('Plugin Timezone:', 'reventor-calendar-appointment-booking'); ?></strong> <span id="eab-plugin-timezone"><?php 
                            $plugin_timezone = get_option('reventorcab_timezone');
                            if ($plugin_timezone) {
                                echo esc_html($plugin_timezone);
                            } else {
                                echo '<em>' . esc_html__('Not configured (using WordPress timezone)', 'reventor-calendar-appointment-booking') . '</em>';
                            }
                        ?></span> - <span id="eab-plugin-time" data-timezone="<?php echo esc_attr(get_option('reventorcab_timezone') ?: 'UTC'); ?>"><?php esc_html_e('Loading...', 'reventor-calendar-appointment-booking'); ?></span></p>
                        <p><strong><?php esc_html_e('Server Timezone:', 'reventor-calendar-appointment-booking'); ?></strong> <span id="eab-server-timezone">UTC</span> - <span id="eab-server-time"><?php echo esc_html(gmdate('Y-m-d H:i:s')); ?></span></p>
                    </div>
                </div>
            </div>
            
            <div class="eab-save-status" id="eab-save-status"></div>
        </form>
    </div>
</div>

<script type="text/template" id="appointment-type-template">
    <div class="eab-appointment-type-row">
        <input type="text" name="appointment_types[INDEX][name]" value="" placeholder="<?php esc_attr_e('Appointment type name', 'reventor-calendar-appointment-booking'); ?>" required />
        <select name="appointment_types[INDEX][duration]" class="eab-duration-select" required>
            <option value="15">15 <?php esc_html_e('min', 'reventor-calendar-appointment-booking'); ?></option>
            <option value="30" selected>30 <?php esc_html_e('min', 'reventor-calendar-appointment-booking'); ?></option>
            <option value="45">45 <?php esc_html_e('min', 'reventor-calendar-appointment-booking'); ?></option>
            <option value="60">60 <?php esc_html_e('min', 'reventor-calendar-appointment-booking'); ?></option>
            <option value="90">90 <?php esc_html_e('min', 'reventor-calendar-appointment-booking'); ?></option>
            <option value="120">120 <?php esc_html_e('min', 'reventor-calendar-appointment-booking'); ?></option>
        </select>
        <button type="button" class="eab-remove-type" title="<?php esc_attr_e('Remove this appointment type', 'reventor-calendar-appointment-booking'); ?>">
            <span class="dashicons dashicons-trash"></span>
        </button>
    </div>
</script>