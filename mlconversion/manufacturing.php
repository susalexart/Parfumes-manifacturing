<?php
/* Copyright (C) 2024 ML Conversion Module
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

/**
 * \file    manufacturing.php
 * \ingroup mlconversion
 * \brief   Enhanced Manufacturing Orders with ML Conversion
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once 'class/mlconversion.class.php';

// Load translation files required by the page
$langs->loadLangs(array("mlconversion@mlconversion", "products"));

$action = GETPOST('action', 'aZ09');
$selected_oil_id = GETPOST('selected_oil_id', 'int');

/*
 * Stock Management Functions
 */

function updateStockLevels($db, $user, $oil_id, $oil_quantity, $alcohol_quantity, $bottle_10ml, $bottle_20ml, $bottle_30ml, $bottle_50ml, $oil_code) {
    global $conf;
    
    try {
        $db->begin();
        
        // 1. Decrease oil stock
        if ($oil_quantity > 0) {
            $result = updateProductStock($db, $user, $oil_id, -$oil_quantity, "Manufacturing consumption");
            if (!$result) {
                $db->rollback();
                return false;
            }
        }
        
        // 2. Decrease alcohol stock (find BASE_ALCOHOL_95 product)
        if ($alcohol_quantity > 0) {
            $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE ref LIKE '%ALCOHOL%' OR ref = 'BASE_ALCOHOL_95' LIMIT 1";
            $resql = $db->query($sql);
            if ($resql && $db->num_rows($resql) > 0) {
                $alcohol_obj = $db->fetch_object($resql);
                $result = updateProductStock($db, $user, $alcohol_obj->rowid, -$alcohol_quantity, "Manufacturing consumption");
                if (!$result) {
                    $db->rollback();
                    return false;
                }
            }
        }
        
        // 3. Decrease empty bottle stock and increase final product stock
        $bottle_sizes = [
            ['size' => 10, 'qty' => $bottle_10ml, 'suffix' => '_T10', 'bottle_ref' => 'bottl_10'],
            ['size' => 20, 'qty' => $bottle_20ml, 'suffix' => '_P20', 'bottle_ref' => 'bottl_20'],
            ['size' => 30, 'qty' => $bottle_30ml, 'suffix' => '_M30', 'bottle_ref' => 'bottl_30'],
            ['size' => 50, 'qty' => $bottle_50ml, 'suffix' => '_G50', 'bottle_ref' => 'bottl_50']
        ];
        
        foreach ($bottle_sizes as $bottle) {
            if ($bottle['qty'] > 0) {
                // Decrease empty bottle stock
                $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE ref = '".$db->escape($bottle['bottle_ref'])."'";
                $resql = $db->query($sql);
                if ($resql && $db->num_rows($resql) > 0) {
                    $empty_bottle_obj = $db->fetch_object($resql);
                    $result = updateProductStock($db, $user, $empty_bottle_obj->rowid, -$bottle['qty'], "Used in manufacturing");
                    if (!$result) {
                        $db->rollback();
                        return false;
                    }
                }
                
                // Create or update final product and increase its stock
                $final_product_ref = $oil_code . $bottle['suffix'];
                $final_product_id = createOrGetFinalProduct($db, $user, $final_product_ref, $bottle['size'], $oil_code);
                
                if ($final_product_id > 0) {
                    $result = updateProductStock($db, $user, $final_product_id, $bottle['qty'], "Manufacturing production");
                    if (!$result) {
                        $db->rollback();
                        return false;
                    }
                }
            }
        }
        
        $db->commit();
        return true;
        
    } catch (Exception $e) {
        $db->rollback();
        return false;
    }
}

function updateProductStock($db, $user, $product_id, $quantity, $label) {
    global $conf;
    
    // Find the default warehouse or use the first available
    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."entrepot WHERE entity = ".$conf->entity." LIMIT 1";
    $resql = $db->query($sql);
    $warehouse_id = 1; // Default fallback
    
    if ($resql && $db->num_rows($resql) > 0) {
        $warehouse_obj = $db->fetch_object($resql);
        $warehouse_id = $warehouse_obj->rowid;
    }
    
    // Create stock movement
    require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
    
    $mouvS = new MouvementStock($db);
    $mouvS->origin = null;
    $mouvS->fk_product = $product_id;
    $mouvS->fk_entrepot = $warehouse_id;
    $mouvS->qty = $quantity;
    $mouvS->type = ($quantity > 0) ? 3 : 2; // 3 = input, 2 = output
    $mouvS->label = $label;
    $mouvS->inventorycode = '';
    $mouvS->fk_user = $user->id;
    
    $result = $mouvS->create($user);
    
    return ($result > 0);
}

function createOrGetFinalProduct($db, $user, $product_ref, $size_ml, $oil_code) {
    global $conf;
    
    // Check if product already exists
    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE ref = '".$db->escape($product_ref)."'";
    $resql = $db->query($sql);
    
    if ($resql && $db->num_rows($resql) > 0) {
        $product_obj = $db->fetch_object($resql);
        return $product_obj->rowid;
    }
    
    // Create new final product
    require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
    
    $product = new Product($db);
    $product->ref = $product_ref;
    $product->label = "Perfume {$oil_code} - {$size_ml}ml";
    $product->description = "Final perfume product - {$size_ml}ml bottle";
    $product->type = 0; // Product (not service)
    $product->status = 1; // Active
    $product->status_buy = 0; // Not for purchase
    $product->status_batch = 0; // No batch management
    $product->fk_product_type = 0;
    $product->duration_value = '';
    $product->duration_unit = '';
    $product->canvas = '';
    $product->net_measure = $size_ml;
    $product->net_measure_units = 0; // ml
    $product->weight = 0;
    $product->weight_units = 0;
    $product->length = 0;
    $product->length_units = 0;
    $product->width = 0;
    $product->width_units = 0;
    $product->height = 0;
    $product->height_units = 0;
    $product->surface = 0;
    $product->surface_units = 0;
    $product->volume = $size_ml;
    $product->volume_units = 0;
    
    $result = $product->create($user);
    
    if ($result > 0) {
        return $product->id;
    }
    
    return 0;
}

/*
 * Actions
 */

if ($action == 'create_manufacturing_order') {
    $selected_oil_id = GETPOST('selected_oil_id', 'int');
    $oil_quantity = floatval(GETPOST('oil_quantity', 'alpha'));
    $alcohol_quantity = floatval(GETPOST('alcohol_quantity', 'alpha'));
    $bottle_10ml = GETPOST('bottle_10ml', 'int');
    $bottle_20ml = GETPOST('bottle_20ml', 'int');
    $bottle_30ml = GETPOST('bottle_30ml', 'int');
    $bottle_50ml = GETPOST('bottle_50ml', 'int');
    
    $error = 0;
    $messages = [];
    
    // Production order processing
    
    // Validate inputs
    if (empty($selected_oil_id)) {
        $error++;
        $messages[] = "Please select an oil (ID: {$selected_oil_id})";
    }
    
    if ($oil_quantity <= 0 || $alcohol_quantity <= 0) {
        $error++;
        $messages[] = "Oil and alcohol quantities must be greater than 0 (Oil: {$oil_quantity}, Alcohol: {$alcohol_quantity})";
    }
    
    $total_bottles = $bottle_10ml + $bottle_20ml + $bottle_30ml + $bottle_50ml;
    if ($total_bottles <= 0) {
        $error++;
        $messages[] = "Please specify at least one bottle to produce";
    }
    
    if (!$error) {
        try {
            // Get oil information
            $sql = "SELECT ref, label FROM ".MAIN_DB_PREFIX."product WHERE rowid = ".$selected_oil_id;
            $resql = $db->query($sql);
            if ($resql && $db->num_rows($resql) > 0) {
                $oil_obj = $db->fetch_object($resql);
                $oil_ref = $oil_obj->ref;
                $oil_label = $oil_obj->label;
                
                // Extract oil code for final product references
                $oil_code_match = preg_match('/_([A-Z0-9]+)$/', $oil_ref, $matches);
                $oil_code = $oil_code_match ? $matches[1] : str_replace(['oil_', 'OIL_'], '', $oil_ref);
                
                // Create manufacturing order description
                $mo_label = "Perfume Production - " . $oil_ref;
                $mo_description = "Formula: {$oil_quantity}ml {$oil_ref} + {$alcohol_quantity}ml alcohol\n";
                $mo_description .= "Production:\n";
                if ($bottle_10ml > 0) $mo_description .= "- {$bottle_10ml} × 10ml bottles ({$oil_code}_T10)\n";
                if ($bottle_20ml > 0) $mo_description .= "- {$bottle_20ml} × 20ml bottles ({$oil_code}_P20)\n";
                if ($bottle_30ml > 0) $mo_description .= "- {$bottle_30ml} × 30ml bottles ({$oil_code}_M30)\n";
                if ($bottle_50ml > 0) $mo_description .= "- {$bottle_50ml} × 50ml bottles ({$oil_code}_G50)\n";
                
                // Create actual manufacturing order if MRP module is enabled
                if (isModEnabled('mrp')) {
                    require_once DOL_DOCUMENT_ROOT.'/mrp/class/mo.class.php';
                    
                    $mo = new Mo($db);
                    $mo->ref = $mo->getNextNumRef();
                    $mo->label = $mo_label;
                    $mo->note_private = $mo_description;
                    $mo->date_creation = dol_now();
                    $mo->date_start_planned = dol_now();
                    $mo->fk_user_creat = $user->id;
                    $mo->entity = $conf->entity;
                    $mo->status = 0; // Draft status
                    
                    $mo_id = $mo->create($user);
                    
                    if ($mo_id > 0) {
                        setEventMessage("✅ Manufacturing Order {$mo->ref} created successfully!", 'mesgs');
                        
                        // Update stock levels
                        $stock_updated = updateStockLevels($db, $user, $selected_oil_id, $oil_quantity, $alcohol_quantity, 
                                                         $bottle_10ml, $bottle_20ml, $bottle_30ml, $bottle_50ml, $oil_code);
                        
                        if ($stock_updated) {
                            setEventMessage("📦 Stock levels updated successfully!", 'mesgs');
                        } else {
                            setEventMessage("⚠️ Manufacturing order created but stock update failed", 'warnings');
                        }
                        
                        // Redirect to manufacturing orders list
                        header("Location: ".dol_buildpath('/mlconversion/manufacturing_orders_list.php', 1)."?success=1");
                        exit;
                    } else {
                        $error++;
                        $messages[] = "Failed to create manufacturing order: " . $mo->error;
                    }
                } else {
                    // MRP module not enabled, create a simple record
                    setEventMessage("✅ Production order recorded successfully!", 'mesgs');
                    setEventMessage("⚠️ Enable MRP module to create actual manufacturing orders", 'warnings');
                    
                    // Still update stock levels
                    $stock_updated = updateStockLevels($db, $user, $selected_oil_id, $oil_quantity, $alcohol_quantity, 
                                                     $bottle_10ml, $bottle_20ml, $bottle_30ml, $bottle_50ml, $oil_code);
                    
                    if ($stock_updated) {
                        setEventMessage("📦 Stock levels updated successfully!", 'mesgs');
                    }
                    
                    // Redirect to avoid form resubmission
                    header("Location: ".$_SERVER["PHP_SELF"]."?success=1");
                    exit;
                }
                
            } else {
                $error++;
                $messages[] = "Selected oil not found";
            }
        } catch (Exception $e) {
            $error++;
            $messages[] = "Error creating manufacturing order: " . $e->getMessage();
        }
    }
    
    // Show errors
    if ($error) {
        foreach ($messages as $message) {
            setEventMessage($message, 'errors');
        }
    }
}

// Show success message if redirected after creation
if (GETPOST('success', 'int') == 1) {
    setEventMessage("🎉 Manufacturing order created successfully! You can view it in the Manufacturing Orders List.", 'mesgs');
}

// Security check
if (!isModEnabled('mlconversion')) {
    accessforbidden('Module not enabled');
}
restrictedArea($user, 'mlconversion');

$ml_converter = new MLConversion($db);

/*
 * Actions
 */

if ($action == 'create_multi_size_order') {
    $selected_oil_id = GETPOST('selected_oil_id', 'int');
    $bottle_20ml = GETPOST('bottle_20ml', 'int');
    $bottle_30ml = GETPOST('bottle_30ml', 'int');
    $bottle_50ml = GETPOST('bottle_50ml', 'int');
    $bottle_125ml = GETPOST('bottle_125ml', 'int');
    
    if ($selected_oil_id > 0) {
        // Get oil product details
        $oil_product = new Product($db);
        $oil_product->fetch($selected_oil_id);
        
        // Prepare bottle requirements (use oil ref as base for bottle refs)
        $oil_code = str_replace(['OIL_', 'oil_'], '', $oil_product->ref);
        $requirements = [];
        if ($bottle_20ml > 0) $requirements[$oil_code.'_20ML'] = $bottle_20ml;
        if ($bottle_30ml > 0) $requirements[$oil_code.'_30ML'] = $bottle_30ml;
        if ($bottle_50ml > 0) $requirements[$oil_code.'_50ML'] = $bottle_50ml;
        if ($bottle_125ml > 0) $requirements[$oil_code.'_125ML'] = $bottle_125ml;
        
        if (!empty($requirements)) {
            // Check feasibility
            $formula_output_ml = getDolGlobalInt('MLCONVERSION_DEFAULT_FORMULA_OUTPUT', 1050);
            $feasibility = $ml_converter->checkProductionFeasibility($requirements, $selected_oil_id, $formula_output_ml);
            
            if ($feasibility['can_produce']) {
                setEventMessage("Production order feasible for ".$oil_product->label.". Total ML needed: ".number_format($feasibility['total_ml_needed'])."ml", 'mesgs');
            } else {
                $limiting = [];
                foreach ($feasibility['limiting_materials'] as $material) {
                    $limiting[] = 'Oil shortage: need '.number_format($material['shortage'], 0).'ml more';
                }
                setEventMessage("Cannot create order for ".$oil_product->label." - ".implode(', ', $limiting), 'errors');
            }
        } else {
            setEventMessage("Please specify at least one bottle quantity", 'errors');
        }
    } else {
        setEventMessage("Please select an oil first", 'errors');
    }
}

/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader("", $langs->trans("ManufacturingOrders"), '');

print load_fiche_titre("🧪 Parfumery Lab - Manufacturing Orders", '', 'fa-flask');

?>

<style>
/* Professional Business Theme */
.parfumery-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 30px;
    background: #f8f9fa;
    min-height: 100vh;
}

.section-card {
    background: white;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 2px 20px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.section-card:hover {
    box-shadow: 0 4px 30px rgba(0,0,0,0.12);
}

.section-header {
    display: flex;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f1f3f4;
}

.section-icon {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-right: 20px;
    box-shadow: 0 4px 15px rgba(44,62,80,0.3);
}

.section-title {
    font-size: 28px;
    font-weight: 700;
    color: #2c3e50;
    margin: 0;
    letter-spacing: -0.5px;
}

.section-subtitle {
    font-size: 16px;
    color: #6c757d;
    margin: 5px 0 0 0;
    font-weight: 400;
}

/* Searchable Dropdown */
.search-dropdown-container {
    position: relative;
    margin-bottom: 20px;
}

.search-input {
    width: 100%;
    padding: 15px 20px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 16px;
    background: white;
    transition: all 0.3s ease;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.search-input:focus {
    outline: none;
    border-color: #2c3e50;
    box-shadow: 0 0 0 3px rgba(44,62,80,0.1);
}

.dropdown-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 2px solid #e9ecef;
    border-top: none;
    border-radius: 0 0 8px 8px;
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.dropdown-item {
    padding: 15px 20px;
    cursor: pointer;
    border-bottom: 1px solid #f8f9fa;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.dropdown-item:hover {
    background: #f8f9fa;
}

.dropdown-item:last-child {
    border-bottom: none;
}

.oil-name {
    font-weight: 600;
    color: #2c3e50;
    font-size: 16px;
}

.oil-details {
    font-size: 14px;
    color: #6c757d;
    margin-top: 3px;
}

.oil-stock {
    text-align: right;
}

.stock-amount {
    font-weight: 700;
    color: #28a745;
    font-size: 16px;
}

.stock-status {
    font-size: 12px;
    padding: 2px 8px;
    border-radius: 12px;
    margin-top: 3px;
    display: inline-block;
}

.stock-good { background: #d4edda; color: #155724; }
.stock-warning { background: #fff3cd; color: #856404; }
.stock-critical { background: #f8d7da; color: #721c24; }

/* Form Layout */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 25px;
}

.form-label {
    display: block;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 8px;
    font-size: 16px;
}

.form-input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 16px;
    transition: all 0.3s ease;
    background: white;
}

.form-input:focus {
    outline: none;
    border-color: #2c3e50;
    box-shadow: 0 0 0 3px rgba(44,62,80,0.1);
}

.form-help {
    font-size: 14px;
    color: #6c757d;
    margin-top: 5px;
}

/* Bottle Selection Grid */
.bottle-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
    margin-top: 25px;
}

.bottle-card {
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 25px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.bottle-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #2c3e50, #34495e);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.bottle-card:hover::before {
    transform: scaleX(1);
}

.bottle-card:hover {
    border-color: #2c3e50;
    box-shadow: 0 8px 25px rgba(44,62,80,0.15);
    transform: translateY(-2px);
}

.bottle-icon {
    font-size: 48px;
    margin-bottom: 15px;
    display: block;
}

.bottle-size {
    font-size: 20px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 15px;
}

.bottle-input {
    width: 100px;
    padding: 10px;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    text-align: center;
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 10px;
}

.bottle-input:focus {
    outline: none;
    border-color: #2c3e50;
    box-shadow: 0 0 0 3px rgba(44,62,80,0.1);
}

.bottle-info {
    font-size: 14px;
    color: #6c757d;
    line-height: 1.4;
}

/* Summary Section */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-item {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    border: 1px solid #e9ecef;
}

.summary-value {
    font-size: 32px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 5px;
}

.summary-label {
    font-size: 14px;
    color: #6c757d;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Status Messages */
.status-message {
    padding: 20px;
    border-radius: 10px;
    margin: 20px 0;
    font-weight: 500;
    border-left: 4px solid;
}

.status-success {
    background: #d4edda;
    color: #155724;
    border-left-color: #28a745;
}

.status-error {
    background: #f8d7da;
    color: #721c24;
    border-left-color: #dc3545;
}

.status-info {
    background: #d1ecf1;
    color: #0c5460;
    border-left-color: #17a2b8;
}

/* Action Buttons */
.btn-primary {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    border: none;
    padding: 15px 30px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(44,62,80,0.3);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(44,62,80,0.4);
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.btn-secondary {
    background: white;
    color: #2c3e50;
    border: 2px solid #2c3e50;
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-right: 10px;
    margin-bottom: 10px;
}

.btn-secondary:hover {
    background: #2c3e50;
    color: white;
}

/* Progress Indicator */
.progress-steps {
    display: flex;
    justify-content: center;
    margin-bottom: 40px;
    padding: 0 20px;
}

.progress-step {
    flex: 1;
    text-align: center;
    position: relative;
    max-width: 200px;
}

.progress-step::after {
    content: '';
    position: absolute;
    top: 20px;
    left: 60%;
    right: -40%;
    height: 2px;
    background: #e9ecef;
    z-index: 1;
}

.progress-step:last-child::after {
    display: none;
}

.progress-step.completed::after {
    background: #28a745;
}

.step-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e9ecef;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    margin: 0 auto 10px;
    position: relative;
    z-index: 2;
    transition: all 0.3s ease;
}

.progress-step.active .step-circle {
    background: #2c3e50;
    color: white;
}

.progress-step.completed .step-circle {
    background: #28a745;
    color: white;
}

.step-label {
    font-size: 14px;
    color: #6c757d;
    font-weight: 500;
}

.progress-step.active .step-label,
.progress-step.completed .step-label {
    color: #2c3e50;
    font-weight: 600;
}

@media (max-width: 768px) {
    .parfumery-container {
        padding: 20px;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .bottle-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }
    
    .section-header {
        flex-direction: column;
        text-align: center;
    }
    
    .section-icon {
        margin-right: 0;
        margin-bottom: 15px;
    }
}
</style>

<div class="parfumery-container">

<!-- Progress Steps -->
<div class="progress-steps">
    <div class="progress-step active" id="progress-1">
        <div class="step-circle">1</div>
        <div class="step-label">Select Oil</div>
    </div>
    <div class="progress-step" id="progress-2">
        <div class="step-circle">2</div>
        <div class="step-label">Configure Formula</div>
    </div>
    <div class="progress-step" id="progress-3">
        <div class="step-circle">3</div>
        <div class="step-label">Choose Bottles</div>
    </div>
    <div class="progress-step" id="progress-4">
        <div class="step-circle">4</div>
        <div class="step-label">Production Summary</div>
    </div>
</div>

<!-- Step 1: Oil Selection -->
<div class="section-card" id="step-1">
    <div class="section-header">
        <div class="section-icon">🌸</div>
        <div>
            <h2 class="section-title">Fragrance Oil Selection</h2>
            <p class="section-subtitle">Search and select your fragrance oil from available inventory</p>
        </div>
    </div>
    
    <div class="search-dropdown-container">
        <input type="text" 
               id="oil-search" 
               class="search-input" 
               placeholder="🔍 Search for fragrance oils (name, reference, description)..."
               autocomplete="off"
               onkeyup="searchOils()"
               onfocus="showDropdown()"
               onblur="hideDropdownDelayed()">
        
        <div id="dropdown-results" class="dropdown-results">
            <!-- Dynamic search results will appear here -->
        </div>
    </div>
    
    <div id="selected-oil-info" style="display: none;">
        <div class="form-group">
            <label class="form-label">Selected Oil:</label>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #e9ecef;">
                <div style="font-size: 18px; font-weight: 600; color: #2c3e50;" id="selected-oil-name">No oil selected</div>
                <div style="font-size: 14px; color: #6c757d; margin-top: 5px;">
                    Available: <span id="selected-oil-stock">0ml</span> | 
                    Max Batches: <span id="selected-oil-batches">0</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Step 2: Formula Configuration -->
<div class="section-card" id="step-2" style="display: none;">
    <div class="section-header">
        <div class="section-icon">⚗️</div>
        <div>
            <h2 class="section-title">Formula Configuration</h2>
            <p class="section-subtitle">Configure the exact quantities for your perfume formula</p>
        </div>
    </div>
    
    <div class="form-grid">
        <div class="form-group">
            <label class="form-label">Oil Quantity (ml)</label>
            <input type="number" name="oil_quantity" id="oil_quantity" value="125" min="1" step="0.1" 
                   class="form-input" onchange="updateCalculations()" placeholder="125">
            <div class="form-help">Available: <span id="available_oil_stock">0ml</span></div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Alcohol Quantity (ml)</label>
            <input type="number" name="alcohol_quantity" id="alcohol_quantity" value="1000" min="1" step="0.1" 
                   class="form-input" onchange="updateCalculations()" placeholder="1000">
            <div class="form-help">Professional ratio: 1000ml alcohol + 125ml oil</div>
        </div>
    </div>
</div>

<!-- Step 3: Bottle Selection -->
<div class="section-card" id="step-3" style="display: none;">
    <div class="section-header">
        <div class="section-icon">🍶</div>
        <div>
            <h2 class="section-title">Bottle Size Selection</h2>
            <p class="section-subtitle">Choose the bottle sizes and quantities for production</p>
        </div>
    </div>
    
    <div class="bottle-grid">
        <div class="bottle-card">
            <div class="bottle-icon">🧪</div>
            <div class="bottle-size">10ml Tester</div>
            <input type="number" name="bottle_10ml" id="bottle_10ml" value="0" min="0" 
                   class="bottle-input" onchange="updateCalculations()">
            <div class="bottle-info">
                <div><span id="ml_10ml">0ml</span> total</div>
                <div>Available: <span id="stock_bott_10">0</span> empty bottles</div>
            </div>
        </div>
        
        <div class="bottle-card">
            <div class="bottle-icon">💎</div>
            <div class="bottle-size">20ml Personal</div>
            <input type="number" name="bottle_20ml" id="bottle_20ml" value="0" min="0" 
                   class="bottle-input" onchange="updateCalculations()">
            <div class="bottle-info">
                <div><span id="ml_20ml">0ml</span> total</div>
                <div>Available: <span id="stock_bott_20">0</span> empty bottles</div>
            </div>
        </div>
        
        <div class="bottle-card">
            <div class="bottle-icon">💫</div>
            <div class="bottle-size">30ml Medium</div>
            <input type="number" name="bottle_30ml" id="bottle_30ml" value="0" min="0" 
                   class="bottle-input" onchange="updateCalculations()">
            <div class="bottle-info">
                <div><span id="ml_30ml">0ml</span> total</div>
                <div>Available: <span id="stock_bott_30">0</span> empty bottles</div>
            </div>
        </div>
        
        <div class="bottle-card">
            <div class="bottle-icon">✨</div>
            <div class="bottle-size">50ml Luxury</div>
            <input type="number" name="bottle_50ml" id="bottle_50ml" value="0" min="0" 
                   class="bottle-input" onchange="updateCalculations()">
            <div class="bottle-info">
                <div><span id="ml_50ml">0ml</span> total</div>
                <div>Available: <span id="stock_bott_50">0</span> empty bottles</div>
            </div>
        </div>
    </div>
    
    <div style="margin-top: 25px;">
        <button type="button" class="btn-secondary" onclick="setQuickOrder(10,10,5,0)">Small Mixed (25 bottles)</button>
        <button type="button" class="btn-secondary" onclick="setQuickOrder(20,15,10,5)">Medium Mixed (50 bottles)</button>
        <button type="button" class="btn-secondary" onclick="setQuickOrder(5,5,5,15)">Luxury Focus (30 bottles)</button>
        <button type="button" class="btn-secondary" onclick="resetForm()">Reset All</button>
    </div>
    
    <!-- Debug Information -->
    <?php if (!empty($debug_bottles)): ?>
    <div style="margin-top: 25px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">
        <h4 style="color: #2c3e50; margin-bottom: 15px;">🔍 Available Bottle Products Found:</h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px;">
            <?php foreach ($debug_bottles as $bottle): ?>
                <div style="background: white; padding: 10px; border-radius: 6px; border: 1px solid #dee2e6;">
                    <strong><?php echo $bottle['ref']; ?></strong><br>
                    <small><?php echo $bottle['label']; ?></small><br>
                    <span style="color: #28a745; font-weight: 600;">Stock: <?php echo $bottle['stock']; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <p style="margin-top: 15px; font-size: 14px; color: #6c757d;">
            <strong>Expected references:</strong> bottl_10, bottl_20, bottl_30, bottl_50<br>
            ✅ System now configured for your bottle references!
        </p>
    </div>
    <?php endif; ?>
</div>

<!-- Step 4: Production Summary -->
<div class="section-card" id="step-4" style="display: none;">
    <div class="section-header">
        <div class="section-icon">📊</div>
        <div>
            <h2 class="section-title">Production Summary</h2>
            <p class="section-subtitle">Review your production requirements and start manufacturing</p>
        </div>
    </div>
    
    <div class="summary-grid">
        <div class="summary-item">
            <div class="summary-value" id="total-ml-needed">0ml</div>
            <div class="summary-label">Total ML Needed</div>
        </div>
        <div class="summary-item">
            <div class="summary-value" id="oil-needed">0ml</div>
            <div class="summary-label">Oil Required</div>
        </div>
        <div class="summary-item">
            <div class="summary-value" id="alcohol-needed">0ml</div>
            <div class="summary-label">Alcohol Required</div>
        </div>
        <div class="summary-item">
            <div class="summary-value" id="batches-required">0</div>
            <div class="summary-label">Production Batches</div>
        </div>
    </div>
    
    <div id="feasibility-status" class="status-message status-info">
        💡 Configure your production to see the summary
    </div>
    
    <div style="text-align: center; margin-top: 30px;">
        <form method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>" id="production-form-submit">
            <input type="hidden" name="token" value="<?php echo newToken(); ?>">
            <input type="hidden" name="action" value="create_manufacturing_order">
            <input type="hidden" name="selected_oil_id" id="selected_oil_id_form" value="">
            <input type="hidden" name="oil_quantity" id="oil_quantity_form" value="">
            <input type="hidden" name="alcohol_quantity" id="alcohol_quantity_form" value="">
            <input type="hidden" name="bottle_10ml" id="bottle_10ml_form" value="">
            <input type="hidden" name="bottle_20ml" id="bottle_20ml_form" value="">
            <input type="hidden" name="bottle_30ml" id="bottle_30ml_form" value="">
            <input type="hidden" name="bottle_50ml" id="bottle_50ml_form" value="">
            
            <button type="button" id="submit-btn" class="btn-primary" onclick="startProduction()" disabled>
                🚀 Start Production
            </button>
        </form>
    </div>
</div>

<?php
// Get all raw material oils for JavaScript
$raw_materials = $ml_converter->getRawMaterials();

// Get empty bottle stock
$bottle_stock = [];
$bottle_refs = ['bottl_10', 'bottl_20', 'bottl_30', 'bottl_50'];

foreach ($bottle_refs as $bottle_ref) {
    // First, let's check if the product exists
    $sql = "SELECT p.rowid FROM ".MAIN_DB_PREFIX."product p WHERE p.ref = '".$db->escape($bottle_ref)."'";
    $resql = $db->query($sql);
    
    if ($resql && $db->num_rows($resql) > 0) {
        $product = $db->fetch_object($resql);
        $product_id = $product->rowid;
        
        // Now get the stock for this product from all warehouses
        $sql_stock = "SELECT SUM(ps.reel) as total_stock FROM ".MAIN_DB_PREFIX."product_stock ps";
        $sql_stock .= " WHERE ps.fk_product = ".$product_id;
        
        $resql_stock = $db->query($sql_stock);
        if ($resql_stock && $db->num_rows($resql_stock) > 0) {
            $stock_obj = $db->fetch_object($resql_stock);
            $bottle_stock[$bottle_ref] = $stock_obj->total_stock ? $stock_obj->total_stock : 0;
        } else {
            $bottle_stock[$bottle_ref] = 0;
        }
        $db->free($resql_stock);
    } else {
        // Product doesn't exist, set stock to 0
        $bottle_stock[$bottle_ref] = 0;
    }
    $db->free($resql);
}

// Debug: Let's also check what bottle products actually exist
$debug_bottles = [];
$sql_debug = "SELECT p.ref, p.label, SUM(ps.reel) as stock FROM ".MAIN_DB_PREFIX."product p";
$sql_debug .= " LEFT JOIN ".MAIN_DB_PREFIX."product_stock ps ON p.rowid = ps.fk_product";
$sql_debug .= " WHERE p.ref LIKE 'bott_%' OR p.ref LIKE 'BOTT_%' OR p.label LIKE '%bottle%' OR p.label LIKE '%Bottle%'";
$sql_debug .= " GROUP BY p.rowid, p.ref, p.label";
$sql_debug .= " ORDER BY p.ref";

$resql_debug = $db->query($sql_debug);
if ($resql_debug) {
    while ($obj_debug = $db->fetch_object($resql_debug)) {
        $debug_bottles[] = [
            'ref' => $obj_debug->ref,
            'label' => $obj_debug->label,
            'stock' => $obj_debug->stock ? $obj_debug->stock : 0
        ];
    }
    $db->free($resql_debug);
}
?>

<!-- Step 2: Formula Configuration -->
<div class="step-card" id="step-2" style="display: none;">
    <div class="step-header">
        <div class="step-number">2</div>
        <h2 class="step-title">⚗️ Configure Your Formula</h2>
    </div>
    
    <div class="input-group">
        <div class="input-row">
            <div class="input-label">Oil Quantity:</div>
            <div class="input-field">
                <input type="number" name="oil_quantity" id="oil_quantity" value="125" min="1" step="0.1" 
                       class="modern-input" onchange="updateCalculations()" placeholder="125">
                <div class="stock-info">Available: <span id="available_oil_stock">0ml</span></div>
            </div>
        </div>
        
        <div class="input-row">
            <div class="input-label">Alcohol Quantity:</div>
            <div class="input-field">
                <input type="number" name="alcohol_quantity" id="alcohol_quantity" value="1000" min="1" step="0.1" 
                       class="modern-input" onchange="updateCalculations()" placeholder="1000">
                <div class="stock-info">Professional ratio: 1000ml alcohol + 125ml oil</div>
            </div>
        </div>
    </div>
</div>

<!-- Step 3: Bottle Selection -->
<div class="step-card" id="step-3" style="display: none;">
    <div class="step-header">
        <div class="step-number">3</div>
        <h2 class="step-title">🍶 Choose Your Bottle Sizes</h2>
    </div>
    
    <div class="bottle-grid">
        <div class="bottle-card">
            <div class="bottle-icon">🧪</div>
            <div class="bottle-size">10ml Tester</div>
            <input type="number" name="bottle_10ml" id="bottle_10ml" value="0" min="0" 
                   class="bottle-input" onchange="updateCalculations()">
            <div class="stock-info">
                <span id="ml_10ml">0ml</span> total<br>
                Available: <span id="stock_bott_10">0</span> empty bottles
            </div>
        </div>
        
        <div class="bottle-card">
            <div class="bottle-icon">💎</div>
            <div class="bottle-size">20ml Personal</div>
            <input type="number" name="bottle_20ml" id="bottle_20ml" value="0" min="0" 
                   class="bottle-input" onchange="updateCalculations()">
            <div class="stock-info">
                <span id="ml_20ml">0ml</span> total<br>
                Available: <span id="stock_bott_20">0</span> empty bottles
            </div>
        </div>
        
        <div class="bottle-card">
            <div class="bottle-icon">💫</div>
            <div class="bottle-size">30ml Medium</div>
            <input type="number" name="bottle_30ml" id="bottle_30ml" value="0" min="0" 
                   class="bottle-input" onchange="updateCalculations()">
            <div class="stock-info">
                <span id="ml_30ml">0ml</span> total<br>
                Available: <span id="stock_bott_30">0</span> empty bottles
            </div>
        </div>
        
        <div class="bottle-card">
            <div class="bottle-icon">✨</div>
            <div class="bottle-size">50ml Luxury</div>
            <input type="number" name="bottle_50ml" id="bottle_50ml" value="0" min="0" 
                   class="bottle-input" onchange="updateCalculations()">
            <div class="stock-info">
                <span id="ml_50ml">0ml</span> total<br>
                Available: <span id="stock_bott_50">0</span> empty bottles
            </div>
        </div>
    </div>
    
    <div class="quick-actions">
        <div class="quick-button" onclick="setQuickOrder(10,10,5,0)">🎯 Small Mixed (25 bottles)</div>
        <div class="quick-button" onclick="setQuickOrder(20,15,10,5)">🎯 Medium Mixed (50 bottles)</div>
        <div class="quick-button" onclick="setQuickOrder(5,5,5,15)">🎯 Luxury Focus (30 bottles)</div>
        <div class="quick-button" onclick="resetForm()">🔄 Reset All</div>
    </div>
</div>

<!-- Step 4: Production Summary -->
<div class="summary-card" id="step-4" style="display: none;">
    <div class="step-header">
        <div class="step-number">4</div>
        <h2 class="step-title">📊 Production Summary</h2>
    </div>
    
    <div class="summary-grid">
        <div class="summary-item">
            <div class="summary-value" id="total-ml-needed">0ml</div>
            <div class="summary-label">Total ML Needed</div>
        </div>
        <div class="summary-item">
            <div class="summary-value" id="oil-needed">0ml</div>
            <div class="summary-label">Oil Required</div>
        </div>
        <div class="summary-item">
            <div class="summary-value" id="alcohol-needed">0ml</div>
            <div class="summary-label">Alcohol Required</div>
        </div>
        <div class="summary-item">
            <div class="summary-value" id="batches-required">0</div>
            <div class="summary-label">Production Batches</div>
        </div>
    </div>
    
    <div id="feasibility-status" class="status-message status-info">
        💡 Configure your production to see the summary
    </div>
    
    <div style="text-align: center; margin-top: 25px;">
        <input type="hidden" name="selected_oil_id" id="selected_oil_id" value="">
        <button type="submit" id="submit-btn" class="action-button pulse" disabled>
            🚀 Start Production
        </button>
    </div>
</div>

<div id="production-form" style="display: none;">
    <form method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>" id="manufacturing-form">
        <input type="hidden" name="token" value="<?php echo newToken(); ?>">
        <input type="hidden" name="action" value="create_multi_size_order">
        <input type="hidden" name="selected_oil_id" id="selected_oil_id" value="">
        
        <table class="border centpercent">
            <tr class="liste_titre">
                <th colspan="2"><?php echo $langs->trans("CreateProductionOrder"); ?></th>
            </tr>
            <tr>
                <td width="30%"><?php echo $langs->trans("SelectedOil"); ?>:</td>
                <td>
                    <div id="selected-oil-info">
                        <strong id="selected-oil-name"><?php echo $langs->trans("NoOilSelected"); ?></strong><br>
                        <small><?php echo $langs->trans("Available"); ?>: <span id="selected-oil-stock">0ml</span> | <?php echo $langs->trans("MaxBatches"); ?>: <span id="selected-oil-batches">0</span></small>
                    </div>
                </td>
            </tr>
            <tr class="liste_titre">
                <th colspan="2"><?php echo $langs->trans("IngredientQuantities"); ?></th>
            </tr>
            <tr>
                <td>Oil Quantity to Use:</td>
                <td>
                    <input type="number" name="oil_quantity" id="oil_quantity" value="125" min="1" step="0.1" class="flat minwidth100" onchange="updateCalculations()"> ml
                    <small>(Available: <span id="available_oil_stock">0ml</span>)</small>
                </td>
            </tr>
            <tr>
                <td>Alcohol Quantity to Use:</td>
                <td>
                    <input type="number" name="alcohol_quantity" id="alcohol_quantity" value="1000" min="1" step="0.1" class="flat minwidth100" onchange="updateCalculations()"> ml
                    <small>(Standard ratio: 1000ml alcohol + 125ml oil)</small>
                </td>
            </tr>
            <tr class="liste_titre">
                <th colspan="2"><?php echo $langs->trans("BottleQuantities"); ?></th>
            </tr>
            <tr>
                <td>10ml Bottles (T10):</td>
                <td>
                    <input type="number" name="bottle_10ml" id="bottle_10ml" value="0" min="0" class="flat minwidth100" onchange="updateCalculations()"> bottles
                    <small>(10ml each = <span id="ml_10ml">0ml</span> total) | Available: <span id="stock_bott_10">0</span> empty bottles</small>
                </td>
            </tr>
            <tr>
                <td>20ml Bottles (P20):</td>
                <td>
                    <input type="number" name="bottle_20ml" id="bottle_20ml" value="0" min="0" class="flat minwidth100" onchange="updateCalculations()"> bottles
                    <small>(20ml each = <span id="ml_20ml">0ml</span> total) | Available: <span id="stock_bott_20">0</span> empty bottles</small>
                </td>
            </tr>
            <tr>
                <td>30ml Bottles (M30):</td>
                <td>
                    <input type="number" name="bottle_30ml" id="bottle_30ml" value="0" min="0" class="flat minwidth100" onchange="updateCalculations()"> bottles
                    <small>(30ml each = <span id="ml_30ml">0ml</span> total) | Available: <span id="stock_bott_30">0</span> empty bottles</small>
                </td>
            </tr>
            <tr>
                <td>50ml Bottles (G50):</td>
                <td>
                    <input type="number" name="bottle_50ml" id="bottle_50ml" value="0" min="0" class="flat minwidth100" onchange="updateCalculations()"> bottles
                    <small>(50ml each = <span id="ml_50ml">0ml</span> total) | Available: <span id="stock_bott_50">0</span> empty bottles</small>
                </td>
            </tr>
            <tr class="liste_titre">
                <td><strong><?php echo $langs->trans("ProductionSummary"); ?>:</strong></td>
                <td>
                    <div id="production-summary">
                        <strong><?php echo $langs->trans("TotalMLNeeded"); ?>: <span id="total-ml-needed">0ml</span></strong><br>
                        <strong><?php echo $langs->trans("BatchesRequired"); ?>: <span id="batches-required">0</span></strong><br>
                        <strong><?php echo $langs->trans("OilNeeded"); ?>: <span id="oil-needed">0ml</span></strong><br>
                        <strong><?php echo $langs->trans("AlcoholNeeded"); ?>: <span id="alcohol-needed">0ml</span></strong><br>
                        <span id="feasibility-status"></span>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="center">
                    <input type="submit" class="button" value="<?php echo $langs->trans("CreateProductionOrder"); ?>" id="submit-btn" disabled>
                    <button type="button" class="button" onclick="resetForm()"><?php echo $langs->trans("Reset"); ?></button>
                </td>
            </tr>
        </table>
    </form>
</div>

<br>

<!-- Quick Examples -->
<div class="div-table-responsive-no-min">
    <table class="border centpercent">
        <tr class="liste_titre">
            <th colspan="4"><?php echo $langs->trans("QuickOrderExamples"); ?></th>
        </tr>
        <tr class="liste_titre">
            <th><?php echo $langs->trans("Example"); ?></th>
            <th><?php echo $langs->trans("Bottles"); ?></th>
            <th><?php echo $langs->trans("TotalML"); ?></th>
            <th><?php echo $langs->trans("Action"); ?></th>
        </tr>
        <tr>
            <td><strong>Small Mixed Order</strong></td>
            <td>10×10ml + 10×20ml + 5×30ml</td>
            <td>500ml total (T10, P20, M30)</td>
            <td>
                <a href="#" onclick="setQuickOrder(10,10,5,0)" class="button buttonxs">Use This</a>
            </td>
        </tr>
        <tr>
            <td><strong>Medium Mixed Order</strong></td>
            <td>20×10ml + 15×20ml + 10×30ml + 5×50ml</td>
            <td>850ml total (all sizes)</td>
            <td>
                <a href="#" onclick="setQuickOrder(20,15,10,5)" class="button buttonxs">Use This</a>
            </td>
        </tr>
        <tr>
            <td><strong>Large Bottle Focus</strong></td>
            <td>5×10ml + 5×20ml + 5×30ml + 15×50ml</td>
            <td>1000ml total (focus on G50)</td>
            <td>
                <a href="#" onclick="setQuickOrder(5,5,5,15)" class="button buttonxs">Use This</a>
            </td>
        </tr>
    </table>
</div>

</div>

<script>
// Global variables for selected oil
let selectedOil = {
    id: 0,
    ref: '',
    label: '',
    availableML: 0,
    maxBatches: 0
};

let allOils = <?php echo json_encode($raw_materials); ?>;
let dropdownTimeout;

// Configuration from Dolibarr
const FORMULA_OUTPUT_ML = <?php echo getDolGlobalInt('MLCONVERSION_DEFAULT_FORMULA_OUTPUT', 1125); ?>;
const OIL_PER_BATCH = <?php echo getDolGlobalInt('MLCONVERSION_DEFAULT_OIL_PER_BATCH', 125); ?>;
const ALCOHOL_PER_BATCH = <?php echo getDolGlobalInt('MLCONVERSION_DEFAULT_ALCOHOL_PER_BATCH', 1000); ?>;

// Empty bottle stock
const BOTTLE_STOCK = {
    bottl_10: <?php echo $bottle_stock['bottl_10']; ?>,
    bottl_20: <?php echo $bottle_stock['bottl_20']; ?>,
    bottl_30: <?php echo $bottle_stock['bottl_30']; ?>,
    bottl_50: <?php echo $bottle_stock['bottl_50']; ?>
};

// Search functionality
function searchOils() {
    const searchTerm = document.getElementById('oil-search').value.toLowerCase();
    const dropdown = document.getElementById('dropdown-results');
    
    if (searchTerm.length === 0) {
        dropdown.innerHTML = '';
        dropdown.style.display = 'none';
        return;
    }
    
    const filteredOils = allOils.filter(oil => {
        return oil.ref.toLowerCase().includes(searchTerm) ||
               oil.label.toLowerCase().includes(searchTerm) ||
               (oil.description && oil.description.toLowerCase().includes(searchTerm));
    });
    
    dropdown.innerHTML = '';
    
    if (filteredOils.length === 0) {
        dropdown.innerHTML = '<div class="dropdown-item" style="cursor: default; opacity: 0.6;">No oils found matching "' + searchTerm + '"</div>';
    } else {
        filteredOils.forEach(oil => {
            const stockStatus = oil.status === 'critical' ? 'stock-critical' : 
                               oil.status === 'warning' ? 'stock-warning' : 'stock-good';
            
            const item = document.createElement('div');
            item.className = 'dropdown-item';
            item.onclick = () => selectOilFromSearch(oil);
            
            item.innerHTML = `
                <div>
                    <div class="oil-name">${oil.ref}</div>
                    <div class="oil-details">${oil.label}</div>
                </div>
                <div class="oil-stock">
                    <div class="stock-amount">${parseFloat(oil.stock_ml).toFixed(1)}ml</div>
                    <div class="stock-status ${stockStatus}">${oil.status_text}</div>
                </div>
            `;
            
            dropdown.appendChild(item);
        });
    }
    
    dropdown.style.display = 'block';
}

function showDropdown() {
    if (allOils.length > 0) {
        searchOils();
    }
}

function hideDropdownDelayed() {
    dropdownTimeout = setTimeout(() => {
        document.getElementById('dropdown-results').style.display = 'none';
    }, 200);
}

function selectOilFromSearch(oil) {
    clearTimeout(dropdownTimeout);
    
    document.getElementById('oil-search').value = oil.ref + ' - ' + oil.label;
    document.getElementById('dropdown-results').style.display = 'none';
    
    selectOil(oil.id, oil.ref, oil.label, parseFloat(oil.stock_ml), oil.possible_batches);
    updateProgressStep(2);
}

function updateProgressStep(step) {
    // Update progress indicators
    for (let i = 1; i <= 4; i++) {
        const progressStep = document.getElementById('progress-' + i);
        if (i < step) {
            progressStep.classList.add('completed');
            progressStep.classList.remove('active');
        } else if (i === step) {
            progressStep.classList.add('active');
            progressStep.classList.remove('completed');
        } else {
            progressStep.classList.remove('active', 'completed');
        }
    }
    
    // Show the corresponding step
    if (step >= 2) {
        document.getElementById('step-2').style.display = 'block';
    }
    if (step >= 3) {
        document.getElementById('step-3').style.display = 'block';
    }
    if (step >= 4) {
        document.getElementById('step-4').style.display = 'block';
    }
}

// Function to select oil from dropdown
function selectOilFromDropdown() {
    const dropdown = document.getElementById('oil-dropdown');
    const selectedOption = dropdown.options[dropdown.selectedIndex];
    
    if (selectedOption.value) {
        const oilId = selectedOption.value;
        const oilRef = selectedOption.getAttribute('data-ref');
        const oilLabel = selectedOption.getAttribute('data-label');
        const availableML = parseFloat(selectedOption.getAttribute('data-stock'));
        const maxBatches = parseInt(selectedOption.getAttribute('data-batches'));
        
        selectOil(oilId, oilRef, oilLabel, availableML, maxBatches);
        showStep(2);
        updateProgress(25);
    } else {
        // Hide subsequent steps if no oil selected
        hideStepsAfter(1);
        updateProgress(0);
    }
}

// Function to show a specific step
function showStep(stepNumber) {
    document.getElementById('step-' + stepNumber).style.display = 'block';
    document.getElementById('step-' + stepNumber).classList.add('active');
    
    // Mark previous steps as completed
    for (let i = 1; i < stepNumber; i++) {
        const step = document.getElementById('step-' + i);
        step.classList.remove('active');
        step.classList.add('completed');
    }
}

// Function to hide steps after a certain number
function hideStepsAfter(stepNumber) {
    for (let i = stepNumber + 1; i <= 4; i++) {
        const step = document.getElementById('step-' + i);
        step.style.display = 'none';
        step.classList.remove('active', 'completed');
    }
}

// Function to update progress bar
function updateProgress(percentage) {
    document.getElementById('progress-fill').style.width = percentage + '%';
}

// Function to select an oil
function selectOil(oilId, oilRef, oilLabel, availableML, maxBatches) {
    selectedOil = {
        id: oilId,
        ref: oilRef,
        label: oilLabel,
        availableML: availableML,
        maxBatches: maxBatches
    };
    
    // Extract oil code for final product references
    // Example: oil_women_YVESROCHER_F309 -> F309
    const oilCodeMatch = oilRef.match(/_([A-Z0-9]+)$/);
    selectedOil.code = oilCodeMatch ? oilCodeMatch[1] : oilRef.replace(/^oil_/i, '').replace(/^OIL_/i, '');
    
    // Update the form
    document.getElementById('selected_oil_id').value = oilId;
    document.getElementById('selected-oil-name').textContent = oilRef + ' - ' + oilLabel;
    document.getElementById('selected-oil-stock').textContent = availableML.toFixed(1) + 'ml';
    document.getElementById('selected-oil-batches').textContent = maxBatches;
    document.getElementById('available_oil_stock').textContent = availableML.toFixed(1) + 'ml';
    
    // Update bottle stock displays
    document.getElementById('stock_bott_10').textContent = BOTTLE_STOCK.bottl_10;
    document.getElementById('stock_bott_20').textContent = BOTTLE_STOCK.bottl_20;
    document.getElementById('stock_bott_30').textContent = BOTTLE_STOCK.bottl_30;
    document.getElementById('stock_bott_50').textContent = BOTTLE_STOCK.bottl_50;
    
    // Show oil info
    document.getElementById('selected-oil-info').style.display = 'block';
    
    // Reset form values
    resetForm();
}

// Function to update calculations in real-time
function updateCalculations() {
    if (selectedOil.id === 0) return;
    
    // Get ingredient quantities
    const oilQuantity = parseFloat(document.getElementById('oil_quantity').value) || 0;
    const alcoholQuantity = parseFloat(document.getElementById('alcohol_quantity').value) || 0;
    
    // Get bottle quantities
    const bottle10ml = parseInt(document.getElementById('bottle_10ml').value) || 0;
    const bottle20ml = parseInt(document.getElementById('bottle_20ml').value) || 0;
    const bottle30ml = parseInt(document.getElementById('bottle_30ml').value) || 0;
    const bottle50ml = parseInt(document.getElementById('bottle_50ml').value) || 0;
    
    // Calculate individual bottle totals
    const ml10 = bottle10ml * 10;
    const ml20 = bottle20ml * 20;
    const ml30 = bottle30ml * 30;
    const ml50 = bottle50ml * 50;
    
    // Update individual displays
    document.getElementById('ml_10ml').textContent = ml10 + 'ml';
    document.getElementById('ml_20ml').textContent = ml20 + 'ml';
    document.getElementById('ml_30ml').textContent = ml30 + 'ml';
    document.getElementById('ml_50ml').textContent = ml50 + 'ml';
    
    // Calculate totals
    const totalMLNeeded = ml10 + ml20 + ml30 + ml50;
    const totalMLProduced = oilQuantity + alcoholQuantity;
    
    // Update summary
    document.getElementById('total-ml-needed').textContent = totalMLNeeded + 'ml';
    document.getElementById('batches-required').textContent = '1 batch';
    document.getElementById('oil-needed').textContent = oilQuantity + 'ml';
    document.getElementById('alcohol-needed').textContent = alcoholQuantity + 'ml';
    
    // Show step 3 when ingredients are configured
    if (oilQuantity > 0 && alcoholQuantity > 0) {
        updateProgressStep(3);
    }
    
    // Show step 4 when bottles are selected
    if (totalMLNeeded > 0) {
        updateProgressStep(4);
    }
    
    // Check feasibility
    const feasibilityStatus = document.getElementById('feasibility-status');
    const submitBtn = document.getElementById('submit-btn');
    
    if (totalMLNeeded === 0) {
        feasibilityStatus.className = 'status-message status-info';
        feasibilityStatus.innerHTML = '💡 Enter bottle quantities to see requirements';
        submitBtn.disabled = true;
    } else if (oilQuantity > selectedOil.availableML) {
        const shortage = oilQuantity - selectedOil.availableML;
        feasibilityStatus.className = 'status-message status-error';
        feasibilityStatus.innerHTML = '❌ <strong>Insufficient oil!</strong><br>Need ' + shortage.toFixed(1) + 'ml more oil';
        submitBtn.disabled = true;
    } else if (totalMLNeeded > totalMLProduced) {
        const shortage = totalMLNeeded - totalMLProduced;
        feasibilityStatus.className = 'status-message status-error';
        feasibilityStatus.innerHTML = '❌ <strong>Not enough base formula!</strong><br>Need ' + shortage.toFixed(1) + 'ml more formula';
        submitBtn.disabled = true;
    } else {
        // Check empty bottle availability
        let bottleShortage = [];
        if (bottle10ml > BOTTLE_STOCK.bottl_10) bottleShortage.push('🧪 10ml: need ' + (bottle10ml - BOTTLE_STOCK.bottl_10) + ' more');
        if (bottle20ml > BOTTLE_STOCK.bottl_20) bottleShortage.push('💎 20ml: need ' + (bottle20ml - BOTTLE_STOCK.bottl_20) + ' more');
        if (bottle30ml > BOTTLE_STOCK.bottl_30) bottleShortage.push('💫 30ml: need ' + (bottle30ml - BOTTLE_STOCK.bottl_30) + ' more');
        if (bottle50ml > BOTTLE_STOCK.bottl_50) bottleShortage.push('✨ 50ml: need ' + (bottle50ml - BOTTLE_STOCK.bottl_50) + ' more');
        
        if (bottleShortage.length > 0) {
            feasibilityStatus.className = 'status-message status-error';
            feasibilityStatus.innerHTML = '❌ <strong>Insufficient empty bottles!</strong><br>' + 
                bottleShortage.join('<br>');
            submitBtn.disabled = true;
        } else {
            const remainingFormula = totalMLProduced - totalMLNeeded;
            const finalProducts = [];
            if (bottle10ml > 0) finalProducts.push('🧪 ' + selectedOil.code + '_T10: ' + bottle10ml + ' bottles');
            if (bottle20ml > 0) finalProducts.push('💎 ' + selectedOil.code + '_P20: ' + bottle20ml + ' bottles');
            if (bottle30ml > 0) finalProducts.push('💫 ' + selectedOil.code + '_M30: ' + bottle30ml + ' bottles');
            if (bottle50ml > 0) finalProducts.push('✨ ' + selectedOil.code + '_G50: ' + bottle50ml + ' bottles');
            
            feasibilityStatus.className = 'status-message status-success';
            feasibilityStatus.innerHTML = '🎉 <strong>Ready to produce!</strong><br>' +
                finalProducts.join('<br>') + '<br>' +
                '💧 Remaining formula: ' + remainingFormula.toFixed(1) + 'ml for samples';
            submitBtn.disabled = false;
            updateProgress(100);
        }
    }
}

// Function to reset form
function resetForm() {
    document.getElementById('oil_quantity').value = 125;
    document.getElementById('alcohol_quantity').value = 1000;
    document.getElementById('bottle_10ml').value = 0;
    document.getElementById('bottle_20ml').value = 0;
    document.getElementById('bottle_30ml').value = 0;
    document.getElementById('bottle_50ml').value = 0;
    updateCalculations();
}

// Quick order functions
function setQuickOrder(ml10, ml20, ml30, ml50) {
    if (selectedOil.id === 0) {
        alert('Please select an oil first!');
        return;
    }
    
    document.getElementById('bottle_10ml').value = ml10;
    document.getElementById('bottle_20ml').value = ml20;
    document.getElementById('bottle_30ml').value = ml30;
    document.getElementById('bottle_50ml').value = ml50;
    updateCalculations();
}

// Function to start production
function startProduction() {
    if (selectedOil.id === 0) {
        alert('Please select an oil first!');
        return;
    }
    
    // Get current values
    const oilQuantity = parseFloat(document.getElementById('oil_quantity').value) || 0;
    const alcoholQuantity = parseFloat(document.getElementById('alcohol_quantity').value) || 0;
    const bottle10ml = parseInt(document.getElementById('bottle_10ml').value) || 0;
    const bottle20ml = parseInt(document.getElementById('bottle_20ml').value) || 0;
    const bottle30ml = parseInt(document.getElementById('bottle_30ml').value) || 0;
    const bottle50ml = parseInt(document.getElementById('bottle_50ml').value) || 0;
    
    // Debug: Log values to console
    console.log('Form values:', {
        oilId: selectedOil.id,
        oilQuantity: oilQuantity,
        alcoholQuantity: alcoholQuantity,
        bottles: [bottle10ml, bottle20ml, bottle30ml, bottle50ml]
    });
    
    // Update hidden form fields with current values
    document.getElementById('selected_oil_id_form').value = selectedOil.id;
    document.getElementById('oil_quantity_form').value = oilQuantity;
    document.getElementById('alcohol_quantity_form').value = alcoholQuantity;
    document.getElementById('bottle_10ml_form').value = bottle10ml;
    document.getElementById('bottle_20ml_form').value = bottle20ml;
    document.getElementById('bottle_30ml_form').value = bottle30ml;
    document.getElementById('bottle_50ml_form').value = bottle50ml;
    
    // Show confirmation dialog
    
    let confirmMessage = `🧪 Confirm Production Order\n\n`;
    confirmMessage += `Oil: ${selectedOil.ref}\n`;
    confirmMessage += `Formula: ${oilQuantity}ml oil + ${alcoholQuantity}ml alcohol\n\n`;
    confirmMessage += `Production:\n`;
    if (bottle10ml > 0) confirmMessage += `• ${bottle10ml} × 10ml bottles (${selectedOil.code}_T10)\n`;
    if (bottle20ml > 0) confirmMessage += `• ${bottle20ml} × 20ml bottles (${selectedOil.code}_P20)\n`;
    if (bottle30ml > 0) confirmMessage += `• ${bottle30ml} × 30ml bottles (${selectedOil.code}_M30)\n`;
    if (bottle50ml > 0) confirmMessage += `• ${bottle50ml} × 50ml bottles (${selectedOil.code}_G50)\n`;
    confirmMessage += `\nProceed with production?`;
    
    if (confirm(confirmMessage)) {
        // Change button text to show processing
        document.getElementById('submit-btn').innerHTML = '⏳ Creating Order...';
        document.getElementById('submit-btn').disabled = true;
        
        // Submit the form
        document.getElementById('production-form-submit').submit();
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to all inputs
    ['oil_quantity', 'alcohol_quantity', 'bottle_10ml', 'bottle_20ml', 'bottle_30ml', 'bottle_50ml'].forEach(function(inputId) {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('input', updateCalculations);
        }
    });
});
</script>

<style>
.badge-status {
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
}
.badge-status4 { background-color: #5cb85c; color: white; }
.badge-status1 { background-color: #f0ad4e; color: white; }
.badge-status8 { background-color: #d9534f; color: white; }
.buttonxs {
    padding: 2px 6px;
    font-size: 11px;
    margin: 1px;
}
</style>

<?php

// End of page
llxFooter();
$db->close();
?>
