<?php
require_once('../config/auth.php');
require_once('../config/db.php');
require_once('../config/activity_logger.php');

// Initialize classes
$auth = new Auth();
$db = new Database();
$logger = new ActivityLogger($db, $auth);

// Check if user is logged in with admin or staff role
if (!$auth->isLoggedIn() || !in_array($auth->getUserRole(), ['admin', 'petugas'])) {
    $_SESSION['error'] = "Anda tidak memiliki akses ke halaman ini";
    header('Location: ../index.php');
    exit;
}

// Required libraries
$hasPhpSpreadsheet = class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet');
if (!$hasPhpSpreadsheet) {
    $_SESSION['import_message'] = [
        'type' => 'danger',
        'text' => 'Library PhpSpreadsheet tidak tersedia. Harap install terlebih dahulu.'
    ];
    header('Location: muzakki.php');
    exit;
}

// Set max execution time for large imports
ini_set('max_execution_time', 300); // 5 minutes

// Check if file was uploaded
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    // Validate file
    $file = $_FILES['import_file'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi upload_max_filesize pada php.ini)',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi MAX_FILE_SIZE pada form)',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak tersedia',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menyimpan file',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP'
        ];
        
        $errorMessage = $errorMessages[$file['error']] ?? 'Unknown upload error';
        $_SESSION['import_message'] = [
            'type' => 'danger',
            'text' => "Error upload: {$errorMessage}"
        ];
        header('Location: muzakki.php');
        exit;
    }
    
    // Check file extension
    $allowedExtensions = ['xlsx', 'xls'];
    $fileName = $file['name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if (!in_array($fileExt, $allowedExtensions)) {
        $_SESSION['import_message'] = [
            'type' => 'danger',
            'text' => 'Format file tidak didukung. Harap upload file Excel (.xlsx atau .xls)'
        ];
        header('Location: muzakki.php');
        exit;
    }
    
    // Check file size (5MB max)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        $_SESSION['import_message'] = [
            'type' => 'danger',
            'text' => 'Ukuran file terlalu besar. Maksimal 5MB'
        ];
        header('Location: muzakki.php');
        exit;
    }
    
    // Process Excel file
    try {
        // Create temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'import_muzakki_');
        move_uploaded_file($file['tmp_name'], $tempFile);
        
        // Load Excel file
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tempFile);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($tempFile);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        
        // Check if file has data
        if ($highestRow <= 1) {
            $_SESSION['import_message'] = [
                'type' => 'danger',
                'text' => 'File Excel kosong atau tidak memiliki data'
            ];
            unlink($tempFile);
            header('Location: muzakki.php');
            exit;
        }
        
        // Get header row
        $headerRow = [];
        for ($col = 'A'; $col <= 'E'; $col++) {
            $cellValue = $worksheet->getCell($col . '1')->getValue();
            $headerRow[] = $cellValue;
        }
        
        // Detect format
        $formatType = detectImportFormat($headerRow);
        
        // Generate batch ID for this import
        $importBatchId = uniqid('import_', true);
        $_SESSION['last_import_batch'] = $importBatchId;
        $_SESSION['last_imported_ids'] = [];
        
        // Start transaction
        $db->begin_transaction();
        
        // Process rows
        $successCount = 0;
        $errorCount = 0;
        $duplicateCount = 0;
        $corruptedCount = 0;
        $importedIds = [];
        
        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($col = 'A'; $col <= 'E'; $col++) {
                $rowData[] = $worksheet->getCell($col . $row)->getValue();
            }
            
            // Skip empty rows
            if (empty(array_filter($rowData))) {
                continue;
            }
            
            // Process data based on format
            $processedData = processRowByFormat($rowData, $formatType);
            
            // Skip rows with empty names
            if (empty($processedData['nama_muzakki'])) {
                $errorCount++;
                continue;
            }
            
            // Check for corrupted data
            if (containsCorruptedData($processedData['nama_muzakki'])) {
                $corruptedCount++;
                continue;
            }
            
            // Check for duplicate names
            $stmt = $db->prepare("SELECT id_muzakki FROM muzakki WHERE nama_muzakki = ?");
            $stmt->bind_param("s", $processedData['nama_muzakki']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing record if duplicate
                $duplicateData = $result->fetch_assoc();
                $duplicateId = $duplicateData['id_muzakki'];
                
                $stmt = $db->prepare("
                    UPDATE muzakki 
                    SET jumlah_tanggungan = ?, 
                        alamat = ?, 
                        keterangan = ?,
                        import_batch = ?
                    WHERE id_muzakki = ?
                ");
                $stmt->bind_param(
                    "isssi",
                    $processedData['jumlah_tanggungan'],
                    $processedData['alamat'],
                    $processedData['keterangan'],
                    $importBatchId,
                    $duplicateId
                );
                $stmt->execute();
                
                $duplicateCount++;
                $importedIds[] = $duplicateId;
                continue;
            }
            
            // Insert new record
            $stmt = $db->prepare("
                INSERT INTO muzakki (nama_muzakki, jumlah_tanggungan, alamat, keterangan, import_batch) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "sisss",
                $processedData['nama_muzakki'],
                $processedData['jumlah_tanggungan'],
                $processedData['alamat'],
                $processedData['keterangan'],
                $importBatchId
            );
            $stmt->execute();
            
            $newId = $db->insert_id;
            $importedIds[] = $newId;
            $successCount++;
        }
        
        // Store imported IDs for potential rollback
        $_SESSION['last_imported_ids'] = $importedIds;
        
        // Commit transaction
        $db->commit();
        
        // Log activity
        $logger->log(
            'import', 
            'muzakki', 
            "Import Excel muzakki: {$successCount} baru, {$duplicateCount} duplikat, {$errorCount} error, {$corruptedCount} corrupt", 
            null
        );
        
        // Create success message with details
        $message = "<strong>Import berhasil!</strong><br>";
        $message .= "- {$successCount} data baru ditambahkan<br>";
        $message .= "- {$duplicateCount} data duplikat diperbarui<br>";
        $message .= "- {$errorCount} baris dilewati karena error<br>";
        
        if ($corruptedCount > 0) {
            $message .= "- {$corruptedCount} data terdeteksi rusak dan dilewati<br>";
            $message .= "<a href='muzakki.php?clean_corrupt_data=true' class='btn btn-sm btn-warning mt-2'>Bersihkan Semua Data Rusak</a>";
        }
        
        $message .= "<div class='mt-2'><a href='muzakki.php?rollback_import=true' class='btn btn-sm btn-danger'>Batalkan Import</a></div>";
        
        $_SESSION['import_message'] = [
            'type' => 'success',
            'text' => $message
        ];
        
    } catch (Exception $e) {
        // Rollback transaction if error occurs
        if ($db->connect_error !== true) {
            $db->rollback();
        }
        
        $_SESSION['import_message'] = [
            'type' => 'danger',
            'text' => "Error saat memproses file: " . $e->getMessage()
        ];
        
    } finally {
        // Clean up temporary file
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
}

// HELPER FUNCTIONS FOR IMPORT
function detectImportFormat($headerRow) {
    $headerRowStr = implode('|', array_map('strtolower', $headerRow));
    
    // Format 1: Standard format
    if (preg_match('/(no|nama.muzakki|nama|jumlah.tanggungan|alamat|keterangan)/i', $headerRowStr)) {
        return 'standard';
    }
    
    // Format 2: Laporan format
    if (preg_match('/(no|nama|jumlah.orang|beras|uang)/i', $headerRowStr)) {
        return 'laporan';
    }
    
    // Format 3: Alternative format
    if (preg_match('/(no|nama.muzakki|rt|lokasi|jiwa|jumlah.orang)/i', $headerRowStr)) {
        return 'alternative';
    }
    
    // Default to standard format
    return 'standard';
}

function processRowByFormat($rowData, $formatType) {
    $result = [
        'nama_muzakki' => '',
        'jumlah_tanggungan' => 1,
        'alamat' => '',
        'keterangan' => ''
    ];
    
    switch ($formatType) {
        case 'standard':
            $result['nama_muzakki'] = trim($rowData[1] ?? '');
            $result['jumlah_tanggungan'] = max(1, intval($rowData[2] ?? 1));
            $result['keterangan'] = trim($rowData[3] ?? '');
            $result['alamat'] = trim($rowData[4] ?? '');
            break;
            
        case 'laporan':
            $result['nama_muzakki'] = trim($rowData[1] ?? '');
            $result['jumlah_tanggungan'] = max(1, intval($rowData[2] ?? 1));
            
            // Generate keterangan from beras/uang fields
            $beras = trim($rowData[3] ?? '');
            $uang = trim($rowData[4] ?? '');
            $keterangan = [];
            
            if (!empty($beras) && $beras != '0') {
                $keterangan[] = "Beras: {$beras}kg";
            }
            
            if (!empty($uang) && $uang != '0') {
                $keterangan[] = "Uang: Rp{$uang}";
            }
            
            $result['keterangan'] = implode(', ', $keterangan);
            break;
            
        case 'alternative':
            $result['nama_muzakki'] = trim($rowData[1] ?? '');
            $result['alamat'] = trim($rowData[2] ?? ''); // RT/Lokasi
            $result['jumlah_tanggungan'] = max(1, intval($rowData[3] ?? 1)); // Jiwa/Jumlah Orang
            break;
    }
    
    return $result;
}

function containsCorruptedData($string) {
    // Check for 4 or more consecutive non-alphanumeric characters (except common punctuation)
    if (preg_match('/[^a-zA-Z0-9 .,\-\/\(\)]{4,}/', $string)) {
        return true;
    }
    
    // Check for 5 or more consecutive special symbols
    if (preg_match('/[\^\$\&\%\@\!\*\+\=\|\<\>\{\}\[\]\?]{5,}/', $string)) {
        return true;
    }
    
    // Check for common corruption patterns
    if (strpos($string, '') !== false) {
        return true;
    }
    
    // Count the number of characters
    if (substr_count($string, '') >= 4) {
        return true;
    }
    
    // Whitelist check for common names that might trigger false positives
    $whitelist = ['xiaoyan', 'xiao er', 'pak ajat'];
    foreach ($whitelist as $safe) {
        if (stripos($string, $safe) !== false) {
            return false;
        }
    }
    
    return false;
}

// Redirect back to muzakki page
header('Location: muzakki.php');
exit;
?> 