<?php
namespace app\components;

use Com\Tecnick\Barcode\Barcode;
use Com\Tecnick\Pdf\Tcpdf;

/**
 * SIP-021 fase 3: QR del pagamento (SVG) e ricevuta PDF A7 come nel POS legacy, con tc-lib-pdf/tc-lib-barcode.
 * Font: solo metriche dei font core PDF in `fonts/core`, convertite una volta da tc-font-mirror 2.4.0 (vedi fonts/README).
 */
final class PaymentDocuments
{
    /** QR in SVG da incorporare nella pagina (senza prologo XML); contenuto nel formato letto dal wallet. */
    public static function qrSvg(string $content): string
    {
        return (new Barcode())->getBarcodeObj('QRCODE,M', $content, -6, -6, 'black', [-2, -2, -2, -2])->getInlineSvgCode();
    }

    /**
     * Ricevuta A7 di un pagamento concluso.
     * @param array{label:string, operator:string, amount:string, paid_at:int, id:string, tx_hash:string} $receipt
     */
    public static function receiptPdf(array $receipt): string
    {
        if (!\defined('K_PATH_FONTS')) \define('K_PATH_FONTS', \dirname(__DIR__) . '/fonts/core');
        $pdf = new Tcpdf('mm', true, false, true);
        $pdf->setCreator('Napay POS');
        $pdf->setTitle(\Yii::t('app', 'Receipt') . ' ' . $receipt['id']);
        $pdf->addPage(['format' => 'A7', 'orientation' => 'P']);
        $date = (new \DateTimeImmutable('@' . $receipt['paid_at']))->setTimezone(new \DateTimeZone('Europe/Rome'))->format('d/m/Y H:i');
        $rows = [
            ['B', 11, \Yii::t('app', 'Payment receipt')],
            ['', 8, $receipt['label']],
            ['', 8, \Yii::t('app', 'Operator: {name}', ['name' => $receipt['operator']])],
            ['', 8, $date],
            ['B', 14, $receipt['amount'] . ' TTS'],
            ['', 7, \Yii::t('app', 'Payment {id}', ['id' => substr($receipt['id'], 0, 8)])],
            ['', 7, \Yii::t('app', 'Transaction')],
            ['', 7, substr($receipt['tx_hash'], 0, 12) . '...' . substr($receipt['tx_hash'], -10)],
            ['', 6, \Yii::t('app', 'Test chain only: no real money.')],
        ];
        $y = 6;
        foreach ($rows as [$style, $size, $text]) {
            // il font corrente misura il testo: va inserito prima di ogni riga, non solo emesso nel contenuto
            $font = $pdf->font->insert($pdf->pon, 'helvetica', $style, $size);
            $pdf->page->addContent($font['out']);
            $pdf->page->addContent($pdf->getTextCell($text, 4, $y, 66, 0, 0, 0, 'T', 'C'));
            $y += $size * 0.6;
        }
        return $pdf->getOutPDFString();
    }
}
