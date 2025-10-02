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
 * \file    manufacturing_orders_list.php
 * \ingroup mlconversion
 * \brief   List of Manufacturing Orders
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once 'class/mlconversion.class.php';

// Load translation files required by the page
$langs->loadLangs(array("mlconversion@mlconversion", "products"));

$action = GETPOST('action', 'aZ09');
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTINT('page');
if (empty($page) || $page == -1) {
    $page = 0;
}

$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$offset = $limit * $page;

if (!$sortfield) {
    $sortfield = 'mo.date_creation';
}
if (!$sortorder) {
    $sortorder = 'DESC';
}

// Security check
if (!isModEnabled('mlconversion')) {
    accessforbidden('Module not enabled');
}
restrictedArea($user, 'mlconversion');

/*
 * Actions
 */

// None

/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader("", $langs->trans("ManufacturingOrdersList"), '');

print load_fiche_titre("🧪 Rezk Parfumery Lab - Manufacturing Orders", '', 'fa-list');

?>

<style>
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
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f1f3f4;
}

.section-title {
    font-size: 28px;
    font-weight: 700;
    color: #2c3e50;
    margin: 0;
}

.btn-primary {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(44,62,80,0.4);
    color: white;
    text-decoration: none;
}

.professional-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.professional-table th {
    background: #f8f9fa;
    color: #2c3e50;
    font-weight: 600;
    padding: 15px;
    text-align: left;
    border-bottom: 2px solid #e9ecef;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.professional-table td {
    padding: 15px;
    border-bottom: 1px solid #f1f3f4;
    color: #495057;
}

.professional-table tr:hover {
    background: #f8f9fa;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-draft { background: #e9ecef; color: #6c757d; }
.status-validated { background: #cce5ff; color: #0066cc; }
.status-progress { background: #fff3cd; color: #856404; }
.status-produced { background: #d4edda; color: #155724; }
.status-canceled { background: #f8d7da; color: #721c24; }

.search-form {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #e9ecef;
}

.search-input {
    padding: 10px 15px;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: #2c3e50;
    box-shadow: 0 0 0 3px rgba(44,62,80,0.1);
}

.btn-search {
    background: #2c3e50;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    margin-left: 10px;
}

.btn-search:hover {
    background: #34495e;
}

.no-records {
    text-align: center;
    padding: 40px;
    color: #6c757d;
    font-style: italic;
}

@media (max-width: 768px) {
    .parfumery-container {
        padding: 20px;
    }
    
    .section-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .professional-table {
        font-size: 14px;
    }
    
    .professional-table th,
    .professional-table td {
        padding: 10px;
    }
}
</style>

<div class="parfumery-container">
    <div class="section-card">
        <div class="section-header">
            <h2 class="section-title">Manufacturing Orders</h2>
            <a href="<?php echo dol_buildpath('/mlconversion/manufacturing.php', 1); ?>" class="btn-primary">
                ➕ Create New Order
            </a>
        </div>

<?php

// Build and execute query
$sql = "SELECT mo.rowid, mo.ref, mo.label, mo.date_creation, mo.date_start_planned, mo.date_end_planned,";
$sql .= " mo.status, mo.fk_product, p.ref as product_ref, p.label as product_label";
$sql .= " FROM ".MAIN_DB_PREFIX."mrp_mo as mo";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON mo.fk_product = p.rowid";
$sql .= " WHERE mo.entity IN (".getEntity('mrp').")";

// Add search filter
$search_ref = GETPOST('search_ref', 'alpha');
if ($search_ref) {
    $sql .= " AND mo.ref LIKE '%".$db->escape($search_ref)."%'";
}

$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    $i = 0;

    $param = '';
    if (!empty($search_ref)) {
        $param .= '&search_ref='.urlencode($search_ref);
    }

    ?>
    
    <!-- Search Form -->
    <form method="POST" id="searchFormList" action="<?php echo $_SERVER["PHP_SELF"]; ?>" class="search-form">
        <input type="hidden" name="token" value="<?php echo newToken(); ?>">
        <input type="hidden" name="formfilteraction" id="formfilteraction" value="list">
        <input type="hidden" name="action" value="list">
        <input type="hidden" name="sortfield" value="<?php echo $sortfield; ?>">
        <input type="hidden" name="sortorder" value="<?php echo $sortorder; ?>">
        
        <div style="display: flex; align-items: center; gap: 15px;">
            <div>
                <label for="search_ref" style="font-weight: 600; color: #2c3e50; margin-right: 10px;">Search Reference:</label>
                <input class="search-input" type="text" name="search_ref" id="search_ref" 
                       value="<?php echo dol_escape_htmltag($search_ref); ?>" 
                       placeholder="Enter manufacturing order reference...">
            </div>
            <button type="submit" class="btn-search">🔍 Search</button>
            <?php if ($search_ref): ?>
                <a href="<?php echo $_SERVER["PHP_SELF"]; ?>" class="btn-search" style="background: #6c757d; text-decoration: none;">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Manufacturing Orders Table -->
    <table class="professional-table">
        <thead>
            <tr>
                <th>Reference</th>
                <th>Label</th>
                <th>Product</th>
                <th>Created</th>
                <th>Planned Start</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>

        <?php if ($num > 0): ?>
            <?php while ($i < min($num, $limit)): ?>
                <?php 
                $obj = $db->fetch_object($resql);
                ?>
                <tr>
                    <!-- Reference -->
                    <td>
                        <a href="<?php echo dol_buildpath('/mrp/mo_card.php', 1); ?>?id=<?php echo $obj->rowid; ?>" 
                           style="color: #2c3e50; font-weight: 600; text-decoration: none;">
                            🏭 <?php echo $obj->ref; ?>
                        </a>
                    </td>

                    <!-- Label -->
                    <td>
                        <?php echo dol_escape_htmltag($obj->label); ?>
                    </td>

                    <!-- Product -->
                    <td>
                        <?php if ($obj->fk_product > 0): ?>
                            <a href="<?php echo dol_buildpath('/product/card.php', 1); ?>?id=<?php echo $obj->fk_product; ?>" 
                               style="color: #2c3e50; text-decoration: none;">
                                🧪 <?php echo $obj->product_ref; ?>
                            </a>
                            <?php if ($obj->product_label): ?>
                                <br><small style="color: #6c757d;"><?php echo dol_trunc($obj->product_label, 30); ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #6c757d;">-</span>
                        <?php endif; ?>
                    </td>

                    <!-- Date Creation -->
                    <td>
                        <?php echo dol_print_date($db->jdate($obj->date_creation), 'day'); ?>
                    </td>

                    <!-- Date Start Planned -->
                    <td>
                        <?php if ($obj->date_start_planned): ?>
                            <?php echo dol_print_date($db->jdate($obj->date_start_planned), 'day'); ?>
                        <?php else: ?>
                            <span style="color: #6c757d;">Not planned</span>
                        <?php endif; ?>
                    </td>

                    <!-- Status -->
                    <td>
                        <?php
                        $statusClass = 'status-draft';
                        $statusText = 'Draft';
                        
                        switch ($obj->status) {
                            case 0:
                                $statusClass = 'status-draft';
                                $statusText = 'Draft';
                                break;
                            case 1:
                                $statusClass = 'status-validated';
                                $statusText = 'Validated';
                                break;
                            case 2:
                                $statusClass = 'status-progress';
                                $statusText = 'In Progress';
                                break;
                            case 3:
                                $statusClass = 'status-produced';
                                $statusText = 'Produced';
                                break;
                            case 9:
                                $statusClass = 'status-canceled';
                                $statusText = 'Canceled';
                                break;
                        }
                        ?>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <?php echo $statusText; ?>
                        </span>
                    </td>

                    <!-- Actions -->
                    <td style="text-align: center;">
                        <a href="<?php echo dol_buildpath('/mrp/mo_card.php', 1); ?>?id=<?php echo $obj->rowid; ?>" 
                           style="color: #2c3e50; text-decoration: none; margin-right: 10px;" 
                           title="View Details">
                            👁️
                        </a>
                        <a href="<?php echo dol_buildpath('/mrp/mo_card.php', 1); ?>?id=<?php echo $obj->rowid; ?>&action=edit&token=<?php echo newToken(); ?>" 
                           style="color: #2c3e50; text-decoration: none;" 
                           title="Edit">
                            ✏️
                        </a>
                    </td>
                </tr>
                <?php $i++; ?>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="7" class="no-records">
                    📋 No manufacturing orders found
                    <?php if ($search_ref): ?>
                        matching "<?php echo dol_escape_htmltag($search_ref); ?>"
                    <?php endif; ?>
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    
    </div>
</div>

<?php
    $db->free($resql);
} else {
    dol_print_error($db);
}

// End of page
llxFooter();
$db->close();
?>
