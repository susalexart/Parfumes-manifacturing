<?php
/* Copyright (C) 2018	Andreu Bisquerra	<jove@bisquerra.com>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2025		MDW						<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2024       Modern POS Interface    <modern@takepos.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/takepos/modern-phone.php
 *	\ingroup    takepos
 *	\brief      Modern TakePOS Mobile/Phone screen with enhanced touch interface
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

if (!defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
	require '../main.inc.php';
}
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
	$place = GETPOSTISSET("key") ? dol_decode(GETPOST('key')) : GETPOST('place', 'aZ09');
} else {
	$place = (GETPOST('place', 'aZ09') ? GETPOST('place', 'aZ09') : 0);
}

$action = GETPOST('action', 'aZ09');
$setterminal = GETPOSTINT('setterminal');
$idproduct = GETPOSTINT('idproduct');
$mobilepage = GETPOST('mobilepage', 'alphanohtml');

if ($setterminal > 0) {
	$_SESSION["takeposterminal"] = $setterminal;
}

$langs->loadLangs(array("bills", "orders", "commercial", "cashdesk", "receiptprinter"));

if (!$user->hasRight('takepos', 'run') && !defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
	accessforbidden('No permission to run the takepos');
}

$title = 'TakePOS Mobile - Dolibarr '.DOL_VERSION;
if (getDolGlobalString('MAIN_APPLICATION_TITLE')) {
	$title = 'TakePOS Mobile - ' . getDolGlobalString('MAIN_APPLICATION_TITLE');
}

// Mobile-optimized header
if (empty($mobilepage) && (empty($action) || ((getDolGlobalString('TAKEPOS_PHONE_BASIC_LAYOUT') == 1 && $conf->browser->layout == 'phone') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')))) {
	$head = '<meta name="apple-mobile-web-app-title" content="TakePOS Mobile"/>
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="default">
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover"/>
	<meta name="theme-color" content="#2563eb"/>
	<meta name="msapplication-navbutton-color" content="#2563eb"/>
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"/>';
	
	$arrayofcss = array(
		'/takepos/css/modern-pos.css',
		'/takepos/css/modern-mobile.css'
	);
	$arrayofjs = array(
		'/takepos/js/jquery.colorbox-min.js',
		'/takepos/js/modern-pos.js',
		'/takepos/js/modern-mobile.js'
	);
	
	$disablejs = 0;
	$disablehead = 0;
	top_htmlhead($head, $title, $disablejs, $disablehead, $arrayofjs, $arrayofcss);

	print '<body class="modern-mobile-body">'."\n";
}

// Get categories for mobile display
$categorie = new Categorie($db);
$categories = $categorie->get_full_arbo('product', ((getDolGlobalInt('TAKEPOS_ROOT_CATEGORY_ID') > 0) ? getDolGlobalInt('TAKEPOS_ROOT_CATEGORY_ID') : 0), 1);

// Process categories
$levelofrootcategory = 0;
if (getDolGlobalInt('TAKEPOS_ROOT_CATEGORY_ID') > 0) {
	foreach ($categories as $key => $categorycursor) {
		if ($categorycursor['id'] == getDolGlobalInt('TAKEPOS_ROOT_CATEGORY_ID')) {
			$levelofrootcategory = $categorycursor['level'];
			break;
		}
	}
}

$levelofmaincategories = $levelofrootcategory + 1;
$maincategories = array();

foreach ($categories as $key => $categorycursor) {
	if ($categorycursor['level'] == $levelofmaincategories) {
		$maincategories[$key] = $categorycursor;
	}
}

$maincategories = dol_sort_array($maincategories, 'label');
?>

<div class="modern-pos-container mobile-container" id="modernMobileContainer">
	<!-- Mobile Header -->
	<header class="modern-pos-header mobile-header">
		<div class="modern-pos-header-center">
			<div class="modern-search-container">
				<i class="fa fa-search modern-search-icon"></i>
				<input type="text" 
					   id="mobileSearch" 
					   class="modern-search-input" 
					   placeholder="<?php echo dol_escape_htmltag($langs->trans("Search")); ?>" 
					   autocomplete="off">
				<button class="modern-search-clear" onclick="clearMobileSearch()">
					<i class="fa fa-times"></i>
				</button>
			</div>
		</div>
	</header>

	<!-- Mobile Navigation Tabs -->
	<nav class="modern-mobile-tabs">
		<button class="modern-mobile-tab active" data-tab="products" onclick="switchMobileTab('products')">
			<i class="fa fa-th-large"></i>
			<span><?php echo $langs->trans("Products"); ?></span>
		</button>
		<button class="modern-mobile-tab" data-tab="invoice" onclick="switchMobileTab('invoice')">
			<i class="fa fa-receipt"></i>
			<span><?php echo $langs->trans("Invoice"); ?></span>
		</button>
		<button class="modern-mobile-tab" data-tab="actions" onclick="switchMobileTab('actions')">
			<i class="fa fa-cog"></i>
			<span><?php echo $langs->trans("Actions"); ?></span>
		</button>
	</nav>

	<!-- Main Content -->
	<main class="modern-pos-main mobile-main">
		<!-- Products Panel -->
		<section class="modern-mobile-panel modern-mobile-products active" id="productsPanel">
			<div class="pull-to-refresh" id="productsRefresh">
				<div class="pull-to-refresh-indicator">
					<i class="fa fa-refresh"></i>
				</div>
				
				<div class="modern-categories-nav" id="mobileCategoriesNav">
					<button class="modern-category-btn active" data-category="all">
						<?php echo $langs->trans("All"); ?>
					</button>
					<?php
					foreach ($maincategories as $category) {
						echo '<button class="modern-category-btn" data-category="'.$category['rowid'].'">';
						echo dol_escape_htmltag($category['label']);
						echo '</button>';
					}
					?>
				</div>
				
				<div class="modern-products-grid" id="mobileProductsGrid">
					<div class="mobile-loading">
						<div class="mobile-loading-spinner"></div>
						<div><?php echo $langs->trans("Loading"); ?>...</div>
					</div>
				</div>
			</div>
		</section>

		<!-- Invoice Panel -->
		<section class="modern-mobile-panel modern-mobile-invoice" id="invoicePanel">
			<div class="modern-invoice-header">
				<div class="modern-customer-info">
					<button class="modern-customer-btn touch-target" onclick="selectMobileCustomer()" id="mobileCustomerBtn">
						<i class="fas fa-user"></i>
						<span><?php echo $langs->trans("Customer"); ?></span>
					</button>
				</div>
				
				<div class="modern-invoice-total">
					<div class="modern-total-label"><?php echo $langs->trans("Total"); ?></div>
					<div class="modern-total-amount" id="mobileTotalAmount">€0.00</div>
				</div>
			</div>
			
			<div class="modern-invoice-lines" id="mobileInvoiceLines">
				<div class="text-center text-muted p-4">
					<i class="fa fa-shopping-cart fa-3x mb-4"></i>
					<div class="text-lg"><?php echo $langs->trans("Empty"); ?></div>
					<div class="text-sm mt-2"><?php echo $langs->trans("AddProductsToStart"); ?></div>
				</div>
			</div>
			
			<!-- Quick Actions Bar -->
			<div class="mobile-quick-actions">
				<button class="modern-action-btn secondary" onclick="editMobileQuantity()" id="mobileQtyBtn">
					<i class="fa fa-edit"></i>
					<span><?php echo $langs->trans("Qty"); ?></span>
				</button>
				<button class="modern-action-btn danger" onclick="deleteMobileLine()" id="mobileDeleteBtn">
					<i class="fa fa-trash"></i>
					<span><?php echo $langs->trans("Delete"); ?></span>
				</button>
				<button class="modern-action-btn success full-width" onclick="processMobilePayment()" id="mobilePaymentBtn">
					<i class="fa fa-credit-card"></i>
					<span><?php echo $langs->trans("Payment"); ?></span>
				</button>
			</div>
		</section>

		<!-- Actions Panel -->
		<section class="modern-mobile-panel modern-mobile-actions" id="actionsPanel">
			<div class="mobile-numpad-container">
				<div class="modern-numpad" id="mobileNumpad">
					<button class="modern-numpad-btn touch-target" onclick="inputMobileNumber('7')">7</button>
					<button class="modern-numpad-btn touch-target" onclick="inputMobileNumber('8')">8</button>
					<button class="modern-numpad-btn touch-target" onclick="inputMobileNumber('9')">9</button>
					<button class="modern-numpad-btn touch-target" onclick="inputMobileNumber('4')">4</button>
					<button class="modern-numpad-btn touch-target" onclick="inputMobileNumber('5')">5</button>
					<button class="modern-numpad-btn touch-target" onclick="inputMobileNumber('6')">6</button>
					<button class="modern-numpad-btn touch-target" onclick="inputMobileNumber('1')">1</button>
					<button class="modern-numpad-btn touch-target" onclick="inputMobileNumber('2')">2</button>
					<button class="modern-numpad-btn touch-target" onclick="inputMobileNumber('3')">3</button>
					<button class="modern-numpad-btn touch-target" onclick="inputMobileNumber('0')">0</button>
					<button class="modern-numpad-btn touch-target" onclick="inputMobileNumber('.')">.</button>
					<button class="modern-numpad-btn special touch-target" onclick="clearMobileInput()">C</button>
				</div>
				
				<div class="mobile-edit-display" id="mobileEditDisplay">
					<div class="edit-mode-indicator" id="editModeIndicator"></div>
					<div class="edit-value-display" id="editValueDisplay">0</div>
				</div>
			</div>
			
			<div class="modern-action-buttons mobile-actions-grid">
				<button class="modern-action-btn secondary touch-target" onclick="editMobilePrice()">
					<i class="fa fa-tag"></i>
					<span><?php echo $langs->trans("Price"); ?></span>
				</button>
				
				<button class="modern-action-btn secondary touch-target" onclick="editMobileDiscount()">
					<i class="fa fa-percent"></i>
					<span><?php echo $langs->trans("Discount"); ?></span>
				</button>
				
				<button class="modern-action-btn warning touch-target" onclick="addMobileFreeProduct()">
					<i class="fa fa-cube"></i>
					<span><?php echo $langs->trans("FreeZone"); ?></span>
				</button>
				
				<button class="modern-action-btn primary touch-target" onclick="addMobileDiscount()">
					<i class="fa fa-percent"></i>
					<span><?php echo $langs->trans("GlobalDiscount"); ?></span>
				</button>
				
				<?php if (getDolGlobalString('TAKEPOS_BAR_RESTAURANT')) { ?>
				<button class="modern-action-btn secondary touch-target" onclick="selectMobileTable()">
					<i class="fa fa-utensils"></i>
					<span><?php echo $langs->trans("Tables"); ?></span>
				</button>
				<?php } ?>
				
				<button class="modern-action-btn info touch-target" onclick="showMobileHistory()">
					<i class="fa fa-history"></i>
					<span><?php echo $langs->trans("History"); ?></span>
				</button>
			</div>
		</section>
	</main>
</div>

<!-- Mobile-specific modals -->
<div id="mobileCustomerModal" class="modal mobile-modal" style="display: none;">
	<div class="modal-content">
		<div class="modal-header">
			<span class="close" onclick="closeMobileModal('mobileCustomerModal')">&times;</span>
			<h3><?php echo $langs->trans("SelectCustomer"); ?></h3>
		</div>
		<div class="modal-body">
			<div class="mobile-search-container">
				<input type="text" id="customerSearch" placeholder="<?php echo $langs->trans("SearchCustomer"); ?>" class="modern-search-input">
			</div>
			<div id="customerList" class="mobile-list">
				<!-- Customer list will be loaded here -->
			</div>
		</div>
	</div>
</div>

<div id="mobilePaymentModal" class="modal mobile-modal" style="display: none;">
	<div class="modal-content">
		<div class="modal-header">
			<span class="close" onclick="closeMobileModal('mobilePaymentModal')">&times;</span>
			<h3><?php echo $langs->trans("Payment"); ?></h3>
		</div>
		<div class="modal-body">
			<div class="payment-amount-display">
				<div class="payment-label"><?php echo $langs->trans("AmountToPay"); ?></div>
				<div class="payment-amount" id="paymentAmount">€0.00</div>
			</div>
			
			<div class="payment-methods">
				<button class="modern-action-btn success full-width touch-target" onclick="processPaymentMethod('cash')">
					<i class="fa fa-money-bill"></i>
					<span><?php echo $langs->trans("Cash"); ?></span>
				</button>
				<button class="modern-action-btn primary full-width touch-target" onclick="processPaymentMethod('card')">
					<i class="fa fa-credit-card"></i>
					<span><?php echo $langs->trans("Card"); ?></span>
				</button>
				<button class="modern-action-btn secondary full-width touch-target" onclick="processPaymentMethod('cheque')">
					<i class="fa fa-money-check"></i>
					<span><?php echo $langs->trans("Cheque"); ?></span>
				</button>
			</div>
		</div>
	</div>
</div>

<script>
// Mobile-specific variables
var mobileCurrentTab = 'products';
var mobileCurrentCategory = null;
var mobileSelectedLine = null;
var mobileEditMode = null;
var mobileEditValue = '';
var mobileInvoiceId = 0;
var mobilePlace = "<?php echo $place; ?>";
var mobilePullToRefresh = null;

// Initialize mobile POS
document.addEventListener('DOMContentLoaded', function() {
	initializeMobilePOS();
});

function initializeMobilePOS() {
	console.log('Initializing Mobile POS');
	
	setupMobileEventListeners();
	setupPullToRefresh();
	setupTouchGestures();
	loadMobileProducts();
	loadMobileInvoice();
	
	// Prevent zoom on double tap
	let lastTouchEnd = 0;
	document.addEventListener('touchend', function (event) {
		const now = (new Date()).getTime();
		if (now - lastTouchEnd <= 300) {
			event.preventDefault();
		}
		lastTouchEnd = now;
	}, false);
	
	// Handle orientation change
	window.addEventListener('orientationchange', function() {
		setTimeout(handleMobileOrientationChange, 100);
	});
	
	// Handle visibility change (app switching)
	document.addEventListener('visibilitychange', function() {
		if (!document.hidden) {
			loadMobileInvoice(); // Refresh when app becomes visible
		}
	});
}

function setupMobileEventListeners() {
	// Search functionality
	const searchInput = document.getElementById('mobileSearch');
	if (searchInput) {
		searchInput.addEventListener('input', function(e) {
			handleMobileSearch(e.target.value);
		});
	}
	
	// Category navigation
	document.querySelectorAll('.modern-category-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			selectMobileCategory(this.dataset.category);
		});
	});
	
	// Tab switching
	document.querySelectorAll('.modern-mobile-tab').forEach(function(tab) {
		tab.addEventListener('click', function() {
			switchMobileTab(this.dataset.tab);
		});
	});
}

function setupPullToRefresh() {
	const refreshContainer = document.getElementById('productsRefresh');
	let startY = 0;
	let currentY = 0;
	let isPulling = false;
	
	refreshContainer.addEventListener('touchstart', function(e) {
		startY = e.touches[0].pageY;
		isPulling = refreshContainer.scrollTop === 0;
	});
	
	refreshContainer.addEventListener('touchmove', function(e) {
		if (!isPulling) return;
		
		currentY = e.touches[0].pageY;
		const pullDistance = currentY - startY;
		
		if (pullDistance > 0 && pullDistance < 100) {
			refreshContainer.style.transform = `translateY(${pullDistance / 2}px)`;
			refreshContainer.classList.add('pulling');
		}
	});
	
	refreshContainer.addEventListener('touchend', function(e) {
		if (!isPulling) return;
		
		const pullDistance = currentY - startY;
		
		if (pullDistance > 50) {
			// Trigger refresh
			loadMobileProducts();
			showMobileFeedback('Refreshing products...', 'info');
		}
		
		refreshContainer.style.transform = '';
		refreshContainer.classList.remove('pulling');
		isPulling = false;
	});
}

function setupTouchGestures() {
	// Swipe gestures for tab switching
	let startX = 0;
	let startY = 0;
	
	document.addEventListener('touchstart', function(e) {
		startX = e.touches[0].pageX;
		startY = e.touches[0].pageY;
	});
	
	document.addEventListener('touchend', function(e) {
		if (!startX || !startY) return;
		
		const endX = e.changedTouches[0].pageX;
		const endY = e.changedTouches[0].pageY;
		
		const diffX = startX - endX;
		const diffY = startY - endY;
		
		// Only handle horizontal swipes
		if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
			if (diffX > 0) {
				// Swipe left - next tab
				switchToNextTab();
			} else {
				// Swipe right - previous tab
				switchToPreviousTab();
			}
		}
		
		startX = 0;
		startY = 0;
	});
}

function switchMobileTab(tabName) {
	// Update tab buttons
	document.querySelectorAll('.modern-mobile-tab').forEach(function(tab) {
		tab.classList.remove('active');
	});
	document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
	
	// Update panels
	document.querySelectorAll('.modern-mobile-panel').forEach(function(panel) {
		panel.classList.remove('active');
	});
	
	const targetPanel = document.getElementById(tabName + 'Panel');
	if (targetPanel) {
		targetPanel.classList.add('active');
	}
	
	mobileCurrentTab = tabName;
	
	// Load data if needed
	if (tabName === 'products') {
		loadMobileProducts();
	} else if (tabName === 'invoice') {
		loadMobileInvoice();
	}
}

function switchToNextTab() {
	const tabs = ['products', 'invoice', 'actions'];
	const currentIndex = tabs.indexOf(mobileCurrentTab);
	const nextIndex = (currentIndex + 1) % tabs.length;
	switchMobileTab(tabs[nextIndex]);
}

function switchToPreviousTab() {
	const tabs = ['products', 'invoice', 'actions'];
	const currentIndex = tabs.indexOf(mobileCurrentTab);
	const prevIndex = (currentIndex - 1 + tabs.length) % tabs.length;
	switchMobileTab(tabs[prevIndex]);
}

function selectMobileCategory(categoryId) {
	// Update active category button
	document.querySelectorAll('.modern-category-btn').forEach(function(btn) {
		btn.classList.remove('active');
	});
	document.querySelector(`[data-category="${categoryId}"]`).classList.add('active');
	
	mobileCurrentCategory = categoryId;
	loadMobileProducts(categoryId);
}

async function loadMobileProducts(categoryId = null) {
	const productsGrid = document.getElementById('mobileProductsGrid');
	showMobileProductsLoading();
	
	try {
		let url = `${DOL_URL_ROOT}/takepos/ajax/ajax.php?action=getProducts&token=${newToken()}&tosell=1`;
		if (categoryId && categoryId !== 'all') {
			url += `&category=${categoryId}`;
		}
		
		const response = await fetch(url);
		const products = await response.json();
		
		displayMobileProducts(products);
	} catch (error) {
		console.error('Error loading products:', error);
		showMobileProductsError();
	}
}

function displayMobileProducts(products) {
	const productsGrid = document.getElementById('mobileProductsGrid');
	
	if (!products || products.length === 0) {
		productsGrid.innerHTML = `
			<div class="text-center text-muted p-4">
				<i class="fa fa-search fa-3x mb-4"></i>
				<div class="text-lg"><?php echo $langs->trans("NoProductsFound"); ?></div>
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
			<div class="modern-product-card fade-in-up touch-target" 
				 onclick="addMobileProductToInvoice(${productId})"
				 style="animation-delay: ${index * 50}ms">
				<img src="${DOL_URL_ROOT}/takepos/genimg/index.php?query=pro&id=${productId}" 
					 class="modern-product-image" 
					 alt="${escapeHtml(productLabel)}"
					 onerror="this.src='${DOL_URL_ROOT}/takepos/genimg/empty.png'"
					 loading="lazy">
				<div class="modern-product-name">${escapeHtml(productLabel)}</div>
				<div class="modern-product-price">${productPrice}</div>
			</div>
		`;
	});
	
	productsGrid.innerHTML = html;
}

async function addMobileProductToInvoice(productId, qty = 1) {
	try {
		// Show loading feedback
		showMobileFeedback('Adding product...', 'info');
		
		const url = `${DOL_URL_ROOT}/takepos/invoice.php?action=addline&token=${newToken()}&place=${mobilePlace}&idproduct=${productId}&qty=${qty}&invoiceid=${mobileInvoiceId}`;
		const response = await fetch(url);
		const data = await response.text();
		
		// Switch to invoice tab and refresh
		switchMobileTab('invoice');
		await loadMobileInvoice();
		
		// Show success feedback with haptic feedback
		if (navigator.vibrate) {
			navigator.vibrate(50);
		}
		showMobileFeedback('Product added!', 'success');
		
	} catch (error) {
		console.error('Error adding product:', error);
		showMobileFeedback('Error adding product', 'error');
	}
}

async function loadMobileInvoice() {
	try {
		const url = `${DOL_URL_ROOT}/takepos/invoice.php?place=${mobilePlace}&invoiceid=${mobileInvoiceId}`;
		const response = await fetch(url);
		const data = await response.text();
		
		updateMobileInvoiceDisplay(data);
	} catch (error) {
		console.error('Error loading invoice:', error);
	}
}

function updateMobileInvoiceDisplay(invoiceData) {
	// Parse the invoice HTML response
	const parser = new DOMParser();
	const doc = parser.parseFromString(invoiceData, 'text/html');
	
	// Extract invoice lines
	const lines = doc.querySelectorAll('.posinvoiceline');
	const invoiceLines = document.getElementById('mobileInvoiceLines');
	
	if (lines.length === 0) {
		invoiceLines.innerHTML = `
			<div class="text-center text-muted p-4">
				<i class="fa fa-shopping-cart fa-3x mb-4"></i>
				<div class="text-lg"><?php echo $langs->trans("Empty"); ?></div>
				<div class="text-sm mt-2"><?php echo $langs->trans("AddProductsToStart"); ?></div>
			</div>
		`;
	} else {
		let html = '';
		lines.forEach((line, index) => {
			const lineId = line.id;
			const productName = extractMobileProductName(line.innerHTML);
			const quantity = extractMobileQuantity(line.innerHTML);
			const price = extractMobilePrice(line.innerHTML);
			
			html += `
				<div class="modern-invoice-line touch-target ${mobileSelectedLine === lineId ? 'selected' : ''}" 
					 onclick="selectMobileInvoiceLine('${lineId}')"
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
	updateMobileTotalAmount(doc);
	
	// Update invoice ID
	const invoiceIdInput = doc.querySelector('#invoiceid');
	if (invoiceIdInput) {
		mobileInvoiceId = invoiceIdInput.value;
	}
}

function selectMobileInvoiceLine(lineId) {
	mobileSelectedLine = lineId;
	
	// Update visual selection
	document.querySelectorAll('.modern-invoice-line').forEach(function(line) {
		line.classList.remove('selected');
	});
	
	const selectedElement = document.querySelector(`[data-line-id="${lineId}"]`);
	if (selectedElement) {
		selectedElement.classList.add('selected');
		
		// Haptic feedback
		if (navigator.vibrate) {
			navigator.vibrate(30);
		}
	}
}

// Mobile-specific action functions
function selectMobileCustomer() {
	document.getElementById('mobileCustomerModal').style.display = 'block';
	document.body.classList.add('no-scroll');
}

function processMobilePayment() {
	if (mobileInvoiceId > 0) {
		document.getElementById('mobilePaymentModal').style.display = 'block';
		document.body.classList.add('no-scroll');
		
		// Update payment amount
		const totalElement = document.getElementById('mobileTotalAmount');
		const paymentElement = document.getElementById('paymentAmount');
		if (totalElement && paymentElement) {
			paymentElement.textContent = totalElement.textContent;
		}
	} else {
		showMobileFeedback('No invoice to process payment for', 'error');
	}
}

function processPaymentMethod(method) {
	// Close modal
	closeMobileModal('mobilePaymentModal');
	
	// Process payment
	const url = `${DOL_URL_ROOT}/takepos/invoice.php?action=valid&token=${newToken()}&place=${mobilePlace}&pay=${method}&invoiceid=${mobileInvoiceId}`;
	
	fetch(url)
		.then(response => response.text())
		.then(data => {
			showMobileFeedback('Payment processed successfully!', 'success');
			
			// Haptic feedback
			if (navigator.vibrate) {
				navigator.vibrate([100, 50, 100]);
			}
			
			// Reset invoice
			mobileInvoiceId = 0;
			mobileSelectedLine = null;
			loadMobileInvoice();
		})
		.catch(error => {
			console.error('Error processing payment:', error);
			showMobileFeedback('Error processing payment', 'error');
		});
}

function editMobileQuantity() {
	if (mobileSelectedLine) {
		mobileEditMode = 'qty';
		mobileEditValue = '';
		switchMobileTab('actions');
		updateMobileEditDisplay();
	} else {
		showMobileFeedback('Please select a line first', 'error');
	}
}

function editMobilePrice() {
	if (mobileSelectedLine) {
		mobileEditMode = 'price';
		mobileEditValue = '';
		updateMobileEditDisplay();
	} else {
		showMobileFeedback('Please select a line first', 'error');
	}
}

function editMobileDiscount() {
	if (mobileSelectedLine) {
		mobileEditMode = 'discount';
		mobileEditValue = '';
		updateMobileEditDisplay();
	} else {
		showMobileFeedback('Please select a line first', 'error');
	}
}

function deleteMobileLine() {
	if (!mobileSelectedLine) {
		showMobileFeedback('Please select a line first', 'error');
		return;
	}
	
	if (!confirm('<?php echo $langs->trans("ConfirmDeleteLine"); ?>')) {
		return;
	}
	
	const url = `${DOL_URL_ROOT}/takepos/invoice.php?action=deleteline&token=${newToken()}&place=${mobilePlace}&idline=${mobileSelectedLine}&invoiceid=${mobileInvoiceId}`;
	
	fetch(url)
		.then(response => response.text())
		.then(data => {
			mobileSelectedLine = null;
			loadMobileInvoice();
			showMobileFeedback('Line deleted', 'success');
			
			// Haptic feedback
			if (navigator.vibrate) {
				navigator.vibrate(100);
			}
		})
		.catch(error => {
			console.error('Error deleting line:', error);
			showMobileFeedback('Error deleting line', 'error');
		});
}

function inputMobileNumber(number) {
	if (mobileEditMode) {
		mobileEditValue += number;
		updateMobileEditDisplay();
		
		// Haptic feedback
		if (navigator.vibrate) {
			navigator.vibrate(20);
		}
	}
}

function clearMobileInput() {
	if (mobileEditMode) {
		mobileEditValue = '';
		updateMobileEditDisplay();
	} else {
		mobileEditMode = null;
		mobileSelectedLine = null;
		updateMobileEditDisplay();
	}
}

function updateMobileEditDisplay() {
	const modeIndicator = document.getElementById('editModeIndicator');
	const valueDisplay = document.getElementById('editValueDisplay');
	
	if (mobileEditMode) {
		let modeText = '';
		switch (mobileEditMode) {
			case 'qty':
				modeText = '<?php echo $langs->trans("Quantity"); ?>';
				break;
			case 'price':
				modeText = '<?php echo $langs->trans("Price"); ?>';
				break;
			case 'discount':
				modeText = '<?php echo $langs->trans("Discount"); ?>';
				break;
		}
		
		modeIndicator.textContent = modeText;
		valueDisplay.textContent = mobileEditValue || '0';
		
		// Add confirm button functionality
		const numpadContainer = document.querySelector('.mobile-numpad-container');
		if (!document.getElementById('mobileConfirmBtn')) {
			const confirmBtn = document.createElement('button');
			confirmBtn.id = 'mobileConfirmBtn';
			confirmBtn.className = 'modern-action-btn success full-width touch-target';
			confirmBtn.innerHTML = '<i class="fa fa-check"></i> <span>Confirm</span>';
			confirmBtn.onclick = confirmMobileEdit;
			numpadContainer.appendChild(confirmBtn);
		}
	} else {
		modeIndicator.textContent = '';
		valueDisplay.textContent = '0';
		
		// Remove confirm button
		const confirmBtn = document.getElementById('mobileConfirmBtn');
		if (confirmBtn) {
			confirmBtn.remove();
		}
	}
}

async function confirmMobileEdit() {
	if (!mobileEditMode || !mobileSelectedLine || !mobileEditValue) {
		return;
	}
	
	try {
		let action = '';
		switch (mobileEditMode) {
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
		
		const url = `${DOL_URL_ROOT}/takepos/invoice.php?action=${action}&token=${newToken()}&place=${mobilePlace}&idline=${mobileSelectedLine}&number=${mobileEditValue}&invoiceid=${mobileInvoiceId}`;
		const response = await fetch(url);
		const data = await response.text();
		
		mobileEditMode = null;
		mobileEditValue = '';
		updateMobileEditDisplay();
		
		// Switch back to invoice tab and refresh
		switchMobileTab('invoice');
		await loadMobileInvoice();
		
		showMobileFeedback('Line updated successfully', 'success');
		
		// Haptic feedback
		if (navigator.vibrate) {
			navigator.vibrate(50);
		}
	} catch (error) {
		console.error('Error updating line:', error);
		showMobileFeedback('Error updating line', 'error');
	}
}

// Utility functions
function closeMobileModal(modalId) {
	document.getElementById(modalId).style.display = 'none';
	document.body.classList.remove('no-scroll');
}

function showMobileProductsLoading() {
	const productsGrid = document.getElementById('mobileProductsGrid');
	productsGrid.innerHTML = `
		<div class="mobile-loading">
			<div class="mobile-loading-spinner"></div>
			<div><?php echo $langs->trans("Loading"); ?>...</div>
		</div>
	`;
}

function showMobileProductsError() {
	const productsGrid = document.getElementById('mobileProductsGrid');
	productsGrid.innerHTML = `
		<div class="text-center text-muted p-4">
			<i class="fa fa-exclamation-triangle fa-3x mb-4"></i>
			<div class="text-lg"><?php echo $langs->trans("ErrorLoadingProducts"); ?></div>
			<button class="modern-action-btn secondary mt-4 touch-target" onclick="loadMobileProducts()">
				<i class="fa fa-refresh"></i> <?php echo $langs->trans("Retry"); ?>
			</button>
		</div>
	`;
}

function showMobileFeedback(message, type = 'info') {
	// Create mobile-optimized feedback
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

function handleMobileOrientationChange() {
	// Adjust layout for orientation change
	const container = document.getElementById('modernMobileContainer');
	if (window.orientation === 90 || window.orientation === -90) {
		container.classList.add('landscape');
	} else {
		container.classList.remove('landscape');
	}
}

function handleMobileSearch(searchTerm) {
	// Implement mobile search with debouncing
	clearTimeout(window.mobileSearchTimer);
	window.mobileSearchTimer = setTimeout(() => {
		searchMobileProducts(searchTerm);
	}, 300);
}

async function searchMobileProducts(searchTerm) {
	if (searchTerm.length < 2) {
		loadMobileProducts(mobileCurrentCategory);
		return;
	}
	
	showMobileProductsLoading();
	
	try {
		const url = `${DOL_URL_ROOT}/takepos/ajax/ajax.php?action=search&token=${newToken()}&search_term=${encodeURIComponent(searchTerm)}`;
		const response = await fetch(url);
		const products = await response.json();
		
		displayMobileProducts(products);
	} catch (error) {
		console.error('Error searching products:', error);
		showMobileProductsError();
	}
}

function clearMobileSearch() {
	document.getElementById('mobileSearch').value = '';
	loadMobileProducts(mobileCurrentCategory);
}

// Helper functions for parsing invoice data
function extractMobileProductName(lineContent) {
	const tempDiv = document.createElement('div');
	tempDiv.innerHTML = lineContent;
	const productLabel = tempDiv.querySelector('.product_label') || tempDiv.querySelector('td:first-child');
	return productLabel ? productLabel.textContent.trim() : 'Unknown Product';
}

function extractMobileQuantity(lineContent) {
	const tempDiv = document.createElement('div');
	tempDiv.innerHTML = lineContent;
	const qtyCell = tempDiv.querySelector('.linecolqty');
	return qtyCell ? qtyCell.textContent.trim() : '1';
}

function extractMobilePrice(lineContent) {
	const tempDiv = document.createElement('div');
	tempDiv.innerHTML = lineContent;
	const priceCell = tempDiv.querySelector('.linecolht:last-child');
	return priceCell ? priceCell.textContent.trim() : '€0.00';
}

function updateMobileTotalAmount(doc) {
	const totalElement = document.getElementById('mobileTotalAmount');
	const invoiceTotal = doc.querySelector('#linecolht-span-total');
	
	if (totalElement && invoiceTotal) {
		totalElement.textContent = invoiceTotal.textContent.trim();
	}
}

function escapeHtml(text) {
	const div = document.createElement('div');
	div.textContent = text;
	return div.innerHTML;
}

// Additional mobile action functions
function addMobileFreeProduct() {
	const popup = window.open(
		`${DOL_URL_ROOT}/takepos/freezone.php?action=freezone&token=${newToken()}&place=${mobilePlace}&invoiceid=${mobileInvoiceId}`,
		'freezone',
		'width=400,height=300,scrollbars=yes,resizable=yes'
	);
	
	if (popup) {
		popup.focus();
	}
}

function addMobileDiscount() {
	const popup = window.open(
		`${DOL_URL_ROOT}/takepos/reduction.php?place=${mobilePlace}&invoiceid=${mobileInvoiceId}`,
		'discount',
		'width=400,height=400,scrollbars=yes,resizable=yes'
	);
	
	if (popup) {
		popup.focus();
	}
}

function selectMobileTable() {
	const popup = window.open(
		`${DOL_URL_ROOT}/takepos/floors.php?place=${mobilePlace}`,
		'tables',
		'width=600,height=500,scrollbars=yes,resizable=yes'
	);
	
	if (popup) {
		popup.focus();
	}
}

function showMobileHistory() {
	const popup = window.open(
		`${DOL_URL_ROOT}/compta/facture/list.php?contextpage=poslist`,
		'history',
		'width=600,height=500,scrollbars=yes,resizable=yes'
	);
	
	if (popup) {
		popup.focus();
	}
}
</script>

</body>
</html>

<?php
if (empty($mobilepage) && (empty($action) || ((getDolGlobalString('TAKEPOS_PHONE_BASIC_LAYOUT') == 1 && $conf->browser->layout == 'phone') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')))) {
	llxFooter();
}
$db->close();
?>
