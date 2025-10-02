/**
 * Modern TakePos Mobile JavaScript
 * Enhanced mobile functionality for touch devices
 * Copyright (C) 2024 - Modern POS Interface
 */

class ModernMobilePOS {
    constructor() {
        this.currentTab = 'products';
        this.currentCategory = null;
        this.selectedLine = null;
        this.editMode = null;
        this.editValue = '';
        this.invoiceId = 0;
        this.place = '';
        this.searchTimer = null;
        this.refreshTimer = null;
        this.touchStartTime = 0;
        this.touchStartPos = { x: 0, y: 0 };
        
        // Mobile-specific configuration
        this.config = {
            searchDelay: 300,
            refreshInterval: 10000,
            swipeThreshold: 50,
            longPressDelay: 500,
            hapticEnabled: true,
            pullToRefreshThreshold: 80
        };
        
        this.init();
    }
    
    init() {
        this.setupMobileEventListeners();
        this.setupTouchGestures();
        this.setupPullToRefresh();
        this.setupHapticFeedback();
        this.preventZoom();
        this.handleOrientationChange();
        this.setupServiceWorker();
        
        console.log('Modern Mobile POS initialized');
    }
    
    setupMobileEventListeners() {
        // Search functionality with mobile optimizations
        const searchInput = document.getElementById('mobileSearch');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.handleMobileSearch(e.target.value);
            });
            
            // Prevent zoom on focus for iOS
            searchInput.addEventListener('focus', (e) => {
                e.target.style.fontSize = '16px';
            });
            
            searchInput.addEventListener('blur', (e) => {
                e.target.style.fontSize = '';
            });
        }
        
        // Tab switching with touch feedback
        document.querySelectorAll('.modern-mobile-tab').forEach(tab => {
            tab.addEventListener('touchstart', (e) => {
                this.addTouchFeedback(e.target);
            });
            
            tab.addEventListener('click', (e) => {
                this.switchTab(e.target.dataset.tab);
            });
        });
        
        // Category selection
        document.querySelectorAll('.modern-category-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.selectCategory(e.target.dataset.category);
            });
        });
        
        // Prevent double-tap zoom
        let lastTouchEnd = 0;
        document.addEventListener('touchend', (e) => {
            const now = Date.now();
            if (now - lastTouchEnd <= 300) {
                e.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
        
        // Handle visibility change (app switching)
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.refreshData();
            }
        });
        
        // Handle online/offline status
        window.addEventListener('online', () => {
            this.showFeedback('Connection restored', 'success');
            this.refreshData();
        });
        
        window.addEventListener('offline', () => {
            this.showFeedback('Working offline', 'warning');
        });
    }
    
    setupTouchGestures() {
        let startX = 0;
        let startY = 0;
        let startTime = 0;
        
        document.addEventListener('touchstart', (e) => {
            startX = e.touches[0].pageX;
            startY = e.touches[0].pageY;
            startTime = Date.now();
            this.touchStartTime = startTime;
            this.touchStartPos = { x: startX, y: startY };
        }, { passive: true });
        
        document.addEventListener('touchend', (e) => {
            if (!startX || !startY) return;
            
            const endX = e.changedTouches[0].pageX;
            const endY = e.changedTouches[0].pageY;
            const endTime = Date.now();
            
            const diffX = startX - endX;
            const diffY = startY - endY;
            const timeDiff = endTime - startTime;
            
            // Swipe gestures for tab switching
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > this.config.swipeThreshold && timeDiff < 300) {
                if (diffX > 0) {
                    this.switchToNextTab();
                } else {
                    this.switchToPreviousTab();
                }
            }
            
            // Long press detection
            if (timeDiff > this.config.longPressDelay && Math.abs(diffX) < 10 && Math.abs(diffY) < 10) {
                this.handleLongPress(e);
            }
            
            startX = 0;
            startY = 0;
            startTime = 0;
        }, { passive: true });
    }
    
    setupPullToRefresh() {
        const refreshContainer = document.getElementById('productsRefresh');
        if (!refreshContainer) return;
        
        let startY = 0;
        let currentY = 0;
        let isPulling = false;
        let pullDistance = 0;
        
        refreshContainer.addEventListener('touchstart', (e) => {
            startY = e.touches[0].pageY;
            isPulling = refreshContainer.scrollTop === 0;
        }, { passive: true });
        
        refreshContainer.addEventListener('touchmove', (e) => {
            if (!isPulling) return;
            
            currentY = e.touches[0].pageY;
            pullDistance = currentY - startY;
            
            if (pullDistance > 0 && pullDistance < 150) {
                const indicator = refreshContainer.querySelector('.pull-to-refresh-indicator');
                const translateY = Math.min(pullDistance / 2, 75);
                
                refreshContainer.style.transform = `translateY(${translateY}px)`;
                
                if (indicator) {
                    indicator.style.transform = `translateX(-50%) rotate(${pullDistance * 2}deg)`;
                    indicator.style.opacity = Math.min(pullDistance / 50, 1);
                }
                
                if (pullDistance > this.config.pullToRefreshThreshold) {
                    refreshContainer.classList.add('pulling');
                    this.hapticFeedback('light');
                }
            }
        }, { passive: true });
        
        refreshContainer.addEventListener('touchend', () => {
            if (!isPulling) return;
            
            if (pullDistance > this.config.pullToRefreshThreshold) {
                this.refreshProducts();
                this.showFeedback('Refreshing products...', 'info');
                this.hapticFeedback('medium');
            }
            
            // Reset transform
            refreshContainer.style.transform = '';
            refreshContainer.classList.remove('pulling');
            
            const indicator = refreshContainer.querySelector('.pull-to-refresh-indicator');
            if (indicator) {
                indicator.style.transform = 'translateX(-50%)';
                indicator.style.opacity = '0';
            }
            
            isPulling = false;
            pullDistance = 0;
        }, { passive: true });
    }
    
    setupHapticFeedback() {
        // Check if haptic feedback is supported
        this.hapticSupported = 'vibrate' in navigator;
        
        if (this.hapticSupported) {
            console.log('Haptic feedback enabled');
        }
    }
    
    hapticFeedback(type = 'light') {
        if (!this.config.hapticEnabled || !this.hapticSupported) return;
        
        const patterns = {
            light: 20,
            medium: 50,
            heavy: 100,
            success: [50, 25, 50],
            error: [100, 50, 100, 50, 100],
            warning: [75, 25, 75]
        };
        
        const pattern = patterns[type] || patterns.light;
        navigator.vibrate(pattern);
    }
    
    preventZoom() {
        // Prevent zoom on double tap
        let lastTouchEnd = 0;
        document.addEventListener('touchend', (e) => {
            const now = Date.now();
            if (now - lastTouchEnd <= 300) {
                e.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
        
        // Prevent zoom on pinch
        document.addEventListener('gesturestart', (e) => {
            e.preventDefault();
        });
        
        document.addEventListener('gesturechange', (e) => {
            e.preventDefault();
        });
        
        document.addEventListener('gestureend', (e) => {
            e.preventDefault();
        });
    }
    
    handleOrientationChange() {
        window.addEventListener('orientationchange', () => {
            setTimeout(() => {
                this.adjustLayoutForOrientation();
            }, 100);
        });
        
        // Initial adjustment
        this.adjustLayoutForOrientation();
    }
    
    adjustLayoutForOrientation() {
        const container = document.getElementById('modernMobileContainer');
        if (!container) return;
        
        const isLandscape = window.orientation === 90 || window.orientation === -90;
        
        if (isLandscape) {
            container.classList.add('landscape');
            // Adjust grid columns for landscape
            const productsGrid = document.getElementById('mobileProductsGrid');
            if (productsGrid) {
                productsGrid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(120px, 1fr))';
            }
        } else {
            container.classList.remove('landscape');
            // Reset grid for portrait
            const productsGrid = document.getElementById('mobileProductsGrid');
            if (productsGrid) {
                productsGrid.style.gridTemplateColumns = '';
            }
        }
        
        // Force layout recalculation
        window.dispatchEvent(new Event('resize'));
    }
    
    setupServiceWorker() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/takepos/sw.js')
                .then((registration) => {
                    console.log('Service Worker registered:', registration);
                })
                .catch((error) => {
                    console.log('Service Worker registration failed:', error);
                });
        }
    }
    
    // Tab management
    switchTab(tabName) {
        // Update tab buttons
        document.querySelectorAll('.modern-mobile-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        
        const activeTab = document.querySelector(`[data-tab="${tabName}"]`);
        if (activeTab) {
            activeTab.classList.add('active');
            this.hapticFeedback('light');
        }
        
        // Update panels
        document.querySelectorAll('.modern-mobile-panel').forEach(panel => {
            panel.classList.remove('active');
        });
        
        const targetPanel = document.getElementById(tabName + 'Panel');
        if (targetPanel) {
            targetPanel.classList.add('active');
            targetPanel.classList.add('slide-in-right');
            
            // Remove animation class after animation completes
            setTimeout(() => {
                targetPanel.classList.remove('slide-in-right');
            }, 300);
        }
        
        this.currentTab = tabName;
        
        // Load data if needed
        if (tabName === 'products') {
            this.loadProducts();
        } else if (tabName === 'invoice') {
            this.loadInvoice();
        }
    }
    
    switchToNextTab() {
        const tabs = ['products', 'invoice', 'actions'];
        const currentIndex = tabs.indexOf(this.currentTab);
        const nextIndex = (currentIndex + 1) % tabs.length;
        this.switchTab(tabs[nextIndex]);
    }
    
    switchToPreviousTab() {
        const tabs = ['products', 'invoice', 'actions'];
        const currentIndex = tabs.indexOf(this.currentTab);
        const prevIndex = (currentIndex - 1 + tabs.length) % tabs.length;
        this.switchTab(tabs[prevIndex]);
    }
    
    // Category management
    selectCategory(categoryId) {
        document.querySelectorAll('.modern-category-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        const activeBtn = document.querySelector(`[data-category="${categoryId}"]`);
        if (activeBtn) {
            activeBtn.classList.add('active');
            this.hapticFeedback('light');
        }
        
        this.currentCategory = categoryId;
        this.loadProducts(categoryId);
    }
    
    // Data loading
    async loadProducts(categoryId = null) {
        const productsGrid = document.getElementById('mobileProductsGrid');
        this.showProductsLoading();
        
        try {
            let url = `${DOL_URL_ROOT}/takepos/ajax/ajax.php?action=getProducts&token=${newToken()}&tosell=1`;
            if (categoryId && categoryId !== 'all') {
                url += `&category=${categoryId}`;
            }
            
            const response = await fetch(url);
            if (!response.ok) throw new Error('Network response was not ok');
            
            const products = await response.json();
            this.displayProducts(products);
            
        } catch (error) {
            console.error('Error loading products:', error);
            this.showProductsError();
            this.hapticFeedback('error');
        }
    }
    
    async refreshProducts() {
        await this.loadProducts(this.currentCategory);
    }
    
    displayProducts(products) {
        const productsGrid = document.getElementById('mobileProductsGrid');
        
        if (!products || products.length === 0) {
            productsGrid.innerHTML = `
                <div class="text-center text-muted p-4">
                    <i class="fa fa-search fa-3x mb-4"></i>
                    <div class="text-lg">No products found</div>
                    <div class="text-sm mt-2">Try a different category or search term</div>
                </div>
            `;
            return;
        }
        
        let html = '';
        products.forEach((product, index) => {
            const productId = product.rowid || product.id;
            const productLabel = product.label || product.product_label || 'Unknown Product';
            const productPrice = product.price_ttc_formated || product.price_formated || '€0.00';
            const productRef = product.ref || '';
            
            html += `
                <div class="modern-product-card fade-in-up touch-target" 
                     onclick="mobilePOS.addProductToInvoice(${productId})"
                     data-product-id="${productId}"
                     style="animation-delay: ${index * 30}ms">
                    <img src="${DOL_URL_ROOT}/takepos/genimg/index.php?query=pro&id=${productId}" 
                         class="modern-product-image" 
                         alt="${this.escapeHtml(productLabel)}"
                         onerror="this.src='${DOL_URL_ROOT}/takepos/genimg/empty.png'"
                         loading="lazy">
                    <div class="modern-product-name">${this.escapeHtml(productLabel)}</div>
                    ${productRef ? `<div class="modern-product-ref">${this.escapeHtml(productRef)}</div>` : ''}
                    <div class="modern-product-price">${productPrice}</div>
                </div>
            `;
        });
        
        productsGrid.innerHTML = html;
        
        // Add intersection observer for lazy loading
        this.setupLazyLoading();
    }
    
    setupLazyLoading() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src || img.src;
                        img.classList.remove('lazy');
                        observer.unobserve(img);
                    }
                });
            });
            
            document.querySelectorAll('img[loading="lazy"]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    }
    
    async addProductToInvoice(productId, qty = 1) {
        try {
            this.showFeedback('Adding product...', 'info');
            
            const url = `${DOL_URL_ROOT}/takepos/invoice.php?action=addline&token=${newToken()}&place=${this.place}&idproduct=${productId}&qty=${qty}&invoiceid=${this.invoiceId}`;
            const response = await fetch(url);
            
            if (!response.ok) throw new Error('Failed to add product');
            
            // Switch to invoice tab and refresh
            this.switchTab('invoice');
            await this.loadInvoice();
            
            this.showFeedback('Product added!', 'success');
            this.hapticFeedback('success');
            
        } catch (error) {
            console.error('Error adding product:', error);
            this.showFeedback('Error adding product', 'error');
            this.hapticFeedback('error');
        }
    }
    
    async loadInvoice() {
        try {
            const url = `${DOL_URL_ROOT}/takepos/invoice.php?place=${this.place}&invoiceid=${this.invoiceId}`;
            const response = await fetch(url);
            
            if (!response.ok) throw new Error('Failed to load invoice');
            
            const data = await response.text();
            this.updateInvoiceDisplay(data);
            
        } catch (error) {
            console.error('Error loading invoice:', error);
            this.showFeedback('Error loading invoice', 'error');
        }
    }
    
    updateInvoiceDisplay(invoiceData) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(invoiceData, 'text/html');
        
        // Extract invoice lines
        const lines = doc.querySelectorAll('.posinvoiceline');
        const invoiceLines = document.getElementById('mobileInvoiceLines');
        
        if (lines.length === 0) {
            invoiceLines.innerHTML = `
                <div class="text-center text-muted p-4">
                    <i class="fa fa-shopping-cart fa-3x mb-4"></i>
                    <div class="text-lg">Empty</div>
                    <div class="text-sm mt-2">Add products to start</div>
                </div>
            `;
        } else {
            let html = '';
            lines.forEach((line, index) => {
                const lineId = line.id;
                const productName = this.extractProductName(line.innerHTML);
                const quantity = this.extractQuantity(line.innerHTML);
                const price = this.extractPrice(line.innerHTML);
                
                html += `
                    <div class="modern-invoice-line touch-target ${this.selectedLine === lineId ? 'selected' : ''}" 
                         onclick="mobilePOS.selectInvoiceLine('${lineId}')"
                         data-line-id="${lineId}">
                        <div class="modern-line-info">
                            <div class="modern-line-name">${productName}</div>
                            <div class="modern-line-details">Line ${index + 1}</div>
                        </div>
                        <div class="modern-line-qty">${quantity}</div>
                        <div class="modern-line-price">${price}</div>
                    </div>
                `;
            });
            
            invoiceLines.innerHTML = html;
        }
        
        // Update total amount
        this.updateTotalAmount(doc);
        
        // Update invoice ID
        const invoiceIdInput = doc.querySelector('#invoiceid');
        if (invoiceIdInput) {
            this.invoiceId = invoiceIdInput.value;
        }
    }
    
    selectInvoiceLine(lineId) {
        this.selectedLine = lineId;
        
        // Update visual selection
        document.querySelectorAll('.modern-invoice-line').forEach(line => {
            line.classList.remove('selected');
        });
        
        const selectedElement = document.querySelector(`[data-line-id="${lineId}"]`);
        if (selectedElement) {
            selectedElement.classList.add('selected');
            this.hapticFeedback('light');
        }
    }
    
    // Search functionality
    handleMobileSearch(searchTerm) {
        clearTimeout(this.searchTimer);
        
        this.searchTimer = setTimeout(() => {
            this.searchProducts(searchTerm);
        }, this.config.searchDelay);
    }
    
    async searchProducts(searchTerm) {
        if (searchTerm.length < 2) {
            this.loadProducts(this.currentCategory);
            return;
        }
        
        this.showProductsLoading();
        
        try {
            const url = `${DOL_URL_ROOT}/takepos/ajax/ajax.php?action=search&token=${newToken()}&search_term=${encodeURIComponent(searchTerm)}`;
            const response = await fetch(url);
            
            if (!response.ok) throw new Error('Search failed');
            
            const products = await response.json();
            this.displayProducts(products);
            
        } catch (error) {
            console.error('Error searching products:', error);
            this.showProductsError();
        }
    }
    
    // UI feedback
    showFeedback(message, type = 'info') {
        const feedback = document.createElement('div');
        feedback.className = `modern-feedback modern-feedback-${type} mobile-feedback`;
        feedback.innerHTML = `
            <i class="fa fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'}-circle"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(feedback);
        
        setTimeout(() => feedback.classList.add('show'), 10);
        
        setTimeout(() => {
            feedback.classList.remove('show');
            setTimeout(() => {
                if (feedback.parentNode) {
                    feedback.parentNode.removeChild(feedback);
                }
            }, 300);
        }, 3000);
    }
    
    showProductsLoading() {
        const productsGrid = document.getElementById('mobileProductsGrid');
        if (productsGrid) {
            productsGrid.innerHTML = `
                <div class="mobile-loading">
                    <div class="mobile-loading-spinner"></div>
                    <div>Loading products...</div>
                </div>
            `;
        }
    }
    
    showProductsError() {
        const productsGrid = document.getElementById('mobileProductsGrid');
        if (productsGrid) {
            productsGrid.innerHTML = `
                <div class="text-center text-muted p-4">
                    <i class="fa fa-exclamation-triangle fa-3x mb-4"></i>
                    <div class="text-lg">Error loading products</div>
                    <button class="modern-action-btn secondary mt-4 touch-target" onclick="mobilePOS.refreshProducts()">
                        <i class="fa fa-refresh"></i> Retry
                    </button>
                </div>
            `;
        }
    }
    
    // Touch feedback
    addTouchFeedback(element) {
        element.classList.add('touch-active');
        setTimeout(() => {
            element.classList.remove('touch-active');
        }, 150);
    }
    
    handleLongPress(e) {
        const target = e.target.closest('.modern-product-card');
        if (target) {
            const productId = target.dataset.productId;
            if (productId) {
                this.showProductOptions(productId);
                this.hapticFeedback('heavy');
            }
        }
    }
    
    showProductOptions(productId) {
        // Show product options modal (quantity selection, etc.)
        const modal = document.createElement('div');
        modal.className = 'modal mobile-modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <span class="close" onclick="this.closest('.modal').remove()">&times;</span>
                    <h3>Product Options</h3>
                </div>
                <div class="modal-body">
                    <div class="quantity-selector">
                        <label>Quantity:</label>
                        <div class="quantity-controls">
                            <button class="modern-action-btn secondary" onclick="this.nextElementSibling.stepDown()">-</button>
                            <input type="number" value="1" min="1" max="99" class="quantity-input">
                            <button class="modern-action-btn secondary" onclick="this.previousElementSibling.stepUp()">+</button>
                        </div>
                    </div>
                    <button class="modern-action-btn success full-width mt-4" onclick="mobilePOS.addProductWithQuantity(${productId}, this.closest('.modal').querySelector('.quantity-input').value); this.closest('.modal').remove();">
                        Add to Invoice
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        modal.style.display = 'block';
    }
    
    addProductWithQuantity(productId, quantity) {
        this.addProductToInvoice(productId, parseInt(quantity) || 1);
    }
    
    // Data refresh
    refreshData() {
        if (this.currentTab === 'products') {
            this.loadProducts(this.currentCategory);
        } else if (this.currentTab === 'invoice') {
            this.loadInvoice();
        }
    }
    
    // Utility functions
    extractProductName(lineContent) {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = lineContent;
        const productLabel = tempDiv.querySelector('.product_label') || tempDiv.querySelector('td:first-child');
        return productLabel ? productLabel.textContent.trim() : 'Unknown Product';
    }
    
    extractQuantity(lineContent) {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = lineContent;
        const qtyCell = tempDiv.querySelector('.linecolqty');
        return qtyCell ? qtyCell.textContent.trim() : '1';
    }
    
    extractPrice(lineContent) {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = lineContent;
        const priceCell = tempDiv.querySelector('.linecolht:last-child');
        return priceCell ? priceCell.textContent.trim() : '€0.00';
    }
    
    updateTotalAmount(doc) {
        const totalElement = document.getElementById('mobileTotalAmount');
        const invoiceTotal = doc.querySelector('#linecolht-span-total');
        
        if (totalElement && invoiceTotal) {
            totalElement.textContent = invoiceTotal.textContent.trim();
        }
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Performance monitoring
    startPerformanceMonitoring() {
        if ('performance' in window) {
            // Monitor navigation timing
            window.addEventListener('load', () => {
                const perfData = performance.getEntriesByType('navigation')[0];
                console.log('Page load time:', perfData.loadEventEnd - perfData.loadEventStart, 'ms');
            });
            
            // Monitor resource timing
            const observer = new PerformanceObserver((list) => {
                for (const entry of list.getEntries()) {
                    if (entry.duration > 1000) {
                        console.warn('Slow resource:', entry.name, entry.duration, 'ms');
                    }
                }
            });
            
            observer.observe({ entryTypes: ['resource'] });
        }
    }
    
    // Cleanup
    destroy() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
        }
        
        if (this.searchTimer) {
            clearTimeout(this.searchTimer);
        }
        
        // Remove event listeners
        document.removeEventListener('visibilitychange', this.refreshData);
        window.removeEventListener('orientationchange', this.adjustLayoutForOrientation);
    }
}

// Global instance
let mobilePOS;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    mobilePOS = new ModernMobilePOS();
    
    // Make functions globally available for onclick handlers
    window.mobilePOS = mobilePOS;
    
    // Global functions for backward compatibility
    window.switchMobileTab = (tab) => mobilePOS.switchTab(tab);
    window.selectMobileCategory = (categoryId) => mobilePOS.selectCategory(categoryId);
    window.addMobileProductToInvoice = (productId, qty) => mobilePOS.addProductToInvoice(productId, qty);
    window.selectMobileInvoiceLine = (lineId) => mobilePOS.selectInvoiceLine(lineId);
    window.handleMobileSearch = (searchTerm) => mobilePOS.handleMobileSearch(searchTerm);
    window.clearMobileSearch = () => {
        document.getElementById('mobileSearch').value = '';
        mobilePOS.loadProducts(mobilePOS.currentCategory);
    };
});

// Service Worker for offline functionality
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/takepos/sw.js')
            .then((registration) => {
                console.log('SW registered: ', registration);
            })
            .catch((registrationError) => {
                console.log('SW registration failed: ', registrationError);
            });
    });
}
