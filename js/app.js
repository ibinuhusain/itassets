// Client-side JavaScript for Apparels Collection App

// Configuration
const APP_CONFIG = {
    syncInterval: 300000,  // 5 minutes
    backupInterval: 300000, // 5 minutes
    cleanupDays: 2,        // days to keep data
    refreshInterval: 600000 // 10 minutes for page refresh
};

// Main App Class
class ApparelsCollectionApp {
    constructor() {
        this.isInitialized = false;
        this.pendingSyncKey = 'pending_sync_data';
        this.localBackupKey = 'local_backup';
    }

    // Initialize the application
    init() {
        if (this.isInitialized) return;
        
        document.addEventListener('DOMContentLoaded', () => {
            this.setupEventListeners();
            this.setupResponsiveTables();
            this.setupPeriodicTasks();
            this.setupUIComponents();
            this.setupPWAInstallPrompt();
            this.cleanupOldData();
            this.checkOnlineStatus();
            
            // Initial sync if online
            if (navigator.onLine) {
                this.syncOfflineData();
            }
            
            this.isInitialized = true;
        });
    }

    // Setup all event listeners
    setupEventListeners() {
        window.addEventListener('online', () => this.handleOnlineStatus());
        window.addEventListener('offline', () => this.handleOfflineStatus());
    }

    // Setup periodic background tasks
    setupPeriodicTasks() {
        // Set up periodic local backup
        setInterval(() => this.saveLocalBackup(), APP_CONFIG.backupInterval);
        
        // Set up periodic sync check
        setInterval(() => this.syncOfflineData(), APP_CONFIG.syncInterval);
        
        // Set up periodic cleanup
        setInterval(() => this.cleanupOldData(), APP_CONFIG.syncInterval * 4);
        
        // Auto-refresh dashboard if needed
        if (window.location.href.includes('dashboard.php')) {
            setInterval(() => location.reload(), APP_CONFIG.refreshInterval);
        }
    }

    // Setup UI components
    setupUIComponents() {
        this.setupHamburgerMenu();
        this.setupResponsiveTables();
        this.setupImagePreviews();
    }
    
    
    setupResponsiveTables() {
    const tables = document.querySelectorAll('.table-responsive table');
    
    tables.forEach(table => {
        // Make table rows clickable on mobile
        if (window.innerWidth <= 768) {
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const link = row.querySelector('a.btn');
                if (link) {
                    row.style.cursor = 'pointer';
                    row.addEventListener('click', (e) => {
                        if (!e.target.matches('a, button, input')) {
                            window.location.href = link.href;
                        }
                    });
                }
            });
        }
        
        // Add data-label attributes for mobile
        if (window.innerWidth <= 768) {
            const headers = table.querySelectorAll('thead th');
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                cells.forEach((cell, index) => {
                    if (headers[index]) {
                        const headerText = headers[index].textContent.trim();
                        cell.setAttribute('data-label', headerText);
                    }
                });
            });
        }
    });
    
    // Add resize event listener to update on screen size change
    window.addEventListener('resize', () => {
        this.setupResponsiveTables();
    });
}

    // Setup hamburger menu
    setupHamburgerMenu() {
        const hamburger = document.querySelector('.hamburger-menu');
        const sidebar = document.querySelector('.sidebar');
        let overlay = document.querySelector('.overlay');

        // Create overlay if it doesn't exist
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'overlay';
            document.body.appendChild(overlay);
        }

        if (!hamburger || !sidebar) {
            return;
        }

        // Toggle sidebar
        const toggleSidebar = () => {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            document.body.classList.toggle('menu-open');
        };

        // Close sidebar
        const closeSidebar = () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.classList.remove('menu-open');
        };

        // Hamburger click
        hamburger.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleSidebar();
        });

        // Overlay click
        overlay.addEventListener('click', closeSidebar);

        // Close when clicking on nav items (mobile)
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    closeSidebar();
                }
            });
        });

        // Close when clicking outside (desktop)
        document.addEventListener('click', (e) => {
            if (window.innerWidth > 768 && 
                !sidebar.contains(e.target) && 
                !hamburger.contains(e.target) &&
                sidebar.classList.contains('active')) {
                closeSidebar();
            }
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768 && sidebar.classList.contains('active')) {
                closeSidebar();
            }
        });
    }

    // Setup responsive tables
    setupResponsiveTables() {
    const tables = document.querySelectorAll('table');
    
    tables.forEach(table => {
        // Check if already wrapped
        if (table.parentElement.classList.contains('table-responsive')) {
            return;
        }
        
        // Create wrapper
        const wrapper = document.createElement('div');
        wrapper.className = 'table-responsive';
        
        // Insert wrapper before table and move table into wrapper
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);
        
        // Update for current screen size
        this.updateTableForMobile(table);
    });

    // Listen for resize events
    window.addEventListener('resize', () => {
        tables.forEach(table => this.updateTableForMobile(table));
    });
    
    // Also trigger on load
    window.addEventListener('load', () => {
        tables.forEach(table => this.updateTableForMobile(table));
    });
}

// Update table for mobile view
updateTableForMobile(table) {
    const isMobile = window.innerWidth < 768;
    const rows = table.querySelectorAll('tbody tr');
    
    if (isMobile) {
        // On mobile: Add condensed store info to first column
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 6) {
                // Extract key information from hidden columns
                const region = cells[1]?.textContent.trim() || '';
                const mall = cells[2]?.textContent.trim() || '';
                const entity = cells[3]?.textContent.trim() || '';
                const brand = cells[4]?.textContent.trim() || '';
                const collected = cells[6]?.textContent.trim() || '';
                
                // Create condensed info string
                const storeInfo = [];
                if (brand) storeInfo.push(brand);
                if (mall) storeInfo.push(mall);
                if (entity) storeInfo.push(entity);
                if (region) storeInfo.push(region);
                if (collected && collected !== '0.00') storeInfo.push(`Collected: ${collected}`);
                
                // Add data attribute to first cell
                const firstCell = cells[0];
                if (firstCell) {
                    firstCell.setAttribute('data-store-info', storeInfo.join(' • '));
                }
            }
        });
    } else {
        // On desktop: Remove mobile-specific attributes
        rows.forEach(row => {
            const firstCell = row.querySelector('td:first-child');
            if (firstCell) {
                firstCell.removeAttribute('data-store-info');
            }
        });
    }
}

    // Setup image previews
    setupImagePreviews() {
        document.addEventListener('change', (e) => {
            if (e.target.matches('input[type="file"][data-preview]')) {
                this.previewImages(e.target, e.target.dataset.preview);
            }
        });
    }

    // Preview images
    previewImages(input, containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        container.innerHTML = '';
        
        if (input.files) {
            Array.from(input.files).forEach(file => {
                const reader = new FileReader();
                
                reader.onload = (e) => {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'image-preview';
                    container.appendChild(img);
                };
                
                reader.readAsDataURL(file);
            });
        }
    }

    // Handle online status
    handleOnlineStatus() {
        this.hideOfflineIndicator();
        this.syncOfflineData();
    }

    // Handle offline status
    handleOfflineStatus() {
        this.showOfflineIndicator();
    }

    // Show offline indicator
    showOfflineIndicator() {
        if (document.getElementById('offline-indicator')) return;

        const offlineIndicator = document.createElement('div');
        offlineIndicator.id = 'offline-indicator';
        offlineIndicator.className = 'offline-indicator';
        offlineIndicator.textContent = '⚠️ Offline Mode Active';
        document.body.appendChild(offlineIndicator);
    }

    // Hide offline indicator
    hideOfflineIndicator() {
        const indicator = document.getElementById('offline-indicator');
        if (indicator) {
            indicator.remove();
        }
    }

    // Check initial online status
    checkOnlineStatus() {
        if (!navigator.onLine) {
            this.showOfflineIndicator();
        }
    }

    // Save local backup
    saveLocalBackup() {
        const userData = {
            timestamp: new Date().toISOString(),
        };
        
        localStorage.setItem(this.localBackupKey, JSON.stringify(userData));
    }

    // Sync offline data
    async syncOfflineData() {
        const pendingData = this.getPendingSyncData();
        
        if (pendingData.length > 0 && navigator.onLine) {
            const itemsToSync = [...pendingData];
            
            for (const item of itemsToSync) {
                try {
                    const response = await fetch(item.url, {
                        method: item.method,
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(item.data)
                    });
                    
                    if (response.ok) {
                        const index = pendingData.findIndex(i => i.timestamp === item.timestamp);
                        if (index !== -1) {
                            pendingData.splice(index, 1);
                            this.savePendingSyncData(pendingData);
                        }
                    }
                } catch (error) {
                    // Handle sync error
                }
            }
        }
    }

    // Queue data for sync
    queueForSync(url, method, data) {
        const pendingData = this.getPendingSyncData();
        pendingData.push({
            url,
            method,
            data,
            timestamp: new Date().toISOString()
        });
        this.savePendingSyncData(pendingData);
    }

    // Get pending sync data
    getPendingSyncData() {
        return JSON.parse(localStorage.getItem(this.pendingSyncKey)) || [];
    }

    // Save pending sync data
    savePendingSyncData(data) {
        localStorage.setItem(this.pendingSyncKey, JSON.stringify(data));
    }

    // Cleanup old data
    cleanupOldData() {
        const cutoffDate = new Date();
        cutoffDate.setDate(cutoffDate.getDate() - APP_CONFIG.cleanupDays);
        
        // Cleanup old backups
        const backup = localStorage.getItem(this.localBackupKey);
        if (backup) {
            try {
                const backupObj = JSON.parse(backup);
                const backupTime = new Date(backupObj.timestamp);
                
                if (backupTime < cutoffDate) {
                    localStorage.removeItem(this.localBackupKey);
                }
            } catch (e) {
                // Handle error
            }
        }
        
        // Cleanup old pending sync data (older than 7 days)
        const pendingData = this.getPendingSyncData();
        const sevenDaysAgo = new Date();
        sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
        
        const filteredPendingData = pendingData.filter(item => {
            try {
                const itemTime = new Date(item.timestamp);
                return itemTime >= sevenDaysAgo;
            } catch (e) {
                return false;
            }
        });
        
        if (filteredPendingData.length !== pendingData.length) {
            this.savePendingSyncData(filteredPendingData);
        }
    }

    // Setup PWA install prompt
    setupPWAInstallPrompt() {
        let deferredPrompt;
        const installButton = document.getElementById('installButton');
        const installContainer = document.getElementById('installContainer');

        if (window.matchMedia('(display-mode: standalone)').matches) {
            return;
        }

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;

            if (installContainer) {
                installContainer.style.display = 'block';
            }

            if (installButton) {
                installButton.addEventListener('click', async () => {
                    if (deferredPrompt) {
                        deferredPrompt.prompt();
                        const { outcome } = await deferredPrompt.userChoice;
                        if (installContainer) {
                            installContainer.style.display = 'none';
                        }
                        deferredPrompt = null;
                    }
                });
            }
        });

        window.addEventListener('appinstalled', () => {
            if (installContainer) {
                installContainer.style.display = 'none';
            }
            deferredPrompt = null;
        });
    }
}

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    const app = new ApparelsCollectionApp();
    app.init();
});