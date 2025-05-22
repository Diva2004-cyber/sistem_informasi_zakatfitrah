<?php
// Alias untuk kompabilitas jika belum didefinisikan
if (!class_exists('Spreadsheet', false)) {
    class_alias('PhpOffice\PhpSpreadsheet\Spreadsheet', 'Spreadsheet');
}
if (!class_exists('IOFactory', false)) {
    class_alias('PhpOffice\PhpSpreadsheet\IOFactory', 'IOFactory');
}
if (!class_exists('Xlsx', false)) {
    class_alias('PhpOffice\PhpSpreadsheet\Writer\Xlsx', 'Xlsx');
}

// Autoload untuk namespace PhpOffice\PhpSpreadsheet
spl_autoload_register(function ($class) {
    $prefix = 'PhpOffice\\PhpSpreadsheet\\';
    $len = strlen($prefix);
    
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Class tanpa namespace prefix
    $relative_class = substr($class, $len);
    
    // Base directory untuk PhpSpreadsheet
    $base_dir = __DIR__ . '/';
    
    // Ubah namespace separator dengan directory separator
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
        return true;
    }
    
    return false;
}); 