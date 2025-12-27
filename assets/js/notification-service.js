/**
 * Notification Service
 * Handles displaying notifications using react-toastify library
 */

import { toast,Bounce  } from 'react-toastify';

class NotificationService {
    constructor() {
        // Configure default options for all toasts
        this.defaultOptions = {
            position: "top-right",
            autoClose: 5000,
            hideProgressBar: false,
            closeOnClick: true,
            pauseOnHover: true,
            draggable: true,
            progress: undefined,
            pauseOnFocusLoss:false
        };
    }

    /**
     * Show a success notification
     * @param {string} message - The message to display
     * @param {number} timeout - Duration in ms (null = default, false = no auto close)
     */
    success(message, timeout = null) {
        const options = { 
            ...this.defaultOptions,
        };
        if (timeout === 0 || timeout === false) {
            options.autoClose = false;
        } else if (timeout !== null) {
            options.autoClose = timeout;
        }
        
        toast.success(message, options);
    }

    /**
     * Show an error notification
     * @param {string} message - The message to display
     * @param {number} timeout - Duration in ms (0 or false = no auto close)
     */
    error(message, timeout = 0) {
        const options = { ...this.defaultOptions };
        if (timeout === 0 || timeout === false) {
            options.autoClose = false;
        } else {
            options.autoClose = timeout;
        }
        
        toast.error(message, options);
    }

    /**
     * Show a warning notification
     * @param {string} message - The message to display
     * @param {number} timeout - Duration in ms
     */
    warning(message, timeout = 5000) {
        const options = { ...this.defaultOptions };
        if (timeout === 0 || timeout === false) {
            options.autoClose = false;
        } else {
            options.autoClose = timeout;
        }
        
        toast.warning(message, options);
    }

    /**
     * Show an info notification
     * @param {string} message - The message to display
     * @param {number} timeout - Duration in ms
     */
    info(message, timeout = 5000) {
        const options = { ...this.defaultOptions };
        if (timeout === 0 || timeout === false) {
            options.autoClose = false;
        } else {
            options.autoClose = timeout;
        }
        
        toast.info(message, options);
    }

    /**
     * Show a notification with custom options
     * @param {string} type - Type of notification (success, error, warning, info)
     * @param {string} message - The message to display
     * @param {Object} options - Custom toast options
     */
    custom(type, message, options = {}) {
        const mergedOptions = { ...this.defaultOptions, ...options };
        
        switch(type) {
            case 'success':
                toast.success(message, mergedOptions);
                break;
            case 'error':
                toast.error(message, mergedOptions);
                break;
            case 'warning':
                toast.warning(message, mergedOptions);
                break;
            case 'info':
                toast.info(message, mergedOptions);
                break;
            default:
                toast(message, mergedOptions);
        }
    }

    /**
     * Dismiss all active toasts
     */
    dismissAll() {
        toast.dismiss();
    }
}

// Create a singleton instance
const notificationService = new NotificationService();

// Make it globally available
window.NotificationService = notificationService;

export default notificationService;