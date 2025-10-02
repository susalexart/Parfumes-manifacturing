<?php
/* Copyright (C) 2018	Andreu Bisquerra	<jove@bisquerra.com>
 * Copyright (C) 2019	Josep Lluís Amador	<joseplluis@lliuretic.cat>
 * Copyright (C) 2020	Thibault FOUCART	<support@ptibogxiv.net>
 * Copyright (C) 2024-2025	MDW				<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
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
 *    \file       htdocs/takepos/modern-index.php
 *    \ingroup    takepos
 *    \brief      Modern TakePOS screen with improved UI/UX
 */

if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

// Load Dolibarr environment
require '../main.inc.php'; // Load $user and permissions
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Societe $mysoc
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("bills", "orders", "commercial", "cashdesk", "receiptprinter", "banks"));

$place = (GETPOST('place', 'aZ09') ? GETPOST('place', 'aZ09') : 0);
$action = GETPOST('action', 'aZ09');
$setterminal = GETPOSTINT('setterminal');
$setcurrency = GETPOST('setcurrency', 'aZ09');

$hookmanager->initHooks(array('takeposfrontend'));

// Terminal session management
if (empty($_SESSION["takeposterminal"])) {
	if (getDolGlobalInt('TAKEPOS_NUM_TERMINALS') == 1) {
		$_SESSION["takeposterminal"] = 1;
	} elseif (!empty($_COOKIE["takeposterminal"])) {
		$_SESSION["takeposterminal"] = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_COOKIE["takeposterminal"]);
	}
}

if ($setterminal > 0) {
	$_SESSION["takeposterminal"] = $setterminal;
	dolSetCookie("takeposterminal", (string) $setterminal, -1);
}

if ($setcurrency != "") {
	$_SESSION["takeposcustomercurrency"] = $setcurrency;
}

$categorie = new Categorie($db);

// Device-specific settings
$maxcategbydefaultforthisdevice = 12;
$maxproductbydefaultforthisdevice = 24;
if ($conf->browser->layout == 'phone') {
	$maxcategbydefaultforthisdevice = 8;
	$maxproductbydefaultforthisdevice = 16;
}

$MAXCATEG = (!getDolGlobalString('TAKEPOS_NB_MAXCATEG') ? $maxcategbydefaultforthisdevice : $conf->global->TAKEPOS_NB_MAXCATEG);
$MAXPRODUCT = (!getDolGlobalString('TAKEPOS_NB_MAXPRODUCT') ? $maxproductbydefaultforthisdevice : $conf->global->TAKEPOS_NB_MAXPRODUCT);

$term = empty($_SESSION['takeposterminal']) ? 1 : $_SESSION['takeposterminal'];

// Security check
$result = restrictedArea($user, 'takepos', 0, '');

/*
 * View
 */

$form = new Form($db);

$disablejs = 0;
$disablehead = 0;
$arrayofjs = array(
	'/takepos/js/jquery.colorbox-min.js',
	'/takepos/js/modern-pos.js'
);
$arrayofcss = array(
	'/takepos/css/modern-pos.css'
);

// Add color theme if enabled
if (getDolGlobalInt('TAKEPOS_COLOR_THEME') == 1) {
	$arrayofcss[] = '/takepos/css/colorful.css';
}

// Title
$title = 'TakePOS - Dolibarr '.DOL_VERSION;
if (getDolGlobalString('MAIN_APPLICATION_TITLE')) {
	$title = 'TakePOS - ' . getDolGlobalString('MAIN_APPLICATION_TITLE');
}

$head = '<meta name="apple-mobile-web-app-title" content="TakePOS"/>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
<meta name="theme-color" content="#2563eb"/>';

top_htmlhead($head, $title, $disablejs, $disablehead, $arrayofjs, $arrayofcss);

// Get categories
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
$subcategories = array();

foreach ($categories as $key => $categorycursor) {
	if ($categorycursor['level'] == $levelofmaincategories) {
		$maincategories[$key] = $categorycursor;
	} else {
		$subcategories[$key] = $categorycursor;
	}
}

$maincategories = dol_sort_array($maincategories, 'label');
$subcategories = dol_sort_array($subcategories, 'label');
?>

<body class="modern-pos-body">

<div class="modern-pos-container" id="modernPosContainer">
	<!-- Header -->
	<header class="modern-pos-header">
		<div class="modern-pos-header-left">
			<div class="terminal-info" onclick="showTerminalModal()">
				<i class="fa fa-cash-register"></i>
				<span>
					<?php
					if (!empty($_SESSION["takeposterminal"])) {
						echo getDolGlobalString("TAKEPOS_TERMINAL_NAME_".$_SESSION["takeposterminal"], $langs->trans("TerminalName", $_SESSION["takeposterminal"]));
					}
					?>
				</span>
				<span class="text-sm">- <?php echo dol_print_date(dol_now(), "day"); ?></span>
			</div>
			
			<?php if (isModEnabled('multicurrency')) { ?>
			<button class="modern-customer-btn" onclick="showCurrencyModal()" title="<?php echo $langs->trans("Currency"); ?>">
				<i class="fas fa-coins"></i>
				<span><?php echo $langs->trans("Currency"); ?></span>
			</button>
			<?php } ?>
		</div>
		
		<div class="modern-pos-header-center">
			<div class="modern-search-container">
				<i class="fa fa-search modern-search-icon"></i>
				<input type="text" 
					   id="modernSearch" 
					   class="modern-search-input" 
					   placeholder="<?php echo dol_escape_htmltag($langs->trans("Search")); ?>" 
					   autocomplete="off">
				<button class="modern-search-clear" onclick="clearSearch()">
					<i class="fa fa-times"></i>
				</button>
			</div>
		</div>
		
		<div class="modern-pos-header-right">
			<div id="customerInfo" class="modern-customer-info">
				<!-- Customer info will be loaded here -->
			</div>
			
			<div id="shoppingCarts" class="modern-shopping-carts">
				<!-- Shopping carts will be loaded here -->
			</div>
			
			<a href="<?php echo DOL_URL_ROOT.'/'; ?>" target="backoffice" rel="opener" class="modern-customer-btn" title="<?php echo $langs->trans("BackOffice"); ?>">
				<i class="fas fa-home"></i>
			</a>
			
			<?php if (empty($conf->dol_use_jmobile)) { ?>
			<button class="modern-customer-btn" onclick="toggleFullscreen()" title="<?php echo dol_escape_htmltag($langs->trans("ClickFullScreenEscapeToLeave")); ?>">
				<i class="fa fa-expand-arrows-alt"></i>
			</button>
			<?php } ?>
			
			<div class="modern-user-menu">
				<?php print top_menu_user(1, DOL_URL_ROOT.'/user/logout.php?token='.newToken().'&urlfrom='.urlencode('/takepos/modern-index.php?setterminal='.((int) $term))); ?>
			</div>
		</div>
	</header>

	<!-- Main Content -->
	<main class="modern-pos-main">
		<!-- Product Grid -->
		<section class="modern-product-grid">
			<div class="modern-categories-nav" id="categoriesNav">
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
			
			<div class="modern-products-container">
				<div class="modern-products-grid" id="productsGrid">
					<!-- Products will be loaded here -->
				</div>
			</div>
		</section>

		<!-- Invoice Panel -->
		<section class="modern-invoice-panel">
			<div class="modern-invoice-header">
				<div class="modern-customer-info">
					<button class="modern-customer-btn" onclick="selectCustomer()" id="customerBtn">
						<i class="fas fa-user"></i>
						<span><?php echo $langs->trans("Customer"); ?></span>
					</button>
				</div>
				
				<div class="modern-invoice-total">
					<div class="modern-total-label"><?php echo $langs->trans("Total"); ?></div>
					<div class="modern-total-amount" id="totalAmount">€0.00</div>
				</div>
			</div>
			
			<div class="modern-invoice-lines" id="invoiceLines">
				<div class="text-center text-muted p-4">
					<i class="fa fa-shopping-cart fa-2x mb-2"></i>
					<div><?php echo $langs->trans("Empty"); ?></div>
				</div>
			</div>
		</section>

		<!-- Action Panel -->
		<section class="modern-action-panel">
			<div class="modern-numpad">
				<button class="modern-numpad-btn" onclick="inputNumber('7')">7</button>
				<button class="modern-numpad-btn" onclick="inputNumber('8')">8</button>
				<button class="modern-numpad-btn" onclick="inputNumber('9')">9</button>
				<button class="modern-numpad-btn" onclick="inputNumber('4')">4</button>
				<button class="modern-numpad-btn" onclick="inputNumber('5')">5</button>
				<button class="modern-numpad-btn" onclick="inputNumber('6')">6</button>
				<button class="modern-numpad-btn" onclick="inputNumber('1')">1</button>
				<button class="modern-numpad-btn" onclick="inputNumber('2')">2</button>
				<button class="modern-numpad-btn" onclick="inputNumber('3')">3</button>
				<button class="modern-numpad-btn" onclick="inputNumber('0')">0</button>
				<button class="modern-numpad-btn" onclick="inputNumber('.')">.</button>
				<button class="modern-numpad-btn special" onclick="clearInput()">C</button>
			</div>
			
			<div class="modern-action-buttons">
				<button class="modern-action-btn secondary" onclick="editQuantity()" id="qtyBtn">
					<i class="fa fa-edit"></i>
					<?php echo $langs->trans("Qty"); ?>
				</button>
				
				<button class="modern-action-btn secondary" onclick="editPrice()" id="priceBtn">
					<i class="fa fa-tag"></i>
					<?php echo $langs->trans("Price"); ?>
				</button>
				
				<button class="modern-action-btn secondary" onclick="editDiscount()" id="discountBtn">
					<i class="fa fa-percent"></i>
					<?php echo $langs->trans("Discount"); ?>
				</button>
				
				<button class="modern-action-btn danger" onclick="deleteLine()" id="deleteBtn">
					<i class="fa fa-trash"></i>
					<?php echo $langs->trans("Delete"); ?>
				</button>
				
				<button class="modern-action-btn warning" onclick="addFreeProduct()">
					<i class="fa fa-cube"></i>
					<?php echo $langs->trans("FreeZone"); ?>
				</button>
				
				<button class="modern-action-btn primary" onclick="addDiscount()">
					<i class="fa fa-percent"></i>
					<?php echo $langs->trans("Discount"); ?>
				</button>
				
				<?php if (getDolGlobalString('TAKEPOS_BAR_RESTAURANT')) { ?>
				<button class="modern-action-btn secondary" onclick="selectTable()">
					<i class="fa fa-utensils"></i>
					<?php echo $langs->trans("Tables"); ?>
				</button>
				<?php } ?>
				
				<button class="modern-action-btn success" onclick="processPayment()" id="paymentBtn">
					<i class="fa fa-credit-card"></i>
					<?php echo $langs->trans("Payment"); ?>
				</button>
			</div>
		</section>
	</main>
</div>

<!-- Modals -->
<div id="terminalModal" class="modal" style="display: none;">
	<div class="modal-content">
		<div class="modal-header">
			<?php if (!getDolGlobalString('TAKEPOS_FORCE_TERMINAL_SELECT')) { ?>
			<span class="close" onclick="closeModal('terminalModal')">&times;</span>
			<?php } ?>
			<h3><?php print $langs->trans("TerminalSelect"); ?></h3>
		</div>
		<div class="modal-body">
			<?php
			$nbloop = getDolGlobalInt('TAKEPOS_NUM_TERMINALS');
			for ($i = 1; $i <= $nbloop; $i++) {
				echo '<button type="button" class="modern-action-btn primary" onclick="selectTerminal('.$i.')">';
				echo getDolGlobalString("TAKEPOS_TERMINAL_NAME_".$i, $langs->trans("TerminalName", $i));
				echo '</button>';
			}
			?>
		</div>
	</div>
</div>

<?php if (isModEnabled('multicurrency')) { ?>
<div id="currencyModal" class="modal" style="display: none;">
	<div class="modal-content">
		<div class="modal-header">
			<span class="close" onclick="closeModal('currencyModal')">&times;</span>
			<h3><?php print $langs->trans("SetMultiCurrencyCode"); ?></h3>
		</div>
		<div class="modal-body">
			<?php
			$sql = 'SELECT code FROM '.MAIN_DB_PREFIX.'multicurrency';
			$sql .= " WHERE entity IN ('".getEntity('multicurrency')."')";
			$resql = $db->query($sql);
			if ($resql) {
				while ($obj = $db->fetch_object($resql)) {
					echo '<button type="button" class="modern-action-btn secondary" onclick="setCurrency(\''.$obj->code.'\')">'.$obj->code.'</button>';
				}
			}
			?>
		</div>
	</div>
</div>
<?php } ?>

<script>
// Global variables
var categories = <?php echo json_encode($maincategories); ?>;
var subcategories = <?php echo json_encode($subcategories); ?>;
var currentCategory = null;
var currentInvoice = null;
var selectedLine = null;
var editMode = null;
var editValue = '';
var place = "<?php echo $place; ?>";
var invoiceid = 0;

// Initialize the modern POS
document.addEventListener('DOMContentLoaded', function() {
	initializeModernPos();
	
	<?php
	// Show terminal selection if needed
	if (empty($_SESSION["takeposterminal"]) || $_SESSION["takeposterminal"] == "") {
		echo "showTerminalModal();";
	}
	?>
});

function initializeModernPos() {
	loadProducts();
	loadInvoice();
	setupEventListeners();
	
	// Auto-refresh invoice every 5 seconds
	setInterval(function() {
		if (!editMode) {
			loadInvoice();
		}
	}, 5000);
}

function setupEventListeners() {
	// Search functionality
	document.getElementById('modernSearch').addEventListener('input', function(e) {
		searchProducts(e.target.value);
	});
	
	// Category navigation
	document.querySelectorAll('.modern-category-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			selectCategory(this.dataset.category);
		});
	});
	
	// Keyboard shortcuts
	document.addEventListener('keydown', function(e) {
		handleKeyboardShortcuts(e);
	});
}

function selectCategory(categoryId) {
	// Update active category button
	document.querySelectorAll('.modern-category-btn').forEach(function(btn) {
		btn.classList.remove('active');
	});
	document.querySelector('[data-category="' + categoryId + '"]').classList.add('active');
	
	currentCategory = categoryId;
	loadProducts(categoryId);
}

function loadProducts(categoryId = null) {
	const productsGrid = document.getElementById('productsGrid');
	productsGrid.innerHTML = '<div class="loading">Loading products...</div>';
	
	let url = '<?php echo DOL_URL_ROOT ?>/takepos/ajax/ajax.php?action=getProducts&token=<?php echo newToken();?>&tosell=1';
	if (categoryId && categoryId !== 'all') {
		url += '&category=' + categoryId;
	}
	
	fetch(url)
		.then(response => response.json())
		.then(products => {
			displayProducts(products);
		})
		.catch(error => {
			console.error('Error loading products:', error);
			productsGrid.innerHTML = '<div class="text-center text-muted p-4">Error loading products</div>';
		});
}

function displayProducts(products) {
	const productsGrid = document.getElementById('productsGrid');
	
	if (!products || products.length === 0) {
		productsGrid.innerHTML = '<div class="text-center text-muted p-4">No products found</div>';
		return;
	}
	
	let html = '';
	products.forEach(function(product) {
		html += `
			<div class="modern-product-card fade-in" onclick="addProductToInvoice(${product.id})">
				<img src="<?php echo DOL_URL_ROOT ?>/takepos/genimg/index.php?query=pro&id=${product.id}" 
					 class="modern-product-image" 
					 alt="${product.label}"
					 onerror="this.src='<?php echo DOL_URL_ROOT ?>/takepos/genimg/empty.png'">
				<div class="modern-product-name">${product.label}</div>
				<div class="modern-product-price">${product.price_ttc_formated || product.price_formated}</div>
			</div>
		`;
	});
	
	productsGrid.innerHTML = html;
}

function searchProducts(searchTerm) {
	if (searchTerm.length < 2) {
		loadProducts(currentCategory);
		return;
	}
	
	const url = '<?php echo DOL_URL_ROOT ?>/takepos/ajax/ajax.php?action=search&token=<?php echo newToken();?>&search_term=' + encodeURIComponent(searchTerm);
	
	fetch(url)
		.then(response => response.json())
		.then(products => {
			displayProducts(products);
		})
		.catch(error => {
			console.error('Error searching products:', error);
		});
}

function addProductToInvoice(productId, qty = 1) {
	const url = '<?php echo DOL_URL_ROOT ?>/takepos/invoice.php?action=addline&token=<?php echo newToken();?>&place=' + place + '&idproduct=' + productId + '&qty=' + qty + '&invoiceid=' + invoiceid;
	
	fetch(url)
		.then(response => response.text())
		.then(data => {
			loadInvoice();
		})
		.catch(error => {
			console.error('Error adding product:', error);
		});
}

function loadInvoice() {
	const url = '<?php echo DOL_URL_ROOT ?>/takepos/invoice.php?place=' + place + '&invoiceid=' + invoiceid;
	
	fetch(url)
		.then(response => response.text())
		.then(data => {
			updateInvoiceDisplay(data);
		})
		.catch(error => {
			console.error('Error loading invoice:', error);
		});
}

function updateInvoiceDisplay(invoiceData) {
	// This is a simplified version - in a real implementation,
	// you would parse the invoice data and update the display accordingly
	const invoiceLines = document.getElementById('invoiceLines');
	
	// Extract invoice lines from the response
	// This would need to be implemented based on the actual response format
	
	// Update total amount
	// This would also need to be extracted from the response
}

// Modal functions
function showTerminalModal() {
	document.getElementById('terminalModal').style.display = 'block';
}

function showCurrencyModal() {
	document.getElementById('currencyModal').style.display = 'block';
}

function closeModal(modalId) {
	document.getElementById(modalId).style.display = 'none';
}

function selectTerminal(terminalId) {
	window.location.href = 'modern-index.php?setterminal=' + terminalId;
}

function setCurrency(currency) {
	window.location.href = 'modern-index.php?setcurrency=' + currency;
}

// Utility functions
function clearSearch() {
	document.getElementById('modernSearch').value = '';
	loadProducts(currentCategory);
}

function toggleFullscreen() {
	if (!document.fullscreenElement) {
		document.documentElement.requestFullscreen();
	} else {
		document.exitFullscreen();
	}
}

// Action functions
function selectCustomer() {
	// Open customer selection modal
	window.open('<?php echo DOL_URL_ROOT ?>/societe/list.php?type=t&contextpage=poslist&nomassaction=1&place=' + place, 'customer', 'width=90%,height=80%');
}

function editQuantity() {
	if (selectedLine) {
		editMode = 'qty';
		editValue = '';
		updateEditButtons();
	}
}

function editPrice() {
	if (selectedLine) {
		editMode = 'price';
		editValue = '';
		updateEditButtons();
	}
}

function editDiscount() {
	if (selectedLine) {
		editMode = 'discount';
		editValue = '';
		updateEditButtons();
	}
}

function deleteLine() {
	if (selectedLine) {
		const url = '<?php echo DOL_URL_ROOT ?>/takepos/invoice.php?action=deleteline&token=<?php echo newToken();?>&place=' + place + '&idline=' + selectedLine + '&invoiceid=' + invoiceid;
		
		fetch(url)
			.then(response => response.text())
			.then(data => {
				selectedLine = null;
				loadInvoice();
				updateEditButtons();
			})
			.catch(error => {
				console.error('Error deleting line:', error);
			});
	}
}

function inputNumber(number) {
	if (editMode) {
		editValue += number;
		updateEditDisplay();
	}
}

function clearInput() {
	if (editMode) {
		editValue = '';
		updateEditDisplay();
	} else {
		// Clear current operation
		editMode = null;
		selectedLine = null;
		updateEditButtons();
	}
}

function updateEditButtons() {
	const qtyBtn = document.getElementById('qtyBtn');
	const priceBtn = document.getElementById('priceBtn');
	const discountBtn = document.getElementById('discountBtn');
	
	// Reset all buttons
	[qtyBtn, priceBtn, discountBtn].forEach(btn => {
		btn.classList.remove('primary');
		btn.classList.add('secondary');
	});
	
	// Highlight active edit mode
	if (editMode === 'qty') {
		qtyBtn.classList.remove('secondary');
		qtyBtn.classList.add('primary');
		qtyBtn.innerHTML = '<i class="fa fa-edit"></i> OK';
	} else if (editMode === 'price') {
		priceBtn.classList.remove('secondary');
		priceBtn.classList.add('primary');
		priceBtn.innerHTML = '<i class="fa fa-tag"></i> OK';
	} else if (editMode === 'discount') {
		discountBtn.classList.remove('secondary');
		discountBtn.classList.add('primary');
		discountBtn.innerHTML = '<i class="fa fa-percent"></i> OK';
	} else {
		qtyBtn.innerHTML = '<i class="fa fa-edit"></i> <?php echo $langs->trans("Qty"); ?>';
		priceBtn.innerHTML = '<i class="fa fa-tag"></i> <?php echo $langs->trans("Price"); ?>';
		discountBtn.innerHTML = '<i class="fa fa-percent"></i> <?php echo $langs->trans("Discount"); ?>';
	}
}

function updateEditDisplay() {
	// Update the selected line display with the current edit value
	if (selectedLine && editValue) {
		// This would update the visual display of the line being edited
	}
}

function processPayment() {
	if (invoiceid > 0) {
		window.open('<?php echo DOL_URL_ROOT ?>/takepos/pay.php?place=' + place + '&invoiceid=' + invoiceid, 'payment', 'width=80%,height=90%');
	}
}

function addFreeProduct() {
	window.open('<?php echo DOL_URL_ROOT ?>/takepos/freezone.php?action=freezone&token=<?php echo newToken();?>&place=' + place + '&invoiceid=' + invoiceid, 'freezone', 'width=80%,height=40%');
}

function addDiscount() {
	window.open('<?php echo DOL_URL_ROOT ?>/takepos/reduction.php?place=' + place + '&invoiceid=' + invoiceid, 'discount', 'width=80%,height=90%');
}

function selectTable() {
	window.open('<?php echo DOL_URL_ROOT ?>/takepos/floors.php?place=' + place, 'tables', 'width=90%,height=90%');
}

function handleKeyboardShortcuts(e) {
	// Implement keyboard shortcuts for better usability
	switch(e.key) {
		case 'F1':
			e.preventDefault();
			selectCustomer();
			break;
		case 'F2':
			e.preventDefault();
			processPayment();
			break;
		case 'F3':
			e.preventDefault();
			addFreeProduct();
			break;
		case 'Delete':
			e.preventDefault();
			deleteLine();
			break;
		case 'Escape':
			e.preventDefault();
			clearInput();
			break;
	}
}
</script>

</body>
</html>

<?php
llxFooter();
$db->close();
?>
