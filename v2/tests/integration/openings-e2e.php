<?php
// SIP-021 fase 2, e2e reale: browser simulato con cURL -> gateway TLS -> POS v2 -> client POS -> gateway TLS -> API Napay.
// Nessun mock: CA privata del test verificata, due cookie jar = due dispositivi. Nessun pagamento.
// Modalità --logs: legge da stdin i log dei container e verifica che non contengano token o segreti.
if (PHP_SAPI !== 'cli') exit(1);
$e2e = '/e2e';
$password = trim((string) file_get_contents($e2e . '/password'));
$clientKey = trim((string) file_get_contents($e2e . '/pos-secrets/client-key'));
if (!preg_match('/^[0-9a-f]{48}$/D', $password) || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $clientKey)) exit(1);
$checks = 0;
function check($condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    ++$checks; echo 'PASS: ' . $label . "\n";
}
if (($argv[1] ?? '') === '--logs') {
    $logs = stream_get_contents(STDIN);
    check($logs !== '' && !preg_match('/napos_[A-Za-z0-9_-]{43}/', $logs) && !str_contains($logs, $clientKey) && !str_contains($logs, $password), 'Gateway, POS and Napay logs hold no bearer, client key or password');
    exit(0);
}
$fixture = json_decode((string) file_get_contents($e2e . '/fixture.json'), true, 8, JSON_THROW_ON_ERROR);
[$op1, $op2] = $fixture['operators'];

/** Un dispositivo = un cookie jar; solo HTTPS con la CA del test, nessun proxy, nessun redirect seguito. */
final class Device
{
    public array $seen = [];
    private string $jar;
    public function __construct(public string $origin) { $this->jar = tempnam(sys_get_temp_dir(), 'e2e-jar-'); }
    public function request(string $path, ?array $form = null, array $headers = []): array
    {
        $curl = curl_init($this->origin . $path);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20,
            CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*', CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CAINFO => '/e2e/ca.pem', CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_HTTPHEADER => $headers]);
        if ($form !== null) curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($form)]);
        $raw = curl_exec($curl);
        if ($raw === false) { $error = curl_errno($curl); curl_close($curl); return ['status' => 0, 'headers' => '', 'body' => '', 'curl' => $error]; }
        $size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $r = ['status' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'headers' => substr($raw, 0, $size), 'body' => substr($raw, $size)];
        curl_close($curl);
        $this->seen[] = $r['headers'] . $r['body'];
        return $r;
    }
    public function cookies(): string { return (string) file_get_contents($this->jar); }
}
function location(array $r): string { return preg_match('/^Location:\s*(\S+)/mi', $r['headers'], $m) ? $m[1] : ''; }
function hidden(string $html, string $name): ?string {
    return preg_match('/name="' . preg_quote($name, '/') . '" value="([^"]*)"/', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES) : null;
}
function meta(string $html): string {
    if (!preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $m)) throw new RuntimeException('CSRF unavailable.');
    return html_entity_decode($m[1], ENT_QUOTES);
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
function posLogin(Device $device, string $email, string $password): array {
    $page = $device->request('/site/login');
    return $device->request('/site/login', ['_pos_csrf' => hidden($page['body'], '_pos_csrf'), 'OperatorLoginForm' => ['email' => $email, 'password' => $password]]);
}
function openTerminal(Device $device, string $label): array {
    $account = $device->request('/site/account');
    [$action, $fields] = form($account['body'], $label);
    return $device->request($action, $fields);
}
function notice(Device $device): string {
    $page = $device->request('/site/account');
    return preg_match('/<p class="notice-line"[^>]*>(.*?)<\/p>/s', $page['body'], $m) ? html_entity_decode($m[1], ENT_QUOTES) : '';
}
function heartbeat(Device $device): ?array {
    $cash = $device->request('/');
    if ($cash['status'] !== 200) return null;
    $r = $device->request('/site/heartbeat', [], ['X-CSRF-Token: ' . meta($cash['body']), 'Accept: application/json']);
    return json_decode($r['body'], true);
}

$label1 = $fixture['store'] . ' - ' . $fixture['terminals'][0];
$label2 = $fixture['store'] . ' - ' . $fixture['terminals'][1];
$a = new Device('https://pos.local'); $b = new Device('https://pos.local');

// --- login reale via Napay e catalogo
check(str_contains($a->request('/site/login')['body'], 'operator-login'), 'Login form served over verified TLS');
$wrong = posLogin($a, $op1, 'not-the-password');
check($wrong['status'] === 200 && str_contains($wrong['body'], 'Email o password non validi'), 'Wrong password refused by Napay through the POS');
$login = posLogin($a, $op1, $password);
check($login['status'] === 302 && str_contains(location($login), '/site/account'), 'Operator 1 logs in on device A');
$account = $a->request('/site/account');
check($account['status'] === 200 && str_contains($account['body'], 'E2E Cassa 1') && str_contains($account['body'], 'E2E Cassa 2'), 'Catalog from Napay lists the granted terminals');

// --- apertura, cassa, seconda apertura bloccata
$open = openTerminal($a, $label1);
check($open['status'] === 302, 'Device A opens Cassa 1');
$cash = $a->request('/');
check($cash['status'] === 200 && str_contains($cash['body'], 'POS APERTO') && str_contains($cash['body'], 'E2E Cassa 1'), 'Cash screen shown only with a Napay-confirmed opening');
check(heartbeat($a) === ['status' => 'ok'], 'Heartbeat from the cash screen accepted by Napay');
check(posLogin($b, $op1, $password)['status'] === 302, 'Same operator logs in on device B (second device)');
$second = openTerminal($b, $label1);
check($second['status'] === 302 && str_contains(location($second), '/site/account') && str_contains(notice($b), 'occupato'), 'Second device cannot open the busy terminal (controlled message)');
check(openTerminal($b, $label2)['status'] === 302 && $b->request('/')['status'] === 200, 'Device B opens the other terminal');

// --- chiusura normale e riapertura dall'altro dispositivo
[$action, $fields] = form($b->request('/')['body'], 'Chiudi POS');
check(str_contains(location($b->request($action, $fields)), '/site/account') && str_contains(notice($b), 'POS chiuso'), 'Device B releases Cassa 2');
[$action, $fields] = form($a->request('/')['body'], 'Chiudi POS');
check(str_contains(location($a->request($action, $fields)), '/site/account'), 'Device A releases Cassa 1');
check(openTerminal($b, $label1)['status'] === 302 && $b->request('/')['status'] === 200, 'Released terminal reopened from device B');

// --- logout libera il terminale
[$action, $fields] = form($b->request('/')['body'], 'Esci');
$logout = $b->request($action, $fields);
check($logout['status'] === 302 && str_contains(location($logout), '/site/login') && str_ends_with(location($b->request('/site/account')), '/site/login'), 'Logout on device B ends its POS session');
check(openTerminal($a, $label1)['status'] === 302 && $a->request('/')['status'] === 200, 'Logout freed Cassa 1: device A opens it');

// --- sblocco forzato dal responsabile nel backoffice Napay
$napay = new Device('https://napay.local');
$page = $napay->request('/site/login');
check($napay->request('/site/login', ['_csrf' => meta($page['body']), 'LoginForm' => ['email' => $fixture['manager'], 'password' => $password]])['status'] === 302, 'Manager logs into the Napay backoffice');
$index = $napay->request('/pos-management/index');
$row = static function (string $html, string $terminal): string {
    preg_match_all('~<tr>(.*?)</tr>~s', $html, $rows);
    return (string) current(array_filter($rows[1], fn($r) => str_contains($r, $terminal)));
};
$live = $row($index['body'], 'E2E Cassa 1');
check($index['status'] === 200 && str_contains($live, 'In uso') && str_contains($live, 'e2e-op1'), 'Backoffice shows the live opening and its holder');
[$action, $fields] = form($live, 'Sblocca');
check($napay->request($action, $fields)['status'] === 302 && str_contains($row($napay->request('/pos-management/index')['body'], 'E2E Cassa 1'), 'Libero'), 'Manager force-unlocks Cassa 1');
check(heartbeat($a) === null && str_contains(notice($a), 'sbloccato'), 'Unlocked device is sent back to the list (old generation refused by Napay)');
check(posLogin($b, $op2, $password)['status'] === 302 && openTerminal($b, $label1)['status'] === 302 && $b->request('/')['status'] === 200, 'Another operator opens the unlocked terminal');
check(heartbeat($b) === ['status' => 'ok'], 'New opening is live');

// --- CA assente o errata nel POS: nessun accesso
foreach (['https://pos-badca.local' => 'untrusted CA', 'https://pos-noca.local' => 'missing CA file'] as $origin => $label) {
    $device = new Device($origin);
    $r = posLogin($device, $op1, $password);
    check($r['status'] === 503 && str_contains($r['body'], 'non disponibile') && str_ends_with(location($device->request('/site/account')), '/site/login'), 'POS with ' . $label . ' cannot reach Napay; no session');
}

// --- il token Napay non arriva mai al browser
$browser = implode("\n", array_merge($a->seen, $b->seen)) . $a->cookies() . $b->cookies();
check(!preg_match('/napos_[A-Za-z0-9_-]{43}/', $browser) && !str_contains($browser, $clientKey), 'No Napay bearer or client key in POS HTML, headers, URLs or cookies');
$names = [];
foreach (explode("\n", $a->cookies() . "\n" . $b->cookies()) as $line) {
    $line = preg_replace('/^#HttpOnly_/', '', $line);
    if ($line === '' || $line[0] === '#') continue;
    $names[explode("\t", $line)[5] ?? ''] = true;
}
ksort($names);
check(array_keys($names) === ['POSSESSID', '_pos_csrf'], 'Browser holds only the POS session and CSRF cookies');
echo "PASS $checks POS-Napay openings e2e checks\n";
