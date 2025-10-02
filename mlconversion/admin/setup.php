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
 * \file    mlconversion/admin/setup.php
 * \ingroup mlconversion
 * \brief   MLConversion setup page.
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
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

global $langs, $user;

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/mlconversion.lib.php';

// Translations
$langs->loadLangs(array("admin", "mlconversion@mlconversion"));

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('mlconversionsetup', 'globalsetup'));

// Access control
if (!$user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');	// Used by actions_setmoduleoptions.inc.php

$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');
$scandir = GETPOST('scan_dir', 'alpha');
$type = 'mlconversion';

$arrayofparameters = array(
    'MLCONVERSION_DEFAULT_FORMULA_OUTPUT' => array('css'=>'minwidth500', 'enabled'=>1),
    'MLCONVERSION_DEFAULT_OIL_PER_BATCH' => array('css'=>'minwidth500', 'enabled'=>1),
    'MLCONVERSION_DEFAULT_ALCOHOL_PER_BATCH' => array('css'=>'minwidth500', 'enabled'=>1),
);

$error = 0;
$setupnotempty = 0;

// Set this to 1 to use the factory to manage generation of documents.
// Set this to 0 if you don't need this feature.
$uselocaltax = 1;

if (empty($setupnotempty)) {
    $setupnotempty = count($arrayofparameters);
}

/*
 * Actions
 */

// For retrocompatibility
if (!empty($conf->global->MLCONVERSION_DEFAULT_FORMULA_OUTPUT)) {
    dolibarr_set_const($db, 'MLCONVERSION_DEFAULT_FORMULA_OUTPUT', $conf->global->MLCONVERSION_DEFAULT_FORMULA_OUTPUT, 'chaine', 0, '', $conf->entity);
    dolibarr_del_const($db, 'MLCONVERSION_DEFAULT_FORMULA_OUTPUT', $conf->entity);
}

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

if ($action == 'updateMask') {
    $maskconst = GETPOST('maskconst', 'aZ09');
    $maskvalue = GETPOST('maskvalue', 'alpha');

    if ($maskconst && preg_match('/_MASK$/', $maskconst)) {
        $res = dolibarr_set_const($db, $maskconst, $maskvalue, 'chaine', 0, '', $conf->entity);
        if (!($res > 0)) {
            $error++;
        }
    }

    if (!$error) {
        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    } else {
        setEventMessages($langs->trans("Error"), null, 'errors');
    }
} elseif ($action == 'specimen') {
    $modele = GETPOST('module', 'alpha');
    $tmpobjectkey = GETPOST('object');

    $tmpobject = new $tmpobjectkey($db);
    $tmpobject->initAsSpecimen();

    // Search template files
    $file = ''; $classname = ''; $filefound = 0;
    $dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
    foreach ($dirmodels as $reldir) {
        $file = dol_buildpath($reldir."core/modules/mlconversion/doc/pdf_".$modele."_".strtolower($tmpobjectkey).".modules.php", 0);
        if (file_exists($file)) {
            $filefound = 1;
            $classname = "pdf_".$modele."_".strtolower($tmpobjectkey);
            break;
        }
    }

    if ($filefound) {
        require_once $file;

        $module = new $classname($db);

        if ($module->write_file($tmpobject, $langs) > 0) {
            header("Location: ".DOL_URL_ROOT."/document.php?modulepart=mlconversion&file=SPECIMEN.pdf");
            return;
        } else {
            setEventMessages($module->error, $module->errors, 'errors');
            dol_syslog($module->error, LOG_ERR);
        }
    } else {
        setEventMessages($langs->trans("ErrorModuleNotFound"), null, 'errors');
        dol_syslog($langs->trans("ErrorModuleNotFound"), LOG_ERR);
    }
} elseif ($action == 'set') {
    // Activate a model
    $ret = addDocumentModel($value, $type, $label, $scandir);
} elseif ($action == 'del') {
    $ret = delDocumentModel($value, $type);
    if ($ret > 0) {
        if ($conf->global->MLCONVERSION_ADDON_PDF == "$value") {
            dolibarr_del_const($db, 'MLCONVERSION_ADDON_PDF', $conf->entity);
        }
    }
} elseif ($action == 'setmod') {
    // TODO Check if numbering module chosen can be activated by calling method canBeActivated
    dolibarr_set_const($db, "MLCONVERSION_ADDON", $value, 'chaine', 0, '', $conf->entity);
} elseif ($action == 'setdoc') {
    // Set or unset default model
    if (dolibarr_set_const($db, "MLCONVERSION_ADDON_PDF", $value, 'chaine', 0, '', $conf->entity)) {
        // The constant that was read before the new set
        // We therefore requires a variable to have a coherent view
        $conf->global->MLCONVERSION_ADDON_PDF = $value;
    }

    // We disable/enable the document template (into llx_document_model table)
    $ret = delDocumentModel($value, $type);
    if ($ret > 0) {
        $ret = addDocumentModel($value, $type, $label, $scandir);
    }
} elseif ($action == 'unsetdoc') {
    dolibarr_del_const($db, "MLCONVERSION_ADDON_PDF", $conf->entity);
}

/*
 * View
 */

$form = new Form($db);

$help_url = '';
$page_name = "MLConversionSetup";

llxHeader('', $langs->trans($page_name), $help_url);

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = mlconversionAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($page_name), -1, "mlconversion@mlconversion");

// Setup page goes here
print '<span class="opacitymedium">'.$langs->trans("MLConversionSetupPage").'</span><br><br>';

if ($action == 'edit') {
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="update">';

    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td class="titlefield">'.$langs->trans("Parameter").'</td>';
    print '<td>'.$langs->trans("Value").'</td>';
    print '</tr>';

    foreach ($arrayofparameters as $constname => $val) {
        if (empty($val['enabled'])) {
            continue;
        }
        $setupnotempty++;

        print '<tr class="oddeven">';
        print '<td>';
        $tooltiphelp = (($langs->trans($constname.'Tooltip') != $constname.'Tooltip') ? $langs->trans($constname.'Tooltip') : '');
        print '<span id="helplink'.$constname.'" class="spanforparamtooltip">'.$form->textwithpicto($langs->trans($constname), $tooltiphelp, 1, 'info', '', 0, 3, 'tootips'.$constname).'</span>';
        print '</td>';
        print '<td>';

        if ($val['type'] == 'textarea') {
            print '<textarea class="flat" name="'.$constname.'" id="'.$constname.'" cols="50" rows="5" wrap="soft">'."\n";
            print getDolGlobalString($constname);
            print "</textarea>\n";
        } elseif ($val['type'] == 'html') {
            require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
            $doleditor = new DolEditor($constname, getDolGlobalString($constname), '', 160, 'dolibarr_notes', '', false, false, isModEnabled('fckeditor'), ROWS_5, '90%');
            $doleditor->Create();
        } elseif ($val['type'] == 'yesno') {
            print $form->selectyesno($constname, getDolGlobalString($constname), 1);
        } elseif (preg_match('/emailtemplate:/', $val['type'])) {
            include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
            $formmail = new FormMail($db);

            $tmp = explode(':', $val['type']);
            $nboftemplates = $formmail->fetchAllEMailTemplate($tmp[1], $user, null, 1); // We set lang=null to get in priority record with no lang, then record with same lang.

            $arrayofmessagename = array();
            if (is_array($formmail->lines_model)) {
                foreach ($formmail->lines_model as $modelmail) {
                    //var_dump($modelmail);
                    $moreonlabel = '';
                    if (!empty($arrayofmessagename[$modelmail->label])) {
                        $moreonlabel = ' <span class="opacitymedium">('.$langs->trans("SeveralLangugeVariatFound").')</span>';
                    }
                    // The 'label' is the key that is unique if we exclude the language
                    $arrayofmessagename[$modelmail->id] = $langs->trans($modelmail->label).$moreonlabel;
                }
            }
            print $form->selectarray($constname, $arrayofmessagename, getDolGlobalString($constname), 'None', 0, 0, '', 0, 0, 0, '', 'minwidth400', 1);
        } elseif (preg_match('/category:/', $val['type'])) {
            require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
            require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
            $formother = new FormOther($db);

            $tmp = explode(':', $val['type']);
            print img_picto('', 'category', 'class="pictofixedwidth"');
            print $formother->select_categories($tmp[1], getDolGlobalString($constname), $constname, 0, $langs->trans('CustomersProspectsCategoriesShort'));
        } elseif (preg_match('/thirdparty_type/', $val['type'])) {
            require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
            $formcompany = new FormCompany($db);
            print $formcompany->selectProspectCustomerType(getDolGlobalString($constname), $constname);
        } elseif ($val['type'] == 'securekey') {
            print '<input required="required" type="text" class="flat" id="'.$constname.'" name="'.$constname.'" value="'.(GETPOST($constname, 'alpha') ? GETPOST($constname, 'alpha') : getDolGlobalString($constname)).'" size="40">';
            if (!empty($conf->use_javascript_ajax)) {
                print '&nbsp;'.img_picto($langs->trans('Generate'), 'refresh', 'id="generate_token'.$constname.'" class="linkobject"');
            }
            if (!empty($conf->use_javascript_ajax)) {
                print "\n".'<script type="text/javascript">';
                print '$(document).ready(function () {
                    $("#generate_token'.$constname.'").click(function() {
                        $.get( "'.DOL_URL_ROOT.'/core/ajax/security.php", {
                            "action": "getrandompassword",
                            "generic": 1
                        },
                        function(token) {
                            $("#'.$constname.'").val(token);
                        });
                    });
                });';
                print '</script>';
            }
        } elseif ($val['type'] == 'product') {
            if (isModEnabled("product") || isModEnabled("service")) {
                $selected = getDolGlobalString($constname);
                print img_picto('', 'product', 'class="pictofixedwidth"');
                print $form->select_produits($selected, $constname, '', 0, 0, 1, 2, '', 0, array(), 0, '1', 0, 'maxwidth500 widthcentpercentminusx');
            }
        } else {
            print '<input name="'.$constname.'"  class="flat '.(empty($val['css']) ? 'minwidth200' : $val['css']).'" value="'.getDolGlobalString($constname).'">';
        }
        print '</td>';
        print '</tr>';
    }
    print '</table>';

    print '<br><div class="center">';
    print '<input class="button button-save" type="submit" value="'.$langs->trans("Save").'">';
    print ' &nbsp; ';
    print '<input class="button button-cancel" type="submit" name="cancel" value="'.$langs->trans("Cancel").'">';
    print '</div>';

    print '</form>';
    print '<br>';
} else {
    if (!empty($arrayofparameters)) {
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<td class="titlefield">'.$langs->trans("Parameter").'</td>';
        print '<td>'.$langs->trans("Value").'</td>';
        print '<td class="center width20">&nbsp;</td>';
        print '</tr>';

        foreach ($arrayofparameters as $constname => $val) {
            if (empty($val['enabled'])) {
                continue;
            }
            $setupnotempty++;

            print '<tr class="oddeven">';
            print '<td>';
            $tooltiphelp = (($langs->trans($constname.'Tooltip') != $constname.'Tooltip') ? $langs->trans($constname.'Tooltip') : '');
            print $form->textwithpicto($langs->trans($constname), $tooltiphelp);
            print '</td>';
            print '<td>';

            if ($val['type'] == 'textarea') {
                print dol_nl2br(getDolGlobalString($constname));
            } elseif ($val['type'] == 'html') {
                print getDolGlobalString($constname);
            } elseif ($val['type'] == 'yesno') {
                print ajax_constantonoff($constname);
            } elseif (preg_match('/emailtemplate:/', $val['type'])) {
                include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
                $formmail = new FormMail($db);

                $tmp = explode(':', $val['type']);

                $template = $formmail->getEMailTemplate($db, $tmp[1], $user, $langs, getDolGlobalString($constname));
                if ($template < 0) {
                    print '<div class="error">'.$langs->trans("ErrorFailedToFindEmailTemplate").'</div>';
                } elseif (empty($template)) {
                    print '<span class="opacitymedium">'.$langs->trans("None").'</span>';
                } else {
                    print $template->label;
                }
            } elseif (preg_match('/category:/', $val['type'])) {
                $c = new Categorie($db);
                $result = $c->fetch(getDolGlobalString($constname));
                if ($result < 0) {
                    print '<div class="error">'.$langs->trans("ErrorFailedToFindCategory").'</div>';
                } elseif (empty($c->id)) {
                    print '<span class="opacitymedium">'.$langs->trans("None").'</span>';
                } else {
                    print $c->getNomUrl(1);
                }
            } elseif ($val['type'] == 'thirdparty_type') {
                if (getDolGlobalString($constname) == 2) {
                    print $langs->trans("Prospect");
                } elseif (getDolGlobalString($constname) == 3) {
                    print $langs->trans("ProspectCustomer");
                } elseif (getDolGlobalString($constname) == 1) {
                    print $langs->trans("Customer");
                } elseif (getDolGlobalString($constname) == 0) {
                    print $langs->trans("NorProspectNorCustomer");
                }
            } elseif ($val['type'] == 'product') {
                $product = new Product($db);
                $resprod = $product->fetch(getDolGlobalString($constname));
                if ($resprod > 0) {
                    print $product->ref;
                } elseif ($resprod < 0) {
                    print '<div class="error">'.$langs->trans("ErrorFailedToFindProduct").'</div>';
                }
            } else {
                print getDolGlobalString($constname);
            }
            print '</td>';
            print '<td class="center">';
            print '<a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'#'.$constname.'">';
            print img_edit();
            print '</a>';
            print '</td>';
            print '</tr>';
        }

        print '</table>';

        print '<div class="tabsAction">';
        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'">'.$langs->trans("Modify").'</a>';
        print '</div>';
    } else {
        print '<br>'.$langs->trans("NothingToSetup");
    }
}

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
?>
