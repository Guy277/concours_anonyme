<?php
// Version simplifiée de TCPDF pour export PDF
class TCPDF {
    private $content = "";
    private $title = "";
    
    public function __construct($orientation = "P", $unit = "mm", $format = "A4") {
        // Constructor simplifié
    }
    
    public function SetCreator($creator) {
        return $this;
    }
    
    public function SetAuthor($author) {
        return $this;
    }
    
    public function SetTitle($title) {
        $this->title = $title;
        return $this;
    }
    
    public function SetSubject($subject) {
        return $this;
    }
    
    public function AddPage() {
        return $this;
    }
    
    public function SetFont($family, $style = "", $size = 0) {
        return $this;
    }
    
    public function Cell($w, $h = 0, $txt = "", $border = 0, $ln = 0, $align = "") {
        $this->content .= $txt . " ";
        return $this;
    }
    
    public function Ln($h = null) {
        $this->content .= "\n";
        return $this;
    }
    
    public function WriteHTML($html) {
        $this->content .= strip_tags($html) . "\n";
        return $this;
    }
    
    public function Output($name = "doc.pdf", $dest = "I") {
        // Pour la démo, on génère un fichier texte
        if ($dest == "F") {
            file_put_contents($name, "PDF Content:\n" . $this->title . "\n\n" . $this->content);
        } else {
            header("Content-Type: application/pdf");
            header("Content-Disposition: attachment; filename=\"" . $name . "\"");
            echo "PDF Content:\n" . $this->title . "\n\n" . $this->content;
        }
    }
}
?>