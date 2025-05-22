<?php
// Check for PDO extension and available drivers
echo "<h1>PHP Database Configuration Test</h1>";

// Check PDO extension
echo "<h2>PDO Extension</h2>";
if (extension_loaded('pdo')) {
    echo "PDO extension is loaded. ✅<br>";
} else {
    echo "PDO extension is NOT loaded. ❌<br>";
    echo "Please enable the PDO extension in your PHP configuration.<br>";
}

// Check available PDO drivers
echo "<h2>Available PDO Drivers</h2>";
$drivers = PDO::getAvailableDrivers();
if (empty($drivers)) {
    echo "No PDO drivers are installed. ❌<br>";
} else {
    echo "Available drivers: ";
    foreach ($drivers as $driver) {
        echo "$driver ";
        if ($driver == 'mysql') {
            echo "✅";
        }
    }
    echo "<br>";
    
    if (!in_array('mysql', $drivers)) {
        echo "MySQL PDO driver is NOT available. ❌<br>";
        echo "Please install or enable the pdo_mysql extension.<br>";
    }
}

// Test database connection
echo "<h2>Database Connection Test</h2>";
require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "Successfully connected to the database. ✅<br>";
        
        // Test running a simple query
        $stmt = $db->query("SELECT 1");
        if ($stmt->fetch()) {
            echo "Successfully ran a test query. ✅<br>";
        } else {
            echo "Failed to run a test query. ❌<br>";
        }
    } else {
        echo "Failed to connect to the database. ❌<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . " ❌<br>";
}

// PHP Info about PDO and MySQL
echo "<h2>PHP Information</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "MySQL client info: ";
if (function_exists('mysqli_get_client_info')) {
    echo mysqli_get_client_info() . "<br>";
} else {
    echo "mysqli is not available<br>";
}

echo "<h3>Common Solutions</h3>";
echo "<ol>";
echo "<li>Make sure pdo_mysql extension is enabled in php.ini</li>";
echo "<li>For XAMPP, check that the MySQL service is running</li>";
echo "<li>Verify that the database name in config/database.php exists</li>";
echo "<li>Check username and password in config/database.php</li>";
echo "</ol>";
?> 