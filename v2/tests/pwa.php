<?php
// POS installabile e mobile-first (requisito utente 2026-11-13): controlli strutturali dei file serviti.
// Non sostituiscono la prova su dispositivo reale (installazione, schermo piccolo, stampante).
if (PHP_SAPI !== 'cli') exit(1);
$web = dirname(__DIR__) . '/web';
$checks = 0;
$check = static function ($condition, string $label) use (&$checks) { if (!$condition) throw new RuntimeException($label); ++$checks; };
$manifest = json_decode((string) file_get_contents($web . '/manifest.webmanifest'), true, 8, JSON_THROW_ON_ERROR);
$check($manifest['name'] === 'Napay POS' && $manifest['start_url'] === '/' && $manifest['scope'] === '/' && $manifest['display'] === 'standalone'
    && preg_match('/^#[0-9a-f]{6}$/', $manifest['theme_color']) && preg_match('/^#[0-9a-f]{6}$/', $manifest['background_color']), 'Manifest: standalone app scoped to the POS');
$sizes = [];
foreach ($manifest['icons'] as $icon) {
    $file = $web . $icon['src'];
    $check(is_file($file), 'Icon exists: ' . $icon['src']);
    if ($icon['type'] === 'image/png') {
        [$w, $h] = getimagesize($file);
        $check($icon['sizes'] === "{$w}x{$h}", 'Icon size matches file: ' . $icon['src']);
        $sizes[$icon['purpose']][] = $w;
    }
}
$check(in_array(192, $sizes['any']) && in_array(512, $sizes['any']) && in_array(512, $sizes['maskable']), 'Installable icon set: 192/512 and maskable');
$sw = (string) file_get_contents($web . '/sw.js');
preg_match("/const SHELL = \\[(.*?)\\];/", $sw, $m);
$shell = json_decode('[' . str_replace("'", '"', $m[1]) . ']', true);
$check(is_array($shell) && $shell !== [], 'Service worker declares a static shell');
foreach ($shell as $path) $check(is_file($web . $path) && !str_ends_with($path, '.php'), 'Shell entry is a static file: ' . $path);
$check(str_contains($sw, "request.method !== 'GET'") && str_contains($sw, "request.mode === 'navigate'") && str_contains($sw, "caches.match('/offline.html')")
    && substr_count($sw, 'cache.put') === 1 && str_contains($sw, 'SHELL.includes(url.pathname)'), 'Pages and forms never cached; offline page only as fallback');
$offline = (string) file_get_contents($web . '/offline.html');
$check(!str_contains($offline, '<?') && !str_contains($offline, 'csrf') && str_contains($offline, 'viewport'), 'Offline page static, no session data');
$js = (string) file_get_contents($web . '/pos.js');
$check(str_contains($js, "register('/sw.js', { scope: '/' })") && str_contains($js, 'setInterval(beat, 30000)') && str_contains($js, "'X-CSRF-Token'"), 'Worker registered; heartbeat every 30 s with CSRF header');
$layout = (string) file_get_contents(dirname(__DIR__) . '/views/layouts/main.php');
foreach (['rel="manifest"', 'name="theme-color"', 'viewport-fit=cover', 'apple-touch-icon', 'csrfMetaTags'] as $needle) $check(str_contains($layout, $needle), 'Layout declares ' . $needle);
$css = (string) file_get_contents($web . '/pos.css');
$check(!preg_match('/@media\s*\(\s*max-width/', $css) && str_contains($css, '@media(min-width:900px)'), 'CSS mobile-first: only min-width breakpoints');
$check(preg_match('/--tap:(\d+)px/', $css, $tap) && (int) $tap[1] >= 48 && preg_match('/\.keypad button\{min-height:(\d+)px/', $css, $key) && (int) $key[1] >= 56, 'Touch targets at least 48px, keypad 56px+');
$check(str_contains($css, 'env(safe-area-inset-top)'), 'Safe areas respected on notched phones');
echo "PASS $checks POS PWA/mobile structure checks\n";
