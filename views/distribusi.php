<?php
require_once '../middleware/auth_middleware.php';
require_once '../config/database.php';
require_once '../config/activity_logger.php';
require_once '../includes/functions.php';

// Define base path for assets and links
$base_path = '../';

// Set current page for sidebar
$current_page = 'distribusi';

// Initialize auth middleware
$auth = new AuthMiddleware();
$auth->requireAuth();

// Initialize database and logger
$database = new Database();
$db = $database->getConnection();
$logger = new ActivityLogger($db, $auth);

// Get rice price configuration
$stmt = $db->query("SELECT harga_beras FROM konfigurasi LIMIT 1");
$harga_beras = $stmt->fetch(PDO::FETCH_ASSOC)['harga_beras'] ?? 13000; // Default 13000 per kg

// Handle rice price update
if (isset($_POST['update_harga_beras'])) {
    $new_price = $_POST['harga_beras'];
    $stmt = $db->prepare("UPDATE konfigurasi SET harga_beras = ?");
    $stmt->execute([$new_price]);
    $harga_beras = $new_price;
    $logger->log('update', 'konfigurasi', 'Memperbarui harga beras menjadi: Rp ' . number_format($new_price, 0, ',', '.'));
}

// Get all muzakki for mustahik warga selection
$stmt = $db->query("SELECT * FROM muzakki ORDER BY nama_muzakki ASC");
$muzakki = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total zakat collected
$stmt = $db->query("SELECT SUM(bayar_beras) as total_beras, SUM(bayar_uang) as total_uang FROM bayarzakat");
$zakat = $stmt->fetch(PDO::FETCH_ASSOC);
$total_zakat = $zakat['total_beras'] ?? 0;
$total_uang = $zakat['total_uang'] ?? 0;

// Get count of existing mustahik per category
$stmt = $db->query("SELECT kategori, COUNT(*) as jumlah FROM mustahik_warga GROUP BY kategori");
$jumlah_per_kategori_warga = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $jumlah_per_kategori_warga[$row['kategori']] = $row['jumlah'];
}

$stmt = $db->query("SELECT kategori, COUNT(*) as jumlah FROM mustahik_lainnya GROUP BY kategori");
$jumlah_per_kategori_lainnya = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $jumlah_per_kategori_lainnya[$row['kategori']] = $row['jumlah'];
}

// Get total distributed per category
$stmt = $db->query("SELECT kategori, SUM(hak) as total_hak FROM mustahik_warga GROUP BY kategori");
$total_hak_per_kategori_warga = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $total_hak_per_kategori_warga[$row['kategori']] = $row['total_hak'];
}

$stmt = $db->query("SELECT kategori, SUM(hak) as total_hak FROM mustahik_lainnya GROUP BY kategori");
$total_hak_per_kategori_lainnya = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $total_hak_per_kategori_lainnya[$row['kategori']] = $row['total_hak'];
}

// Calculate total allocation per category for money
$total_fakir_uang = $total_uang * 0.40;
$total_miskin_uang = $total_uang * 0.20;
$total_amilin_uang = $total_uang * 0.125;
$total_fisabilillah_uang = $total_uang * 0.255;
$total_mualaf_uang = $total_uang * 0.01;
$total_ibnu_sabil_uang = $total_uang * 0.005;
$total_gharimin_uang = $total_uang * 0.005;

// Get total distributed zakat
$stmt = $db->query("SELECT SUM(hak) as total_distributed FROM mustahik_warga");
$total_distributed_warga = $stmt->fetch(PDO::FETCH_ASSOC)['total_distributed'] ?? 0;

$stmt = $db->query("SELECT SUM(hak) as total_distributed FROM mustahik_lainnya");
$total_distributed_lainnya = $stmt->fetch(PDO::FETCH_ASSOC)['total_distributed'] ?? 0;

$total_distributed = $total_distributed_warga + $total_distributed_lainnya;
$sisa_zakat = $total_zakat - $total_distributed;

// Get count of fakir and miskin from muzakki
$stmt = $db->query("SELECT COUNT(*) as count FROM muzakki WHERE kategori = 'fakir'");
$jumlah_fakir = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $db->query("SELECT COUNT(*) as count FROM muzakki WHERE kategori = 'miskin'");
$jumlah_miskin = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get count of other mustahik categories
$stmt = $db->query("SELECT kategori, COUNT(*) as count FROM mustahik_lainnya GROUP BY kategori");
$jumlah_mustahik_lainnya = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $jumlah_mustahik_lainnya[$row['kategori']] = $row['count'];
}

// Calculate total hak for each category
$total_hak_fakir = $total_zakat * 0.40;
$total_hak_miskin = $total_zakat * 0.20;
$total_hak_amilin = $total_zakat * 0.125;
$total_hak_fisabilillah = $total_zakat * 0.255;
$total_hak_mualaf = $total_zakat * 0.01;
$total_hak_ibnu_sabil = $total_zakat * 0.005;
$total_hak_gharimin = $total_zakat * 0.005;

// Calculate hak per person
$hak_fakir_per_orang = $jumlah_fakir > 0 ? $total_hak_fakir / $jumlah_fakir : 0;
$hak_miskin_per_orang = $jumlah_miskin > 0 ? $total_hak_miskin / $jumlah_miskin : 0;

// Calculate hak per person for other mustahik
$hak_amilin_per_orang = ($jumlah_mustahik_lainnya['amilin'] ?? 0) > 0 ? $total_hak_amilin / $jumlah_mustahik_lainnya['amilin'] : 0;
$hak_fisabilillah_per_orang = ($jumlah_mustahik_lainnya['fisabilillah'] ?? 0) > 0 ? $total_hak_fisabilillah / $jumlah_mustahik_lainnya['fisabilillah'] : 0;
$hak_mualaf_per_orang = ($jumlah_mustahik_lainnya['mualaf'] ?? 0) > 0 ? $total_hak_mualaf / $jumlah_mustahik_lainnya['mualaf'] : 0;
$hak_ibnu_sabil_per_orang = ($jumlah_mustahik_lainnya['ibnu sabil'] ?? 0) > 0 ? $total_hak_ibnu_sabil / $jumlah_mustahik_lainnya['ibnu sabil'] : 0;
$hak_gharimin_per_orang = ($jumlah_mustahik_lainnya['gharimin'] ?? 0) > 0 ? $total_hak_gharimin / $jumlah_mustahik_lainnya['gharimin'] : 0;

// Calculate surplus hak (hak yang belum terdistribusi)
$surplus_hak = 0;
if ($jumlah_fakir == 0) $surplus_hak += $total_hak_fakir;
if ($jumlah_miskin == 0) $surplus_hak += $total_hak_miskin;
if (($jumlah_mustahik_lainnya['amilin'] ?? 0) == 0) $surplus_hak += $total_hak_amilin;
if (($jumlah_mustahik_lainnya['fisabilillah'] ?? 0) == 0) $surplus_hak += $total_hak_fisabilillah;
if (($jumlah_mustahik_lainnya['mualaf'] ?? 0) == 0) $surplus_hak += $total_hak_mualaf;
if (($jumlah_mustahik_lainnya['ibnu sabil'] ?? 0) == 0) $surplus_hak += $total_hak_ibnu_sabil;
if (($jumlah_mustahik_lainnya['gharimin'] ?? 0) == 0) $surplus_hak += $total_hak_gharimin;

// Add surplus to fakir and miskin if they exist
if ($jumlah_fakir > 0 || $jumlah_miskin > 0) {
    $total_orang_fakir_miskin = $jumlah_fakir + $jumlah_miskin;
    $surplus_per_orang = $surplus_hak / $total_orang_fakir_miskin;
    
    if ($jumlah_fakir > 0) {
        $hak_fakir_per_orang += $surplus_per_orang;
    }
    if ($jumlah_miskin > 0) {
        $hak_miskin_per_orang += $surplus_per_orang;
    }
}

// Get kategori mustahik with proper names for display
$stmt = $db->query("SELECT id_kategori, nama_kategori, jumlah_hak FROM kategori_mustahik ORDER BY nama_kategori ASC");
$kategori_mustahik = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map for display names
$kategori_display_names = [
    'ibnu_sabil' => 'Ibnu Sabil',
    'muallaf' => 'Mualaf'
];

// Convert kategori_mustahik names for display
foreach ($kategori_mustahik as &$kategori) {
    if (isset($kategori_display_names[$kategori['nama_kategori']])) {
        $kategori['display_name'] = $kategori_display_names[$kategori['nama_kategori']];
    } else {
        $kategori['display_name'] = ucfirst($kategori['nama_kategori']);
    }
}
unset($kategori);

// Group kategori by type (warga and lainnya)
$kategori_warga = [];
$kategori_lainnya = [];
foreach ($kategori_mustahik as $kategori) {
    // Kategori untuk warga: fakir, miskin, mampu
    if (in_array(strtolower($kategori['nama_kategori']), ['fakir', 'miskin', 'mampu'])) {
        $kategori_warga[] = $kategori;
    } else {
        // Make sure all other categories are included in lainnya
        $kategori_lainnya[] = $kategori;
    }
}

// Tambahkan variabel pencarian nama
$search_nama = isset($_GET['search_nama']) ? trim($_GET['search_nama']) : '';

// Get all mustahik warga dengan filter pencarian nama
if ($search_nama !== '') {
    $stmt = $db->prepare("
        SELECT mw.*, m.id_muzakki, km.id_kategori
        FROM mustahik_warga mw
        LEFT JOIN muzakki m ON mw.nama = m.nama_muzakki
        LEFT JOIN kategori_mustahik km ON LOWER(mw.kategori) = LOWER(km.nama_kategori)
        WHERE mw.nama LIKE ?
        ORDER BY mw.status ASC, mw.id_mustahikwarga DESC
    ");
    $stmt->execute(["%$search_nama%"]);
    $mustahik_warga = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $db->query("
        SELECT mw.*, m.id_muzakki, km.id_kategori
        FROM mustahik_warga mw
        LEFT JOIN muzakki m ON mw.nama = m.nama_muzakki
        LEFT JOIN kategori_mustahik km ON LOWER(mw.kategori) = LOWER(km.nama_kategori)
        ORDER BY mw.status ASC, mw.id_mustahikwarga DESC
    ");
    $mustahik_warga = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Automatically update existing records that have null id_muzakki or id_kategori
foreach ($mustahik_warga as $mustahik) {
    $updates = [];
    $params = [];
    
    // If id_muzakki in the table is null but we found a match in the join
    if ($mustahik['id_muzakki'] === null && isset($mustahik['id_muzakki']) && $mustahik['id_muzakki'] > 0) {
        $updates[] = "id_muzakki = ?";
        $params[] = $mustahik['id_muzakki'];
    }
    
    // If id_kategori in the table is null but we found a match in the join
    if ($mustahik['id_kategori'] === null && isset($mustahik['id_kategori']) && $mustahik['id_kategori'] > 0) {
        $updates[] = "id_kategori = ?";
        $params[] = $mustahik['id_kategori'];
    }
    
    // If we have updates to make
    if (!empty($updates)) {
        $params[] = $mustahik['id_mustahikwarga'];
        $stmt = $db->prepare("UPDATE mustahik_warga SET " . implode(", ", $updates) . " WHERE id_mustahikwarga = ?");
        $stmt->execute($params);
    }
}

// Get all mustahik lainnya
$stmt = $db->query("
    SELECT ml.*, 
           km.id_kategori,
           CASE 
               WHEN ml.kategori = '' AND km.nama_kategori = 'muallaf' THEN 'mualaf'
               WHEN ml.kategori = '' AND km.nama_kategori = 'ibnu_sabil' THEN 'ibnu sabil'
               WHEN km.nama_kategori = 'ibnu_sabil' THEN 'ibnu sabil'
               WHEN ml.kategori = '' AND km.nama_kategori = 'gharimin' THEN 'fisabilillah'
               ELSE ml.kategori 
           END as fixed_kategori
    FROM mustahik_lainnya ml
    LEFT JOIN kategori_mustahik km ON (
        LOWER(ml.kategori) = LOWER(km.nama_kategori) OR
        (ml.kategori = '' AND ml.id_kategori = km.id_kategori) OR
        (LOWER(ml.kategori) = 'ibnu sabil' AND LOWER(km.nama_kategori) = 'ibnu_sabil') OR
        (LOWER(ml.kategori) = 'mualaf' AND LOWER(km.nama_kategori) = 'muallaf')
    )
    ORDER BY ml.status ASC, ml.id_mustahiklainnya DESC
");
$mustahik_lainnya = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Automatically update existing records that have null id_kategori
foreach ($mustahik_lainnya as $mustahik) {
    // If id_kategori in the table is null but we found a match in the join
    if ($mustahik['id_kategori'] === null && isset($mustahik['id_kategori']) && $mustahik['id_kategori'] > 0) {
        $stmt = $db->prepare("UPDATE mustahik_lainnya SET id_kategori = ? WHERE id_mustahiklainnya = ?");
        $stmt->execute([$mustahik['id_kategori'], $mustahik['id_mustahiklainnya']]);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $error = '';
        
        switch ($_POST['action']) {
            case 'create_muzakki':
                try {
                    $stmt = $db->prepare("INSERT INTO muzakki (nama_muzakki, jumlah_tanggungan, alamat, keterangan) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['nama_muzakki'],
                        $_POST['jumlah_tanggungan'],
                        $_POST['alamat'],
                        $_POST['keterangan']
                    ]);
                    $logger->log('create', 'muzakki', 'Menambah muzakki: ' . $_POST['nama_muzakki']);
                    $success = "Muzakki berhasil ditambahkan";
                    
                    // Refresh muzakki list
                    $stmt = $db->query("SELECT * FROM muzakki ORDER BY nama_muzakki ASC");
                    $muzakki = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $error = "Gagal menambah muzakki: " . $e->getMessage();
                }
                break;
                
            case 'create_warga':
                try {
                    // Get muzakki data
                    $id_muzakki = $_POST['nama']; // nama field sekarang berisi id_muzakki
                    
                    // Get selected category data
                    $kategori = $_POST['kategori'];
                    $stmt = $db->prepare("SELECT id_kategori FROM kategori_mustahik WHERE LOWER(nama_kategori) = LOWER(?)");
                    $stmt->execute([$kategori]);
                    $id_kategori = $stmt->fetch(PDO::FETCH_ASSOC)['id_kategori'] ?? null;
                    
                    // Get muzakki name
                    $stmt = $db->prepare("SELECT nama_muzakki FROM muzakki WHERE id_muzakki = ?");
                    $stmt->execute([$id_muzakki]);
                    $nama_muzakki = $stmt->fetch(PDO::FETCH_ASSOC)['nama_muzakki'];

                    $stmt = $db->prepare("INSERT INTO mustahik_warga (nama, id_muzakki, kategori, id_kategori, hak, status) VALUES (?, ?, ?, ?, 0, 'belum_terdistribusi')");
                    $stmt->execute([$nama_muzakki, $id_muzakki, $kategori, $id_kategori]);
                    $logger->log('create', 'mustahik_warga', 'Menambah mustahik warga: ' . $nama_muzakki);
                    $success = "Mustahik warga berhasil ditambahkan";
                } catch (Exception $e) {
                    $error = "Gagal menambah mustahik warga: " . $e->getMessage();
                }
                break;
                
            case 'create_lainnya':
                try {
                    // Validate that the category selected is one of the allowed values
                    $valid_categories = ['amilin', 'fisabilillah', 'mualaf', 'muallaf', 'ibnu sabil', 'ibnu_sabil', 'gharimin'];
                    $kategori = $_POST['kategori'];
                    
                    // Check if category exists in kategori_mustahik
                    $stmt = $db->prepare("SELECT id_kategori, nama_kategori FROM kategori_mustahik WHERE LOWER(nama_kategori) = LOWER(?)");
                    $stmt->execute([$kategori]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$result) {
                        throw new Exception("Kategori tidak valid");
                    }
                    
                    $id_kategori = $result['id_kategori'];
                    
                    // Map category names to match the database enum values
                    $category_mapping = [
                        'amilin' => 'amilin',
                        'fisabilillah' => 'fisabilillah',
                        'mualaf' => 'mualaf',
                        'muallaf' => 'mualaf',
                        'ibnu sabil' => 'ibnu sabil',
                        'ibnu_sabil' => 'ibnu sabil',
                        'gharimin' => 'fisabilillah' // Temporary mapping since gharimin isn't in the enum
                    ];
                    
                    // Get the mapped category or use the original if not in mapping
                    $db_kategori = $category_mapping[strtolower($kategori)] ?? $kategori;
                    
                    // Here we handle special cases - if the category doesn't fit in the enum, we'll
                    // set the kategori field to one that does fit but rely on id_kategori to identify correctly
                    if ($db_kategori === 'gharimin') {
                        // Store as fisabilillah in the kategori field, but keep the id_kategori pointing to gharimin
                        $stmt = $db->prepare("INSERT INTO mustahik_lainnya (nama, kategori, id_kategori, hak, hak_uang, status) VALUES (?, 'fisabilillah', ?, 0, 0, 'belum_terdistribusi')");
                        $stmt->execute([$_POST['nama'], $id_kategori]);
                    } else {
                        $stmt = $db->prepare("INSERT INTO mustahik_lainnya (nama, kategori, id_kategori, hak, hak_uang, status) VALUES (?, ?, ?, 0, 0, 'belum_terdistribusi')");
                        $stmt->execute([$_POST['nama'], $db_kategori, $id_kategori]);
                    }
                    
                    $logger->log('create', 'mustahik_lainnya', 'Menambah mustahik lainnya: ' . $_POST['nama']);
                    $success = "Mustahik lainnya berhasil ditambahkan";
                } catch (Exception $e) {
                    $error = "Gagal menambah mustahik lainnya: " . $e->getMessage();
                }
                break;
                
            case 'edit_warga':
                try {
                    // Get selected category data
                    $kategori = $_POST['kategori'];
                    $stmt = $db->prepare("SELECT id_kategori FROM kategori_mustahik WHERE LOWER(nama_kategori) = LOWER(?)");
                    $stmt->execute([$kategori]);
                    $id_kategori = $stmt->fetch(PDO::FETCH_ASSOC)['id_kategori'] ?? null;
                    
                    $stmt = $db->prepare("UPDATE mustahik_warga SET nama = ?, kategori = ?, id_kategori = ? WHERE id_mustahikwarga = ?");
                    $stmt->execute([$_POST['nama'], $_POST['kategori'], $id_kategori, $_POST['id']]);
                    $logger->log('update', 'mustahik_warga', 'Mengubah mustahik warga ID: ' . $_POST['id']);
                    $success = "Mustahik warga berhasil diperbarui";
                } catch (Exception $e) {
                    $error = "Gagal memperbarui mustahik warga: " . $e->getMessage();
                }
                break;
                
            case 'edit_lainnya':
                try {
                    // Get selected category data
                    $kategori = $_POST['kategori'];
                    $stmt = $db->prepare("SELECT id_kategori, nama_kategori FROM kategori_mustahik WHERE LOWER(nama_kategori) = LOWER(?)");
                    $stmt->execute([$kategori]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$result) {
                        throw new Exception("Kategori tidak valid");
                    }
                    
                    $id_kategori = $result['id_kategori'];
                    $nama_kategori = $result['nama_kategori'];
                    
                    // Map category names to match the database enum values
                    $category_mapping = [
                        'amilin' => 'amilin',
                        'fisabilillah' => 'fisabilillah',
                        'mualaf' => 'mualaf',
                        'muallaf' => 'mualaf',
                        'ibnu sabil' => 'ibnu sabil',
                        'ibnu_sabil' => 'ibnu sabil',
                        'gharimin' => 'fisabilillah' // Temporary mapping since gharimin isn't in the enum
                    ];
                    
                    // Get the mapped category or use the original if not in mapping
                    $db_kategori = $category_mapping[strtolower($kategori)] ?? $kategori;
                    
                    // Here we handle special cases - if the category doesn't fit in the enum, we'll
                    // set the kategori field to one that does fit but rely on id_kategori to identify correctly
                    if (strtolower($nama_kategori) === 'gharimin') {
                        // Use fisabilillah in the kategori field but keep the id_kategori correct
                        $stmt = $db->prepare("UPDATE mustahik_lainnya SET nama = ?, kategori = 'fisabilillah', id_kategori = ? WHERE id_mustahiklainnya = ?");
                        $stmt->execute([$_POST['nama'], $id_kategori, $_POST['id']]);
                    } else {
                        $stmt = $db->prepare("UPDATE mustahik_lainnya SET nama = ?, kategori = ?, id_kategori = ? WHERE id_mustahiklainnya = ?");
                        $stmt->execute([$_POST['nama'], $db_kategori, $id_kategori, $_POST['id']]);
                    }
                    
                    $logger->log('update', 'mustahik_lainnya', 'Mengubah mustahik lainnya ID: ' . $_POST['id']);
                    $success = "Mustahik lainnya berhasil diperbarui";
                } catch (Exception $e) {
                    $error = "Gagal memperbarui mustahik lainnya: " . $e->getMessage();
                }
                break;
                
            case 'delete_warga':
                try {
                    $stmt = $db->prepare("DELETE FROM mustahik_warga WHERE id_mustahikwarga = ?");
                    $stmt->execute([$_POST['id']]);
                    $logger->log('delete', 'mustahik_warga', 'Menghapus mustahik warga ID: ' . $_POST['id']);
                    $success = "Mustahik warga berhasil dihapus";
                } catch (Exception $e) {
                    $error = "Gagal menghapus mustahik warga: " . $e->getMessage();
                }
                break;
                
            case 'delete_lainnya':
                try {
                    $stmt = $db->prepare("DELETE FROM mustahik_lainnya WHERE id_mustahiklainnya = ?");
                    $stmt->execute([$_POST['id']]);
                    $logger->log('delete', 'mustahik_lainnya', 'Menghapus mustahik lainnya ID: ' . $_POST['id']);
                    $success = "Mustahik lainnya berhasil dihapus";
                } catch (Exception $e) {
                    $error = "Gagal menghapus mustahik lainnya: " . $e->getMessage();
                }
                break;
                
            case 'distribusi_otomatis':
                try {
                    // Get all mustahik yang belum terdistribusi
                    $stmt = $db->query("SELECT * FROM mustahik_warga WHERE status = 'belum_terdistribusi'");
                    $mustahik_warga_belum = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $stmt = $db->query("
                        SELECT ml.*, 
                              km.nama_kategori as actual_kategori,
                              km.id_kategori as actual_id_kategori
                        FROM mustahik_lainnya ml
                        LEFT JOIN kategori_mustahik km ON ml.id_kategori = km.id_kategori
                        WHERE ml.status = 'belum_terdistribusi'
                    ");
                    $mustahik_lainnya_belum = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Hitung total penerima per kategori
                    $jumlah_per_kategori = [];
                    foreach ($kategori_mustahik as $kategori) {
                        $jumlah_per_kategori[$kategori['nama_kategori']] = 0;
                    }
                    
                    // Filter dan hitung mustahik yang valid
                    foreach ($mustahik_warga_belum as $mustahik) {
                        if (isset($jumlah_per_kategori[$mustahik['kategori']])) {
                            $jumlah_per_kategori[$mustahik['kategori']]++;
                        }
                    }
                    
                    foreach ($mustahik_lainnya_belum as $mustahik) {
                        // Use actual_kategori from join instead of kategori field
                        if (!empty($mustahik['actual_kategori']) && isset($jumlah_per_kategori[$mustahik['actual_kategori']])) {
                            $jumlah_per_kategori[$mustahik['actual_kategori']]++;
                        } elseif (isset($jumlah_per_kategori[$mustahik['kategori']])) {
                            $jumlah_per_kategori[$mustahik['kategori']]++;
                        }
                    }
                    
                    // Hitung hak per kategori berdasarkan persentase di database
                    $hak_per_kategori = [];
                    foreach ($kategori_mustahik as $kategori) {
                        $hak_per_kategori[$kategori['nama_kategori']] = $total_zakat * ($kategori['jumlah_hak'] / 100);
                    }
                    
                    // Hitung hak per orang
                    $hak_per_orang = [];
                    foreach ($hak_per_kategori as $kategori => $hak) {
                        $hak_per_orang[$kategori] = $jumlah_per_kategori[$kategori] > 0 ? 
                            $hak / $jumlah_per_kategori[$kategori] : 0;
                    }
                    
                    // Update hak untuk mustahik warga
                    foreach ($mustahik_warga_belum as $mustahik) {
                        if (isset($hak_per_orang[$mustahik['kategori']])) {
                            $hak = $hak_per_orang[$mustahik['kategori']];
                            $hak_uang = $hak * $harga_beras;
                            $stmt = $db->prepare("UPDATE mustahik_warga SET hak = ?, status = 'terdistribusi' WHERE id_mustahikwarga = ?");
                            $stmt->execute([$hak, $mustahik['id_mustahikwarga']]);
                        }
                    }
                    
                    // Update hak untuk mustahik lainnya
                    foreach ($mustahik_lainnya_belum as $mustahik) {
                        $kategori_to_use = !empty($mustahik['actual_kategori']) ? $mustahik['actual_kategori'] : $mustahik['kategori'];
                        
                        if (isset($hak_per_orang[$kategori_to_use])) {
                            $hak = $hak_per_orang[$kategori_to_use];
                            $hak_uang = $hak * $harga_beras;
                            $stmt = $db->prepare("UPDATE mustahik_lainnya SET hak = ?, hak_uang = ?, status = 'terdistribusi' WHERE id_mustahiklainnya = ?");
                            $stmt->execute([$hak, $hak_uang, $mustahik['id_mustahiklainnya']]);
                        }
                    }
                    
                    $logger->log('distribusi', 'zakat', 'Melakukan distribusi otomatis zakat');
                    $success = "Distribusi otomatis berhasil dilakukan";
                } catch (Exception $e) {
                    $error = "Gagal melakukan distribusi otomatis: " . $e->getMessage();
                }
                break;
                
            case 'reset_distribusi':
                // Periksa izin
                if (!$auth->hasPermission('reset_distribusi')) {
                    $error = "Anda tidak memiliki izin untuk mereset distribusi.";
                    break;
                }
                
                try {
                    // Reset mustahik warga
                    $stmt = $db->prepare("UPDATE mustahik_warga SET status = 'belum_terdistribusi', hak = 0");
                    $stmt->execute();
                    
                    // Reset mustahik lainnya
                    $stmt = $db->prepare("UPDATE mustahik_lainnya SET status = 'belum_terdistribusi', hak = 0, hak_uang = 0");
                    $stmt->execute();
                    
                    $logger->log('reset', 'distribusi', 'Mereset distribusi zakat');
                    $success = "Reset distribusi berhasil dilakukan";
                } catch (Exception $e) {
                    $error = "Gagal melakukan reset distribusi: " . $e->getMessage();
                }
                break;


        }
    }
}

// Prepare content for template
ob_start();

// Existing HTML content goes here (without the HTML, HEAD, BODY tags)
?>
<div class="row mb-4">
    <div class="col-lg-12">
        <div class="d-flex justify-content-between mb-3">
            <h2>Distribusi Zakat Fitrah</h2>
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalHargaBeras">
                    <i class="bi bi-gear"></i> Atur Harga Beras
                </button>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Zakat Terkumpul</h5>
                        <p class="card-text"><?php echo number_format($total_zakat, 2); ?> kg</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Uang Terkumpul</h5>
                        <p class="card-text">Rp <?php echo number_format($total_uang, 0, ',', '.'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Zakat Tersalurkan</h5>
                        <p class="card-text"><?php echo number_format($total_distributed, 2); ?> kg</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Sisa Zakat</h5>
                        <p class="card-text"><?php echo number_format($sisa_zakat, 2); ?> kg</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Tabs -->
        <ul class="nav nav-tabs mb-4" id="distributionTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="warga-tab" data-bs-toggle="tab" data-bs-target="#warga" type="button" role="tab" aria-controls="warga" aria-selected="true">Warga</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="lainnya-tab" data-bs-toggle="tab" data-bs-target="#lainnya" type="button" role="tab" aria-controls="lainnya" aria-selected="false">Lainnya</button>
            </li>
        </ul>
        
        <!-- Tab contents -->
        <div class="tab-content" id="distributionTabsContent">
            <!-- Warga Tab -->
            <div class="tab-pane fade show active" id="warga" role="tabpanel" aria-labelledby="warga-tab">
                <div class="row">
                    <!-- Add Distribution Form -->
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Tambah Mustahik Warga</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="create_warga">
                                    <div class="mb-3">
                                        <label class="form-label">Nama Muzakki</label>
                                        <select class="form-select" name="nama" required>
                                            <option value="">Pilih Muzakki</option>
                                            <?php foreach ($muzakki as $m): ?>
                                            <option value="<?php echo $m['id_muzakki']; ?>">
                                                [<?php echo $m['id_muzakki']; ?>] <?php echo htmlspecialchars($m['nama_muzakki']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Kategori</label>
                                        <select class="form-select" name="kategori" required>
                                            <option value="">Pilih Kategori</option>
                                            <?php foreach ($kategori_warga as $k): ?>
                                            <option value="<?php echo htmlspecialchars($k['nama_kategori']); ?>" data-id="<?php echo $k['id_kategori']; ?>">
                                                [<?php echo $k['id_kategori']; ?>] <?php echo htmlspecialchars($k['nama_kategori']); ?> 
                                                (<?php echo number_format($k['jumlah_hak'] ?? 0, 1); ?>%)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Simpan</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Distribution Control Card -->
                    <div class="col-md-8 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Pengaturan Distribusi</h5>
                                <div>
                                    <?php if ($auth->hasPermission('reset_distribusi')): ?>
                                    <button class="btn btn-warning btn-sm me-2" data-bs-toggle="modal" data-bs-target="#resetModal">
                                        <i class="bi bi-arrow-counterclockwise"></i> Reset Distribusi
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#distribusiModal">
                                        <i class="bi bi-cash"></i> Distribusi Otomatis
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <h5 class="mb-2">Informasi Distribusi</h5>
                                    <p class="mb-1"><strong>Total Zakat Beras Terkumpul:</strong> <?php echo number_format($total_zakat, 2); ?> kg</p>
                                    <p class="mb-1"><strong>Total Zakat Uang Terkumpul:</strong> Rp <?php echo number_format($total_uang, 0, ',', '.'); ?></p>
                                    <p class="mb-1"><strong>Total Zakat Beras Terdistribusi:</strong> <?php echo number_format($total_distributed, 2); ?> kg</p>
                                    <p class="mb-0"><strong>Sisa Zakat Beras:</strong> <?php echo number_format($sisa_zakat, 2); ?> kg</p>
                                </div>
                                
                                <?php if (isset($error)): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                                <?php endif; ?>
                                
                                <?php if (isset($success)): ?>
                                <div class="alert alert-success"><?php echo $success; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Form Pencarian Nama Mustahik Warga -->
                <form method="get" class="row g-3 mb-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search_nama" placeholder="Cari Nama Mustahik Warga" value="<?php echo htmlspecialchars($search_nama); ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Cari</button>
                    </div>
                </form>
                
                <!-- Mustahik Warga Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Daftar Mustahik Warga</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama</th>
                                        <th>ID Muzakki</th>
                                        <th>ID Kategori</th>
                                        <th>Kategori</th>
                                        <th>Hak (kg)</th>
                                        <th>Hak Uang (Rp)</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mustahik_warga as $mustahik): ?>
                                    <tr>
                                        <td><?php echo $mustahik['id_mustahikwarga']; ?></td>
                                        <td><?php echo htmlspecialchars($mustahik['nama']); ?></td>
                                        <td><?php echo $mustahik['id_muzakki'] ?? '-'; ?></td>
                                        <td><?php echo $mustahik['id_kategori'] ?? '-'; ?></td>
                                        <td><?php echo ucfirst($mustahik['kategori']); ?></td>
                                        <td><?php echo number_format($mustahik['hak'], 2); ?></td>
                                        <td>Rp <?php echo number_format($mustahik['hak'] * $harga_beras, 0, ',', '.'); ?></td>
                                        <td>
                                            <?php if ($mustahik['status'] == 'belum_terdistribusi'): ?>
                                                <span class="badge bg-warning">Belum Terdistribusi</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Terdistribusi</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary edit-warga-btn" 
                                                data-id="<?php echo $mustahik['id_mustahikwarga']; ?>"
                                                data-nama="<?php echo htmlspecialchars($mustahik['nama']); ?>"
                                                data-kategori="<?php echo htmlspecialchars($mustahik['kategori']); ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-warga-btn" 
                                                data-id="<?php echo $mustahik['id_mustahikwarga']; ?>"
                                                data-nama="<?php echo htmlspecialchars($mustahik['nama']); ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php if ($mustahik['status'] == 'belum_terdistribusi'): ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Lainnya Tab -->
            <div class="tab-pane fade" id="lainnya" role="tabpanel" aria-labelledby="lainnya-tab">
                <div class="row">
                    <!-- Add Distribution Form -->
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Tambah Mustahik Lainnya</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="create_lainnya">
                                    <div class="mb-3">
                                        <label class="form-label">Nama</label>
                                        <input type="text" class="form-control" name="nama" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Kategori</label>
                                        <select class="form-select" name="kategori" required>
                                            <option value="">Pilih Kategori</option>
                                            <?php foreach ($kategori_lainnya as $k): ?>
                                            <option value="<?php echo htmlspecialchars($k['nama_kategori']); ?>" data-id="<?php echo $k['id_kategori']; ?>">
                                                [<?php echo $k['id_kategori']; ?>] <?php echo htmlspecialchars($k['display_name'] ?? $k['nama_kategori']); ?> 
                                                (<?php echo number_format($k['jumlah_hak'] ?? 0, 1); ?>%)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Simpan</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Allocation Info Card -->
                    <div class="col-md-8 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Alokasi Per Kategori</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Alokasi Beras:</h6>
                                        <p><strong>Fakir (40%):</strong> <?php echo number_format($total_hak_fakir, 2); ?> kg</p>
                                        <p><strong>Miskin (20%):</strong> <?php echo number_format($total_hak_miskin, 2); ?> kg</p>
                                        <p><strong>Amil (12.5%):</strong> <?php echo number_format($total_hak_amilin, 2); ?> kg</p>
                                        <p><strong>Fisabilillah (25.5%):</strong> <?php echo number_format($total_hak_fisabilillah, 2); ?> kg</p>
                                        <p><strong>Mualaf (1%):</strong> <?php echo number_format($total_hak_mualaf, 2); ?> kg</p>
                                        <p><strong>Gharimin (0.5%):</strong> <?php echo number_format($total_hak_gharimin, 2); ?> kg</p>
                                        <p><strong>Ibnu Sabil (0.5%):</strong> <?php echo number_format($total_hak_ibnu_sabil, 2); ?> kg</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Alokasi Uang:</h6>
                                        <p><strong>Fakir (40%):</strong> Rp <?php echo number_format($total_fakir_uang, 0, ',', '.'); ?></p>
                                        <p><strong>Miskin (20%):</strong> Rp <?php echo number_format($total_miskin_uang, 0, ',', '.'); ?></p>
                                        <p><strong>Amil (12.5%):</strong> Rp <?php echo number_format($total_amilin_uang, 0, ',', '.'); ?></p>
                                        <p><strong>Fisabilillah (25.5%):</strong> Rp <?php echo number_format($total_fisabilillah_uang, 0, ',', '.'); ?></p>
                                        <p><strong>Mualaf (1%):</strong> Rp <?php echo number_format($total_mualaf_uang, 0, ',', '.'); ?></p>
                                        <p><strong>Gharimin (0.5%):</strong> Rp <?php echo number_format($total_gharimin_uang, 0, ',', '.'); ?></p>
                                        <p><strong>Ibnu Sabil (0.5%):</strong> Rp <?php echo number_format($total_ibnu_sabil_uang, 0, ',', '.'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Mustahik Lainnya Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Daftar Mustahik Lainnya</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama</th>
                                        <th>ID Kategori</th>
                                        <th>Kategori</th>
                                        <th>Hak Beras (kg)</th>
                                        <th>Hak Uang (Rp)</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mustahik_lainnya as $mustahik): ?>
                                    <tr>
                                        <td><?php echo $mustahik['id_mustahiklainnya']; ?></td>
                                        <td><?php echo htmlspecialchars($mustahik['nama']); ?></td>
                                        <td><?php echo $mustahik['id_kategori'] ?? '-'; ?></td>
                                        <td><?php echo ucfirst($mustahik['fixed_kategori'] ?: 'Tidak ada kategori'); ?></td>
                                        <td><?php echo number_format($mustahik['hak'], 2); ?></td>
                                        <td>Rp <?php echo number_format($mustahik['hak'] * $harga_beras, 0, ',', '.'); ?></td>
                                        <td>
                                            <?php if ($mustahik['status'] == 'belum_terdistribusi'): ?>
                                                <span class="badge bg-warning">Belum Terdistribusi</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Terdistribusi</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary edit-lainnya-btn" 
                                                data-id="<?php echo $mustahik['id_mustahiklainnya']; ?>"
                                                data-nama="<?php echo htmlspecialchars($mustahik['nama']); ?>"
                                                data-kategori="<?php echo htmlspecialchars($mustahik['fixed_kategori']); ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-lainnya-btn" 
                                                data-id="<?php echo $mustahik['id_mustahiklainnya']; ?>"
                                                data-nama="<?php echo htmlspecialchars($mustahik['nama']); ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- Edit Warga Modal -->
<div class="modal fade" id="editWargaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Mustahik Warga</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_warga">
                <input type="hidden" name="id" id="edit_warga_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama</label>
                        <input type="text" class="form-control" name="nama" id="edit_warga_nama" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select class="form-select" name="kategori" id="edit_warga_kategori" required>
                            <?php foreach ($kategori_warga as $k): ?>
                            <option value="<?php echo htmlspecialchars($k['nama_kategori']); ?>">
                                <?php echo htmlspecialchars($k['nama_kategori']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Lainnya Modal -->
<div class="modal fade" id="editLainnyaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Mustahik Lainnya</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_lainnya">
                <input type="hidden" name="id" id="edit_lainnya_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama</label>
                        <input type="text" class="form-control" name="nama" id="edit_lainnya_nama" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select class="form-select" name="kategori" id="edit_lainnya_kategori" required>
                            <?php foreach ($kategori_lainnya as $k): ?>
                            <option value="<?php echo htmlspecialchars($k['nama_kategori']); ?>" data-id="<?php echo $k['id_kategori']; ?>">
                                [<?php echo $k['id_kategori']; ?>] <?php echo htmlspecialchars($k['display_name'] ?? $k['nama_kategori']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Warga Modal -->
<div class="modal fade" id="deleteWargaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hapus Mustahik Warga</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus mustahik warga <strong id="delete_warga_nama"></strong>?</p>
                <form method="POST">
                    <input type="hidden" name="action" value="delete_warga">
                    <input type="hidden" name="id" id="delete_warga_id">
                    <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Lainnya Modal -->
<div class="modal fade" id="deleteLainnyaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hapus Mustahik Lainnya</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus mustahik lainnya <strong id="delete_lainnya_nama"></strong>?</p>
                <form method="POST">
                    <input type="hidden" name="action" value="delete_lainnya">
                    <input type="hidden" name="id" id="delete_lainnya_id">
                    <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Distribusi Modal -->
<div class="modal fade" id="distribusiModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Distribusi Otomatis</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Informasi Penting
                    <p class="mb-0">Pastikan semua data mustahik sudah diinput sebelum melakukan distribusi otomatis!</p>
                </div>

                <h6 class="mb-3">Ringkasan Data Mustahik:</h6>
                
                <!-- Data Mustahik Warga -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="m-0">Mustahik Warga</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <p><strong>Fakir:</strong> <?php echo $jumlah_per_kategori_warga['fakir'] ?? 0; ?> orang</p>
                                <p class="text-muted">Estimasi: <?php echo number_format(($total_zakat * 0.40) / max(1, $jumlah_per_kategori_warga['fakir'] ?? 0), 2); ?> kg/orang</p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Miskin:</strong> <?php echo $jumlah_per_kategori_warga['miskin'] ?? 0; ?> orang</p>
                                <p class="text-muted">Estimasi: <?php echo number_format(($total_zakat * 0.20) / max(1, $jumlah_per_kategori_warga['miskin'] ?? 0), 2); ?> kg/orang</p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Mampu:</strong> <?php echo $jumlah_per_kategori_warga['mampu'] ?? 0; ?> orang</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Mustahik Lainnya -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="m-0">Mustahik Lainnya</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <p><strong>Amil:</strong> <?php echo $jumlah_per_kategori_lainnya['amilin'] ?? 0; ?> orang</p>
                                <p class="text-muted">Estimasi: <?php echo number_format(($total_zakat * 0.125) / max(1, $jumlah_per_kategori_lainnya['amilin'] ?? 0), 2); ?> kg/orang</p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Fisabilillah:</strong> <?php echo $jumlah_per_kategori_lainnya['fisabilillah'] ?? 0; ?> orang</p>
                                <p class="text-muted">Estimasi: <?php echo number_format(($total_zakat * 0.255) / max(1, $jumlah_per_kategori_lainnya['fisabilillah'] ?? 0), 2); ?> kg/orang</p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Mualaf:</strong> <?php echo $jumlah_per_kategori_lainnya['mualaf'] ?? 0; ?> orang</p>
                                <p class="text-muted">Estimasi: <?php echo number_format(($total_zakat * 0.01) / max(1, $jumlah_per_kategori_lainnya['mualaf'] ?? 0), 2); ?> kg/orang</p>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-4">
                                <p><strong>Gharimin:</strong> <?php echo $jumlah_per_kategori_lainnya['gharimin'] ?? 0; ?> orang</p>
                                <p class="text-muted">Estimasi: <?php echo number_format(($total_zakat * 0.005) / max(1, $jumlah_per_kategori_lainnya['gharimin'] ?? 0), 2); ?> kg/orang</p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Ibnu Sabil:</strong> <?php echo $jumlah_per_kategori_lainnya['ibnu sabil'] ?? 0; ?> orang</p>
                                <p class="text-muted">Estimasi: <?php echo number_format(($total_zakat * 0.005) / max(1, $jumlah_per_kategori_lainnya['ibnu sabil'] ?? 0), 2); ?> kg/orang</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary -->
                <div class="alert alert-success">
                    <h6>Ringkasan Zakat:</h6>
                    <p class="mb-1">Total Zakat: <?php echo number_format($total_zakat, 2); ?> kg</p>
                    <p class="mb-1">Sudah Terdistribusi: <?php echo number_format($total_distributed, 2); ?> kg</p>
                    <p class="mb-0">Sisa Zakat: <?php echo number_format($sisa_zakat, 2); ?> kg</p>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="distribusi_otomatis">
                    <button type="submit" class="btn btn-primary">Lakukan Distribusi</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reset Modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reset Distribusi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> Peringatan!
                    <p class="mb-0">Tindakan ini akan menghapus semua data distribusi yang sudah ada. Semua hak akan dikembalikan ke 0 dan status akan diubah menjadi "belum terdistribusi".</p>
                </div>
                <p>Apakah Anda yakin ingin melakukan reset distribusi?</p>
                <?php if ($auth->hasPermission('reset_distribusi')): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="reset_distribusi">
                    <button type="submit" class="btn btn-warning">Ya, Reset Distribusi</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                </form>
                <?php else: ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> Anda tidak memiliki izin untuk mereset distribusi.
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Harga Beras Modal -->
<div class="modal fade" id="modalHargaBeras" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Atur Harga Beras</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Harga Beras (Rp/kg)</label>
                        <input type="number" class="form-control" name="harga_beras" value="<?php echo $harga_beras; ?>" required>
                        <small class="text-muted">Harga ini akan digunakan untuk konversi zakat beras ke uang</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="update_harga_beras" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Set template variables
$page_title = 'Distribusi Zakat Fitrah';
$current_page = 'distribusi';
$base_path = '../';

// Additional JavaScript
$additional_js = <<<JS
<script src="{$base_path}assets/js/distribusi.js"></script>
<script>
// Fix to ensure tabs and modals work correctly
document.addEventListener('DOMContentLoaded', function() {
    // Force show the active tab content
    const activeTabContent = document.querySelector('.tab-pane.active');
    if (activeTabContent) {
        activeTabContent.classList.add('show');
    }
    
    // Initialize any Bootstrap modals
    [].slice.call(document.querySelectorAll('.modal')).forEach(function(modalEl) {
        new bootstrap.Modal(modalEl);
    });
    
    // Setup edit buttons for mustahik lainnya
    document.querySelectorAll('.edit-lainnya-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nama = this.getAttribute('data-nama');
            const kategori = this.getAttribute('data-kategori');
            
            document.getElementById('edit_lainnya_id').value = id;
            document.getElementById('edit_lainnya_nama').value = nama;
            
            // Find the correct option based on the displayed category
            const select = document.getElementById('edit_lainnya_kategori');
            let found = false;
            
            // First try to match by nama_kategori
            for (let i = 0; i < select.options.length; i++) {
                const option = select.options[i];
                if (option.text.toLowerCase().includes(kategori.toLowerCase())) {
                    select.selectedIndex = i;
                    found = true;
                    break;
                }
            }
            
            // If not found, try matching by value
            if (!found) {
                for (let i = 0; i < select.options.length; i++) {
                    const option = select.options[i];
                    if (option.value.toLowerCase() === kategori.toLowerCase()) {
                        select.selectedIndex = i;
                        found = true;
                        break;
                    }
                }
            }
            
            // Special case handling for categories with multiple names
            if (!found) {
                if (kategori.toLowerCase() === 'ibnu sabil') {
                    // Find ibnu_sabil
                    for (let i = 0; i < select.options.length; i++) {
                        const option = select.options[i];
                        if (option.value.toLowerCase() === 'ibnu_sabil') {
                            select.selectedIndex = i;
                            break;
                        }
                    }
                } else if (kategori.toLowerCase() === 'mualaf') {
                    // Find muallaf
                    for (let i = 0; i < select.options.length; i++) {
                        const option = select.options[i];
                        if (option.value.toLowerCase() === 'muallaf') {
                            select.selectedIndex = i;
                            break;
                        }
                    }
                }
            }
            
            const modal = new bootstrap.Modal(document.getElementById('editLainnyaModal'));
            modal.show();
        });
    });
});
</script>
JS;

// Include template
include '../views/templates/layout.php';
?> 