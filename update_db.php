<?php
// Connect to the database
$host = 'localhost';
$dbname = 'zakatfitrah';
$username = 'root'; 
$password = '';  // Default XAMPP MySQL password is empty

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully<br>";

// Check if timezone column exists
$result = $conn->query("SHOW COLUMNS FROM konfigurasi LIKE 'timezone'");
if ($result->num_rows == 0) {
    // Column doesn't exist, add it
    if ($conn->query("ALTER TABLE konfigurasi ADD COLUMN timezone VARCHAR(100) DEFAULT 'Asia/Jakarta' AFTER harga_beras")) {
        echo "Added timezone column to konfigurasi table<br>";
    } else {
        echo "Error adding timezone column: " . $conn->error . "<br>";
    }
} else {
    echo "Timezone column already exists<br>";
}

// Update the timezone in the configuration table
$stmt = $conn->prepare("UPDATE konfigurasi SET timezone = ? WHERE id = 1");
$timezone = 'Asia/Jakarta';
$stmt->bind_param("s", $timezone);
if ($stmt->execute()) {
    echo "Updated timezone to 'Asia/Jakarta'<br>";
} else {
    echo "Error updating timezone: " . $stmt->error . "<br>";
}
$stmt->close();

// Set MySQL session timezone
if ($conn->query("SET time_zone = '+07:00'")) {
    echo "Set MySQL session timezone to '+07:00'<br>";
} else {
    echo "Error setting timezone: " . $conn->error . "<br>";
}

echo "Database update completed successfully!";

$conn->close();
?> 