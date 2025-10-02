/**
 * Modern TakePos JavaScript Framework
 * Enhanced POS functionality with modern UI/UX
 * Copyright (C) 2024 - Modern POS Interface
 */

class ModernPOS {
    constructor() {
        this.currentCategory = null;
        this.currentInvoice = null;
        this.selectedLine = null;
        this.editMode = null;
        this.editValue = '';
        this.place = '';
        this.invoiceid = 0;
        this.searchTimer = null;
        this.refreshTimer = null;
        
        // Configuration
        this.config = {
            searchDelay: 300,
            refreshInterval: 5000,
            animationDuration: 250
        };
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.loadInitialData();
        this.startAutoRefresh();
        
        // Add loading states
        this.showLoading();
        
        console.log('Modern POS initialized');
    }
    
    setupEventListeners() {
        // Search functionality
        const searchInput = document.getElementById('modernSearch');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.handleSearch(e.target.value);
            });
            
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.handleSearchEnter(e.target.value);
                }
            });
        }
        
        // Category navigation
        this.setupCategoryNavigation();
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            this.handleKeyboardShortcuts(e);
        });
        
        // Touch/click events for mobile
        this.setupTouchEvents();
        
        // Window resize handling
        window.addEventListener('resize', () => {
            this.handleResize();
        });
        
        // Prevent context menu on long press (mobile)
        document.addEventListener('contextmenu', (e) => {
            if (e.target.closest('.modern-product-card') || e.target.closest('.modern-numpad-btn')) {
                e.preventDefault();
            }
        });
    }
    
    setupCategoryNavigation() {
        const categoryButtons = document.querySelectorAll('.modern-category-btn');
        categoryButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.selectCategory(e.target.dataset.category);
            });
        });
    }
    
    setupTouchEvents() {
        // Add touch feedback for better mobile experience
        const touchElements = document.querySelectorAll('.modern-product-card, .modern-numpad-btn, .modern-action-btn');
        
        touchElements.forEach(element => {
            element.addEventListener('touchstart', (e) => {
                element.classList.add('touch-active');
            });
            
            element.addEventListener('touchend', (e) => {
                setTimeout(() => {
                    element.classList.remove('touch-active');
                }, 150);
            });
            
            element.addEventListener('touchcancel', (e) => {
                element.classList.remove('touch-active');
            });
        });
    }
    
    handleResize() {
        // Adjust layout for different screen sizes
        const container = document.getElementById('modernPosContainer');
        const width = window.innerWidth;
        
        if (width < 768) {
            container.classList.add('mobile-layout');
        } else {
            container.classList.remove('mobile-layout');
        }
        
        if (width < 1024) {
            container.classList.add('tablet-layout');
        } else {
            container.classList.remove('tablet-layout');
        }
    }
    
    handleSearch(searchTerm) {
        // Clear previous timer
        if (this.searchTimer) {
            clearTimeout(this.searchTimer);
        }
        
        // Debounce search
        this.searchTimer = setTimeout(() => {
            this.searchProducts(searchTerm);
        }, this.config.searchDelay);
    }
    
    handleSearchEnter(searchTerm) {
        // Clear timer and search immediately
        if (this.searchTimer) {
            clearTimeout(this.searchTimer);
        }
        this.searchProducts(searchTerm, true);
    }
    
    selectCategory(categoryId) {
        // Update active category button
        document.querySelectorAll('.modern-category-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        const activeBtn = document.querySelector(`[data-category="${categoryId}"]`);
        if (activeBtn) {
            activeBtn.classList.add('active');
        }
        
        this.currentCategory = categoryId;
        this.loadProducts(categoryId);
    }
    
    async loadProducts(categoryId = null) {
        const productsGrid = document.getElementById('productsGrid');
        this.showProductsLoading();
        
        try {
            let url = `${DOL_URL_ROOT}/takepos/ajax/ajax.php?action=getProducts&token=${newToken()}&tosell=1`;
            if (categoryId && categoryId !== 'all') {
                url += `&category=${categoryId}`;
            }
            
            const response = await fetch(url);
            const products = await response.json();
            
            this.displayProducts(products);
        } catch (error) {
            console.error('Error loading products:', error);
            this.showProductsError();
        }
    }
    
    async searchProducts(searchTerm, isEnterSearch = false) {
        if (searchTerm.length < 2 && !isEnterSearch) {
            this.loadProducts(this.currentCategory);
            return;
        }
        
        const productsGrid = document.getElementById('productsGrid');
        this.showProductsLoading();
        
        try {
            const url = `${DOL_URL_ROOT}/takepos/ajax/ajax.php?action=search&token=${newToken()}&search_term=${encodeURIComponent(searchTerm)}`;
            const response = await fetch(url);
            const products = await response.json();
            
            this.displayProducts(products);
            
            // Handle single product selection on enter
            if (isEnterSearch && products.length === 1) {
                this.addProductToInvoice(products[0].rowid || products[0].id);
                this.clearSearch();
            }
        } catch (error) {
            console.error('Error searching products:', error);
            this.showProductsError();
        }
    }
    
    displayProducts(products) {
        const productsGrid = document.getElementById('productsGrid');
        
        if (!products || products.length === 0) {
            productsGrid.innerHTML = `
                <div class="text-center text-muted p-4">
                    <i class="fa fa-search fa-2x mb-2"></i>
                    <div>No products found</div>
                </div>
            `;
            return;
        }
        
        let html = '';
        products.forEach((product, index) => {
            const productId = product.rowid || product.id;
            const productLabel = product.label || product.product_label || 'Unknown Product';
            const productPrice = product.price_ttc_formated || product.price_formated || '€0.00';
            
            html += `
                <div class="modern-product-card fade-in" 
                     onclick="modernPos.addProductToInvoice(${productId})"
                     style="animation-delay: ${index * 50}ms">
                    <img src="${DOL_URL_ROOT}/takepos/genimg/index.php?query=pro&id=${productId}" 
                         class="modern-product-image" 
                         alt="${this.escapeHtml(productLabel)}"
                         onerror="this.src='${DOL_URL_ROOT}/takepos/genimg/empty.png'"
                         loading="lazy">
                    <div class="modern-product-name">${this.escapeHtml(productLabel)}</div>
                    <div class="modern-product-price">${productPrice}</div>
                </div>
            `;
        });
        
        productsGrid.innerHTML = html;
    }
    
    async addProductToInvoice(productId, qty = 1) {
        try {
            // Show loading state
            this.showInvoiceLoading();
            
            const url = `${DOL_URL_ROOT}/takepos/invoice.php?action=addline&token=${newToken()}&place=${this.place}&idproduct=${productId}&qty=${qty}&invoiceid=${this.invoiceid}`;
            const response = await fetch(url);
            const data = await response.text();
            
            // Refresh invoice display
            await this.loadInvoice();
            
            // Show success feedback
            this.showSuccessFeedback(`Product added to invoice`);
            
        } catch (error) {
            console.error('Error adding product:', error);
            this.showErrorFeedback('Error adding product to invoice');
        }
    }
    
    async loadInvoice() {
        try {
            const url = `${DOL_URL_ROOT}/takepos/invoice.php?place=${this.place}&invoiceid=${this.invoiceid}`;
            const response = await fetch(url);
            const data = await response.text();
            
            this.updateInvoiceDisplay(data);
        } catch (error) {
            console.error('Error loading invoice:', error);
        }
    }
    
    updateInvoiceDisplay(invoiceData) {
        // Parse the invoice HTML response and extract relevant data
        const parser = new DOMParser();
        const doc = parser.parseFromString(invoiceData, 'text/html');
        
        // Extract invoice lines
        const lines = doc.querySelectorAll('.posinvoiceline');
        const invoiceLines = document.getElementById('invoiceLines');
        
        if (lines.length === 0) {
            invoiceLines.innerHTML = `
                <div class="text-center text-muted p-4">
                    <i class="fa fa-shopping-cart fa-2x mb-2"></i>
                    <div>Empty</div>
                </div>
            `;
        } else {
            let html = '';
            lines.forEach((line, index) => {
                const lineId = line.id;
                const lineContent = line.innerHTML;
                
                // Extract line information (this is a simplified version)
                const productName = this.extractProductName(lineContent);
                const quantity = this.extractQuantity(lineContent);
                const price = this.extractPrice(lineContent);
                
                html += `
                    <div class="modern-invoice-line ${this.selectedLine === lineId ? 'selected' : ''}" 
                         onclick="modernPos.selectInvoiceLine('${lineId}')"
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
        
        // Update invoice ID if found
        const invoiceIdInput = doc.querySelector('#invoiceid');
        if (invoiceIdInput) {
            this.invoiceid = invoiceIdInput.value;
        }
    }
    
    extractProductName(lineContent) {
        // Extract product name from line content
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = lineContent;
        const productLabel = tempDiv.querySelector('.product_label') || tempDiv.querySelector('td:first-child');
        return productLabel ? productLabel.textContent.trim() : 'Unknown Product';
    }
    
    extractQuantity(lineContent) {
        // Extract quantity from line content
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = lineContent;
        const qtyCell = tempDiv.querySelector('.linecolqty');
        return qtyCell ? qtyCell.textContent.trim() : '1';
    }
    
    extractPrice(lineContent) {
        // Extract price from line content
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = lineContent;
        const priceCell = tempDiv.querySelector('.linecolht:last-child');
        return priceCell ? priceCell.textContent.trim() : '€0.00';
    }
    
    updateTotalAmount(doc) {
        // Extract total amount from invoice
        const totalElement = document.getElementById('totalAmount');
        const invoiceTotal = doc.querySelector('#linecolht-span-total');
        
        if (totalElement && invoiceTotal) {
            totalElement.textContent = invoiceTotal.textContent.trim();
        }
    }
    
    selectInvoiceLine(lineId) {
        // Update selected line
        this.selectedLine = lineId;
        
        // Update visual selection
        document.querySelectorAll('.modern-invoice-line').forEach(line => {
            line.classList.remove('selected');
        });
        
        const selectedElement = document.querySelector(`[data-line-id="${lineId}"]`);
        if (selectedElement) {
            selectedElement.classList.add('selected');
        }
        
        // Update edit buttons state
        this.updateEditButtons();
    }
    
    // Edit functions
    editQuantity() {
        if (this.selectedLine) {
            this.editMode = 'qty';
            this.editValue = '';
            this.updateEditButtons();
        } else {
            this.showErrorFeedback('Please select a line first');
        }
    }
    
    editPrice() {
        if (this.selectedLine) {
            this.editMode = 'price';
            this.editValue = '';
            this.updateEditButtons();
        } else {
            this.showErrorFeedback('Please select a line first');
        }
    }
    
    editDiscount() {
        if (this.selectedLine) {
            this.editMode = 'discount';
            this.editValue = '';
            this.updateEditButtons();
        } else {
            this.showErrorFeedback('Please select a line first');
        }
    }
    
    async deleteLine() {
        if (!this.selectedLine) {
            this.showErrorFeedback('Please select a line first');
            return;
        }
        
        if (!confirm('Are you sure you want to delete this line?')) {
            return;
        }
        
        try {
            const url = `${DOL_URL_ROOT}/takepos/invoice.php?action=deleteline&token=${newToken()}&place=${this.place}&idline=${this.selectedLine}&invoiceid=${this.invoiceid}`;
            const response = await fetch(url);
            const data = await response.text();
            
            this.selectedLine = null;
            await this.loadInvoice();
            this.updateEditButtons();
            
            this.showSuccessFeedback('Line deleted successfully');
        } catch (error) {
            console.error('Error deleting line:', error);
            this.showErrorFeedback('Error deleting line');
        }
    }
    
    // Numpad functions
    inputNumber(number) {
        if (this.editMode) {
            this.editValue += number;
            this.updateEditDisplay();
        }
    }
    
    clearInput() {
        if (this.editMode) {
            this.editValue = '';
            this.updateEditDisplay();
        } else {
            this.editMode = null;
            this.selectedLine = null;
            this.updateEditButtons();
        }
    }
    
    async confirmEdit() {
        if (!this.editMode || !this.selectedLine || !this.editValue) {
            return;
        }
        
        try {
            let action = '';
            switch (this.editMode) {
                case 'qty':
                    action = 'updateqty';
                    break;
                case 'price':
                    action = 'updateprice';
                    break;
                case 'discount':
                    action = 'updatereduction';
                    break;
            }
            
            const url = `${DOL_URL_ROOT}/takepos/invoice.php?action=${action}&token=${newToken()}&place=${this.place}&idline=${this.selectedLine}&number=${this.editValue}&invoiceid=${this.invoiceid}`;
            const response = await fetch(url);
            const data = await response.text();
            
            this.editMode = null;
            this.editValue = '';
            await this.loadInvoice();
            this.updateEditButtons();
            
            this.showSuccessFeedback('Line updated successfully');
        } catch (error) {
            console.error('Error updating line:', error);
            this.showErrorFeedback('Error updating line');
        }
    }
    
    updateEditButtons() {
        const qtyBtn = document.getElementById('qtyBtn');
        const priceBtn = document.getElementById('priceBtn');
        const discountBtn = document.getElementById('discountBtn');
        
        if (!qtyBtn || !priceBtn || !discountBtn) return;
        
        // Reset all buttons
        [qtyBtn, priceBtn, discountBtn].forEach(btn => {
            btn.classList.remove('primary');
            btn.classList.add('secondary');
        });
        
        // Update button text and highlight active mode
        if (this.editMode === 'qty') {
            qtyBtn.classList.remove('secondary');
            qtyBtn.classList.add('primary');
            qtyBtn.innerHTML = '<i class="fa fa-check"></i> Confirm';
            qtyBtn.onclick = () => this.confirmEdit();
        } else if (this.editMode === 'price') {
            priceBtn.classList.remove('secondary');
            priceBtn.classList.add('primary');
            priceBtn.innerHTML = '<i class="fa fa-check"></i> Confirm';
            priceBtn.onclick = () => this.confirmEdit();
        } else if (this.editMode === 'discount') {
            discountBtn.classList.remove('secondary');
            discountBtn.classList.add('primary');
            discountBtn.innerHTML = '<i class="fa fa-check"></i> Confirm';
            discountBtn.onclick = () => this.confirmEdit();
        } else {
            qtyBtn.innerHTML = '<i class="fa fa-edit"></i> Qty';
            priceBtn.innerHTML = '<i class="fa fa-tag"></i> Price';
            discountBtn.innerHTML = '<i class="fa fa-percent"></i> Discount';
            qtyBtn.onclick = () => this.editQuantity();
            priceBtn.onclick = () => this.editPrice();
            discountBtn.onclick = () => this.editDiscount();
        }
    }
    
    updateEditDisplay() {
        if (this.selectedLine && this.editValue) {
            const selectedElement = document.querySelector(`[data-line-id="${this.selectedLine}"]`);
            if (selectedElement) {
                const detailsElement = selectedElement.querySelector('.modern-line-details');
                if (detailsElement) {
                    detailsElement.textContent = `${this.editMode}: ${this.editValue}`;
                }
            }
        }
    }
    
    // Modal functions
    showTerminalModal() {
        const modal = document.getElementById('terminalModal');
        if (modal) {
            modal.style.display = 'block';
        }
    }
    
    showCurrencyModal() {
        const modal = document.getElementById('currencyModal');
        if (modal) {
            modal.style.display = 'block';
        }
    }
    
    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
    }
    
    selectTerminal(terminalId) {
        window.location.href = `modern-index.php?setterminal=${terminalId}`;
    }
    
    setCurrency(currency) {
        window.location.href = `modern-index.php?setcurrency=${currency}`;
    }
    
    // Action functions
    selectCustomer() {
        const popup = window.open(
            `${DOL_URL_ROOT}/societe/list.php?type=t&contextpage=poslist&nomassaction=1&place=${this.place}`,
            'customer',
            'width=900,height=600,scrollbars=yes,resizable=yes'
        );
        
        if (popup) {
            popup.focus();
        }
    }
    
    processPayment() {
        if (this.invoiceid > 0) {
            const popup = window.open(
                `${DOL_URL_ROOT}/takepos/pay.php?place=${this.place}&invoiceid=${this.invoiceid}`,
                'payment',
                'width=800,height=700,scrollbars=yes,resizable=yes'
            );
            
            if (popup) {
                popup.focus();
            }
        } else {
            this.showErrorFeedback('No invoice to process payment for');
        }
    }
    
    addFreeProduct() {
        const popup = window.open(
            `${DOL_URL_ROOT}/takepos/freezone.php?action=freezone&token=${newToken()}&place=${this.place}&invoiceid=${this.invoiceid}`,
            'freezone',
            'width=600,height=400,scrollbars=yes,resizable=yes'
        );
        
        if (popup) {
            popup.focus();
        }
    }
    
    addDiscount() {
        const popup = window.open(
            `${DOL_URL_ROOT}/takepos/reduction.php?place=${this.place}&invoiceid=${this.invoiceid}`,
            'discount',
            'width=600,height=500,scrollbars=yes,resizable=yes'
        );
        
        if (popup) {
            popup.focus();
        }
    }
    
    selectTable() {
        const popup = window.open(
            `${DOL_URL_ROOT}/takepos/floors.php?place=${this.place}`,
            'tables',
            'width=900,height=700,scrollbars=yes,resizable=yes'
        );
        
        if (popup) {
            popup.focus();
        }
    }
    
    // Utility functions
    clearSearch() {
        const searchInput = document.getElementById('modernSearch');
        if (searchInput) {
            searchInput.value = '';
            this.loadProducts(this.currentCategory);
        }
    }
    
    toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                console.log('Error attempting to enable fullscreen:', err);
            });
        } else {
            document.exitFullscreen();
        }
    }
    
    // Loading states
    showLoading() {
        const container = document.getElementById('modernPosContainer');
        if (container) {
            container.classList.add('loading');
        }
    }
    
    hideLoading() {
        const container = document.getElementById('modernPosContainer');
        if (container) {
            container.classList.remove('loading');
        }
    }
    
    showProductsLoading() {
        const productsGrid = document.getElementById('productsGrid');
        if (productsGrid) {
            productsGrid.innerHTML = `
                <div class="text-center p-4">
                    <div class="loading-spinner"></div>
                    <div class="mt-2">Loading products...</div>
                </div>
            `;
        }
    }
    
    showProductsError() {
        const productsGrid = document.getElementById('productsGrid');
        if (productsGrid) {
            productsGrid.innerHTML = `
                <div class="text-center text-muted p-4">
                    <i class="fa fa-exclamation-triangle fa-2x mb-2"></i>
                    <div>Error loading products</div>
                    <button class="modern-action-btn secondary mt-2" onclick="modernPos.loadProducts()">
                        <i class="fa fa-refresh"></i> Retry
                    </button>
                </div>
            `;
        }
    }
    
    showInvoiceLoading() {
        const invoiceLines = document.getElementById('invoiceLines');
        if (invoiceLines) {
            invoiceLines.classList.add('loading');
        }
    }
    
    hideInvoiceLoading() {
        const invoiceLines = document.getElementById('invoiceLines');
        if (invoiceLines) {
            invoiceLines.classList.remove('loading');
        }
    }
    
    // Feedback functions
    showSuccessFeedback(message) {
        this.showFeedback(message, 'success');
    }
    
    showErrorFeedback(message) {
        this.showFeedback(message, 'error');
    }
    
    showFeedback(message, type = 'info') {
        // Create feedback element
        const feedback = document.createElement('div');
        feedback.className = `modern-feedback modern-feedback-${type}`;
        feedback.innerHTML = `
            <i class="fa fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'}-circle"></i>
            <span>${message}</span>
        `;
        
        // Add to page
        document.body.appendChild(feedback);
        
        // Animate in
        setTimeout(() => {
            feedback.classList.add('show');
        }, 10);
        
        // Remove after delay
        setTimeout(() => {
            feedback.classList.remove('show');
            setTimeout(() => {
                if (feedback.parentNode) {
                    feedback.parentNode.removeChild(feedback);
                }
            }, 300);
        }, 3000);
    }
    
    // Auto-refresh
    startAutoRefresh() {
        this.refreshTimer = setInterval(() => {
            if (!this.editMode) {
                this.loadInvoice();
            }
        }, this.config.refreshInterval);
    }
    
    stopAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
    }
    
    // Initial data loading
    loadInitialData() {
        this.loadProducts();
        this.loadInvoice();
        this.hideLoading();
    }
    
    // Keyboard shortcuts
    handleKeyboardShortcuts(e) {
        // Don't handle shortcuts when typing in input fields
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            return;
        }
        
        switch(e.key) {
            case 'F1':
                e.preventDefault();
                this.selectCustomer();
                break;
            case 'F2':
                e.preventDefault();
                this.processPayment();
                break;
            case 'F3':
                e.preventDefault();
                this.addFreeProduct();
                break;
            case 'F4':
                e.preventDefault();
                this.addDiscount();
                break;
            case 'Delete':
                e.preventDefault();
                this.deleteLine();
                break;
            case 'Escape':
                e.preventDefault();
                this.clearInput();
                break;
            case '/':
                e.preventDefault();
                document.getElementById('modernSearch').focus();
                break;
            // Numpad shortcuts
            case '0':
            case '1':
            case '2':
            case '3':
            case '4':
            case '5':
            case '6':
            case '7':
            case '8':
            case '9':
                if (this.editMode) {
                    e.preventDefault();
                    this.inputNumber(e.key);
                }
                break;
            case '.':
                if (this.editMode) {
                    e.preventDefault();
                    this.inputNumber('.');
                }
                break;
            case 'Enter':
                if (this.editMode) {
                    e.preventDefault();
                    this.confirmEdit();
                }
                break;
        }
    }
    
    // Utility helper functions
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    formatPrice(price) {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR'
        }).format(price);
    }
    
    debounce(func, wait) {
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
}

// Global instance
let modernPos;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    modernPos = new ModernPOS();
    
    // Make functions globally available for onclick handlers
    window.modernPos = modernPos;
    
    // Global functions for backward compatibility
    window.showTerminalModal = () => modernPos.showTerminalModal();
    window.showCurrencyModal = () => modernPos.showCurrencyModal();
    window.closeModal = (modalId) => modernPos.closeModal(modalId);
    window.selectTerminal = (terminalId) => modernPos.selectTerminal(terminalId);
    window.setCurrency = (currency) => modernPos.setCurrency(currency);
    window.clearSearch = () => modernPos.clearSearch();
    window.toggleFullscreen = () => modernPos.toggleFullscreen();
    window.selectCustomer = () => modernPos.selectCustomer();
    window.editQuantity = () => modernPos.editQuantity();
    window.editPrice = () => modernPos.editPrice();
    window.editDiscount = () => modernPos.editDiscount();
    window.deleteLine = () => modernPos.deleteLine();
    window.inputNumber = (number) => modernPos.inputNumber(number);
    window.clearInput = () => modernPos.clearInput();
    window.processPayment = () => modernPos.processPayment();
    window.addFreeProduct = () => modernPos.addFreeProduct();
    window.addDiscount = () => modernPos.addDiscount();
    window.selectTable = () => modernPos.selectTable();
});

// CSS for feedback notifications
const feedbackStyles = `
.modern-feedback {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    border-radius: 8px;
    padding: 12px 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 8px;
    z-index: 1000;
    transform: translateX(100%);
    transition: transform 0.3s ease;
    max-width: 300px;
}

.modern-feedback.show {
    transform: translateX(0);
}

.modern-feedback-success {
    border-left: 4px solid #10b981;
    color: #065f46;
}

.modern-feedback-error {
    border-left: 4px solid #ef4444;
    color: #991b1b;
}

.modern-feedback-info {
    border-left: 4px solid #06b6d4;
    color: #0c4a6e;
}

.loading-spinner {
    width: 20px;
    height: 20px;
    border: 2px solid #e2e8f0;
    border-top-color: #2563eb;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.touch-active {
    transform: scale(0.95);
    opacity: 0.8;
}

@media (max-width: 768px) {
    .modern-feedback {
        top: 10px;
        right: 10px;
        left: 10px;
        max-width: none;
    }
}
`;

// Inject feedback styles
const styleSheet = document.createElement('style');
styleSheet.textContent = feedbackStyles;
document.head.appendChild(styleSheet);
