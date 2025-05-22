<?php

class ReportHelper {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function getDateRange($report_type) {
        $dates = [
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d'),
            'title' => ''
        ];
        
        switch ($report_type) {
            case 'daily':
                $dates['title'] = "Laporan Harian: " . date('d M Y');
                break;
                
            case 'weekly':
                $dates['start_date'] = date('Y-m-d', strtotime('monday this week'));
                $dates['end_date'] = date('Y-m-d', strtotime('sunday this week'));
                $dates['title'] = "Laporan Mingguan: " . date('d M Y', strtotime('monday this week')) . 
                                 " - " . date('d M Y', strtotime('sunday this week'));
                break;
                
            case 'monthly':
                $dates['start_date'] = date('Y-m-01');
                $dates['end_date'] = date('Y-m-t');
                $dates['title'] = "Laporan Bulanan: " . date('M Y');
                break;
                
            default:
                if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
                    $dates['start_date'] = $_GET['start_date'];
                    $dates['end_date'] = $_GET['end_date'];
                    $dates['title'] = "Laporan Kustom: " . date('d M Y', strtotime($dates['start_date'])) . 
                                    " - " . date('d M Y', strtotime($dates['end_date']));
                }
        }
        
        return $dates;
    }
    
    public function getZakatSummary($start_date, $end_date) {
        // Get basic zakat summary information
        $stmt = $this->db->prepare("SELECT COUNT(*) as total_transaksi, 
                                         SUM(bayar_beras) as total_beras, 
                                         SUM(bayar_uang) as total_uang
                                  FROM bayarzakat 
                                  WHERE DATE(created_at) BETWEEN ? AND ?");
        $stmt->execute([$start_date, $end_date]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get total muzakki (unique muzakki in transactions)
        $stmt = $this->db->prepare("SELECT COUNT(DISTINCT nama_KK) as total_muzakki 
                                  FROM bayarzakat 
                                  WHERE DATE(created_at) BETWEEN ? AND ?");
        $stmt->execute([$start_date, $end_date]);
        $muzakki_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $summary['total_muzakki'] = $muzakki_result['total_muzakki'] ?? 0;
        
        // Get total jiwa (sum of jumlah_tanggungan in transactions)
        $stmt = $this->db->prepare("SELECT SUM(jumlah_tanggungan) as total_jiwa 
                                  FROM bayarzakat 
                                  WHERE DATE(created_at) BETWEEN ? AND ?");
        $stmt->execute([$start_date, $end_date]);
        $jiwa_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $summary['total_jiwa'] = $jiwa_result['total_jiwa'] ?? 0;
        
        return $summary;
    }
    
    public function getDistributionSummary($start_date, $end_date) {
        $stmt = $this->db->prepare("SELECT COUNT(*) as total_distribusi, 
                                         SUM(hak) as total_distribusi_beras
                                  FROM (
                                    SELECT id_mustahikwarga as id, hak, created_at 
                                    FROM mustahik_warga
                                    UNION ALL
                                    SELECT id_mustahiklainnya as id, hak, created_at 
                                    FROM mustahik_lainnya
                                  ) as distribusi
                                  WHERE DATE(created_at) BETWEEN ? AND ?");
        $stmt->execute([$start_date, $end_date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getDistributionByCategory($start_date, $end_date) {
        // First get distribution for mustahik_warga (which only uses beras)
        $stmt = $this->db->prepare("SELECT 
                                    kategori, 
                                    COUNT(*) as jumlah, 
                                    SUM(hak) as total_hak,
                                    0 as total_uang
                                  FROM mustahik_warga
                                  WHERE DATE(created_at) BETWEEN ? AND ?
                                  GROUP BY kategori");
        $stmt->execute([$start_date, $end_date]);
        $warga_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Next get distribution for mustahik_lainnya (which can have uang)
        $stmt = $this->db->prepare("SELECT 
                                    kategori, 
                                    COUNT(*) as jumlah, 
                                    SUM(hak) as total_hak,
                                    SUM(hak_uang) as total_uang
                                  FROM mustahik_lainnya
                                  WHERE DATE(created_at) BETWEEN ? AND ?
                                  GROUP BY kategori");
        $stmt->execute([$start_date, $end_date]);
        $lainnya_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combine both results
        $combined_distribution = array_merge($warga_distribution, $lainnya_distribution);
        
        // Group by kategori and sum the values
        $result = [];
        foreach ($combined_distribution as $dist) {
            $kategori = $dist['kategori'];
            if (!isset($result[$kategori])) {
                $result[$kategori] = [
                    'kategori' => $kategori,
                    'jumlah' => 0,
                    'total_hak' => 0,
                    'total_uang' => 0
                ];
            }
            $result[$kategori]['jumlah'] += $dist['jumlah'];
            $result[$kategori]['total_hak'] += $dist['total_hak'];
            $result[$kategori]['total_uang'] += $dist['total_uang'];
        }
        
        return array_values($result);
    }
    
    public function getLembagaInfo() {
        $stmt = $this->db->query("SELECT * FROM lembaga_zakat ORDER BY id DESC LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
} 