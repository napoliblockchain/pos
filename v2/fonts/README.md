# Font della ricevuta PDF

Solo metriche dei font core PDF (Helvetica normale e grassetto), nessun file di
font da incorporare: il lettore PDF usa i propri. Usati da
`components/PaymentDocuments.php` tramite `K_PATH_FONTS`.

Generati una volta con `Com\Tecnick\Pdf\Font\Import` (tc-lib-pdf-font 4.8.0,
codifica cp1252) dai file AFM `core/Helvetica.afm` e `core/Helvetica-Bold.afm`
dell'archivio `tecnickcom/tc-font-mirror` 2.4.0
(`https://github.com/tecnickcom/tc-font-mirror/archive/refs/tags/2.4.0.zip`,
sha256 `623833e1bcac994fc84542bf8f0311d191d6da4d4cb8d9712a559716fccf42f7`).
Nessun download durante la build dell'immagine.
