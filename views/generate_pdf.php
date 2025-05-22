<?php
require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../lib/tcpdf/tcpdf.php'; // Pastikan Anda sudah mengunduh dan menempatkan TCPDF di folder lib

$auth = new Auth();
// Pastikan hanya admin dan petugas yang bisa akses halaman ini
if (!$auth->isLoggedIn() || !in_array($auth->getUserRole(), ['admin', 'petugas'])) {
    header('Location: login.php?error=Akses ditolak! Anda tidak memiliki hak akses ke halaman ini.');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Default ke hari ini
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'daily';

// Set tanggal berdasarkan tipe laporan
if ($report_type == 'daily') {
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
    $title = "Laporan Harian: " . date('d M Y');
} elseif ($report_type == 'weekly') {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date = date('Y-m-d', strtotime('sunday this week'));
    $title = "Laporan Mingguan: " . date('d M Y', strtotime('monday this week')) . " - " . date('d M Y', strtotime('sunday this week'));
} elseif ($report_type == 'monthly') {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
    $title = "Laporan Bulanan: " . date('M Y');
} else {
    $title = "Laporan Kustom: " . date('d M Y', strtotime($start_date)) . " - " . date('d M Y', strtotime($end_date));
}

// Ambil data pembayaran zakat dalam rentang tanggal
$stmt = $db->prepare("SELECT COUNT(*) as total_transaksi, 
                            SUM(bayar_beras) as total_beras, 
                            SUM(bayar_uang) as total_uang
                      FROM bayarzakat 
                      WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$zakat_summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Ambil data distribusi dalam rentang tanggal
$stmt = $db->prepare("SELECT COUNT(*) as total_distribusi, SUM(hak) as total_distribusi_beras
                      FROM (
                        SELECT id_mustahikwarga as id, hak, created_at FROM mustahik_warga
                        UNION ALL
                        SELECT id_mustahiklainnya as id, hak, created_at FROM mustahik_lainnya
                      ) as distribusi
                      WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$distribusi_summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Ambil data per kategori mustahik
$stmt = $db->prepare("SELECT kategori, COUNT(*) as jumlah, SUM(hak) as total_hak
                      FROM (
                        SELECT kategori, hak, created_at FROM mustahik_warga
                        UNION ALL
                        SELECT kategori, hak, created_at FROM mustahik_lainnya
                      ) as kategori_distribusi
                      WHERE DATE(created_at) BETWEEN ? AND ?
                      GROUP BY kategori");
$stmt->execute([$start_date, $end_date]);
$kategori_distribusi = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buat instance TCPDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Sistem Manajemen Zakat Fitrah');
$pdf->SetAuthor('Admin Zakat');
$pdf->SetTitle($title);
$pdf->SetSubject('Laporan Zakat');
$pdf->SetKeywords('Zakat, Laporan, PDF');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, 10, PDF_MARGIN_RIGHT);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', 'B', 16);

// Title
$pdf->Cell(0, 10, $title, 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);
$pdf->Ln(5);

// Ringkasan Zakat
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Ringkasan Zakat', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 12);

$pdf->Cell(80, 8, 'Total Transaksi:', 0, 0);
$pdf->Cell(0, 8, $zakat_summary['total_transaksi'], 0, 1);

$pdf->Cell(80, 8, 'Total Beras:', 0, 0);
$pdf->Cell(0, 8, number_format($zakat_summary['total_beras'], 2) . ' kg', 0, 1);

$pdf->Cell(80, 8, 'Total Uang:', 0, 0);
$pdf->Cell(0, 8, 'Rp ' . number_format($zakat_summary['total_uang'], 0, ',', '.'), 0, 1);

$pdf->Ln(5);

// Ringkasan Distribusi
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Ringkasan Distribusi', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 12);

$pdf->Cell(80, 8, 'Total Distribusi:', 0, 0);
$pdf->Cell(0, 8, $distribusi_summary['total_distribusi'], 0, 1);

$pdf->Cell(80, 8, 'Total Beras Terdistribusi:', 0, 0);
$pdf->Cell(0, 8, number_format($distribusi_summary['total_distribusi_beras'], 2) . ' kg', 0, 1);

$pdf->Ln(5);

// Distribusi per Kategori
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Distribusi per Kategori', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 12);

// Table header
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(60, 8, 'Kategori', 1, 0, 'C', 1);
$pdf->Cell(60, 8, 'Jumlah', 1, 0, 'C', 1);
$pdf->Cell(60, 8, 'Total (kg)', 1, 1, 'C', 1);

// Table data
foreach ($kategori_distribusi as $kategori) {
    $pdf->Cell(60, 8, ucfirst($kategori['kategori']), 1, 0, 'L');
    $pdf->Cell(60, 8, $kategori['jumlah'], 1, 0, 'C');
    $pdf->Cell(60, 8, number_format($kategori['total_hak'], 2), 1, 1, 'R');
}

// Close and output PDF document
$pdf->Output('laporan_zakat_' . $start_date . '_' . $end_date . '.pdf', 'I'); 