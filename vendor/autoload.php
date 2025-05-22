<?php
// Fungsi autoload utama
spl_autoload_register(function ($class) {
    // Namespace PhpOffice\PhpSpreadsheet
    $phpOfficePrefix = 'PhpOffice\\PhpSpreadsheet\\';
    $phpOfficeLen = strlen($phpOfficePrefix);
    
    if (strncmp($phpOfficePrefix, $class, $phpOfficeLen) === 0) {
        $relativeClass = substr($class, $phpOfficeLen);
        $file = __DIR__ . '/PhpOffice/PhpSpreadsheet/' . str_replace('\\', '/', $relativeClass) . '.php';
        
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    
    // Untuk kelas lainnya, gunakan pendekatan dasar
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    $file = __DIR__ . DIRECTORY_SEPARATOR . $class . '.php';
    
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    
    return false;
});

// Definisikan alias kelas untuk kemudahan penggunaan, hanya jika belum didefinisikan
if (!class_exists('Spreadsheet', false)) {
    class_alias('PhpOffice\PhpSpreadsheet\Spreadsheet', 'Spreadsheet');
}
if (!class_exists('Xlsx', false)) {
    class_alias('PhpOffice\PhpSpreadsheet\Writer\Xlsx', 'Xlsx');
}
if (!class_exists('IOFactory', false)) {
    class_alias('PhpOffice\PhpSpreadsheet\IOFactory', 'IOFactory');
} 