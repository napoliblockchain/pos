<?php
// SIP-021 fase 3, e2e reale: browser simulato con cURL -> gateway TLS -> POS v2 -> API Napay -> chain Besu usa-e-getta.
// Fasi: create (incasso dal tastierino, QR) -> transfer reale dal runner -> verify (stato, ricevuta, annullamento).
// I cookie jar dei dispositivi restano in /state tra le fasi. Modalità --logs: nessun segreto nei log dei container.
if (PHP_SAPI !== 'cli') exit(1);
$e2e = '/e2e'; $state = '/state';
$password = trim((string) file_get_contents($e2e . '/password'));
$clientKey = trim((string) file_get_contents($e2e . '/pos-secrets/client-key'));
if (!preg_match('/^[0-9a-f]{48}$/D', $password) || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $clientKey)) exit(1);
$phase = $argv[1] ?? '';
$checks = 0;
function check($condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    ++$checks; echo 'PASS: ' . $label . "\n";
}
if ($phase === '--logs') {
    $logs = stream_get_contents(STDIN);
    check($logs !== '' && !preg_match('/napos_[A-Za-z0-9_-]{43}/', $logs) && !str_contains($logs, $clientKey) && !str_contains($logs, $password), 'Gateway, POS and Napay logs hold no bearer, client key or password');
    exit(0);
}
if (!in_array($phase, ['create', 'verify'], true)) exit(1);
$fixture = json_decode((string) file_get_contents($e2e . '/fixture.json'), true, 8, JSON_THROW_ON_ERROR);

/** Un dispositivo = un cookie jar persistente in /state; solo HTTPS con la CA del test, nessun redirect seguito. */
final class Device
{
    public array $seen = [];
    public function __construct(public string $origin, private string $jar) {}
    public function request(string $path, ?array $form = null, array $headers = []): array
    {
        $curl = curl_init($this->origin . $path);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30,
            CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*', CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CAINFO => '/e2e/ca.pem', CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_HTTPHEADER => $headers]);
        if ($form !== null) curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($form)]);
        $raw = curl_exec($curl);
        if ($raw === false) { curl_close($curl); return ['status' => 0, 'headers' => '', 'body' => '']; }
        $size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $r = ['status' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'headers' => substr($raw, 0, $size), 'body' => substr($raw, $size)];
        curl_close($curl);
        $this->seen[] = $r['headers'] . $r['body'];
        return $r;
    }
    public function cookies(): string { return (string) file_get_contents($this->jar); }
}
function location(array $r): string { return preg_match('/^Location:\s*(\S+)/mi', $r['headers'], $m) ? html_entity_decode($m[1]) : ''; }
function hidden(string $html, string $name): ?string {
    return preg_match('/name="' . preg_quote($name, '/') . '" value="([^"]*)"/', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES) : null;
}
/** Form POST della pagina che contiene il testo indicato; restituisce azione e campi nascosti. */
function form(string $html, string $contains): array {
    preg_match_all('~<form[^>]*action="([^"]+)"[^>]*method="post"[^>]*>(.*?)</form>~s', $html, $forms, PREG_SET_ORDER);
    foreach ($forms as [, $action, $inner]) {
        if (!str_contains(html_entity_decode($inner, ENT_QUOTES) . html_entity_decode($action), $contains)) continue;
        preg_match_all('/<input type="hidden" name="([^"]+)" value="([^"]*)"/', $inner, $fields, PREG_SET_ORDER);
        $data = [];
        foreach ($fields as [, $name, $value]) $data[html_entity_decode($name)] = html_entity_decode($value, ENT_QUOTES);
        return [html_entity_decode($action), $data];
    }
    throw new RuntimeException('Form missing: ' . $contains);
}
function collect(Device $device, string $amount): array {
    [$action, $fields] = form($device->request('/')['body'], 'Incassa TTS');
    return $device->request($action, $fields + ['AmountForm' => ['amount' => $amount]]);
}
function paymentId(array $r): string {
    return preg_match('~/payment/view\?id=([0-9a-f-]{36})~', location($r), $m) ? $m[1] : '';
}

$a = new Device('https://pos.local', $state . '/device-a.jar');
$label = $fixture['store'] . ' - ' . $fixture['terminals'][0];

if ($phase === 'create') {
    $page = $a->request('/site/login');
    $login = $a->request('/site/login', ['_pos_csrf' => hidden($page['body'], '_pos_csrf'), 'OperatorLoginForm' => ['email' => $fixture['operators'][0], 'password' => $password]]);
    check($login['status'] === 302, 'Operator logs in on the POS');
    [$action, $fields] = form($a->request('/site/account')['body'], $label);
    check($a->request($action, $fields)['status'] === 302, 'Operator opens Cassa 1');
    $till = $a->request('/');
    check($till['status'] === 200 && str_contains($till['body'], 'Incassa TTS') && str_contains($till['body'], 'Solo chain di test'), 'Till offers TTS collection');
    $bad = collect($a, '1000,01');
    check($bad['status'] === 422 && str_contains($bad['body'], 'Importo massimo'), 'Amount over the limit refused on the POS');
    $created = collect($a, '12,34');
    $id = paymentId($created);
    check($created['status'] === 302 && $id !== '', 'Collect 12,34: payment created in Napay, redirect to its page');
    $view = $a->request('/payment/view?id=' . $id);
    check($view['status'] === 200 && str_contains($view['body'], '<svg') && str_contains($view['body'], '12.34') && str_contains($view['body'], 'In attesa del pagamento')
        && str_contains(strtolower($view['body']), $fixture['wallet']) && str_contains($view['body'], 'data-payment-status'), 'Payment page: QR, amount, merchant wallet resolved by Napay, polling');
    $status = json_decode($a->request('/payment/status?id=' . $id, null, ['Accept: application/json'])['body'], true);
    check($status === ['status' => 'pending'], 'Status endpoint: pending before any transfer');
    $cancelId = paymentId(collect($a, '3'));
    [$action, $fields] = form($a->request('/payment/view?id=' . $cancelId)['body'], 'Annulla pagamento');
    $a->request($action, $fields);
    check(str_contains($a->request('/payment/view?id=' . $cancelId)['body'], 'Annullato'), 'Second payment cancelled from the POS');
    $other = new Device('https://pos.local', $state . '/device-b.jar');
    check($other->request('/payment/view?id=' . $id)['status'] === 302, 'Another browser cannot see the payment');
    $browser = implode("\n", $a->seen) . $a->cookies();
    check(!preg_match('/napos_[A-Za-z0-9_-]{43}/', $browser) && !str_contains($browser, $clientKey) && !preg_match('/napay:[0-9a-f]{32}/', $browser),
        'No Napay bearer, client key or payment reference as text in the browser (reference only inside the QR)');
    file_put_contents($state . '/pay-ids', $id);
    file_put_contents($state . '/ids.json', json_encode(['paid' => $id, 'cancelled' => $cancelId]));
    echo "PASS $checks POS payments e2e create checks\n";
    exit(0);
}

$ids = json_decode((string) file_get_contents($state . '/ids.json'), true);
$paid = json_decode((string) file_get_contents($state . '/paid.json'), true);
check(is_array($paid) && count($paid) === 1 && preg_match('/^0x[0-9a-f]{64}$/', $paid[0]['tx'] ?? ''), 'Real transfer mined on the disposable chain');
$final = null;
for ($i = 0; $i < 20 && $final === null; $i++) {
    $status = json_decode($a->request('/payment/status?id=' . $ids['paid'], null, ['Accept: application/json'])['body'], true)['status'] ?? null;
    if ($status !== 'pending') $final = $status; else sleep(1);
}
check($final === 'paid', 'POS polling sees the payment confirmed by Napay on chain');
$view = $a->request('/payment/view?id=' . $ids['paid']);
check(str_contains($view['body'], 'Pagato') && str_contains($view['body'], substr($paid[0]['tx'], 0, 12)) && !str_contains($view['body'], '<svg'), 'Paid page: transaction shown, QR gone');
$receipt = $a->request('/payment/receipt?id=' . $ids['paid']);
check($receipt['status'] === 200 && str_starts_with($receipt['body'], '%PDF-') && preg_match('~^Content-Type:\s*application/pdf~mi', $receipt['headers'])
    && preg_match('/Cache-Control:\s*no-store/i', $receipt['headers']), 'A7 PDF receipt served, not cacheable');
check($a->request('/payment/receipt?id=' . $ids['cancelled'])['status'] === 404, 'No receipt for the cancelled payment');
check(str_contains($a->request('/payment/view?id=' . $ids['cancelled'])['body'], 'Annullato'), 'Cancelled payment stays cancelled');
$browser = implode("\n", $a->seen) . $a->cookies();
check(!preg_match('/napos_[A-Za-z0-9_-]{43}/', $browser) && !str_contains($browser, $clientKey), 'No Napay bearer or client key in POS pages, headers or cookies');
echo "PASS $checks POS payments e2e verify checks\n";
