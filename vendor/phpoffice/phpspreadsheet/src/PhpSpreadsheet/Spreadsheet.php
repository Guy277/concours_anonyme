<?php
// Version simplifiée de PhpSpreadsheet pour export Excel
class Spreadsheet {
    private $data = [];
    private $headers = [];
    
    public function getActiveSheet() {
        return new Worksheet($this);
    }
    
    public function setData($data) {
        $this->data = $data;
    }
    
    public function getData() {
        return $this->data;
    }
    
    public function setHeaders($headers) {
        $this->headers = $headers;
    }
    
    public function getHeaders() {
        return $this->headers;
    }
}

class Worksheet {
    private $spreadsheet;
    
    public function __construct($spreadsheet) {
        $this->spreadsheet = $spreadsheet;
    }
    
    public function setCellValue($cell, $value) {
        // Implémentation simplifiée
        return $this;
    }
    
    public function fromArray($data, $nullValue = null, $startCell = "A1") {
        $this->spreadsheet->setData($data);
        return $this;
    }
}

class Writer {
    public static function createWriter($spreadsheet, $format) {
        return new ExcelWriter($spreadsheet);
    }
}

class ExcelWriter {
    private $spreadsheet;
    
    public function __construct($spreadsheet) {
        $this->spreadsheet = $spreadsheet;
    }
    
    public function save($filename) {
        // Export CSV pour simulation Excel
        $data = $this->spreadsheet->getData();
        $headers = $this->spreadsheet->getHeaders();
        
        $output = fopen($filename, "w");
        
        if ($headers) {
            fputcsv($output, $headers);
        }
        
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
    }
}
?>