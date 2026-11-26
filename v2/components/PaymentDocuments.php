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
     * @param array{label:string, operator:?string, amount:string, fiat_amount:string, rate:string, paid_at:int, id:string, tx_hash:string} $receipt
     */
    public static function receiptPdf(array $receipt): string
    {
        $rows = [
            ['B', 11, \Yii::t('app', 'Payment receipt')],
            ['', 8, $receipt['label']],
            $receipt['operator'] === null ? null : ['', 8, \Yii::t('app', 'Operator: {name}', ['name' => $receipt['operator']])],
            ['', 8, self::date($receipt['paid_at'])],
            ['B', 14, $receipt['amount'] . ' TTS'],
            ['', 8, self::money($receipt['fiat_amount']) . ' EUR'],
            ['', 7, \Yii::t('app', '1 TTS = {rate} EUR', ['rate' => self::money($receipt['rate'])])],
            ['', 7, \Yii::t('app', 'Payment {id}', ['id' => substr($receipt['id'], 0, 8)])],
            ['', 7, \Yii::t('app', 'Transaction')],
            ['', 7, substr($receipt['tx_hash'], 0, 12) . '...' . substr($receipt['tx_hash'], -10)],
            ['', 6, self::modeNotice()],
        ];
        return self::pdf($receipt['id'], $rows);
    }

    /**
     * SIP-033 passo 5: ricevuta A7 di un pagamento BTC ricevuto (D1) o confermato: importo nella valuta del negozio,
     * BTC pagati e cambio di BTCPay. Mai indirizzo o fattura.
     * @param array{label:string, operator:?string, amount:string, currency:string, paid_at:int, id:string, settled:bool, paid_btc:?string, rate:?string, txid:?string} $receipt
     */
    public static function btcReceiptPdf(array $receipt): string
    {
        $rows = [
            ['B', 11, \Yii::t('app', 'Payment receipt')],
            ['', 8, $receipt['label']],
            $receipt['operator'] === null ? null : ['', 8, \Yii::t('app', 'Operator: {name}', ['name' => $receipt['operator']])],
            ['', 8, self::date($receipt['paid_at'])],
            ['B', 14, self::money($receipt['amount']) . ' ' . $receipt['currency']],
            ['', 8, \Yii::t('app', 'Paid in Bitcoin')],
            $receipt['paid_btc'] === null ? null : ['B', 9, $receipt['paid_btc'] . ' BTC'],
            $receipt['rate'] === null ? null : ['', 7, \Yii::t('app', '1 BTC = {rate} {currency}', ['rate' => self::money($receipt['rate']), 'currency' => $receipt['currency']])],
            ['', 7, $receipt['settled'] ? \Yii::t('app', 'Payment confirmed') : \Yii::t('app', 'Payment received, waiting for the network confirmation')],
            $receipt['txid'] === null ? null : ['', 7, \Yii::t('app', 'Transaction')],
            $receipt['txid'] === null ? null : ['', 7, substr($receipt['txid'], 0, 12) . '...' . substr($receipt['txid'], -10)],
            ['', 7, \Yii::t('app', 'Payment {id}', ['id' => substr($receipt['id'], 0, 8)])],
        ];
        return self::pdf($receipt['id'], $rows);
    }

    /** Avviso della modalità, uguale su pagine e ricevuta: in produzione nessun riferimento alla chain di test. */
    public static function modeNotice(): string
    {
        return \Yii::t('app', getenv('POS_MODE') === 'production' ? 'Payments managed by Napay.' : 'Test chain only: no real money.');
    }

    /** "61700.00" -> "61.700,00" (testo decimale, mai float). */
    public static function money(string $value): string
    {
        [$whole, $cents] = explode('.', $value);
        return ltrim(strrev(implode('.', str_split(strrev($whole), 3))), '.') . ',' . $cents;
    }

    private static function date(int $at): string
    {
        return (new \DateTimeImmutable('@' . $at))->setTimezone(new \DateTimeZone('Europe/Rome'))->format('d/m/Y H:i');
    }

    /** @param list<?array{0:string,1:int,2:string}> $rows stile, corpo, testo (righe null saltate) */
    private static function pdf(string $id, array $rows): string
    {
        if (!\defined('K_PATH_FONTS')) \define('K_PATH_FONTS', \dirname(__DIR__) . '/fonts/core');
        $pdf = new Tcpdf('mm', true, false, true);
        $pdf->setCreator('Napay POS');
        $pdf->setTitle(\Yii::t('app', 'Receipt') . ' ' . $id);
        $pdf->addPage(['format' => 'A7', 'orientation' => 'P']);
        $y = 6;
        foreach (array_filter($rows) as [$style, $size, $text]) {
            // il font corrente misura il testo: va inserito prima di ogni riga, non solo emesso nel contenuto
            $font = $pdf->font->insert($pdf->pon, 'helvetica', $style, $size);
            $pdf->page->addContent($font['out']);
            $pdf->page->addContent($pdf->getTextCell($text, 4, $y, 66, 0, 0, 0, 'T', 'C'));
            $y += $size * 0.6;
        }
        return $pdf->getOutPDFString();
    }
}
