<?php
// Get the system timezone from Windows
$systemTimezone = null;

// Try to get the timezone from Windows registry
exec('powershell -command "[System.TimeZoneInfo]::Local.Id"', $tzOutput);
if (!empty($tzOutput[0])) {
    // Convert Windows time zone ID to PHP time zone ID
    $windowsToPhpTimezones = [
        'Malay Peninsula Standard Time' => 'Asia/Kuala_Lumpur',
        'Singapore Standard Time' => 'Asia/Singapore',
        'China Standard Time' => 'Asia/Shanghai',
        'Tokyo Standard Time' => 'Asia/Tokyo',
        'Korea Standard Time' => 'Asia/Seoul',
        'SE Asia Standard Time' => 'Asia/Bangkok',
        'W. Europe Standard Time' => 'Europe/Berlin',
        'Romance Standard Time' => 'Europe/Paris',
        'GMT Standard Time' => 'Europe/London',
        'Central Europe Standard Time' => 'Europe/Budapest',
        'E. Europe Standard Time' => 'Europe/Minsk',
        'Russian Standard Time' => 'Europe/Moscow',
        'Eastern Standard Time' => 'America/New_York',
        'Central Standard Time' => 'America/Chicago',
        'Mountain Standard Time' => 'America/Denver',
        'Pacific Standard Time' => 'America/Los_Angeles',
        'AUS Eastern Standard Time' => 'Australia/Sydney',
        'Egypt Standard Time' => 'Africa/Cairo',
        'South Africa Standard Time' => 'Africa/Johannesburg',
        'India Standard Time' => 'Asia/Kolkata'
    ];
    
    if (isset($windowsToPhpTimezones[$tzOutput[0]])) {
        $systemTimezone = $windowsToPhpTimezones[$tzOutput[0]];
    }
}

// If we couldn't determine the timezone from Windows, use a default Indonesian timezone
if (!$systemTimezone) {
    // Set Jakarta as default (adjust this if you're in a different region of Indonesia)
    $systemTimezone = 'Asia/Jakarta'; 
}

// Set PHP's timezone
date_default_timezone_set($systemTimezone);

// Connect to the database
require_once __DIR__ . '/koneksi.php';
$database = new Database();
$db = $database->getConnection();

try {
    // Set MySQL session timezone to +7 (WIB - Western Indonesian Time)
    $db->exec("SET time_zone = '+07:00'");
    
    // First check if the timezone column exists in the konfigurasi table
    $stmt = $db->query("SHOW COLUMNS FROM `konfigurasi` LIKE 'timezone'");
    $columnExists = $stmt->rowCount() > 0;
    
    // If the column doesn't exist, add it
    if (!$columnExists) {
        $db->exec("ALTER TABLE `konfigurasi` ADD COLUMN `timezone` VARCHAR(100) DEFAULT 'Asia/Jakarta' AFTER `harga_beras`");
        echo "Added timezone column to konfigurasi table<br>";
    }
    
    // Update the timezone in the configuration table
    $stmt = $db->prepare("UPDATE `konfigurasi` SET `timezone` = ? WHERE id = 1");
    $stmt->execute([$systemTimezone]);
    
    // Output result
    echo "<h4>Timezone setup completed successfully!</h4>";
    echo "<p><strong>PHP Timezone:</strong> " . date_default_timezone_get() . "</p>";
    echo "<p><strong>Current PHP time:</strong> " . date('Y-m-d H:i:s') . "</p>";
    
    // Check MySQL time
    $stmt = $db->query("SELECT NOW() as mysql_time");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Current MySQL time:</strong> " . $result['mysql_time'] . "</p>";
    
    // Get Windows time for comparison
    exec('powershell -command "Get-Date -Format \"yyyy-MM-dd HH:mm:ss\""', $winTimeOutput);
    if (!empty($winTimeOutput[0])) {
        echo "<p><strong>Windows system time:</strong> " . $winTimeOutput[0] . "</p>";
    }
    
} catch (PDOException $e) {
    echo "<h4>Database error:</h4>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?> 