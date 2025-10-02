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
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \defgroup   mlconversion     Module MLConversion
 * \brief      MLConversion module descriptor.
 *
 * \file       htdocs/custom/mlconversion/core/modules/modMLConversion.class.php
 * \ingroup    mlconversion
 * \brief      Description and activation file for module MLConversion
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module MLConversion
 */
class modMLConversion extends DolibarrModules
{
    /**
     * Constructor. Define names, constants, directories, boxes, permissions
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;

        // Id for module (must be unique).
        // Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
        $this->numero = 900001;
        // Key text used to identify module (for permissions, menus, etc...)
        $this->rights_class = 'mlconversion';

        // Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (interface modules),'other','...'
        // It is used to group modules by family in module setup page
        $this->family = "products";
        // Module position in the family on 2 digits ('01', '10', '20', ...)
        $this->module_position = '90';

        // Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
        //$this->familyinfo = array('myownfamily' => array('position' => '01', 'label' => $langs->trans("MyOwnFamily")));
        // Module label (no space allowed), used if translation string 'ModuleMLConversionName' not found (MLConversion is name of module).
        $this->name = 'RezkParfumeryLab';

        // Module description, used if translation string 'ModuleMLConversionDesc' not found (MLConversion is name of module).
        $this->description = "Parfumery Lab - Complete perfume manufacturing and stock management system";
        // Used only if file README.md and README-LL.md not found.
        $this->descriptionlong = "Parfumery Lab provides complete perfume manufacturing management with ML conversions, oil selection, empty bottle tracking, and multi-size production orders.";

        // Author
        $this->editor_name = 'Parfumery Lab Team';
        $this->editor_url = '';

        // Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'
        $this->version = '1.0.0';
        // Url to the file with your last numberversion of this module
        //$this->url_last_version = 'http://www.example.com/versionmodule.txt';

        // Key used in llx_const table to save module status enabled/disabled (where MLCONVERSION is value of property name of module in uppercase)
        $this->const_name = 'MAIN_MODULE_MLCONVERSION';

        // Name of image file used for this module.
        // If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
        // If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
        // To use a supported fa-xxx css style of font awesome, use this->picto='xxx'
        $this->picto = 'fa-flask';

        // Define some features supported by module (triggers, login, substitutions, menus, css, etc...)
        $this->module_parts = array(
            // Set this to 1 if module has its own trigger directory (core/triggers)
            'triggers' => 0,
            // Set this to 1 if module has its own login method file (core/login)
            'login' => 0,
            // Set this to 1 if module has its own substitution function file (core/substitutions)
            'substitutions' => 0,
            // Set this to 1 if module has its own menus handler directory (core/menus)
            'menus' => 0,
            // Set this to 1 if module overwrite template dir (core/tpl)
            'tpl' => 0,
            // Set this to 1 if module has its own barcode directory (core/modules/barcode)
            'barcode' => 0,
            // Set this to 1 if module has its own models directory (core/modules/xxx)
            'models' => 0,
            // Set this to 1 if module has its own printing directory (core/modules/printing)
            'printing' => 0,
            // Set this to 1 if module has its own theme directory (theme)
            'theme' => 0,
            // Set this to relative path of css file if module has its own css file
            'css' => array(
                '/mlconversion/css/mlconversion.css.php',
            ),
            // Set this to relative path of js file if module must load a js on all pages
            'js' => array(
                '/mlconversion/js/mlconversion.js.php',
            ),
            // Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context to 'all'
            'hooks' => array(
                'productcard',
                'productlist',
                'stockmovementcard',
                'stockmovementlist'
            ),
            // Set this to 1 if features of module are opened to external users
            'moduleforexternal' => 0,
        );

        // Data directories to create when module is enabled.
        // Example: this->dirs = array("/mlconversion/temp","/mlconversion/subdir");
        $this->dirs = array("/mlconversion/temp");

        // Config pages. Put here list of php page, stored into mlconversion/admin directory, to use to setup module.
        $this->config_page_url = array("setup.php@mlconversion");

        // Dependencies
        // A condition to hide module
        $this->hidden = false;
        // List of module class names as string that must be enabled if this module is enabled. Example: array('always1'=>'modModuleToEnable1','always2'=>'modModuleToEnable2', 'FR'=>'modModuleToEnableFR'...)
        $this->depends = array('modProduct');
        $this->requiredby = array(); // List of module class names as string to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
        $this->conflictwith = array(); // List of module class names as string this module is in conflict with. Example: array('modModuleToDisable1', ...)

        // The language file dedicated to your module
        $this->langfiles = array("mlconversion@mlconversion");

        // Prerequisites
        $this->phpmin = array(7, 0); // Minimum version of PHP required by module
        $this->need_dolibarr_version = array(11, 0); // Minimum version of Dolibarr required by module

        // Messages at activation
        $this->warnings_activation = array(); // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
        $this->warnings_activation_ext = array(); // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
        //$this->automatic_activation = array('FR'=>'MLConversionWasAutomaticallyActivatedBecauseOfYourCountryChoice');
        //$this->always_enabled = true;								// If true, can't be disabled

        // Constants
        // List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
        // Example: $this->const=array(1 => array('MLCONVERSION_MYNEWCONST1', 'chaine', 'myvalue', 'This is a constant to add', 1),
        //                             2 => array('MLCONVERSION_MYNEWCONST2', 'chaine', 'myvalue', 'This is another constant to add', 0, 'current', 1)
        // );
        $this->const = array(
            1 => array('MLCONVERSION_DEFAULT_FORMULA_OUTPUT', 'chaine', '1050', 'Default formula output in ML (oil + alcohol)', 1),
            2 => array('MLCONVERSION_DEFAULT_OIL_PER_BATCH', 'chaine', '50', 'Default oil quantity per batch in ML', 1),
            3 => array('MLCONVERSION_DEFAULT_ALCOHOL_PER_BATCH', 'chaine', '1000', 'Default alcohol quantity per batch in ML', 1),
        );

        // Some keys to add into the overwriting translation tables
        /*$this->overwrite_translation = array(
            'en_US:ParentCompany'=>'Parent company or reseller',
            'fr_FR:ParentCompany'=>'Maison mère ou revendeur'
        )*/

        if (!isset($conf->mlconversion) || !isset($conf->mlconversion->enabled)) {
            $conf->mlconversion = new stdClass();
            $conf->mlconversion->enabled = 0;
        }

        // Array to add new pages in new tabs
        $this->tabs = array();

        // Dictionaries
        $this->dictionaries = array();

        // Boxes/Widgets
        // Add here list of php file(s) stored in mlconversion/core/boxes that contains a class to show a widget.
        $this->boxes = array(
            //  0 => array(
            //      'file' => 'mlconversionwidget1.php@mlconversion',
            //      'note' => 'Widget provided by MLConversion',
            //      'enabledbydefaulton' => 'Home',
            //  ),
        );

        // Cronjobs (List of cron jobs entries to add when module is enabled)
        $this->cronjobs = array();

        // Permissions provided by this module
        $this->rights = array();
        $r = 0;
        // Add here entries to declare new permissions
        // Example:
        $this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1); // Permission id (must not be already used)
        $this->rights[$r][1] = 'Read MLConversion'; // Permission label
        $this->rights[$r][4] = 'read'; // In php code, permission will be checked by test if ($user->rights->mlconversion->level1->read)
        $r++;
        $this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1); // Permission id (must not be already used)
        $this->rights[$r][1] = 'Create/Update MLConversion'; // Permission label
        $this->rights[$r][4] = 'write'; // In php code, permission will be checked by test if ($user->rights->mlconversion->level1->write)
        $r++;
        $this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1); // Permission id (must not be already used)
        $this->rights[$r][1] = 'Delete MLConversion'; // Permission label
        $this->rights[$r][4] = 'delete'; // In php code, permission will be checked by test if ($user->rights->mlconversion->level1->delete)
        $r++;

        // Main menu entries to add
        $this->menu = array();
        $r = 0;

        // Add here entries to declare new menus
        $this->menu[$r++] = array(
            'fk_menu'=>'', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
            'type'=>'top', // This is a Top menu entry
            'titre'=>'Rezk Parfumery Lab',
            'prefix' => img_picto('', $this->picto, 'class="paddingright pictofixedwidth valignmiddle"'),
            'mainmenu'=>'mlconversion',
            'leftmenu'=>'',
            'url'=>'/mlconversion/index.php',
            'langs'=>'mlconversion@mlconversion', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
            'position'=>1000 + $r,
            'enabled'=>'$conf->mlconversion->enabled', // Define condition to show or hide menu entry. Use 'isModEnabled("mlconversion")' if entry must be visible if module is enabled.
            'perms'=>'$user->hasRight("mlconversion", "read")', // Use 'perms'=>'$user->rights->mlconversion->level1->read' if you want your menu with a permission rules
            'target'=>'',
            'user'=>2, // 0=Menu for internal users, 1=external users, 2=both
        );

        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=mlconversion', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
            'type'=>'left', // This is a Left menu entry
            'titre'=>'Create Manufacturing Order',
            'mainmenu'=>'mlconversion',
            'leftmenu'=>'mlconversion_manufacturing',
            'url'=>'/mlconversion/manufacturing.php',
            'langs'=>'mlconversion@mlconversion', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
            'position'=>1000 + $r,
            'enabled'=>'isModEnabled("mlconversion")', // Define condition to show or hide menu entry. Use 'isModEnabled("mlconversion")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
            'perms'=>'$user->hasRight("mlconversion", "read")', // Use 'perms'=>'$user->rights->mlconversion->level1->read' if you want your menu with a permission rules
            'target'=>'',
            'user'=>2, // 0=Menu for internal users, 1=external users, 2=both
        );

        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=mlconversion', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
            'type'=>'left', // This is a Left menu entry
            'titre'=>'Manufacturing Orders List',
            'mainmenu'=>'mlconversion',
            'leftmenu'=>'mlconversion_orders_list',
            'url'=>'/mlconversion/manufacturing_orders_list.php',
            'langs'=>'mlconversion@mlconversion', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
            'position'=>1000 + $r,
            'enabled'=>'isModEnabled("mlconversion")', // Define condition to show or hide menu entry. Use 'isModEnabled("mlconversion")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
            'perms'=>'$user->hasRight("mlconversion", "read")', // Use 'perms'=>'$user->rights->mlconversion->level1->read' if you want your menu with a permission rules
            'target'=>'',
            'user'=>2, // 0=Menu for internal users, 1=external users, 2=both
        );

        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=mlconversion', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
            'type'=>'left', // This is a Left menu entry
            'titre'=>'Interactive Demo',
            'mainmenu'=>'mlconversion',
            'leftmenu'=>'mlconversion_demo',
            'url'=>'/mlconversion/interactive_demo.php',
            'langs'=>'mlconversion@mlconversion', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
            'position'=>1000 + $r,
            'enabled'=>'isModEnabled("mlconversion")', // Define condition to show or hide menu entry. Use 'isModEnabled("mlconversion")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
            'perms'=>'$user->hasRight("mlconversion", "read")', // Use 'perms'=>'$user->rights->mlconversion->level1->read' if you want your menu with a permission rules
            'target'=>'',
            'user'=>2, // 0=Menu for internal users, 1=external users, 2=both
        );

        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=mlconversion', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
            'type'=>'left', // This is a Left menu entry
            'titre'=>'ML Calculator',
            'mainmenu'=>'mlconversion',
            'leftmenu'=>'mlconversion_calculator',
            'url'=>'/mlconversion/demo.php',
            'langs'=>'mlconversion@mlconversion', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
            'position'=>1000 + $r,
            'enabled'=>'isModEnabled("mlconversion")', // Define condition to show or hide menu entry. Use 'isModEnabled("mlconversion")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
            'perms'=>'$user->hasRight("mlconversion", "read")', // Use 'perms'=>'$user->rights->mlconversion->level1->read' if you want your menu with a permission rules
            'target'=>'',
            'user'=>2, // 0=Menu for internal users, 1=external users, 2=both
        );
    }

    /**
     * Function called when module is enabled.
     * The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
     * It also creates data directories
     *
     * @param string $options Options when enabling module ('', 'noboxes')
     * @return int             1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        global $conf, $langs;

        //$result = $this->_load_tables('/install/mysql/', 'mlconversion');
        $result = $this->_load_tables('/mlconversion/sql/');
        if ($result < 0) {
            return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
        }

        // Create extrafields during init
        //include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
        //$extrafields = new ExtraFields($this->db);
        //$result1=$extrafields->addExtraField('mlconversion_myattr1', "New Attr 1 label", 'boolean', 1,  3, 'thirdparty',   0, 0, '', '', 1, '', 0, 0, '', '', 'mlconversion@mlconversion', 'isModEnabled("mlconversion")');
        //$result2=$extrafields->addExtraField('mlconversion_myattr2', "New Attr 2 label", 'varchar', 1, 10, 'project',      0, 0, '', '', 1, '', 0, 0, '', '', 'mlconversion@mlconversion', 'isModEnabled("mlconversion")');
        //$result3=$extrafields->addExtraField('mlconversion_myattr3', "New Attr 3 label", 'varchar', 1, 10, 'bank_account', 0, 0, '', '', 1, '', 0, 0, '', '', 'mlconversion@mlconversion', 'isModEnabled("mlconversion")');
        //$result4=$extrafields->addExtraField('mlconversion_myattr4', "New Attr 4 label", 'select',  1,  3, 'thirdparty',   0, 1, '', array('options'=>array('code1'=>'Val1','code2'=>'Val2','code3'=>'Val3')), 1,'', 0, 0, '', '', 'mlconversion@mlconversion', 'isModEnabled("mlconversion")');
        //$result5=$extrafields->addExtraField('mlconversion_myattr5', "New Attr 5 label", 'text',    1, 10, 'user',         0, 0, '', '', 1, '', 0, 0, '', '', 'mlconversion@mlconversion', 'isModEnabled("mlconversion")');

        // Permissions
        $this->remove($options);

        $sql = array();

        // Document templates
        $moduledir = dol_buildpath('/mlconversion', 0);
        $myTmpObjects = array();
        $myTmpObjects['MLConversion'] = array('includerefgeneration'=>0, 'includedocgeneration'=>0);

        foreach ($myTmpObjects as $myTmpObjectKey => $myTmpObjectArray) {
            if ($myTmpObjectKey == 'MLConversion') {
                continue;
            }
            if ($myTmpObjectArray['includerefgeneration']) {
                $src = DOL_DOCUMENT_ROOT.'/install/doctemplates/mlconversion/template_mlconversions.odt';
                $dirodt = DOL_DATA_ROOT.'/doctemplates/mlconversion';
                $dest = $dirodt.'/template_mlconversions.odt';
                if (file_exists($src) && !file_exists($dest)) {
                    require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
                    dol_mkdir($dirodt);
                    $result = dol_copy($src, $dest, 0, 0);
                    if ($result < 0) {
                        $langs->load("errors");
                        $this->error = $langs->trans('ErrorFailToCopyFile', $src, $dest);
                        return 0;
                    }
                }

                $sql = array_merge($sql, array(
                    "DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom = 'standard_".strtolower($myTmpObjectKey)."' AND type = '".strtolower($myTmpObjectKey)."' AND entity = ".$conf->entity,
                    "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity) VALUES('standard_".strtolower($myTmpObjectKey)."','".strtolower($myTmpObjectKey)."',".$conf->entity.")",
                    "DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom = 'generic_".strtolower($myTmpObjectKey)."_odt' AND type = '".strtolower($myTmpObjectKey)."' AND entity = ".$conf->entity,
                    "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity) VALUES('generic_".strtolower($myTmpObjectKey)."_odt', '".strtolower($myTmpObjectKey)."', ".$conf->entity.")"
                ));
            }
        }

        return $this->_init($sql, $options);
    }

    /**
     * Function called when module is disabled.
     * Remove from database constants, boxes and permissions from Dolibarr database.
     * Data directories are not deleted
     *
     * @param string $options Options when enabling module ('', 'noboxes')
     * @return int                1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
?>
