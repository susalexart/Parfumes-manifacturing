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
 * \file    interactive_demo.php
 * \ingroup mlconversion
 * \brief   Interactive Oil Selection Demo
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
require_once 'class/mlconversion.class.php';

// Load translation files required by the page
$langs->loadLangs(array("mlconversion@mlconversion", "products"));

$action = GETPOST('action', 'aZ09');

// Security check
if (!isModEnabled('mlconversion')) {
    accessforbidden('Module not enabled');
}
restrictedArea($user, 'mlconversion');

$ml_converter = new MLConversion($db);

/*
 * Actions
 */

// None for demo

/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader("", $langs->trans("InteractiveDemo"), '');

print load_fiche_titre("🧪 Parfumery Lab - Interactive Demo", '', 'fa-mouse-pointer');

?>

<div class="tabBar">

<!-- Interactive Demo Content -->
<div class="div-table-responsive-no-min">
    <table class="border centpercent">
        <tr class="liste_titre">
            <th colspan="2">🎮 Interactive ML Conversion Demo</th>
        </tr>
        <tr>
            <td width="30%"><strong>Demo Features:</strong></td>
            <td>
                <ul>
                    <li>🔍 <strong>Oil Selection:</strong> Choose from dropdown menu</li>
                    <li>🧮 <strong>Custom Quantities:</strong> Enter exact ingredient amounts</li>
                    <li>📦 <strong>Bottle Sizes:</strong> 10ml, 20ml, 30ml, 50ml</li>
                    <li>🏷️ <strong>Auto References:</strong> F309_T10, F309_P20, F309_M30, F309_G50</li>
                    <li>📊 <strong>Stock Updates:</strong> Final products stock increased</li>
                </ul>
            </td>
        </tr>
        <tr>
            <td><strong>Your Example:</strong></td>
            <td>
                <strong>300ml oil → 125ml bottles = 2 bottles + 50ml remaining</strong><br>
                <small>But now with 10ml, 20ml, 30ml, 50ml options and custom quantities!</small>
            </td>
        </tr>
    </table>
</div>

<br>

<!-- Link to Enhanced Manufacturing -->
<div class="div-table-responsive-no-min">
    <table class="border centpercent">
        <tr class="liste_titre">
            <th>🚀 Try the Enhanced Manufacturing Interface</th>
        </tr>
        <tr>
            <td class="center">
                <p><strong>The interactive demo has been integrated into the Manufacturing Orders page</strong></p>
                <p>Click below to access the full interactive system:</p>
                <br>
                <a href="<?php echo dol_buildpath('/mlconversion/manufacturing.php', 1); ?>" class="butAction">
                    🎯 Go to Enhanced Manufacturing Orders
                </a>
                <br><br>
                <p><small>Features include: Dropdown oil selection, custom quantities, and automatic final product creation</small></p>
            </td>
        </tr>
    </table>
</div>

<br>

<!-- Feature Preview -->
<div class="div-table-responsive-no-min">
    <table class="border centpercent">
        <tr class="liste_titre">
            <th colspan="2">✨ New Features Preview</th>
        </tr>
        <tr>
            <td width="30%"><strong>Dropdown Oil Selection:</strong></td>
            <td>No more long list - clean dropdown menu with available oils</td>
        </tr>
        <tr>
            <td><strong>Custom Ingredient Quantities:</strong></td>
            <td>Enter exact amounts instead of using all available stock</td>
        </tr>
        <tr>
            <td><strong>Proper Bottle Sizes:</strong></td>
            <td>10ml (T10), 20ml (P20), 30ml (M30), 50ml (G50)</td>
        </tr>
        <tr>
            <td><strong>Auto Final Product References:</strong></td>
            <td>
                <strong>Example:</strong><br>
                Oil: <code>oil_women_YVESROCHER_F309</code><br>
                Final Products:<br>
                • <code>F309_T10</code> (10ml bottle)<br>
                • <code>F309_P20</code> (20ml bottle)<br>
                • <code>F309_M30</code> (30ml bottle)<br>
                • <code>F309_G50</code> (50ml bottle)
            </td>
        </tr>
        <tr>
            <td><strong>Stock Management:</strong></td>
            <td>Final product stock automatically increased after production</td>
        </tr>
    </table>
</div>

</div>

<?php

// End of page
llxFooter();
$db->close();
?>
