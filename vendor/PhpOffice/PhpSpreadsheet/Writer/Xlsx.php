<?php
namespace PhpOffice\PhpSpreadsheet\Writer;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

class Xlsx implements IWriter
{
    private $spreadsheet;
    
    public function __construct(Spreadsheet $spreadsheet)
    {
        $this->spreadsheet = $spreadsheet;
    }
    
    public function save($filename)
    {
        // Header untuk eksport
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . basename($filename) . '"');
        header('Cache-Control: max-age=0');
        
        // Buka output
        $output = fopen('php://output', 'w');
        
        // Ambil worksheet aktif
        $worksheet = $this->spreadsheet->getActiveSheet();
        $data = $worksheet->toArray();
        
        // Tulis data ke output sebagai CSV (simple implementation)
        foreach ($data as $row) {
            if (is_array($row)) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
        
        return true;
    }
} 