<?php
/* Copyright (C) 2024 ML Conversion Module
 *
 * Simple Manufacturing Orders List - Fallback version
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
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
$langs->loadLangs(array("mlconversion@mlconversion", "products"));

// Security check
if (!isModEnabled('mlconversion')) {
    accessforbidden('Module not enabled');
}
restrictedArea($user, 'mlconversion');

$form = new Form($db);

llxHeader("", "Manufacturing Orders", '');

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

.info-box {
    background: #e3f2fd;
    border: 1px solid #2196f3;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.warning-box {
    background: #fff3e0;
    border: 1px solid #ff9800;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.error-box {
    background: #ffebee;
    border: 1px solid #f44336;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
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
        // Check if MRP module is enabled
        if (!isModEnabled('mrp')) {
            ?>
            <div class="warning-box">
                <h3>⚠️ MRP Module Required</h3>
                <p><strong>The Manufacturing Orders (MRP) module is not enabled.</strong></p>
                <p>To view and manage manufacturing orders, you need to:</p>
                <ol>
                    <li>Go to <strong>Setup → Modules/Applications</strong></li>
                    <li>Search for <strong>"MRP"</strong> or <strong>"Manufacturing"</strong></li>
                    <li>Click the <strong>green button</strong> to enable the module</li>
                    <li>Refresh this page</li>
                </ol>
                <p><a href="<?php echo DOL_URL_ROOT; ?>/admin/modules.php" class="btn-primary">Go to Modules Setup</a></p>
            </div>
            <?php
        } else {
            // MRP module is enabled, try to show orders
            try {
                $sql = "SELECT mo.rowid, mo.ref, mo.label, mo.date_creation, mo.status";
                $sql .= " FROM ".MAIN_DB_PREFIX."mrp_mo as mo";
                $sql .= " WHERE mo.entity IN (".getEntity('mrp').")";
                $sql .= " ORDER BY mo.date_creation DESC";
                $sql .= " LIMIT 20";

                $resql = $db->query($sql);
                if ($resql) {
                    $num = $db->num_rows($resql);
                    
                    if ($num > 0) {
                        ?>
                        <div class="info-box">
                            <h3>✅ Found <?php echo $num; ?> Manufacturing Orders</h3>
                        </div>
                        
                        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="padding: 15px; text-align: left; border-bottom: 2px solid #e9ecef;">Reference</th>
                                    <th style="padding: 15px; text-align: left; border-bottom: 2px solid #e9ecef;">Label</th>
                                    <th style="padding: 15px; text-align: left; border-bottom: 2px solid #e9ecef;">Created</th>
                                    <th style="padding: 15px; text-align: left; border-bottom: 2px solid #e9ecef;">Status</th>
                                    <th style="padding: 15px; text-align: left; border-bottom: 2px solid #e9ecef;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 0;
                                while ($i < $num) {
                                    $obj = $db->fetch_object($resql);
                                    
                                    $statusText = 'Unknown';
                                    $statusColor = '#6c757d';
                                    switch ($obj->status) {
                                        case 0: $statusText = 'Draft'; $statusColor = '#6c757d'; break;
                                        case 1: $statusText = 'Validated'; $statusColor = '#0066cc'; break;
                                        case 2: $statusText = 'In Progress'; $statusColor = '#856404'; break;
                                        case 3: $statusText = 'Produced'; $statusColor = '#155724'; break;
                                        case 9: $statusText = 'Canceled'; $statusColor = '#721c24'; break;
                                    }
                                    ?>
                                    <tr style="border-bottom: 1px solid #f1f3f4;">
                                        <td style="padding: 15px;">
                                            <strong><?php echo $obj->ref; ?></strong>
                                        </td>
                                        <td style="padding: 15px;">
                                            <?php echo dol_escape_htmltag($obj->label); ?>
                                        </td>
                                        <td style="padding: 15px;">
                                            <?php echo dol_print_date($db->jdate($obj->date_creation), 'day'); ?>
                                        </td>
                                        <td style="padding: 15px;">
                                            <span style="background: <?php echo $statusColor; ?>; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 15px;">
                                            <a href="<?php echo DOL_URL_ROOT; ?>/mrp/mo_card.php?id=<?php echo $obj->rowid; ?>" 
                                               style="color: #2c3e50; text-decoration: none; margin-right: 10px;" 
                                               title="View Details">
                                                👁️ View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php
                                    $i++;
                                }
                                ?>
                            </tbody>
                        </table>
                        <?php
                    } else {
                        ?>
                        <div class="info-box">
                            <h3>📋 No Manufacturing Orders Found</h3>
                            <p>You haven't created any manufacturing orders yet.</p>
                            <p><a href="<?php echo dol_buildpath('/mlconversion/manufacturing.php', 1); ?>" class="btn-primary">Create Your First Order</a></p>
                        </div>
                        <?php
                    }
                    
                    $db->free($resql);
                } else {
                    ?>
                    <div class="error-box">
                        <h3>❌ Database Error</h3>
                        <p>Could not retrieve manufacturing orders: <?php echo $db->lasterror(); ?></p>
                    </div>
                    <?php
                }
            } catch (Exception $e) {
                ?>
                <div class="error-box">
                    <h3>❌ System Error</h3>
                    <p>An error occurred: <?php echo $e->getMessage(); ?></p>
                </div>
                <?php
            }
        }
        ?>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef;">
            <h3>🔧 Troubleshooting</h3>
            <p>If you're experiencing issues:</p>
            <ul>
                <li><a href="debug_manufacturing_list.php">🔍 Run Diagnostic Tool</a></li>
                <li><a href="<?php echo DOL_URL_ROOT; ?>/admin/modules.php">⚙️ Check Module Settings</a></li>
                <li><a href="<?php echo dol_buildpath('/mlconversion/index.php', 1); ?>">🏠 Return to Parfumery Lab Home</a></li>
            </ul>
        </div>
    </div>
</div>

<?php
// End of page
llxFooter();
$db->close();
?>
