/**
 * Admin Panel JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle
    const toggleSidebarBtn = document.getElementById('toggleSidebar');
    const closeSidebarBtn = document.getElementById('closeSidebar');
    const adminSidebar = document.getElementById('adminSidebar');
    const adminContent = document.getElementById('adminContent');
    
    // Toggle sidebar on button click
    if (toggleSidebarBtn && adminSidebar) {
        toggleSidebarBtn.addEventListener('click', function() {
            if (window.innerWidth >= 992) {
                // On desktop, collapse/expand sidebar
                adminSidebar.classList.toggle('admin-sidebar-collapsed');
                adminContent.classList.toggle('admin-content-expanded');
                
                // Toggle visibility of text in sidebar
                const sidebarTexts = document.querySelectorAll('.sidebar-text');
                sidebarTexts.forEach(text => {
                    text.style.display = adminSidebar.classList.contains('admin-sidebar-collapsed') ? 'none' : 'inline';
                });
            } else {
                // On mobile, show/hide sidebar
                adminSidebar.classList.toggle('show');
            }
        });
    }
    
    // Close sidebar on mobile
    if (closeSidebarBtn && adminSidebar) {
        closeSidebarBtn.addEventListener('click', function() {
            adminSidebar.classList.remove('show');
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        if (window.innerWidth < 992 && 
            adminSidebar && 
            adminSidebar.classList.contains('show') && 
            !adminSidebar.contains(event.target) && 
            toggleSidebarBtn && 
            !toggleSidebarBtn.contains(event.target)) {
            adminSidebar.classList.remove('show');
        }
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (adminSidebar && adminContent) {
            if (window.innerWidth >= 992) {
                // Reset mobile view
                adminSidebar.classList.remove('show');
                
                // Check if sidebar should be collapsed
                const sidebarTexts = document.querySelectorAll('.sidebar-text');
                if (adminSidebar.classList.contains('admin-sidebar-collapsed')) {
                    sidebarTexts.forEach(text => {
                        text.style.display = 'none';
                    });
                }
            } else {
                // Reset desktop view
                adminSidebar.classList.remove('admin-sidebar-collapsed');
                adminContent.classList.remove('admin-content-expanded');
                
                // Show text in sidebar
                const sidebarTexts = document.querySelectorAll('.sidebar-text');
                sidebarTexts.forEach(text => {
                    text.style.display = 'inline';
                });
            }
        }
    });
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Add class to parent if link is active
    const activeNavItems = document.querySelectorAll('.nav-link.active');
    activeNavItems.forEach(item => {
        const parent = item.closest('.nav-item');
        if (parent) {
            parent.classList.add('active-parent');
        }
    });
    
    // Toggle dark mode - if implemented
    const darkModeToggle = document.getElementById('darkModeToggle');
    if (darkModeToggle) {
        darkModeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            
            // Save preference to localStorage
            const isDarkMode = document.body.classList.contains('dark-mode');
            localStorage.setItem('darkMode', isDarkMode ? 'enabled' : 'disabled');
        });
        
        // Check for saved dark mode preference
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.body.classList.add('dark-mode');
        }
    }
    
    // Responsive tables
    const tables = document.querySelectorAll('.table');
    tables.forEach(table => {
        if (!table.parentElement.classList.contains('table-responsive')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
    });
}); 