/**
 * Loading Manager - Centralized loading state management
 * Handles page loaders, form submissions, and browser navigation
 * Includes proper handling of browser back button navigation
 */

class LoadingManager {
    constructor() {
        this.pageLoader = null;
        this.isNavigating = false;
        this.navigationTimeout = null;
        this.initialize();
    }

    initialize() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }

    setup() {
        this.pageLoader = document.getElementById('pageLoader');
        this.setupNavigationHandlers();
        this.setupFormHandlers();
        this.setupBrowserNavigationHandlers();
        this.setupPageVisibilityHandlers();
        this.setupInitialLoader();
    }

    /**
     * Setup handlers for browser navigation events
     * This fixes the issue with loading states getting stuck on browser back button
     */
    setupBrowserNavigationHandlers() {
        // Handle browser back/forward navigation
        window.addEventListener('pageshow', (event) => {
            // Page is being shown (including from browser cache)
            this.hideLoader();
            this.resetFormStates();
            this.isNavigating = false;
            
            // Clear any pending navigation timeouts
            if (this.navigationTimeout) {
                clearTimeout(this.navigationTimeout);
                this.navigationTimeout = null;
            }
        });

        // Handle popstate (back/forward button clicks)
        window.addEventListener('popstate', (event) => {
            this.hideLoader();
            this.resetFormStates();
            this.isNavigating = false;
        });

        // Handle beforeunload (when leaving the page)
        window.addEventListener('beforeunload', (event) => {
            // Don't show loader if we're just refreshing or closing
            if (!this.isNavigating) {
                this.hideLoader();
            }
        });
    }

    /**
     * Setup page visibility handlers to ensure loader is hidden when page becomes visible
     */
    setupPageVisibilityHandlers() {
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                // Page became visible - ensure loader is hidden
                setTimeout(() => {
                    this.hideLoader();
                    this.resetFormStates();
                }, 100);
            }
        });
    }

    /**
     * Setup initial page loader behavior
     */
    setupInitialLoader() {
        if (this.pageLoader) {
            // Show loader initially if it has the 'show' class
            if (this.pageLoader.classList.contains('show')) {
                // Hide after a reasonable time
                setTimeout(() => {
                    this.hideLoader();
                }, 2000);
            }
        }
    }

    /**
     * Setup navigation link handlers
     */
    setupNavigationHandlers() {
        // Handle all navigation links
        document.querySelectorAll('a[href]:not([href^="#"]):not([target="_blank"]):not(.no-loader)').forEach(link => {
            link.addEventListener('click', (e) => {
                // Don't show loader for same-page anchors or external links
                const href = link.getAttribute('href');
                if (!href || href.startsWith('#') || link.hasAttribute('target')) {
                    return;
                }

                this.showNavigationLoader(link);
            });
        });
    }

    /**
     * Setup form submission handlers
     */
    setupFormHandlers() {
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', (e) => {
                this.showFormLoader(form);
            });
        });
    }

    /**
     * Show loader for navigation
     */
    showNavigationLoader(link) {
        if (!this.pageLoader) return;

        this.isNavigating = true;
        this.pageLoader.classList.add('show');
        
        const loaderText = this.pageLoader.querySelector('.loader-text');
        if (loaderText) {
            const href = link.href.toLowerCase();
            if (href.includes('register')) {
                loaderText.textContent = 'Opening Registration...';
            } else if (href.includes('request')) {
                loaderText.textContent = 'Opening Blood Request...';
            } else if (href.includes('login') || href.includes('admin')) {
                loaderText.textContent = 'Loading Admin Panel...';
            } else if (href.includes('dashboard')) {
                loaderText.textContent = 'Loading Dashboard...';
            } else if (href.includes('donor')) {
                loaderText.textContent = 'Loading Donor Portal...';
            } else {
                loaderText.textContent = 'Loading...';
            }
        }

        // Safety timeout - hide loader after max 10 seconds
        this.navigationTimeout = setTimeout(() => {
            this.hideLoader();
            this.isNavigating = false;
        }, 10000);
    }

    /**
     * Show loader for form submissions
     */
    showFormLoader(form) {
        if (!this.pageLoader) return;

        const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitBtn) {
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        }

        this.pageLoader.classList.add('show');
        const loaderText = this.pageLoader.querySelector('.loader-text');
        if (loaderText) {
            if (form.id === 'loginForm' || form.action.includes('login')) {
                loaderText.textContent = 'Authenticating...';
            } else if (form.action.includes('register')) {
                loaderText.textContent = 'Creating Account...';
            } else if (form.action.includes('request')) {
                loaderText.textContent = 'Submitting Request...';
            } else {
                loaderText.textContent = 'Processing...';
            }
        }

        // Disable form inputs to prevent double submission
        const inputs = form.querySelectorAll('input, textarea, select, button');
        inputs.forEach(input => {
            if (input.type !== 'submit') {
                input.disabled = true;
            }
        });
    }

    /**
     * Hide the page loader
     */
    hideLoader() {
        if (this.pageLoader) {
            this.pageLoader.classList.remove('show');
        }
    }

    /**
     * Reset form states (remove loading classes, re-enable inputs)
     */
    resetFormStates() {
        // Reset loading buttons
        document.querySelectorAll('.loading').forEach(element => {
            element.classList.remove('loading');
            element.disabled = false;
        });

        // Re-enable form inputs
        document.querySelectorAll('form').forEach(form => {
            const inputs = form.querySelectorAll('input, textarea, select, button');
            inputs.forEach(input => {
                input.disabled = false;
            });
        });
    }

    /**
     * Manually show loader with custom text
     */
    showLoader(text = 'Loading...') {
        if (!this.pageLoader) return;

        this.pageLoader.classList.add('show');
        const loaderText = this.pageLoader.querySelector('.loader-text');
        if (loaderText) {
            loaderText.textContent = text;
        }
    }

    /**
     * Check if loader is currently visible
     */
    isLoaderVisible() {
        return this.pageLoader && this.pageLoader.classList.contains('show');
    }
}

// Initialize loading manager when script loads
let loadingManager;

function initializeLoadingManager() {
    loadingManager = new LoadingManager();
    window.loadingManager = loadingManager;
    
    // Dispatch a custom event to notify that loading manager is ready
    window.dispatchEvent(new CustomEvent('loadingManagerReady', {
        detail: { loadingManager: loadingManager }
    }));
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeLoadingManager);
} else {
    initializeLoadingManager();
}

// Export for global access
window.LoadingManager = LoadingManager;
