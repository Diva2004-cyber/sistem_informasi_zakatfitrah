<?php
namespace PhpOffice\PhpSpreadsheet\Writer;

/**
 * Interface IWriter.
 */
interface IWriter
{
    /**
     * Save PhpSpreadsheet to file.
     *
     * @param string $pFilename Name of the file to save
     */
    public function save($pFilename);
} 