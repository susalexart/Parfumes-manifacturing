<?php
/**
 * Debug script for manufacturing orders list
 * This will help identify the cause of the 500 error
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Debug Manufacturing Orders List</h2>";

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
    die("❌ Include of main fails");
}

echo "<h3>✅ Dolibarr Environment Loaded</h3>";

// Check basic requirements
echo "<h3>📋 System Checks:</h3>";

// Check if user is logged in
if (empty($user) || !is_object($user)) {
    echo "<p>❌ User not logged in</p>";
    exit;
} else {
    echo "<p>✅ User logged in: " . $user->login . "</p>";
}

// Check if mlconversion module is enabled
if (!isModEnabled('mlconversion')) {
    echo "<p>❌ ML Conversion module is not enabled</p>";
} else {
    echo "<p>✅ ML Conversion module is enabled</p>";
}

// Check if MRP module is enabled (required for manufacturing orders)
if (!isModEnabled('mrp')) {
    echo "<p>❌ MRP module is not enabled - This is required for manufacturing orders!</p>";
    echo "<p><strong>Solution:</strong> Go to Setup → Modules → Enable 'Manufacturing Orders (MRP)' module</p>";
} else {
    echo "<p>✅ MRP module is enabled</p>";
}

// Check database connection
if (empty($db)) {
    echo "<p>❌ Database connection failed</p>";
    exit;
} else {
    echo "<p>✅ Database connection OK</p>";
}

// Check if manufacturing orders table exists
$sql = "SHOW TABLES LIKE '".MAIN_DB_PREFIX."mrp_mo'";
$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
    echo "<p>✅ Manufacturing orders table exists</p>";
} else {
    echo "<p>❌ Manufacturing orders table (llx_mrp_mo) does not exist</p>";
    echo "<p><strong>Solution:</strong> Enable the MRP module to create the required tables</p>";
}

// Check user permissions
if (!$user->hasRight('mlconversion', 'read')) {
    echo "<p>❌ User does not have mlconversion read permission</p>";
} else {
    echo "<p>✅ User has mlconversion read permission</p>";
}

// Test a simple query
echo "<h3>🔍 Database Test:</h3>";
try {
    $sql = "SELECT COUNT(*) as count FROM ".MAIN_DB_PREFIX."mrp_mo WHERE entity IN (".getEntity('mrp').")";
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        echo "<p>✅ Found " . $obj->count . " manufacturing orders in database</p>";
        $db->free($resql);
    } else {
        echo "<p>❌ Query failed: " . $db->lasterror() . "</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Exception: " . $e->getMessage() . "</p>";
}

// Check required files
echo "<h3>📁 File Checks:</h3>";
$requiredFiles = [
    'manufacturing_orders_list.php',
    'class/mlconversion.class.php',
    'core/modules/modMLConversion.class.php'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "<p>✅ File exists: $file</p>";
    } else {
        echo "<p>❌ File missing: $file</p>";
    }
}

echo "<h3>🎯 Recommendations:</h3>";
echo "<ol>";
echo "<li><strong>Enable MRP Module:</strong> Go to Setup → Modules → Search for 'MRP' or 'Manufacturing' → Enable it</li>";
echo "<li><strong>Check User Permissions:</strong> Ensure your user has proper permissions</li>";
echo "<li><strong>Clear Cache:</strong> Try logging out and back in</li>";
echo "</ol>";

echo "<p><a href='manufacturing_orders_list.php' style='background: #2c3e50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔄 Try Manufacturing Orders List Again</a></p>";

echo "<hr>";
echo "<p><small>Debug completed at " . date('Y-m-d H:i:s') . "</small></p>";
?>
