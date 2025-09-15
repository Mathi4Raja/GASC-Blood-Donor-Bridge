/**
 * Timezone Utilities for GASC Blood Donor Bridge
 * Provides IST-aware date/time functions for consistent frontend behavior
 * 
 * This file ensures all JavaScript date operations use IST timezone
 * to match the backend PHP timezone settings.
 */

// IST timezone offset in minutes from UTC (IST = UTC+5:30 = 330 minutes)
const IST_OFFSET_MINUTES = 330;

/**
 * Get current date in IST
 * @param {boolean} dateOnly - If true, returns only date part (YYYY-MM-DD)
 * @returns {string} Date string in IST
 */
function getCurrentISTDate(dateOnly = true) {
    const now = new Date();
    // Convert to IST by adding the offset
    const istTime = new Date(now.getTime() + (IST_OFFSET_MINUTES * 60 * 1000));
    
    if (dateOnly) {
        return istTime.toISOString().split('T')[0];
    } else {
        return istTime.toISOString().replace('Z', '+05:30');
    }
}

/**
 * Get IST date from a specific time offset
 * @param {number} offsetDays - Number of days to offset (negative for past)
 * @param {boolean} dateOnly - If true, returns only date part
 * @returns {string} Date string in IST
 */
function getISTDateWithOffset(offsetDays, dateOnly = true) {
    const now = new Date();
    const offsetTime = now.getTime() + (offsetDays * 24 * 60 * 60 * 1000);
    const istTime = new Date(offsetTime + (IST_OFFSET_MINUTES * 60 * 1000));
    
    if (dateOnly) {
        return istTime.toISOString().split('T')[0];
    } else {
        return istTime.toISOString().replace('Z', '+05:30');
    }
}

/**
 * Format a date string for display in IST
 * @param {string|Date} dateInput - Date to format
 * @param {string} format - Format type: 'short', 'long', 'datetime'
 * @returns {string} Formatted date string
 */
function formatISTDate(dateInput, format = 'short') {
    let date;
    
    if (typeof dateInput === 'string') {
        date = new Date(dateInput);
    } else if (dateInput instanceof Date) {
        date = dateInput;
    } else {
        return 'Invalid date';
    }
    
    // Ensure we're working with IST
    const istDate = new Date(date.getTime() + (IST_OFFSET_MINUTES * 60 * 1000));
    
    const options = {
        timeZone: 'Asia/Kolkata',
    };
    
    switch (format) {
        case 'short':
            options.year = 'numeric';
            options.month = 'short';
            options.day = 'numeric';
            break;
        case 'long':
            options.year = 'numeric';
            options.month = 'long';
            options.day = 'numeric';
            break;
        case 'datetime':
            options.year = 'numeric';
            options.month = 'short';
            options.day = 'numeric';
            options.hour = '2-digit';
            options.minute = '2-digit';
            options.hour12 = true;
            break;
        case 'time':
            options.hour = '2-digit';
            options.minute = '2-digit';
            options.hour12 = true;
            break;
        case 'time24':
            options.hour = '2-digit';
            options.minute = '2-digit';
            options.hour12 = false;
            break;
    }
    
    try {
        return istDate.toLocaleDateString('en-IN', options);
    } catch (e) {
        // Fallback formatting
        return istDate.toISOString().split('T')[0];
    }
}

/**
 * Get the age range dates for donor validation (18-65 years)
 * @returns {object} Object with minDate and maxDate for date input validation
 */
function getDonorAgeRangeDates() {
    const today = getCurrentISTDate(true);
    const todayDate = new Date(today);
    
    // Max age: 65 years (minimum birth date)
    const minBirthDate = new Date(todayDate);
    minBirthDate.setFullYear(todayDate.getFullYear() - 65);
    
    // Min age: 18 years (maximum birth date)
    const maxBirthDate = new Date(todayDate);
    maxBirthDate.setFullYear(todayDate.getFullYear() - 18);
    
    return {
        minDate: minBirthDate.toISOString().split('T')[0],
        maxDate: maxBirthDate.toISOString().split('T')[0]
    };
}

/**
 * Calculate age from birth date in IST context
 * @param {string} birthDate - Birth date in YYYY-MM-DD format
 * @returns {number} Age in years
 */
function calculateAge(birthDate) {
    if (!birthDate) return null;
    
    const today = new Date(getCurrentISTDate(true));
    const birth = new Date(birthDate);
    
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    
    return age;
}

/**
 * Check if a donor is eligible to donate based on last donation date
 * @param {string} lastDonationDate - Last donation date in YYYY-MM-DD format
 * @param {string} gender - Donor gender ('Male' or 'Female')
 * @returns {object} Object with isEligible boolean and nextEligibleDate
 */
function checkDonationEligibility(lastDonationDate, gender) {
    if (!lastDonationDate) {
        return { isEligible: true, nextEligibleDate: null };
    }
    
    const lastDonation = new Date(lastDonationDate);
    const today = new Date(getCurrentISTDate(true));
    
    // Calculate required waiting period (3 months for males, 4 months for females)
    const requiredMonths = gender === 'Female' ? 4 : 3;
    const nextEligibleDate = new Date(lastDonation);
    nextEligibleDate.setMonth(nextEligibleDate.getMonth() + requiredMonths);
    
    const isEligible = today >= nextEligibleDate;
    
    return {
        isEligible: isEligible,
        nextEligibleDate: isEligible ? null : nextEligibleDate.toISOString().split('T')[0]
    };
}

/**
 * Get the IST timestamp for logging and tracking
 * @param {boolean} use12Hour - Whether to use 12-hour format (default: true)
 * @returns {string} Current timestamp in IST
 */
function getISTTimestamp(use12Hour = true) {
    const now = new Date();
    const istTime = new Date(now.getTime() + (IST_OFFSET_MINUTES * 60 * 1000));
    
    const year = istTime.getUTCFullYear();
    const month = String(istTime.getUTCMonth() + 1).padStart(2, '0');
    const day = String(istTime.getUTCDate()).padStart(2, '0');
    
    if (use12Hour) {
        let hours = istTime.getUTCHours();
        const minutes = String(istTime.getUTCMinutes()).padStart(2, '0');
        const seconds = String(istTime.getUTCSeconds()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // 0 should be 12
        const formattedHours = String(hours).padStart(2, '0');
        
        return `${year}-${month}-${day} ${formattedHours}:${minutes}:${seconds} ${ampm}`;
    } else {
        const hours = String(istTime.getUTCHours()).padStart(2, '0');
        const minutes = String(istTime.getUTCMinutes()).padStart(2, '0');
        const seconds = String(istTime.getUTCSeconds()).padStart(2, '0');
        
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }
}

/**
 * Set up date input fields with IST-aware min/max values
 * @param {string} fieldId - ID of the date input field
 * @param {object} options - Options object with minOffset, maxOffset, and type
 */
function setupISTDateField(fieldId, options = {}) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    const {
        minOffset = null,    // Days offset for minimum date (negative for past)
        maxOffset = 0,       // Days offset for maximum date (0 = today)
        type = 'general'     // 'general', 'birthdate', 'donation'
    } = options;
    
    switch (type) {
        case 'birthdate':
            const ageRange = getDonorAgeRangeDates();
            field.setAttribute('min', ageRange.minDate);
            field.setAttribute('max', ageRange.maxDate);
            break;
            
        case 'donation':
            // For donation dates, allow past dates up to 2 years ago, max is today
            field.setAttribute('min', getISTDateWithOffset(-730)); // 2 years ago
            field.setAttribute('max', getCurrentISTDate(true));
            break;
            
        default:
            if (minOffset !== null) {
                field.setAttribute('min', getISTDateWithOffset(minOffset));
            }
            field.setAttribute('max', getISTDateWithOffset(maxOffset));
    }
}

/**
 * Initialize timezone display in the page
 * Shows current IST time and timezone info in 12-hour format
 */
function initTimezoneDisplay() {
    const timezoneElements = document.querySelectorAll('.timezone-display');
    
    function updateTime() {
        const istTime = formatISTDate(new Date(), 'datetime');
        timezoneElements.forEach(element => {
            element.textContent = `${istTime} IST`;
        });
    }
    
    // Update immediately and then every minute
    updateTime();
    setInterval(updateTime, 60000);
}

// Auto-initialize timezone display when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Set up any existing timezone display elements
    initTimezoneDisplay();
    
    // Set up any date fields that need IST handling
    const dateFields = document.querySelectorAll('input[type="date"][data-ist="true"]');
    dateFields.forEach(field => {
        const type = field.getAttribute('data-type') || 'general';
        const minOffset = field.getAttribute('data-min-offset');
        const maxOffset = field.getAttribute('data-max-offset') || 0;
        
        setupISTDateField(field.id, {
            type: type,
            minOffset: minOffset ? parseInt(minOffset) : null,
            maxOffset: parseInt(maxOffset)
        });
    });
});

// Make functions available globally
window.ISTUtils = {
    getCurrentISTDate,
    getISTDateWithOffset,
    formatISTDate,
    getDonorAgeRangeDates,
    calculateAge,
    checkDonationEligibility,
    getISTTimestamp,
    setupISTDateField,
    initTimezoneDisplay
};