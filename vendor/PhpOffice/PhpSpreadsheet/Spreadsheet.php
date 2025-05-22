<?php
namespace PhpOffice\PhpSpreadsheet;

class Spreadsheet
{
    private $activeSheet;
    private $sheets = [];

    public function __construct()
    {
        $this->createSheet();
    }

    public function createSheet($index = null)
    {
        $newSheet = new Worksheet($this);
        if ($index === null) {
            $this->sheets[] = $newSheet;
        } else {
            array_splice($this->sheets, $index, 0, [$newSheet]);
        }
        $this->setActiveSheetIndex(count($this->sheets) - 1);
        return $newSheet;
    }

    public function getActiveSheet()
    {
        return $this->activeSheet;
    }

    public function setActiveSheetIndex($index)
    {
        if (isset($this->sheets[$index])) {
            $this->activeSheet = $this->sheets[$index];
        }
        return $this;
    }

    public function getSheet($index)
    {
        if (isset($this->sheets[$index])) {
            return $this->sheets[$index];
        }
        return null;
    }
} 