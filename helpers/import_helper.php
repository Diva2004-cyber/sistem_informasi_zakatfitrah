<?php

class ImportHelper {
    public static function detectImportFormat($headerRow) {
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

    public static function processRowByFormat($rowData, $formatType) {
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

    public static function containsCorruptedData($string) {
        // Check for common corruption patterns
        // 1. Unusual character sequences
        if (preg_match('/[^a-zA-Z0-9 .,\-\/\(\)]{4,}/', $string)) {
            return true;
        }
        
        // 2. High concentration of special characters
        if (preg_match('/[\^\$\&\%\@\!\*\+\=\|\<\>\{\}\[\]\?]{3,}/', $string)) {
            return true;
        }
        
        // 3. Meaningless repetitions
        if (preg_match('/(.)\1{5,}/', $string)) {
            return true;
        }
        
        // 4. UTF-8 encoding issues (common in Excel imports)
        if (!mb_check_encoding($string, 'UTF-8')) {
            return true;
        }
        
        // 5. Extremely long strings (likely garbage data)
        if (strlen($string) > 100) {
            return true;
        }
        
        return false;
    }
} 