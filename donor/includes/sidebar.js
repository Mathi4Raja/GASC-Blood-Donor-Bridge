/**
 * UNIFIED DONOR SIDEBAR JAVASCRIPT
 * Handles mobile navigation, sidebar interactions, and responsive behavior
 */

document.addEventListener('DOMContentLoaded', function() {
    // Get elements
    const mobileToggle = document.getElementById('mobileNavToggle');
    const sidebar = document.getElementById('donorSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const closeBtn = document.getElementById('sidebarClose');
    
    // Check if elements exist
    if (!mobileToggle || !sidebar || !overlay || !closeBtn) {
        console.log('Sidebar elements not found, skipping initialization');
        return;
    }

    // Mobile toggle functionality
    mobileToggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        openSidebar();
    });

    // Close button functionality
    closeBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        closeSidebar();
    });

    // Overlay click to close
    overlay.addEventListener('click', function(e) {
        e.preventDefault();
        closeSidebar();
    });

    // Escape key to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('active')) {
            closeSidebar();
        }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
            // Desktop view - ensure sidebar is reset
            closeSidebar();
        }
    });

    // Prevent sidebar close when clicking inside sidebar
    sidebar.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Functions
    function openSidebar() {
        sidebar.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Add animation class
        sidebar.classList.add('sidebar-animate-in');
        sidebar.classList.remove('sidebar-animate-out');
        
        // Focus management for accessibility
        const firstFocusable = sidebar.querySelector('button, a, input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (firstFocusable) {
            firstFocusable.focus();
        }
    }

    function closeSidebar() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
        
        // Add animation class
        sidebar.classList.add('sidebar-animate-out');
        sidebar.classList.remove('sidebar-animate-in');
        
        // Return focus to toggle button
        if (mobileToggle) {
            mobileToggle.focus();
        }
    }

    // Navigation link active state management
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = sidebar.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && href === currentPage) {
            link.classList.add('active');
        }
        
        // Close sidebar when clicking navigation links on mobile
        link.addEventListener('click', function() {
            if (window.innerWidth < 992) {
                setTimeout(() => closeSidebar(), 150);
            }
        });
    });

    // Smooth scrolling for anchor links within the page
    const anchorLinks = sidebar.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
                
                // Close sidebar on mobile after scrolling
                if (window.innerWidth < 992) {
                    setTimeout(() => closeSidebar(), 150);
                }
            }
        });
    });

    // Touch/swipe support for mobile
    let touchStartX = null;
    let touchStartY = null;

    document.addEventListener('touchstart', function(e) {
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
    });

    document.addEventListener('touchmove', function(e) {
        if (!touchStartX || !touchStartY) return;

        const touchCurrentX = e.touches[0].clientX;
        const touchCurrentY = e.touches[0].clientY;
        const diffX = touchCurrentX - touchStartX;
        const diffY = touchCurrentY - touchStartY;

        // Horizontal swipe detection
        if (Math.abs(diffX) > Math.abs(diffY)) {
            if (diffX > 50 && touchStartX < 50) {
                // Swipe right from left edge - open sidebar
                openSidebar();
            } else if (diffX < -50 && sidebar.classList.contains('active')) {
                // Swipe left when sidebar is open - close sidebar
                closeSidebar();
            }
        }

        touchStartX = null;
        touchStartY = null;
    });

    // Initialize tooltips if Bootstrap is available
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = sidebar.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    }

    console.log('Unified donor sidebar initialized successfully');
});
