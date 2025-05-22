<?php
/**
 * Application Initialization File
 * This file sets up global configurations for the Zakat Management System
 */

// Set default timezone from system or configuration
function setupTimezone() {
    // Connect to the database
    require_once __DIR__ . '/koneksi.php';
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        // Check if we have a timezone stored in the configuration
        $stmt = $db->query("SELECT timezone FROM konfigurasi WHERE id = 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['timezone'])) {
            // Use the stored timezone from database
            $timezone = $result['timezone'];
            date_default_timezone_set($timezone);
            
            // Also set the MySQL session timezone
            // For Indonesia WIB (+7)
            $db->exec("SET time_zone = '+07:00'");
            
            return true;
        } else {
            // Use a default timezone (Indonesia - Jakarta)
            date_default_timezone_set('Asia/Jakarta');
            $db->exec("SET time_zone = '+07:00'");
            
            // If the column exists but is empty, update it
            if ($result) {
                $stmt = $db->prepare("UPDATE konfigurasi SET timezone = 'Asia/Jakarta' WHERE id = 1");
                $stmt->execute();
            }
            
            return false;
        }
    } catch (PDOException $e) {
        // In case of error, use default
        date_default_timezone_set('Asia/Jakarta');
        
        // Log the error
        error_log("Error setting timezone: " . $e->getMessage());
        return false;
    }
}

// Initialize the application timezone
setupTimezone();

// Other global configurations can be added here
?> 