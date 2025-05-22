/**
 * Zakat Fitrah Layout Fix Script
 * Ensures proper layout between sidebar and main content
 */

document.addEventListener('DOMContentLoaded', function() {
    // Check if wrapper exists
    const wrapper = document.querySelector('.wrapper');
    if (!wrapper) return;

    // Ensure wrapper has display: flex
    wrapper.style.display = 'flex';
    wrapper.style.minHeight = '100vh';
    wrapper.style.width = '100%';
    wrapper.style.position = 'relative';
    wrapper.style.overflowX = 'hidden';

    // Fix sidebar
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.style.width = '250px';
        sidebar.style.height = '100vh';
        sidebar.style.position = 'fixed';
        sidebar.style.top = '0';
        sidebar.style.left = '0';
        sidebar.style.bottom = '0';
        sidebar.style.zIndex = '100';
        sidebar.style.overflowY = 'auto';
        sidebar.style.overflowX = 'hidden';
        sidebar.style.backgroundImage = 'linear-gradient(180deg, #4e73df 10%, #224abe 100%)';
        sidebar.style.backgroundSize = 'cover';
        sidebar.style.color = '#fff';
    }

    // Fix main content
    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        mainContent.style.flex = '1';
        mainContent.style.marginLeft = '250px';
        mainContent.style.width = 'calc(100% - 250px)';
        mainContent.style.minHeight = '100vh';
        mainContent.style.overflowX = 'hidden';
        mainContent.style.backgroundColor = '#f8f9fa';
        mainContent.style.padding = '1.5rem';
    }

    // Responsive fixes for mobile
    function handleResize() {
        const isMobile = window.innerWidth <= 768;
        
        if (isMobile) {
            if (sidebar) {
                sidebar.style.left = '-250px';
                sidebar.style.transition = 'left 0.3s ease';
                
                // Create toggle button if it doesn't exist
                if (!document.querySelector('#sidebarToggle')) {
                    const toggleBtn = document.createElement('button');
                    toggleBtn.id = 'sidebarToggle';
                    toggleBtn.className = 'btn btn-primary btn-sm position-fixed';
                    toggleBtn.style.top = '10px';
                    toggleBtn.style.left = '10px';
                    toggleBtn.style.zIndex = '1031';
                    toggleBtn.innerHTML = '<i class="bi bi-list"></i>';
                    document.body.appendChild(toggleBtn);
                    
                    toggleBtn.addEventListener('click', function() {
                        if (sidebar.style.left === '0px') {
                            sidebar.style.left = '-250px';
                            mainContent.style.marginLeft = '0';
                            // Remove backdrop if exists
                            const backdrop = document.querySelector('.sidebar-backdrop');
                            if (backdrop) backdrop.remove();
                        } else {
                            sidebar.style.left = '0px';
                            // Add backdrop for mobile
                            if (isMobile) {
                                const backdrop = document.createElement('div');
                                backdrop.className = 'sidebar-backdrop';
                                backdrop.style.position = 'fixed';
                                backdrop.style.top = '0';
                                backdrop.style.left = '0';
                                backdrop.style.width = '100%';
                                backdrop.style.height = '100%';
                                backdrop.style.backgroundColor = 'rgba(0,0,0,0.3)';
                                backdrop.style.zIndex = '99';
                                document.body.appendChild(backdrop);
                                
                                backdrop.addEventListener('click', function() {
                                    sidebar.style.left = '-250px';
                                    this.remove();
                                });
                            }
                        }
                    });
                }
            }
            
            if (mainContent) {
                mainContent.style.width = '100%';
                mainContent.style.marginLeft = '0';
            }
        } else {
            if (sidebar) {
                sidebar.style.left = '0';
            }
            
            if (mainContent) {
                mainContent.style.width = 'calc(100% - 250px)';
                mainContent.style.marginLeft = '250px';
            }
            
            // Remove toggle button if it exists
            const toggleBtn = document.querySelector('#sidebarToggle');
            if (toggleBtn) {
                toggleBtn.remove();
            }
            
            // Remove backdrop if exists
            const backdrop = document.querySelector('.sidebar-backdrop');
            if (backdrop) backdrop.remove();
        }
    }

    // Initial call
    handleResize();
    
    // Listen for window resize events
    window.addEventListener('resize', handleResize);
}); 