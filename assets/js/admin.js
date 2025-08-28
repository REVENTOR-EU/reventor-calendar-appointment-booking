/**
 * Admin JavaScript for REVENTOR Calendar Appointment Booking
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        initializeAdmin();
    });
    
    function initializeAdmin() {
        // Initialize color picker
        initColorPicker();
        
        // Initialize appointment types
        initAppointmentTypes();
        
        // Initialize save functionality
        initSaveButton();
        
        // Initialize CalDAV testing
        initCalDAVTesting();
        
        // Initialize shortcode copying
        initShortcodeCopy();
        
        // Initialize calendar preview
        initCalendarPreview();
        
        // Initialize working hours change listeners
        initWorkingHoursListeners();
        
        // Initialize export/import functionality
        initExportImport();
        
        // Initialize live time display
        initLiveTime();
        

    }
    
    function initColorPicker() {
        const colorInput = $('#theme_color');
        const textInput = $('#theme_color_text');
        
        // Sync color picker with text input
        colorInput.on('input change', function() {
            textInput.val($(this).val());
        });
        
        textInput.on('input change', function() {
            const value = $(this).val();
            if (/^#[0-9A-F]{6}$/i.test(value)) {
                colorInput.val(value);
            }
        });
    }
    
    function initAppointmentTypes() {
        // Add new appointment type
        $('#add-appointment-type').on('click', function(e) {
            e.preventDefault();
            addNewAppointmentType();
        });
        
        // Remove appointment type
        $(document).on('click', '.reventorcab-remove-type', function(e) {
            e.preventDefault();
            removeAppointmentType($(this));
        });
        
        // Appointment types are now saved with the main save button
        
        // Validate appointment type names on input
        $(document).on('input', 'input[name*="appointment_types"][name*="[name]"]', function() {
            validateAppointmentTypeName($(this));
        });
    }
    
    function addNewAppointmentType() {
        const container = $('#appointment-types-container');
        const template = $('#appointment-type-template').html();
        
        if (!template) {
            console.error('Appointment type template not found');
            return;
        }
        
        const index = container.find('.reventorcab-appointment-type-row').length;
        const newRow = template.replace(/INDEX/g, index);
        
        const $newRow = $(newRow);
        container.append($newRow);
        
        // Focus on the new input field
        $newRow.find('input[name*="[name]"]').focus();
        
        // Re-index all appointment types
        reindexAppointmentTypes();
    }
    
    function removeAppointmentType($button) {
        const $row = $button.closest('.reventorcab-appointment-type-row');
        const container = $('#appointment-types-container');
        
        // Don't allow removing the last appointment type
        if (container.find('.reventorcab-appointment-type-row').length <= 1) {
            alert('You must have at least one appointment type.');
            return;
        }
        
        // Add fade out animation
        $row.fadeOut(300, function() {
            $row.remove();
            reindexAppointmentTypes();
        });
    }
    
    function reindexAppointmentTypes() {
        $('#appointment-types-container .reventorcab-appointment-type-row').each(function(index) {
            const $row = $(this);
            $row.find('input[name*="[name]"]').attr('name', 'appointment_types[' + index + '][name]');
            $row.find('select[name*="[duration]"]').attr('name', 'appointment_types[' + index + '][duration]');
        });
    }
    
    function validateAppointmentTypeName($input) {
        const value = $input.val().trim();
        const $row = $input.closest('.reventorcab-appointment-type-row');
        
        // Remove existing validation classes
        $row.removeClass('reventorcab-validation-error reventorcab-validation-success');
        
        if (value.length === 0) {
            $row.addClass('reventorcab-validation-error');
            return false;
        } else if (value.length < 2) {
            $row.addClass('reventorcab-validation-error');
            return false;
        } else {
            $row.addClass('reventorcab-validation-success');
            return true;
        }
    }
    
    // saveAppointmentTypes function removed - appointment types are now saved with main settings
    
    function initSaveButton() {
        $('#reventorcab-save-all').on('click', function() {
            const $button = $(this);
            
            // Validate all appointment types before saving
            let isValid = true;
            $('#appointment-types-container input[name*="[name]"]').each(function() {
                if (!validateAppointmentTypeName($(this))) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                alert('Please fill in all appointment type names (minimum 2 characters).');
                return;
            }
            
            // Disable button and show loading state
            $button.prop('disabled', true);
            $button.text('Saving...');
            
            // Get form data
            const formData = new FormData($('#reventorcab-settings-form')[0]);
            formData.append('action', 'reventorcab_save_settings');
            formData.append('nonce', reventorcab_admin.nonce);
            
            // Send AJAX request
            $.ajax({
                url: reventorcab_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Change button to success state
                        $button.removeClass('button-primary').addClass('button-success');
                        $button.text('Saved');
                        $button.prop('disabled', false);
                        
                        // Reload page after successful save
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        // Reset button on failure
                        $button.prop('disabled', false);
                        $button.text('Save Settings');
                        alert('Failed to save settings. Please try again.');
                    }
                },
                error: function() {
                    // Reset button on error
                    $button.prop('disabled', false);
                    $button.text('Save Settings');
                    alert('Network error occurred. Please try again.');
                }
            });
        });
    }
    
    function initCalDAVTesting() {
        $('#test-caldav-connection').on('click', function() {
            const $button = $(this);
            const originalText = $button.text();
            
            $button.prop('disabled', true).text('Testing...');
            
            const data = {
                action: 'reventorcab_test_caldav',
                nonce: reventorcab_admin.nonce,
                caldav_url: $('#caldav_url').val(),
                caldav_username: $('#caldav_username').val(),
                caldav_password: $('#caldav_password').val()
            };
            
            $.post(reventorcab_admin.ajax_url, data, function(response) {
                $button.prop('disabled', false).text(originalText);
                const $result = $('#caldav-test-result');
                
                if (response.success) {
                    $result.removeClass('error').addClass('success')
                           .text(response.data.message || 'CalDAV connection successful!')
                           .show();
                } else {
                    $result.removeClass('success').addClass('error')
                           .text(response.data.message || 'CalDAV connection failed. Please check your settings.')
                           .show();
                }
            }).fail(function() {
                $button.prop('disabled', false).text(originalText);
                const $result = $('#caldav-test-result');
                $result.removeClass('success').addClass('error')
                       .text('Connection test failed. Please try again.')
                       .show();
            });
        });
    }
    
    function initShortcodeCopy() {
        $('.reventorcab-copy-shortcode').on('click', function() {
            const shortcode = '[reventor-booking]';
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(shortcode).then(function() {
                    const $button = $(this);
                    const originalText = $button.text();
                    $button.text('Copied!');
                    setTimeout(function() {
                        $button.text(originalText);
                    }, 2000);
                }.bind(this));
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = shortcode;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                
                const $button = $(this);
                const originalText = $button.text();
                $button.text('Copied!');
                setTimeout(function() {
                    $button.text(originalText);
                }, 2000);
            }
        });
    }
    
    function initCalendarPreview() {
        // Load today's time slots for the calendar preview
        loadTodayTimeSlots();
        
        // Add event listeners for settings changes that affect time slots
        $('#timeslot_duration, #timeslot_granularity, #working_hours_start, #working_hours_end').on('change', function() {
            loadTodayTimeSlots();
        });
        
        // Add event listeners for working days changes
        $('input[name="working_days[]"]').on('change', function() {
            loadTodayTimeSlots();
        });
    }
    
    function loadTodayTimeSlots() {
        const preview = $('#reventorcab-time-slots-preview');
        const today = new Date().toISOString().split('T')[0]; // YYYY-MM-DD format
        
        // Debug: Log the date being sent
        console.log('Sending date to server:', today);
        console.log('Today is:', new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }));
        
        // Show loading state
        preview.html('<div class="reventorcab-loading">Loading time slots...</div>');
        
        // Make AJAX request to get available slots
        $.ajax({
            url: reventorcab_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'reventorcab_get_available_slots',
                nonce: reventorcab_admin.frontend_nonce,
                date: today,
                appointment_type: 'general',
                admin_preview: true
            },
            success: function(response) {
                console.log('REVENTORCAB Debug - AJAX Response:', response);
                
                if (response.success) {
                    // Check if this is admin preview mode with detailed slot data
                    if (response.data.admin_preview && response.data.all_slots) {
                        var allSlots = response.data.all_slots;
                        var availableSlots = response.data.slots;
                        console.log('REVENTORCAB Debug - All slots with status:', allSlots);
                        console.log('REVENTORCAB Debug - Available slots:', availableSlots);
                        
                        if (allSlots.length > 0) {
                             var slotsHtml = '<div class="reventorcab-schedule-preview">';
                             slotsHtml += '<div class="reventorcab-slots-grid">';
                             
                             for (var i = 0; i < allSlots.length; i++) {
                                 var slot = allSlots[i];
                                 var cssClass = 'reventorcab-time-slot reventorcab-slot-' + slot.status;
                                 
                                 slotsHtml += '<div class="' + cssClass + '">' + slot.time + '</div>';
                             }
                             
                             slotsHtml += '</div>';
                             slotsHtml += '</div>';
                             $('#reventorcab-time-slots-preview').html(slotsHtml);
                         } else {
                             $('#reventorcab-time-slots-preview').html('<div class="reventorcab-loading">No time slots configured</div>');
                        }
                    } else if (response.data.slots && response.data.slots.length > 0) {
                        displayPreviewTimeSlots(response.data.slots);
                    } else {
                        console.log('No slots available. Reason:', response.data ? response.data.debug : 'Unknown');
                        showNoSlotsPreview();
                    }
                } else {
                    console.log('No slots available. Reason:', response.data ? response.data.debug : 'Unknown');
                    showNoSlotsPreview();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr.responseText); // Debug log
                showNoSlotsPreview();
            }
        });
    }
    
    function formatTime(time) {
        // Get time format setting from backend
        const timeFormat = reventorcab_admin.settings.time_format || '24h';
        
        if (timeFormat === '12h') {
            // Convert 24-hour format to 12-hour format
            const [hours, minutes] = time.split(':');
            const hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const displayHour = hour % 12 || 12;
            return displayHour + ':' + minutes + ' ' + ampm;
        } else {
            // Return 24-hour format as-is
            return time;
        }
    }
    
    function displayPreviewTimeSlots(slots) {
        const preview = $('#reventorcab-time-slots-preview');
        let html = '';
        
        slots.forEach(function(slot) {
            // All slots in preview are shown as available since they're already filtered
            html += '<div class="reventorcab-time-slot reventorcab-available">' + formatTime(slot) + '</div>';
        });
        
        preview.html(html);
    }
    
    function displayAdminPreviewTimeSlots(allSlots, availableSlots) {
        const preview = $('#reventorcab-time-slots-preview');
        const today = new Date();
        const currentDayName = today.toLocaleDateString('en-US', { weekday: 'long' });
        const currentDate = today.toLocaleDateString();
        
        console.log('REVENTORCAB Debug - All slots with status:', allSlots);
        console.log('REVENTORCAB Debug - Available slots:', availableSlots);
        
        if (allSlots.length > 0) {
            let html = '<div class="reventorcab-schedule-preview">';
            html += '<h4>Today\'s Schedule (' + currentDayName + ', ' + currentDate + ')</h4>';
            html += '<div class="reventorcab-slots-grid">';
            
            allSlots.forEach(function(slot) {
                let cssClass = 'reventorcab-time-slot reventorcab-slot-' + slot.status;
                let statusText = '';
                
                switch (slot.status) {
                    case 'available':
                        statusText = ' (Available)';
                        break;
                    case 'booked':
                        statusText = ' (Booked)';
                        break;
                    case 'caldav_conflict':
                        statusText = ' (CalDAV Conflict)';
                        break;
                }
                
                html += '<div class="' + cssClass + '">' + formatTime(slot.time) + statusText;
                if (slot.reason) {
                    html += '<br><small>' + slot.reason + '</small>';
                }
                html += '</div>';
            });
            
            html += '</div>';
            html += '<p><strong>Available:</strong> ' + availableSlots.length + ' slots</p>';
            html += '</div>';
            preview.html(html);
        } else {
            preview.html('<div class="reventorcab-loading">No time slots configured for today ' + currentDayName + ', ' + currentDate + '</div>');
        }
    }
    
    function showNoSlotsPreview() {
        const preview = $('#reventorcab-time-slots-preview');
        const today = new Date();
        const dayName = today.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
        
        // Check if today is a working day
        const workingDays = [];
        $('input[name="working_days[]"]:checked').each(function() {
            workingDays.push($(this).val());
        });
        
        if (!workingDays.includes(dayName)) {
            preview.html('<div class="reventorcab-loading">Today is not a working day</div>');
        } else {
            preview.html('<div class="reventorcab-loading">No available time slots for today</div>');
        }
    }
    
    function initWorkingHoursListeners() {
        // Add event listeners for working hours changes
        $('#working_hours_start, #working_hours_end').on('change', function() {
            console.log('Working hours changed, refreshing preview...');
            loadTodayTimeSlots();
        });
        
        // Add event listeners for timeslot duration and granularity changes
        $('#timeslot_duration, #timeslot_granularity').on('change', function() {
            console.log('Timeslot settings changed, refreshing preview...');
            loadTodayTimeSlots();
        });
        
        // Add event listeners for working days changes
        $('input[name="working_days[]"]').on('change', function() {
            console.log('Working days changed, refreshing preview...');
            loadTodayTimeSlots();
        });
    }
    

    
    function initLiveTime() {
        const userTimezoneElement = $('#reventorcab-user-timezone');
        const userTimeElement = $('#reventorcab-user-time');
        const pluginTimezoneElement = $('#reventorcab-plugin-timezone');
        const pluginTimeElement = $('#reventorcab-plugin-time');
        const serverTimezoneElement = $('#reventorcab-server-timezone');
        const serverTimeElement = $('#reventorcab-server-time');
        
        if (userTimezoneElement.length || pluginTimezoneElement.length || serverTimezoneElement.length) {
            // Detect and update user device timezone immediately
            if (userTimezoneElement.length) {
                try {
                    const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
                    userTimezoneElement.text(userTimezone);
                } catch (e) {
                    userTimezoneElement.text('Detection failed');
                }
            }
            
            // Update timezone displays and times every second
            setInterval(function() {
                const now = new Date();
                
                // Update user device timezone and time (browser timezone)
                if (userTimezoneElement.length && userTimeElement.length) {
                    try {
                        const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
                        userTimezoneElement.text(userTimezone);
                        
                        const userTimeString = now.toLocaleString('sv-SE', {
                            timeZone: userTimezone,
                            year: 'numeric',
                            month: '2-digit',
                            day: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit',
                            hour12: false
                        });
                        userTimeElement.text(userTimeString);
                    } catch (e) {
                        userTimezoneElement.text('Detection failed');
                        userTimeElement.text('--');
                    }
                }
                
                // Update plugin timezone time
                if (pluginTimeElement.length) {
                    const pluginTimezone = pluginTimeElement.data('timezone') || 'UTC';
                    try {
                        const pluginTimeString = now.toLocaleString('sv-SE', {
                            timeZone: pluginTimezone,
                            year: 'numeric',
                            month: '2-digit',
                            day: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit',
                            hour12: false
                        });
                        pluginTimeElement.text(pluginTimeString);
                    } catch (e) {
                        pluginTimeElement.text('Invalid timezone');
                    }
                }
                
                // Update server time (already shows server timezone time from PHP)
                if (serverTimeElement.length) {
                    const serverTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
                    const serverTimeString = now.toLocaleString('sv-SE', {
                        timeZone: serverTimezone,
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: false
                    });
                    serverTimeElement.text(serverTimeString);
                }
            }, 1000);
        }
    }
    
    function initExportImport() {
        // Export settings functionality
        $('#reventorcab-export-settings').on('click', function() {
            exportSettings();
        });
        
        // Import settings functionality
        $('#reventorcab-import-settings').on('click', function() {
            $('#reventorcab-import-file').click();
        });
        
        $('#reventorcab-import-file').on('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                importSettings(file);
            }
        });
    }
    
    function exportSettings() {
        const exportButton = $('#reventorcab-export-settings');
        const originalText = exportButton.html();
        
        // Show loading state
        exportButton.prop('disabled', true).html('<span class="dashicons dashicons-update-alt"></span> Exporting...');
        
        // Make AJAX request to get settings from server
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'reventorcab_export_settings',
                nonce: $('#reventorcab_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    // Create and download file
                    const dataStr = JSON.stringify(response.data, null, 2);
                    const dataBlob = new Blob([dataStr], {type: 'application/json'});
                    const url = URL.createObjectURL(dataBlob);
                    
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'reventor-calendar-appointment-booking-settings-' + new Date().toISOString().split('T')[0] + '.json';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                    
                    showNotice('Settings exported successfully!', 'success');
                } else {
                    showNotice('Export failed: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showNotice('Export failed: Server error', 'error');
            },
            complete: function() {
                // Restore button state
                exportButton.prop('disabled', false).html(originalText);
            }
        });
    }
    
    function importSettings(file) {
        const reader = new FileReader();
        const importButton = $('#reventorcab-import-settings');
        const originalText = importButton.html();
        
        reader.onload = function(e) {
            try {
                const importData = JSON.parse(e.target.result);
                
                // Validate import data
                if (!importData.settings) {
                    throw new Error('Invalid settings file format');
                }
                
                // Confirm import
                if (!confirm('This will overwrite your current settings. Are you sure you want to continue?')) {
                    return;
                }
                
                // Show loading state
                importButton.prop('disabled', true).html('<span class="dashicons dashicons-update-alt"></span> Importing...');
                
                // Send to server for processing
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'reventorcab_import_settings',
                        nonce: $('#reventorcab_nonce').val(),
                        import_data: JSON.stringify(importData)
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotice(response.data.message + '. The page will reload to reflect the changes.', 'success');
                            
                            // Reload page after 2 seconds to show updated settings
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            showNotice('Import failed: ' + response.data.message, 'error');
                        }
                    },
                    error: function() {
                        showNotice('Import failed: Server error', 'error');
                    },
                    complete: function() {
                        // Restore button state
                        importButton.prop('disabled', false).html(originalText);
                    }
                });
                
            } catch (error) {
                showNotice('Error importing settings: ' + error.message, 'error');
            }
        };
        
        reader.readAsText(file);
        
        // Reset file input
        $('#reventorcab-import-file').val('');
    }
    
    function showNotice(message, type = 'info') {
        const noticeClass = type === 'error' ? 'notice-error' : (type === 'success' ? 'notice-success' : 'notice-info');
        const notice = $(`<div class="notice ${noticeClass} is-dismissible"><p>${message}</p></div>`);
        
        $('.wrap h1').after(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            notice.fadeOut(() => notice.remove());
        }, 5000);
        
        // Add dismiss functionality
        notice.on('click', '.notice-dismiss', function() {
            notice.fadeOut(() => notice.remove());
        });
    }
    

    
})(jQuery);