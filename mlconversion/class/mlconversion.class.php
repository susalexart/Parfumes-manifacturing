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
 * \file        class/mlconversion.class.php
 * \ingroup     mlconversion
 * \brief       This file is a CRUD class file for MLConversion (Create/Read/Update/Delete)
 */

// Put here all includes required by your class file
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

/**
 * Class for MLConversion
 */
class MLConversion extends CommonObject
{
    /**
     * @var string ID of module.
     */
    public $module = 'mlconversion';

    /**
     * @var string ID to identify managed object.
     */
    public $element = 'mlconversion';

    /**
     * @var string Name of table without prefix where object is stored. This is also the key used for extrafields management.
     */
    public $table_element = 'mlconversion';

    /**
     * @var int  Does this object support multicompany module ?
     * 0=No test on entity, 1=Test with field entity, 'field@table'=Test with link by field@table
     */
    public $ismultientitymanaged = 0;

    /**
     * @var int  Does object support extrafields ? 0=No, 1=Yes
     */
    public $isextrafieldmanaged = 1;

    /**
     * @var string String with name of icon for mlconversion. Must be the part after the 'object_' into object_mlconversion.png
     */
    public $picto = 'fa-flask';

    const STATUS_DRAFT = 0;
    const STATUS_VALIDATED = 1;
    const STATUS_CANCELED = 9;

    /**
     *  'type' field format ('integer', 'integer:ObjectClass:PathToClass[:AddCreateButtonOrNot[:Filter[:Sortfield]]]', 'sellist:TableName:LabelFieldName[:KeyFieldName[:KeyFieldParent[:Filter[:Sortfield]]]]', 'varchar(x)', 'double(24,8)', 'real', 'price', 'text', 'text:none', 'html', 'date', 'datetime', 'timestamp', 'duration', 'mail', 'phone', 'url', 'password')
     *         Note: Filter can be a string like "(t.ref:like:'SO-%') or (t.date_creation:<:'20160101') or (t.nature:is:NULL)"
     *  'label' the translation key.
     *  'picto' is code of a picto to show before value in forms
     *  'enabled' is a condition when the field must be managed (Example: 1 or 'isModEnabled("accounting")' or 'getDolGlobalString("MYMODULE_OPTION")==2')
     *  'position' is the sort order of field.
     *  'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty ('' or 0).
     *  'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list but readonly on create/update/view form, 5=Visible on list but readonly on update/view form only (not create). Using a negative value means field is not shown by default on list but can be selected for viewing)
     *  'noteditable' says if field is not editable (1 or 0)
     *  'default' is a default value for creation (can still be overwrote by the Setup of Default Values if field is editable in creation form). Note: If default is set to '(PROV)' and field is 'ref', the default value will be set to '(PROVid)' where id is rowid when a new record is created.
     *  'index' if we want an index in database.
     *  'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommanded to name the field fk_...).
     *  'searchall' is 1 if we want to search in this field when making a search from the quick search button.
     *  'isameasure' must be set to 1 or 2 if field can be used for measure. Field type must be summable like integer or double(24,8). Use 1 in most cases, or 2 if you don't want to see the column total into list (for example for percentage)
     *  'css' and 'cssview' and 'csslist' is the CSS style to use on field. 'css' is used in creation and update. 'cssview' is used in view mode. 'csslist' is used in lists. If no css defined, the "width" field is used to define a CSS width for field.
     *  'help' is a 'TranslationString' to use to show a tooltip on field. You can also use 'TranslationString:keyfortooltiponlick' for a tooltip on click.
     *  'showoncombobox' if value of the field must be visible into the label of the combobox that list record
     *  'disabled' is 1 if we want to have the field locked by a 'disabled' attribute. In most cases, this is never set into the definition of $fields into class, but is set dynamically by some part of code.
     *  'arrayofkeyval' to set a list of values if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel"). Note that type can be 'integer' or 'varchar'
     *  'autofocusoncreate' to have field having the focus on a create form. Only 1 field should have this property set to 1.
     *  'comment' is not used. You can store here any text of your choice. It is not used by application.
     *	'validate' is 1 if need to validate with $this->validateField()
     *  'copytoclipboard' is 1 or 2 to allow to add a picto to copy value into clipboard (1=picto after label, 2=picto after value)
     *
     *  Note: To have value dynamic, you can set value to 0 in definition and edit the value on the fly into the constructor.
     */

    // BEGIN MODULEBUILDER PROPERTIES
    /**
     * @var array  Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
     */
    public $fields=array(
        'rowid' => array('type'=>'integer', 'label'=>'TechnicalID', 'enabled'=>'1', 'position'=>1, 'notnull'=>1, 'visible'=>0, 'noteditable'=>'1', 'index'=>1, 'comment'=>"Id"),
        'ref' => array('type'=>'varchar(128)', 'label'=>'Ref', 'enabled'=>'1', 'position'=>20, 'notnull'=>1, 'visible'=>1, 'noteditable'=>'1', 'default'=>'(PROV)', 'index'=>1, 'searchall'=>1, 'showoncombobox'=>'1', 'validate'=>'1', 'comment'=>"Reference of object"),
        'label' => array('type'=>'varchar(255)', 'label'=>'Label', 'enabled'=>'1', 'position'=>30, 'notnull'=>0, 'visible'=>1, 'searchall'=>1, 'css'=>'minwidth300', 'help'=>"Help text", 'showoncombobox'=>'2', 'validate'=>'1',),
        'description' => array('type'=>'text', 'label'=>'Description', 'enabled'=>'1', 'position'=>60, 'notnull'=>0, 'visible'=>3, 'validate'=>'1',),
        'note_public' => array('type'=>'html', 'label'=>'NotePublic', 'enabled'=>'1', 'position'=>61, 'notnull'=>0, 'visible'=>0, 'cssview'=>'wordbreak', 'validate'=>'1',),
        'note_private' => array('type'=>'html', 'label'=>'NotePrivate', 'enabled'=>'1', 'position'=>62, 'notnull'=>0, 'visible'=>0, 'cssview'=>'wordbreak', 'validate'=>'1',),
        'date_creation' => array('type'=>'datetime', 'label'=>'DateCreation', 'enabled'=>'1', 'position'=>500, 'notnull'=>1, 'visible'=>-2,),
        'tms' => array('type'=>'timestamp', 'label'=>'DateModification', 'enabled'=>'1', 'position'=>501, 'notnull'=>0, 'visible'=>-2,),
        'fk_user_creat' => array('type'=>'integer:User:user/class/user.class.php', 'label'=>'UserAuthor', 'enabled'=>'1', 'position'=>510, 'notnull'=>1, 'visible'=>-2, 'foreignkey'=>'user.rowid',),
        'fk_user_modif' => array('type'=>'integer:User:user/class/user.class.php', 'label'=>'UserModif', 'enabled'=>'1', 'position'=>511, 'notnull'=>-1, 'visible'=>-2,),
        'last_main_doc' => array('type'=>'varchar(255)', 'label'=>'LastMainDoc', 'enabled'=>'1', 'position'=>600, 'notnull'=>0, 'visible'=>0,),
        'import_key' => array('type'=>'varchar(14)', 'label'=>'ImportId', 'enabled'=>'1', 'position'=>1000, 'notnull'=>-1, 'visible'=>-2,),
        'model_pdf' => array('type'=>'varchar(255)', 'label'=>'Model pdf', 'enabled'=>'1', 'position'=>1010, 'notnull'=>-1, 'visible'=>0,),
        'status' => array('type'=>'smallint', 'label'=>'Status', 'enabled'=>'1', 'position'=>2000, 'notnull'=>1, 'visible'=>1, 'index'=>1, 'default'=>'0', 'arrayofkeyval'=>array('0'=>'Draft', '1'=>'Validated', '9'=>'Canceled'), 'validate'=>'1',),
    );
    public $rowid;
    public $ref;
    public $label;
    public $description;
    public $note_public;
    public $note_private;
    public $date_creation;
    public $tms;
    public $fk_user_creat;
    public $fk_user_modif;
    public $last_main_doc;
    public $import_key;
    public $model_pdf;
    public $status;
    // END MODULEBUILDER PROPERTIES

    /**
     * Constructor
     *
     * @param DoliDb $db Database handler
     */
    public function __construct(DoliDB $db)
    {
        global $conf, $langs;

        $this->db = $db;

        if (empty($conf->global->MAIN_SHOW_TECHNICAL_ID) && isset($this->fields['rowid'])) {
            $this->fields['rowid']['visible'] = 0;
        }
        if (!isModEnabled('multicompany') && isset($this->fields['entity'])) {
            $this->fields['entity']['enabled'] = 0;
        }

        // Example to show how to set values of fields definition dynamically
        /*if ($user->rights->mlconversion->read) {
            $this->fields['myfield']['visible'] = 1;
            $this->fields['myfield']['noteditable'] = 0;
        }*/

        // Unset fields that are disabled
        foreach ($this->fields as $key => $val) {
            if (isset($val['enabled']) && empty($val['enabled'])) {
                unset($this->fields[$key]);
            }
        }

        // Translate some data of arrayofkeyval
        if (is_object($langs)) {
            foreach ($this->fields as $key => $val) {
                if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
                    foreach ($val['arrayofkeyval'] as $key2 => $val2) {
                        $this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
                    }
                }
            }
        }
    }

    /**
     * Calculate how many bottles can be produced from available ML
     * @param float $available_ml Available milliliters
     * @param float $ml_per_bottle Milliliters per bottle
     * @return array [bottles, remaining_ml]
     */
    public function calculateBottlesFromML($available_ml, $ml_per_bottle)
    {
        if ($ml_per_bottle <= 0) {
            return ['bottles' => 0, 'remaining_ml' => $available_ml];
        }
        
        $bottles = floor($available_ml / $ml_per_bottle);
        $remaining_ml = $available_ml % $ml_per_bottle;
        
        return [
            'bottles' => $bottles,
            'remaining_ml' => $remaining_ml,
            'used_ml' => $bottles * $ml_per_bottle
        ];
    }
    
    /**
     * Calculate total ML needed for production order
     * @param array $bottle_requirements [size => quantity]
     * @return array Production requirements
     */
    public function calculateProductionRequirements($bottle_requirements)
    {
        $total_ml_needed = 0;
        $production_plan = [];
        
        foreach ($bottle_requirements as $bottle_ref => $quantity) {
            $ml_per_bottle = $this->getBottleCapacity($bottle_ref);
            $ml_needed = $quantity * $ml_per_bottle;
            $total_ml_needed += $ml_needed;
            
            $production_plan[$bottle_ref] = [
                'quantity' => $quantity,
                'ml_per_bottle' => $ml_per_bottle,
                'total_ml' => $ml_needed
            ];
        }
        
        return [
            'production_plan' => $production_plan,
            'total_ml_needed' => $total_ml_needed
        ];
    }
    
    /**
     * Get bottle capacity from product
     * @param string $product_ref Product reference
     * @return float ML capacity per bottle
     */
    public function getBottleCapacity($product_ref)
    {
        $sql = "SELECT volume_capacity FROM ".MAIN_DB_PREFIX."product WHERE ref = '".$this->db->escape($product_ref)."'";
        $result = $this->db->query($sql);
        
        if ($result && $this->db->num_rows($result) > 0) {
            $obj = $this->db->fetch_object($result);
            return floatval($obj->volume_capacity);
        }
        
        // Default capacity based on reference pattern
        if (strpos($product_ref, '20ML') !== false) return 20.0;
        if (strpos($product_ref, '30ML') !== false) return 30.0;
        if (strpos($product_ref, '50ML') !== false) return 50.0;
        if (strpos($product_ref, '100ML') !== false) return 100.0;
        if (strpos($product_ref, '125ML') !== false) return 125.0;
        
        return 50.0; // Default
    }
    
    /**
     * Get current stock in ML for a product
     * @param int $product_id Product ID
     * @return float Stock in ML
     */
    public function getStockML($product_id)
    {
        $sql = "SELECT ps.reel, p.volume_per_unit, p.track_in_ml 
                FROM ".MAIN_DB_PREFIX."product_stock ps
                JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = ps.fk_product
                WHERE ps.fk_product = ".intval($product_id);
        
        $result = $this->db->query($sql);
        
        if ($result && $this->db->num_rows($result) > 0) {
            $obj = $this->db->fetch_object($result);
            
            if ($obj->track_in_ml) {
                // Product tracked in ML directly
                return floatval($obj->reel);
            } else {
                // Convert bottles to ML
                $volume_per_unit = floatval($obj->volume_per_unit);
                return floatval($obj->reel) * $volume_per_unit;
            }
        }
        
        return 0.0;
    }

    /**
     * Get all raw materials (oils) with stock information
     * @return array Raw materials data
     */
    public function getRawMaterials()
    {
        $sql = "SELECT p.rowid, p.ref, p.label, p.description, 
                       COALESCE(ps.reel, 0) as stock_ml,
                       p.price_base_type, p.price
                FROM ".MAIN_DB_PREFIX."product p
                LEFT JOIN ".MAIN_DB_PREFIX."product_stock ps ON p.rowid = ps.fk_product
                WHERE p.track_in_ml = 1 
                AND (p.ref LIKE 'OIL_%' OR p.ref LIKE 'oil_%' OR p.label LIKE '%oil%' OR p.description LIKE '%oil%')
                AND p.tosell = 0 AND p.tobuy = 1
                AND p.entity IN (".getEntity('product').")
                ORDER BY p.ref";
        
        $result = $this->db->query($sql);
        
        if (!$result) {
            dol_syslog("MLConversion::getRawMaterials Error: " . $this->db->lasterror(), LOG_ERR);
            return [];
        }
        
        $raw_materials = [];
        
        while ($obj = $this->db->fetch_object($result)) {
            $stock_ml = floatval($obj->stock_ml);
            $possible_batches = floor($stock_ml / 50); // 50ml oil per batch
            $max_production_ml = $possible_batches * 1050; // 1050ml per batch
            
            // Determine stock status
            $status = 'good';
            $status_text = 'Good Stock';
            if ($stock_ml < 50) {
                $status = 'critical';
                $status_text = 'Low Stock';
            } elseif ($stock_ml < 100) {
                $status = 'warning';
                $status_text = 'Reorder Soon';
            }
            
            // Calculate cost per ml if price is available
            $cost_per_ml = 0;
            if ($obj->price > 0) {
                $cost_per_ml = floatval($obj->price);
            }
            
            $raw_materials[] = [
                'id' => intval($obj->rowid),
                'ref' => $obj->ref,
                'label' => $obj->label,
                'description' => $obj->description,
                'stock_ml' => $stock_ml,
                'possible_batches' => $possible_batches,
                'max_production_ml' => $max_production_ml,
                'status' => $status,
                'status_text' => $status_text,
                'cost_per_ml' => $cost_per_ml,
                'can_produce' => $possible_batches > 0
            ];
        }
        
        return $raw_materials;
    }

    /**
     * Check if production order can be fulfilled
     * @param array $bottle_requirements Bottle requirements
     * @param int $oil_id Oil product ID
     * @param float $formula_output_ml ML output per batch
     * @return array Feasibility analysis
     */
    public function checkProductionFeasibility($bottle_requirements, $oil_id, $formula_output_ml = 1050)
    {
        // Calculate total ML needed
        $production_req = $this->calculateProductionRequirements($bottle_requirements);
        $total_ml_needed = $production_req['total_ml_needed'];
        
        // Calculate batches needed
        $batches_needed = ceil($total_ml_needed / $formula_output_ml);
        $total_ml_produced = $batches_needed * $formula_output_ml;
        $overflow_ml = $total_ml_produced - $total_ml_needed;
        
        // Check oil availability
        $oil_available = $this->getStockML($oil_id);
        $oil_needed = $batches_needed * 50; // 50ml oil per batch
        
        // Check alcohol availability (assuming we have alcohol product)
        $alcohol_needed = $batches_needed * 1000; // 1000ml alcohol per batch
        
        $can_produce = ($oil_needed <= $oil_available);
        $limiting_materials = [];
        
        if ($oil_needed > $oil_available) {
            $limiting_materials[] = [
                'type' => 'oil',
                'needed' => $oil_needed,
                'available' => $oil_available,
                'shortage' => $oil_needed - $oil_available
            ];
        }
        
        return [
            'can_produce' => $can_produce,
            'total_ml_needed' => $total_ml_needed,
            'batches_needed' => $batches_needed,
            'total_ml_produced' => $total_ml_produced,
            'overflow_ml' => $overflow_ml,
            'oil_needed' => $oil_needed,
            'oil_available' => $oil_available,
            'alcohol_needed' => $alcohol_needed,
            'limiting_materials' => $limiting_materials,
            'production_plan' => $production_req['production_plan']
        ];
    }

    /**
     * Create object into database
     *
     * @param  User $user      User that creates
     * @param  bool $notrigger false=launch triggers after, true=disable triggers
     * @return int             <0 if KO, Id of created object if OK
     */
    public function create(User $user, $notrigger = false)
    {
        $resultcreate = $this->createCommon($user, $notrigger);

        //$resultvalidate = $this->validate($user, $notrigger);

        return $resultcreate;
    }

    /**
     * Load object in memory from the database
     *
     * @param int    $id   Id object
     * @param string $ref  Ref
     * @return int         <0 if KO, 0 if not found, >0 if OK
     */
    public function fetch($id, $ref = null)
    {
        $result = $this->fetchCommon($id, $ref);
        if ($result > 0 && !empty($this->table_element_line)) {
            $this->fetchLines();
        }
        return $result;
    }

    /**
     * Update object into database
     *
     * @param  User $user      User that modifies
     * @param  bool $notrigger false=launch triggers after, true=disable triggers
     * @return int             <0 if KO, >0 if OK
     */
    public function update(User $user, $notrigger = false)
    {
        return $this->updateCommon($user, $notrigger);
    }

    /**
     * Delete object in database
     *
     * @param User $user       User that deletes
     * @param bool $notrigger  false=launch triggers after, true=disable triggers
     * @return int             <0 if KO, >0 if OK
     */
    public function delete(User $user, $notrigger = false)
    {
        return $this->deleteCommon($user, $notrigger);
    }

    /**
     *  Return a link to the object card (with optionaly the picto)
     *
     *  @param  int     $withpicto                  Include picto in link (0=No picto, 1=Include picto into link, 2=Only picto)
     *  @param  string  $option                     On what the link point to ('nolink', ...)
     *  @param  int     $notooltip                  1=Disable tooltip
     *  @param  string  $morecss                    Add more css on link
     *  @param  int     $save_lastsearch_value      -1=Auto, 0=No save of lastsearch_values when clicking, 1=Save lastsearch_values whenclicking
     *  @return	string                              String with URL
     */
    public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
    {
        global $conf, $langs, $hookmanager;

        if (!empty($conf->dol_no_mouse_hover)) {
            $notooltip = 1; // Force disable tooltips
        }

        $result = '';

        $label = img_picto('', $this->picto).(' '.$this->ref);
        if (isset($this->status)) {
            $label .= ' '.$this->getLibStatut(5);
        }
        $label = '<u>'.$langs->trans("MLConversion").'</u>';
        if (isset($this->status)) {
            $label .= '<br><b>'.$langs->trans('Status').':</b> '.$this->getLibStatut(0);
        }
        if (!empty($this->label)) {
            $label .= '<br><b>'.$langs->trans('Label').':</b> '.$this->label;
        }

        $url = dol_buildpath('/mlconversion/mlconversion_card.php', 1).'?id='.$this->id;

        if ($option != 'nolink') {
            // Add param to save lastsearch_values or not
            $add_save_lastsearch_values = ($save_lastsearch_value == 1 ? 1 : 0);
            if ($save_lastsearch_value == -1 && preg_match('/list\.php/', $_SERVER["PHP_SELF"])) {
                $add_save_lastsearch_values = 1;
            }
            if ($add_save_lastsearch_values) {
                $url .= '&save_lastsearch_values=1';
            }
        }

        $linkclose = '';
        if (empty($notooltip)) {
            if (!empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER)) {
                $label = $langs->trans("ShowMLConversion");
                $linkclose .= ' alt="'.$label.'"';
            }
            $linkclose .= ' title="'.dol_escape_htmltag($label, 1).'"';
            $linkclose .= ' class="classfortooltip'.($morecss ? ' '.$morecss : '').'"';
        } else {
            $linkclose = ($morecss ? ' class="'.$morecss.'"' : '');
        }

        if ($option == 'nolink') {
            $linkstart = '<span';
        } else {
            $linkstart = '<a href="'.$url.'"';
        }
        $linkstart .= $linkclose.'>';
        if ($option == 'nolink') {
            $linkend = '</span>';
        } else {
            $linkend = '</a>';
        }

        $result .= $linkstart;

        if (empty($this->showphoto_on_popup)) {
            if ($withpicto) {
                $result .= img_object(($notooltip ? '' : $label), ($this->picto ? $this->picto : 'generic'), ($notooltip ? (($withpicto != 2) ? 'class="paddingright"' : '') : 'class="'.(($withpicto != 2) ? 'paddingright ' : '').'classfortooltip"'), 0, 0, $notooltip ? 0 : 1);
            }
        } else {
            if ($withpicto) {
                require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

                list($class, $module) = explode('@', $this->picto);
                $upload_dir = $conf->$module->multidir_output[$conf->entity]."/$class/".dol_sanitizeFileName($this->ref);
                $filearray = dol_dir_list($upload_dir, "files", 0, '', '(\.meta|_preview.*\.png)$', $conf->global->GED_SORT_FIELD, SORT_ASC, 1);
                if (count($filearray)) {
                    $filepath = $upload_dir.'/'.$filearray[0]['name'];
                    $pospoint = strpos($filearray[0]['name'], '.');

                    $pathtophoto = $class.'/'.$this->ref.'/thumbs/'.substr($filearray[0]['name'], 0, $pospoint).'_mini'.substr($filearray[0]['name'], $pospoint);
                    if (!dol_is_file($conf->$module->multidir_output[$conf->entity]."/".$pathtophoto)) {
                        $pathtophoto = $class.'/'.$this->ref.'/'.substr($filearray[0]['name'], 0, $pospoint).substr($filearray[0]['name'], $pospoint);
                    }

                    $result .= '<div class="floatleft inline-block valignmiddle divphotoref"><div class="photoref"><img class="photo'.$module.'" alt="No photo" border="0" src="'.DOL_URL_ROOT.'/viewimage.php?modulepart='.$module.'&entity='.$conf->entity.'&file='.urlencode($pathtophoto).'"></div></div>';
                } else {
                    $result .= img_object(($notooltip ? '' : $label), ($this->picto ? $this->picto : 'generic'), ($notooltip ? (($withpicto != 2) ? 'class="paddingright"' : '') : 'class="'.(($withpicto != 2) ? 'paddingright ' : '').'classfortooltip"'), 0, 0, $notooltip ? 0 : 1);
                }
            }
        }

        if ($withpicto != 2) {
            $result .= $this->ref;
        }

        $result .= $linkend;
        //if ($withpicto != 2) $result.=(($addlabel && $this->label) ? $sep . dol_trunc($this->label, ($addlabel > 1 ? $addlabel : 0)) : '');

        global $action, $hookmanager;
        $hookmanager->initHooks(array('mlconversiondao'));
        $parameters = array('id'=>$this->id, 'getnomurl'=>$result);
        $reshook = $hookmanager->executeHooks('getNomUrl', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
        if ($reshook > 0) {
            $result = $hookmanager->resPrint;
        } else {
            $result .= $hookmanager->resPrint;
        }

        return $result;
    }

    /**
     *  Return the status
     *
     *  @param  int     $mode          0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
     *  @return	string 			       Label of status
     */
    public function getLibStatut($mode = 0)
    {
        return $this->LibStatut($this->status, $mode);
    }

    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
    /**
     *  Return the status
     *
     *  @param  int     $status        Id status
     *  @param  int     $mode          0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
     *  @return string 			       Label of status
     */
    public function LibStatut($status, $mode = 0)
    {
        // phpcs:enable
        if (empty($this->labelStatus) || empty($this->labelStatusShort)) {
            global $langs;
            //$langs->load("mlconversion@mlconversion");
            $this->labelStatus[self::STATUS_DRAFT] = $langs->transnoentitiesnoconv('Draft');
            $this->labelStatus[self::STATUS_VALIDATED] = $langs->transnoentitiesnoconv('Enabled');
            $this->labelStatus[self::STATUS_CANCELED] = $langs->transnoentitiesnoconv('Disabled');
            $this->labelStatusShort[self::STATUS_DRAFT] = $langs->transnoentitiesnoconv('Draft');
            $this->labelStatusShort[self::STATUS_VALIDATED] = $langs->transnoentitiesnoconv('Enabled');
            $this->labelStatusShort[self::STATUS_CANCELED] = $langs->transnoentitiesnoconv('Disabled');
        }

        $statusType = 'status'.$status;
        //if ($status == self::STATUS_VALIDATED) $statusType = 'status1';
        if ($status == self::STATUS_CANCELED) {
            $statusType = 'status6';
        }

        return dolGetStatus($this->labelStatus[$status], $this->labelStatusShort[$status], '', $statusType, $mode);
    }
}
?>
