<?php
// Include necessary files
require_once '../config/init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID Pembayaran tidak valid');
}

$id_zakat = (int)$_GET['id'];

// Get payment details
$stmt = $db->prepare("SELECT 
                        b.id_zakat,
                        b.nama_KK,
                        b.jumlah_tanggungan,
                        b.jumlah_tanggunganyangdibayar,
                        b.jenis_bayar,
                        b.bayar_beras,
                        b.bayar_uang,
                        b.created_at,
                        COALESCE(m.alamat, '') as alamat
                      FROM bayarzakat b
                      LEFT JOIN muzakki m ON b.nama_KK = m.nama_muzakki
                      WHERE b.id_zakat = ?");
$stmt->execute([$id_zakat]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if payment exists
if (!$payment) {
    die('Data pembayaran tidak ditemukan');
}

// Get organization information
$stmt = $db->query("SELECT * FROM lembaga_zakat ORDER BY id DESC LIMIT 1");
$lembaga = $stmt->fetch(PDO::FETCH_ASSOC);

// Format payment amount
$jumlah_bayar = '';
if ($payment['jenis_bayar'] === 'beras') {
    $jumlah_bayar = number_format($payment['bayar_beras'], 2, ',', '.') . ' kg beras';
} else {
    $jumlah_bayar = 'Rp ' . number_format($payment['bayar_uang'], 0, ',', '.');
}

// Get harga beras per kg from configuration
$stmt = $db->query("SELECT harga_beras FROM konfigurasi ORDER BY id DESC LIMIT 1");
$config = $stmt->fetch(PDO::FETCH_ASSOC);
$harga_beras = isset($config['harga_beras']) ? $config['harga_beras'] : 15000;

// Calculate nilai per tanggungan
$nilai_per_tanggungan = $payment['jenis_bayar'] === 'beras' ? 2.5 : $harga_beras * 2.5;

// Format date
$tanggal_bayar = date('d F Y', strtotime($payment['created_at']));

// Terbilang function
function terbilang($angka) {
    $angka = abs($angka);
    $baca = array("", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas");
    $terbilang = "";
    
    if ($angka < 12) {
        $terbilang = " " . $baca[$angka];
    } elseif ($angka < 20) {
        $terbilang = terbilang($angka - 10) . " belas";
    } elseif ($angka < 100) {
        $terbilang = terbilang(floor($angka / 10)) . " puluh" . terbilang($angka % 10);
    } elseif ($angka < 200) {
        $terbilang = " seratus" . terbilang($angka - 100);
    } elseif ($angka < 1000) {
        $terbilang = terbilang(floor($angka / 100)) . " ratus" . terbilang($angka % 100);
    } elseif ($angka < 2000) {
        $terbilang = " seribu" . terbilang($angka - 1000);
    } elseif ($angka < 1000000) {
        $terbilang = terbilang(floor($angka / 1000)) . " ribu" . terbilang($angka % 1000);
    } elseif ($angka < 1000000000) {
        $terbilang = terbilang(floor($angka / 1000000)) . " juta" . terbilang($angka % 1000000);
    }
    
    return $terbilang;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kwitansi Pembayaran Zakat Fitrah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            font-size: 14px;
            line-height: 1.6;
        }
        .kwitansi {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .kwitansi-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        .lembaga-name {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .lembaga-address {
            font-size: 14px;
            margin-bottom: 5px;
        }
        .kwitansi-title {
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            text-decoration: underline;
        }
        .kwitansi-body {
            margin-bottom: 30px;
        }
        .kwitansi-footer {
            margin-top: 50px;
            text-align: right;
        }
        .kwitansi-nomor {
            margin-bottom: 20px;
            font-weight: bold;
        }
        table.kwitansi-detail {
            width: 100%;
            margin-bottom: 30px;
        }
        table.kwitansi-detail td {
            padding: 8px 5px;
        }
        .col-label {
            width: 150px;
        }
        .col-separator {
            width: 20px;
            text-align: center;
        }
        .terbilang {
            font-style: italic;
            margin: 20px 0;
            padding: 10px;
            background-color: #f9f9f9;
            border-left: 3px solid #007bff;
        }
        .signature-area {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 45%;
            text-align: center;
        }
        .signature-line {
            margin-top: 70px;
            border-top: 1px solid #333;
            margin-bottom: 5px;
        }
        .btn-container {
            text-align: center;
            margin: 30px 0;
        }
        @media print {
            .no-print {
                display: none;
            }
            .kwitansi {
                border: none;
                box-shadow: none;
            }
            body {
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-4">
        <div class="kwitansi">
            <div class="kwitansi-header">
                <div class="lembaga-name"><?php echo isset($lembaga['nama_lembaga']) ? htmlspecialchars($lembaga['nama_lembaga']) : 'PANITIA ZAKAT FITRAH'; ?></div>
                <div class="lembaga-address"><?php echo isset($lembaga['alamat_lembaga']) ? htmlspecialchars($lembaga['alamat_lembaga']) : ''; ?></div>
                <div><?php echo isset($lembaga['tahun']) ? 'Tahun ' . htmlspecialchars($lembaga['tahun']) : ''; ?></div>
            </div>
            
            <div class="kwitansi-nomor">No. Kwitansi: ZF-<?php echo str_pad($payment['id_zakat'], 3, '0', STR_PAD_LEFT); ?></div>
            
            <div class="kwitansi-title">KWITANSI PEMBAYARAN ZAKAT FITRAH</div>
            
            <div class="kwitansi-body">
                <table class="kwitansi-detail">
                    <tr>
                        <td class="col-label">Telah Diterima Dari</td>
                        <td class="col-separator">:</td>
                        <td><?php echo htmlspecialchars($payment['nama_KK']); ?></td>
                    </tr>
                    <tr>
                        <td class="col-label">Alamat</td>
                        <td class="col-separator">:</td>
                        <td><?php echo htmlspecialchars($payment['alamat']); ?></td>
                    </tr>
                    <tr>
                        <td class="col-label">Jumlah Jiwa</td>
                        <td class="col-separator">:</td>
                        <td><?php echo $payment['jumlah_tanggungan']; ?> orang</td>
                    </tr>
                    <tr>
                        <td class="col-label">Jumlah Dibayarkan</td>
                        <td class="col-separator">:</td>
                        <td>
                        <?php echo $payment['jumlah_tanggunganyangdibayar']; ?> jiwa × 
<?php if ($payment['jenis_bayar'] === 'beras'): ?>
    2,5 kg = <?php echo number_format($payment['jumlah_tanggunganyangdibayar'] * 2.5, 2, ',', '.'); ?> kg beras
<?php else: ?>
    Rp 37.500 = Rp <?php echo number_format($payment['jumlah_tanggunganyangdibayar'] * 37500, 0, ',', '.'); ?>
<?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="col-label">Tanggal</td>
                        <td class="col-separator">:</td>
                        <td><?php echo $tanggal_bayar; ?></td>
                    </tr>
                </table>
                
                <?php
                // Show terbilang
                if ($payment['jenis_bayar'] === 'beras') {
                    $total_beras = $payment['jumlah_tanggunganyangdibayar'] * 2.5;
                    echo '<div class="terbilang">Terbilang: ' . ucfirst(trim(terbilang(floor($total_beras)))) . ' koma ' . 
                         (($total_beras * 100) % 100 > 0 ? terbilang(($total_beras * 100) % 100) : 'nol') . 
                         ' kilogram beras</div>';
                } else {
                    $total_uang = $payment['jumlah_tanggunganyangdibayar'] * 37500;
                    echo '<div class="terbilang">Terbilang: ' . ucfirst(trim(terbilang($total_uang))) . ' rupiah</div>';
                }                
                ?>
            </div>
            
            <div class="signature-area">
                <div class="signature-box">
                    <div>Pembayar Zakat</div>
                    <div class="signature-line"></div>
                    <div><?php echo htmlspecialchars($payment['nama_KK']); ?></div>
                </div>
                <div class="signature-box">
                    <div><?php echo date('d F Y'); ?></div>
                    <div>Penerima Zakat</div>
                    <div class="signature-line"></div>
                    <div>
                        <?php echo isset($lembaga['ketua']) ? htmlspecialchars($lembaga['ketua']) : 'Ketua Panitia'; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="btn-container no-print">
            <button class="btn btn-primary" onclick="window.print()">Cetak Kwitansi</button>
            <a href="bayar_zakat.php" class="btn btn-secondary">Kembali</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 