<?php
namespace PhpOffice\PhpSpreadsheet;

class IOFactory
{
    public static function load($filename)
    {
        if (!file_exists($filename)) {
            throw new \Exception("File not found: $filename");
        }

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        // Simple CSV reader for basic Excel files
        $handle = fopen($filename, 'r');
        if ($handle) {
            $row = 1;
            while (($data = fgetcsv($handle)) !== false) {
                foreach ($data as $col => $value) {
                    $column = self::stringFromColumnIndex($col + 1);
                    $worksheet->setCellValue($column . $row, $value);
                }
                $row++;
            }
            fclose($handle);
        }

        return $spreadsheet;
    }

    private static function stringFromColumnIndex($index)
    {
        $result = '';
        while ($index > 0) {
            $index--;
            $result = chr(65 + ($index % 26)) . $result;
            $index = (int)($index / 26);
        }
        return $result;
    }
} 