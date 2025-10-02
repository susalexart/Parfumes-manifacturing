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
 * \file    mlconversion/index.php
 * \ingroup mlconversion
 * \brief   Home page of mlconversion top menu
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

// Load translation files required by the page
$langs->loadLangs(array("mlconversion@mlconversion"));

$action = GETPOST('action', 'aZ09');

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

llxHeader("", $langs->trans("MLConversionArea"), '');

print load_fiche_titre("🧪 Parfumery Lab - Welcome", '', 'fa-flask');

print '<div class="fichecenter"><div class="fichethirdleft">';

// BEGIN MODULEBUILDER DRAFT MYOBJECT
// Draft MyObject
if (isModEnabled('mlconversion') && $user->hasRight('mlconversion', 'read')) {
    $langs->load("orders");

    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th colspan="3">'.$langs->trans("MLConversion").'</th>';
    print '</tr>';

    print '<tr class="oddeven"><td class="nowrap">';
    print '<span class="fa fa-flask fa-2x colorblue"></span>';
    print '</td>';
    print '<td>'.$langs->trans("WelcomeToMLConversion").'</td>';
    print '<td class="right"><a class="butAction" href="'.dol_buildpath('/mlconversion/manufacturing.php', 1).'">'.$langs->trans("NewManufacturingOrder").'</a></td>';
    print '</tr>';

    print '<tr class="oddeven"><td class="nowrap">';
    print '<span class="fa fa-calculator fa-2x colorgreen"></span>';
    print '</td>';
    print '<td>'.$langs->trans("MLCalculator").'</td>';
    print '<td class="right"><a class="butAction" href="'.dol_buildpath('/mlconversion/demo.php', 1).'">'.$langs->trans("OpenCalculator").'</a></td>';
    print '</tr>';

    print '<tr class="oddeven"><td class="nowrap">';
    print '<span class="fa fa-mouse-pointer fa-2x colororange"></span>';
    print '</td>';
    print '<td>'.$langs->trans("InteractiveDemo").'</td>';
    print '<td class="right"><a class="butAction" href="'.dol_buildpath('/mlconversion/interactive_demo.php', 1).'">'.$langs->trans("TryDemo").'</a></td>';
    print '</tr>';

    print '</table></div><br>';
}

print '</div><div class="fichetwothirdright">';

// Show ML Conversion Features
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans("MLConversionFeatures").'</th>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>';
print '<h4><span class="fa fa-flask"></span> '.$langs->trans("InteractiveOilSelection").'</h4>';
print '<p>'.$langs->trans("AutomaticallyDetectsOilProducts").'</p>';
print '<ul>';
print '<li>'.$langs->trans("RealTimeStockLevels").'</li>';
print '<li>'.$langs->trans("ProductionCapacityCalculation").'</li>';
print '<li>'.$langs->trans("SmartStockAlerts").'</li>';
print '</ul>';
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>';
print '<h4><span class="fa fa-calculator"></span> '.$langs->trans("MLToBottleConversion").'</h4>';
print '<p>'.$langs->trans("AutomaticConversionBetweenML").'</p>';
print '<ul>';
print '<li>'.$langs->trans("ExampleConversion").'</li>';
print '<li>'.$langs->trans("MultiSizeBottleSupport").'</li>';
print '<li>'.$langs->trans("RealTimeCalculations").'</li>';
print '</ul>';
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>';
print '<h4><span class="fa fa-cogs"></span> '.$langs->trans("ManufacturingOrders").'</h4>';
print '<p>'.$langs->trans("CompleteProductionPlanning").'</p>';
print '<ul>';
print '<li>'.$langs->trans("MultiSizeProduction").'</li>';
print '<li>'.$langs->trans("MaterialRequirementPlanning").'</li>';
print '<li>'.$langs->trans("FeasibilityAnalysis").'</li>';
print '</ul>';
print '</td>';
print '</tr>';

print '</table>';
print '</div>';

print '</div></div>';

// End of page
llxFooter();
$db->close();
?>
