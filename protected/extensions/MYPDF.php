<?php
//Yii::import('application.extensions.tcpdf.*');
require_once (dirname(__FILE__).'/tcpdf/tcpdf.php');
// Extend the TCPDF class to create custom Header and Footer
class MYPDF extends TCPDF {

    // Page footer
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $text = CHtml::encode(Yii::app()->name).' '.CHtml::encode(Yii::app()->params['versione']).' - '. Yii::app()->params['adminName'];
        $this->Cell(0, 10, $text, 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}
?>
