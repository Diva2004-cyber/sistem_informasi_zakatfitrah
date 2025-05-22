<?php
namespace PhpOffice\PhpSpreadsheet;

class Worksheet
{
    private $parent;
    private $cells = [];

    public function __construct(Spreadsheet $parent)
    {
        $this->parent = $parent;
    }

    public function setCellValue($coordinate, $value)
    {
        $this->cells[$coordinate] = $value;
        return $this;
    }

    public function getCell($coordinate)
    {
        return isset($this->cells[$coordinate]) ? $this->cells[$coordinate] : null;
    }

    public function toArray()
    {
        $result = [];
        foreach ($this->cells as $coordinate => $value) {
            preg_match('/([A-Z]+)(\d+)/', $coordinate, $matches);
            $col = $matches[1];
            $row = $matches[2];
            
            if (!isset($result[$row - 1])) {
                $result[$row - 1] = [];
            }
            $result[$row - 1][$this->columnIndexFromString($col)] = $value;
        }
        return $result;
    }

    private function columnIndexFromString($column)
    {
        $column = strtoupper($column);
        $length = strlen($column);
        $result = 0;
        
        for ($i = 0; $i < $length; $i++) {
            $result *= 26;
            $result += ord($column[$i]) - ord('A') + 1;
        }
        
        return $result - 1;
    }
} 