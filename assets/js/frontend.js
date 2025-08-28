/**
 * Frontend JavaScript for REVENTOR Calendar Appointment Booking
 */

(function($) {
    'use strict';
    
    let currentStep = 1;
    let totalSteps = 3;
    let selectedTimeSlot = null;
    let formData = {};
    
    $(document).ready(function() {
        initializeBookingForm();
    });
    
    function initializeBookingForm() {
        // Initialize timezone detection first - if it fails, stop form initialization
        if (!initTimezoneDetection()) {
            console.error('REVENTORCAB: Timezone detection failed, form initialization stopped');
            return;
        }
        
        // Initialize step navigation
        initStepNavigation();
        
        // Initialize date selection
        initDateSelection();
        
        // Initialize time slot selection
        initTimeSlotSelection();
        
        // Initialize form validation
        initFormValidation();
        
        // Initialize form submission
        initFormSubmission();
        
        // Update progress bar
        updateProgressBar();
        
        // Initialize accessibility features
        initAccessibility();
    }
    
    function initStepNavigation() {
        // Handle appointment type selection
        $('input[name="appointment_type"]').on('change', function() {
            var duration = $(this).data('duration');
            $('#appointment_duration').val(duration);
        });
        
        // Next step buttons
        $('.reventorcab-next-step').on('click', function() {
            if (validateCurrentStep()) {
                // If moving from step 1 to step 2, trigger CalDAV sync
                if (currentStep === 1) {
                    showServiceSyncIndicator();
                    performInitialCalendarSync().then(function() {
                        hideServiceSyncIndicator();
                        nextStep();
                    });
                } else {
                    nextStep();
                }
            }
        });
        
        // Previous step buttons
        $('.reventorcab-prev-step').on('click', function() {
            prevStep();
        });
        
        // Step indicator clicks
        $('.reventorcab-step').on('click', function() {
            const targetStep = parseInt($(this).data('step'));
            if (targetStep < currentStep) {
                goToStep(targetStep);
            }
        });
    }
    
    function nextStep() {
        if (currentStep < totalSteps) {
            currentStep++;
            updateStepDisplay();
            
            // No confirmation step needed anymore
        }
    }
    
    function prevStep() {
        if (currentStep > 1) {
            currentStep--;
            updateStepDisplay();
        }
    }
    
    function goToStep(step) {
        if (step >= 1 && step <= totalSteps) {
            currentStep = step;
            updateStepDisplay();
        }
    }
    
    function updateStepDisplay() {
        // Hide all steps
        $('.reventorcab-form-step').removeClass('active');
        
        // Show current step
        $('#step-' + currentStep).addClass('active');
        
        // Update step indicators
        $('.reventorcab-step').removeClass('active completed');
        
        $('.reventorcab-step').each(function() {
            const stepNum = parseInt($(this).data('step'));
            if (stepNum < currentStep) {
                $(this).addClass('completed');
            } else if (stepNum === currentStep) {
                $(this).addClass('active');
            }
        });
        
        // Update progress bar
        updateProgressBar();
        
        // Focus management
        $('#step-' + currentStep).find('input, select, button').first().focus();
    }
    
    function updateProgressBar() {
        const progress = (currentStep / totalSteps) * 100;
        $('.reventorcab-progress-bar').css('width', progress + '%');
    }
    
    function validateCurrentStep() {
        switch (currentStep) {
            case 1:
                return validateServiceSelection();
            case 2:
                return validateDateTimeSelection();
            case 3:
                return validatePersonalInfo();
            default:
                return true;
        }
    }
    
    function validateServiceSelection() {
        const selectedServiceInput = $('input[name="appointment_type"]:checked');
        if (selectedServiceInput.length === 0) {
            showError(reventorcab_frontend.strings.required_fields || 'Please select an appointment type.');
            return false;
        }
        
        const selectedService = selectedServiceInput.val();
        const selectedDuration = selectedServiceInput.data('duration') || 30;
        
        formData.appointmentType = selectedService;
        formData.appointmentTypeName = selectedService;
        formData.appointmentDuration = selectedDuration;
        
        return true;
    }
    
    function validateDateTimeSelection() {
        const selectedDate = $('#appointment_date').val();
        const selectedTime = $('#appointment_time').val();
        
        if (!selectedDate) {
            showError('Please select a date.');
            return false;
        }
        
        if (!selectedTime) {
            showError('Please select a time slot.');
            return false;
        }
        
        formData.appointmentDate = selectedDate;
        formData.appointmentTime = selectedTime;
        return true;
    }
    
    function validatePersonalInfo() {
        const name = $('#customer_name').val().trim();
        const email = $('#customer_email').val().trim();
        
        if (!name) {
            showError('Please enter your full name.');
            $('#customer_name').focus();
            return false;
        }
        
        if (!email) {
            showError('Please enter your email address.');
            $('#customer_email').focus();
            return false;
        }
        
        if (!isValidEmail(email)) {
            showError(reventorcab_frontend.strings.invalid_email || 'Please enter a valid email address.');
            $('#customer_email').focus();
            return false;
        }
        
        formData.name = name;
        formData.email = email;
        formData.phone = $('#customer_phone').val().trim();
        formData.notes = $('#customer_notes').val().trim();
        
        return true;
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function initDateSelection() {
        $('#appointment_date').on('change', function() {
            const selectedDate = $(this).val();
            if (selectedDate) {
                loadTimeSlots(selectedDate);
            } else {
                clearTimeSlots();
            }
        });
        
        // Note: Auto-loading of time slots for the first date is now handled
        // after CalDAV sync completion in performInitialCalendarSync()
    }
    
    function loadTimeSlots(date) {
        const container = $('#time-slots-container');
        const selectedServiceInput = $('input[name="appointment_type"]:checked');
        const appointmentType = selectedServiceInput.val();
        const appointmentDuration = selectedServiceInput.data('duration') || 30;
        
        // Detect user's timezone
        const userTimezone = getUserTimezone();
        const userTimezoneOffset = getUserTimezoneOffsetMinutes();
        
        // Show loading state
        container.html('<div class="reventorcab-loading-slots"><div class="reventorcab-spinner"></div><p>' + (reventorcab_frontend.strings.loading || 'Loading available times...') + '</p></div>');
        
        // Debug logging for AJAX request
        console.log('=== AJAX Request Debug ===');
        console.log('Selected date:', date);
        console.log('Selected appointment type:', appointmentType);
        console.log('Appointment duration:', appointmentDuration);
        console.log('Current browser time:', new Date().toString());
        console.log('User timezone:', userTimezone);
        console.log('User timezone offset:', userTimezoneOffset);
        
        // Load time slots directly (CalDAV sync already done when moving from step 1)
        $.ajax({
            url: reventorcab_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'reventorcab_get_available_slots',
                nonce: reventorcab_frontend.nonce,
                date: date,
                appointment_type: appointmentType,
                duration: appointmentDuration,
                user_timezone: userTimezone,
                user_timezone_offset: getUserTimezoneOffsetMinutes()
            },
            success: function(response) {
                // Debug logging
                console.log('REVENTORCAB AJAX Response:', response);
                console.log('Response success:', response.success);
                console.log('Response data:', response.data);
                
                if (response.success && response.data && response.data.slots && response.data.slots.length > 0) {
                    console.log('Found', response.data.slots.length, 'time slots:', response.data.slots);
                    displayTimeSlots(response.data.slots);
                } else {
                    console.log('No slots available. Response success:', response.success);
                    if (response.data) {
                        console.log('Response data slots:', response.data.slots);
                        console.log('Response data debug:', response.data.debug);
                    }
                    showNoSlotsMessage();
                }
            },
            error: function(xhr, status, error) {
                console.log('REVENTORCAB AJAX Error:', status, error);
                console.log('Response text:', xhr.responseText);
                showNoSlotsMessage();
            }
        });
    }
    
    function displayTimeSlots(slots) {
        const container = $('#time-slots-container');
        let html = '';
        
        // Debug logging for time slots
        console.log('=== Time Slots Debug ===');
        console.log('Current time:', new Date().toLocaleTimeString());
        console.log('Available slots received:', slots);
        console.log('Number of slots:', slots.length);
        
        slots.forEach(function(slot) {
            html += '<div class="reventorcab-time-slot" data-time="' + slot + '">' + formatTime(slot) + '</div>';
            console.log('Displaying slot:', slot, 'formatted as:', formatTime(slot));
        });
        
        container.html(html);
        
        // Reset selected time
        selectedTimeSlot = null;
        $('#appointment_time').val('');
        updateNextButtonState();
    }
    
    function showNoSlotsMessage() {
        const container = $('#time-slots-container');
        container.html('<div class="reventorcab-no-date-selected"><span class="dashicons dashicons-info"></span>' + (reventorcab_frontend.strings.no_slots || 'No available time slots for this date.') + '</div>');
        
        selectedTimeSlot = null;
        $('#appointment_time').val('');
        updateNextButtonState();
    }
    
    function clearTimeSlots() {
        const container = $('#time-slots-container');
        container.html('<div class="reventorcab-no-date-selected"><span class="dashicons dashicons-info"></span>Please select a date first</div>');
        
        selectedTimeSlot = null;
        $('#appointment_time').val('');
        updateNextButtonState();
    }
    
    function formatTime(time) {
        // Get time format setting from backend
        const timeFormat = reventorcab_frontend.settings.time_format || '24h';
        
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
    
    function initTimeSlotSelection() {
        $(document).on('click', '.reventorcab-time-slot', function() {
            if ($(this).hasClass('unavailable')) {
                return;
            }
            
            // Remove previous selection
            $('.reventorcab-time-slot').removeClass('selected');
            
            // Select current slot
            $(this).addClass('selected');
            
            selectedTimeSlot = $(this).data('time');
            $('#appointment_time').val(selectedTimeSlot);
            
            updateNextButtonState();
        });
    }
    
    function updateNextButtonState() {
        const nextButton = $('#step-2 .reventorcab-next-step');
        const hasDate = $('#appointment_date').val();
        const hasTime = $('#appointment_time').val();
        
        if (hasDate && hasTime) {
            nextButton.prop('disabled', false);
        } else {
            nextButton.prop('disabled', true);
        }
    }
    
    function populateConfirmation() {
        // Service - use the parsed name
        $('#confirm-service').text(formData.appointmentTypeName || formData.appointmentType);
        
        // Duration
        $('#confirm-duration').text((formData.appointmentDuration || 30) + ' minutes');
        
        // Date
        const dateObj = new Date(formData.appointmentDate);
        const formattedDate = dateObj.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        $('#confirm-date').text(formattedDate);
        
        // Time
        $('#confirm-time').text(formatTime(formData.appointmentTime));
        
        // Personal info
        $('#confirm-name').text(formData.name);
        $('#confirm-email').text(formData.email);
        $('#confirm-phone').text(formData.phone || 'Not provided');
        
        // Notes
        if (formData.notes) {
            $('#confirm-notes').text(formData.notes);
            $('#confirm-notes-row').show();
        } else {
            $('#confirm-notes-row').hide();
        }
    }
    
    function initFormValidation() {
        // Real-time validation
        $('#customer_email').on('blur', function() {
            const email = $(this).val().trim();
            if (email && !isValidEmail(email)) {
                $(this).addClass('error');
                showError(reventorcab_frontend.strings.invalid_email || 'Please enter a valid email address.');
            } else {
                $(this).removeClass('error');
            }
        });
        
        // Remove error class on input
        $('input, select, textarea').on('input change', function() {
            $(this).removeClass('error');
        });
    }
    
    function initFormSubmission() {
        $('#reventorcab-appointment-form').on('submit', function(e) {
            e.preventDefault();
            
            // Validate all steps before submitting
            if (!validateServiceSelection()) {
                goToStep(1);
                return;
            }
            
            if (!validateDateTimeSelection()) {
                goToStep(2);
                return;
            }
            
            if (!validatePersonalInfo()) {
                return;
            }
            
            submitAppointment();
        });
    }
    
    function submitAppointment() {
        showLoadingOverlay();
        
        const submitData = {
            action: 'reventorcab_book_appointment',
            nonce: reventorcab_frontend.nonce,
            name: formData.name,
            email: formData.email,
            phone: formData.phone,
            appointment_type: formData.appointmentType,
            appointment_duration: formData.appointmentDuration,
            date: formData.appointmentDate,
            time: formData.appointmentTime,
            notes: formData.notes,
            user_timezone: getUserTimezone(),
            user_timezone_offset: getUserTimezoneOffsetMinutes()
        };
        
        // Debug logging for duration
        console.log('REVENTORCAB Submit Data:', submitData);
        console.log('Form Data Duration:', formData.appointmentDuration);
        console.log('Selected Service Duration:', $('input[name="appointment_type"]:checked').data('duration'));
        
        $.ajax({
            url: reventorcab_frontend.ajax_url,
            type: 'POST',
            data: submitData,
            success: function(response) {
                hideLoadingOverlay();
                
                if (response.success) {
                    showSuccessStep();
                } else {
                    // Handle timezone-specific errors and warnings
                    if (response.data.timezone_warning) {
                        // Show confirmation dialog for timezone fallback
                        showTimezoneWarning(response.data.message, response.data.fallback_timezone, submitData);
                    } else if (response.data.timezone_error) {
                        // Show timezone error
                        showTimezoneError(response.data.message);
                    } else {
                        // Regular error
                        showError(response.data.message || reventorcab_frontend.strings.booking_error);
                    }
                }
            },
            error: function() {
                hideLoadingOverlay();
                showError(reventorcab_frontend.strings.booking_error || 'Error booking appointment. Please try again.');
            }
        });
    }
    
    function showSuccessStep() {
        $('.reventorcab-form-step').removeClass('active');
        $('#step-success').show();
        
        // Update progress to 100%
        $('.reventorcab-progress-bar').css('width', '100%');
        
        // Mark all steps as completed
        $('.reventorcab-step').removeClass('active').addClass('completed');
        
        // Scroll to top
        $('.reventorcab-booking-form')[0].scrollIntoView({ behavior: 'smooth' });
    }
    
    function showLoadingOverlay() {
        $('#reventorcab-loading-overlay').show();
    }
    
    function hideLoadingOverlay() {
        $('#reventorcab-loading-overlay').hide();
    }
    
    function showError(message) {
        // Create or update error message
        let errorDiv = $('.reventorcab-error-message');
        
        if (errorDiv.length === 0) {
            errorDiv = $('<div class="reventorcab-error-message"></div>');
            $('.reventorcab-booking-form').prepend(errorDiv);
        }
        
        errorDiv.html('<span class="dashicons dashicons-warning"></span> ' + message)
               .addClass('show');
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            errorDiv.removeClass('show');
        }, 5000);
        
        // Scroll to error
        errorDiv[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    function showTimezoneWarning(message, fallbackTimezone, originalSubmitData) {
        $('.reventorcab-error-message').remove();
        
        const warningHtml = '<div class="reventorcab-timezone-warning" style="background: #fff3cd; color: #856404; padding: 15px; border: 1px solid #ffeaa7; border-radius: 4px; margin: 10px 0;">' +
            '<p><strong>⚠️ Timezone Detection Failed</strong></p>' +
            '<p>' + message + '</p>' +
            '<div style="margin-top: 15px;">' +
                '<button type="button" class="reventorcab-btn reventorcab-btn-primary" id="reventorcab-confirm-timezone" style="margin-right: 10px;">Continue with ' + fallbackTimezone + '</button>' +
                '<button type="button" class="reventorcab-btn reventorcab-btn-secondary" id="reventorcab-cancel-timezone">Cancel</button>' +
            '</div>' +
        '</div>';
        
        $('.reventorcab-form-step.active').prepend(warningHtml);
        
        // Handle confirmation
        $('#reventorcab-confirm-timezone').on('click', function() {
            // Force use plugin timezone and resubmit
            originalSubmitData.user_timezone = fallbackTimezone;
            originalSubmitData.timezone_confirmed = true;
            
            $('.reventorcab-timezone-warning').remove();
            showLoadingOverlay();
            
            $.ajax({
                url: reventorcab_frontend.ajax_url,
                type: 'POST',
                data: originalSubmitData,
                success: function(response) {
                    hideLoadingOverlay();
                    if (response.success) {
                        showSuccessStep();
                    } else {
                        showError(response.data.message || reventorcab_frontend.strings.booking_error);
                    }
                },
                error: function() {
                    hideLoadingOverlay();
                    showError(reventorcab_frontend.strings.booking_error || 'Error booking appointment. Please try again.');
                }
            });
        });
        
        // Handle cancellation
        $('#reventorcab-cancel-timezone').on('click', function() {
            $('.reventorcab-timezone-warning').remove();
        });
        
        // Scroll to warning
        $('html, body').animate({
            scrollTop: $('.reventorcab-timezone-warning').offset().top - 100
        }, 300);
    }
    
    function showTimezoneError(message) {
        $('.reventorcab-error-message').remove();
        
        const errorHtml = '<div class="reventorcab-error-message" style="background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0;">' +
            '<p><strong>❌ Timezone Error</strong></p>' +
            '<p>' + message + '</p>' +
            '<p><small>Please refresh the page and ensure JavaScript is enabled in your browser.</small></p>' +
        '</div>';
        
        $('.reventorcab-form-step.active').prepend(errorHtml);
        
        // Scroll to error
        $('html, body').animate({
            scrollTop: $('.reventorcab-error-message').offset().top - 100
        }, 300);
    }
    
    function initAccessibility() {
        // Add ARIA labels
        $('.reventorcab-time-slot').attr('role', 'button').attr('tabindex', '0');
        $('.reventorcab-service-option').attr('role', 'radio');
        
        // Keyboard navigation for time slots
        $(document).on('keydown', '.reventorcab-time-slot', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).click();
            }
        });
        
        // Keyboard navigation for service options
        $(document).on('keydown', '.reventorcab-service-option', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
            }
        });
        
        // Announce step changes to screen readers
        const stepAnnouncer = $('<div class="sr-only" aria-live="polite" aria-atomic="true"></div>');
        $('.reventorcab-booking-form').append(stepAnnouncer);
        
        // Update announcer when step changes
        $(document).on('stepChanged', function() {
            const stepTitle = $('#step-' + currentStep).find('h4').text();
            stepAnnouncer.text('Step ' + currentStep + ' of ' + totalSteps + ': ' + stepTitle);
        });
    }
    
    function initTimezoneDetection() {
        // Validate timezone detection early and block form if it fails
        const userTimezone = getUserTimezone();
        const userTimezoneOffset = getUserTimezoneOffsetMinutes();
        const timezoneInfo = getTimezoneDisplayName();
        
        console.log('REVENTORCAB Debug - User timezone detected:', userTimezone);
        console.log('REVENTORCAB Debug - User timezone offset (minutes):', userTimezoneOffset);
        console.log('REVENTORCAB Debug - Timezone display info:', timezoneInfo);
        
        // Check if timezone detection completely failed
        if (!userTimezone) {
            // Show timezone error and disable form
            showTimezoneDetectionError();
            return false;
        }
        
        // Validate timezone is supported
        try {
            // Test if the detected timezone is valid
            new Date().toLocaleString('en-US', { timeZone: userTimezone });
        } catch (e) {
            console.error('Invalid timezone detected:', userTimezone, e);
            showTimezoneDetectionError();
            return false;
        }
        
        return true;
    }
    
    function showTimezoneDetectionError() {
        // Remove any existing error messages
        $('.reventorcab-timezone-error').remove();
        
        const errorHtml = '<div class="reventorcab-timezone-error" style="background: #f8d7da; color: #721c24; padding: 20px; border: 1px solid #f5c6cb; border-radius: 4px; margin: 20px 0; text-align: center;">' +
            '<h3 style="margin: 0 0 15px 0; color: #721c24;">❌ Timezone Detection Failed</h3>' +
            '<p style="margin: 0 0 15px 0; font-size: 16px;"><strong>Unable to detect your timezone automatically.</strong></p>' +
            '<p style="margin: 0 0 15px 0;">This booking system requires timezone detection to show accurate appointment times. Please ensure:</p>' +
            '<ul style="text-align: left; display: inline-block; margin: 0 0 15px 0;">' +
                '<li>JavaScript is enabled in your browser</li>' +
                '<li>Your browser supports modern timezone features</li>' +
                '<li>You are not using a VPN that might interfere with timezone detection</li>' +
            '</ul>' +
            '<p style="margin: 0; font-weight: bold;">Please refresh the page or try a different browser to continue.</p>' +
        '</div>';
        
        // Insert error at the top of the form
        $('.reventorcab-booking-form').prepend(errorHtml);
        
        // Disable all form interactions
        $('.reventorcab-booking-form input, .reventorcab-booking-form select, .reventorcab-booking-form button, .reventorcab-booking-form textarea').prop('disabled', true);
        $('.reventorcab-booking-form').addClass('reventorcab-form-disabled');
        
        // Add CSS to visually indicate disabled state
        $('<style>').text(
            '.reventorcab-form-disabled {' +
                'opacity: 0.6;' +
                'pointer-events: none;' +
            '}' +
            '.reventorcab-form-disabled .reventorcab-timezone-error {' +
                'opacity: 1;' +
                'pointer-events: auto;' +
            '}'
        ).appendTo('head');
    }
    
    function getUserTimezone() {
        // Get user's timezone using modern browser API
        try {
            return Intl.DateTimeFormat().resolvedOptions().timeZone;
        } catch (e) {
            // Fallback for older browsers - return null to trigger error handling
            return null;
        }
    }
    
    function getUserTimezoneOffset() {
        // Get timezone offset in minutes (negative for ahead of UTC, positive for behind)
        const now = new Date();
        const offsetMinutes = -now.getTimezoneOffset(); // Flip sign to match standard convention
        return Math.floor(offsetMinutes / 60); // Convert to hours
    }
    
    function getUserTimezoneOffsetMinutes() {
        // Get timezone offset in minutes for backend processing
        const now = new Date();
        return -now.getTimezoneOffset(); // Flip sign to match standard convention
    }
    
    function isDaylightSavingTime() {
        const now = new Date();
        const january = new Date(now.getFullYear(), 0, 1);
        const july = new Date(now.getFullYear(), 6, 1);
        const stdTimezoneOffset = Math.max(january.getTimezoneOffset(), july.getTimezoneOffset());
        return now.getTimezoneOffset() < stdTimezoneOffset;
    }
    
    function getTimezoneDisplayName() {
        const userTimezone = getUserTimezone();
        const isDST = isDaylightSavingTime();
        const offsetHours = getUserTimezoneOffset();
        const offsetMinutes = Math.abs(getUserTimezoneOffsetMinutes() % 60);
        
        // Format offset as ±HH:MM
        const offsetSign = offsetHours >= 0 ? '+' : '-';
        const offsetFormatted = offsetSign + Math.abs(offsetHours).toString().padStart(2, '0') + 
                               (offsetMinutes > 0 ? ':' + offsetMinutes.toString().padStart(2, '0') : ':00');
        
        // Add DST indicator
        const dstIndicator = isDST ? ' (Daylight Saving Time)' : ' (Standard Time)';
        
        return {
            timezone: userTimezone,
            offset: offsetFormatted,
            isDST: isDST,
            displayText: userTimezone + ' (UTC' + offsetFormatted + ')' + dstIndicator
        };
    }
    
    function showServiceSyncIndicator() {
        const step1 = $('#step-1');
        if (step1.find('.reventorcab-service-sync').length === 0) {
            const syncHtml = '<div class="reventorcab-service-sync" style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px; text-align: center;"><div class="reventorcab-spinner" style="display: inline-block; margin-right: 8px;"></div><span>' + (reventorcab_frontend.strings.syncing_calendar || 'Syncing with calendar...') + '</span></div>';
            step1.append(syncHtml);
        }
    }
    
    function hideServiceSyncIndicator() {
        $('.reventorcab-service-sync').remove();
    }
    
    function performInitialCalendarSync() {
        return new Promise(function(resolve) {
            $.ajax({
                url: reventorcab_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'reventorcab_sync_calendar',
                    nonce: reventorcab_frontend.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('Initial calendar sync completed successfully');
                    } else {
                        console.warn('Initial calendar sync failed:', response.data?.message || 'Unknown error');
                    }
                    
                    // After sync, automatically load time slots for the first available date
                    const preselectedDate = $('#appointment_date').val();
                    if (preselectedDate) {
                        console.log('Auto-loading time slots for first available date:', preselectedDate);
                        loadTimeSlots(preselectedDate);
                    }
                    
                    resolve(); // Always resolve to allow form to continue
                },
                error: function(xhr, status, error) {
                    console.warn('Initial calendar sync request failed:', error);
                    
                    // Even if sync fails, load time slots for the preselected date
                    const preselectedDate = $('#appointment_date').val();
                    if (preselectedDate) {
                        console.log('Loading time slots despite sync failure for date:', preselectedDate);
                        loadTimeSlots(preselectedDate);
                    }
                    
                    resolve(); // Always resolve to allow form to continue
                },
                timeout: 10000 // 10 second timeout
            });
        });
    }
    
    function syncCalendarData() {
        // Show loading indicator - centered spinner with description
        const loadingHtml = '<div class="reventorcab-loading-slots"><div class="reventorcab-spinner"></div><p>' + (reventorcab_frontend.strings.syncing_calendar || 'Syncing with calendar...') + '</p></div>';
        
        // Add loading overlay to step 2
        const step2 = $('#step-2');
        if (step2.find('.reventorcab-calendar-sync').length === 0) {
            step2.append(loadingHtml);
        }
        
        $.ajax({
            url: reventorcab_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'reventorcab_sync_calendar',
                nonce: reventorcab_frontend.nonce
            },
            success: function(response) {
                // Remove loading indicator
                $('.reventorcab-loading-slots').remove();
                
                if (response.success) {
                    // Calendar sync successful, proceed normally
                    console.log('Calendar sync completed successfully');
                } else {
                    // Calendar sync failed, but don't block the user
                    console.warn('Calendar sync failed:', response.data?.message || 'Unknown error');
                }
                
                // Always allow the user to continue
                // The time slot loading will handle any conflicts
            },
            error: function(xhr, status, error) {
                // Remove loading indicator
                $('.reventorcab-loading-slots').remove();
                
                // Log error but don't block the user
                console.warn('Calendar sync request failed:', error);
            },
            timeout: 10000 // 10 second timeout
        });
    }
    
    // Utility functions
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Auto-save form data to localStorage
    function saveFormData() {
        localStorage.setItem('reventorcab_form_data', JSON.stringify(formData));
    }
    
    function loadFormData() {
        const saved = localStorage.getItem('reventorcab_form_data');
        if (saved) {
            try {
                formData = JSON.parse(saved);
                // Populate form fields if needed
            } catch (e) {
                console.warn('Failed to load saved form data:', e);
            }
        }
    }
    
    function clearFormData() {
        localStorage.removeItem('reventorcab_form_data');
        formData = {};
    }
    
    // Initialize form data management
    loadFormData();
    
    // Save form data on input changes
    const debouncedSave = debounce(saveFormData, 1000);
    $('input, select, textarea').on('input change', debouncedSave);
    
    // Clear form data on successful submission
    $(document).on('appointmentBooked', clearFormData);
    
    // Add CSS for error messages
    if ($('.reventorcab-error-message').length === 0) {
        $('<style>').text(`
            .reventorcab-error-message {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
                border-radius: 8px;
                padding: 12px 16px;
                margin-bottom: 20px;
                display: none;
                align-items: center;
                gap: 8px;
            }
            .reventorcab-error-message.show {
                display: flex;
            }
            .reventorcab-error-message .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }
            .sr-only {
                position: absolute;
                width: 1px;
                height: 1px;
                padding: 0;
                margin: -1px;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                white-space: nowrap;
                border: 0;
            }
            input.error, select.error, textarea.error {
                border-color: #dc3545 !important;
                box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.1) !important;
            }

        `).appendTo('head');
    }
    
})(jQuery);