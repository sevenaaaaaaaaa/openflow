<?php
$dir = '/www/wwwroot/nownexts_com';
$f = $dir . '/data/articles/index.json';
$arts = json_decode(file_get_contents($f), true);
if (!is_array($arts)) { fwrite(STDERR, "bad json\n"); exit(1); }
$extDir = $dir . '/assets/articles/_ext';
if (!is_dir($extDir)) mkdir($extDir, 0755, true);

function resolve_ext($u) {
  if (preg_match('#^https://camo\.githubusercontent\.com/[0-9a-f]+/([0-9a-f]+)$#', $u, $m)) {
    $d = @hex2bin($m[1]); if ($d && preg_match('#^https?://#', $d)) return $d;
  }
  if (preg_match('#^https://github\.com/([^/]+)/([^/]+)/blob/(.+)$#', $u, $m)) return "https://raw.githubusercontent.com/{$m[1]}/{$m[2]}/{$m[3]}";
  if (preg_match('#^https://github\.com/([^/]+)/([^/]+)/raw/(.+)$#', $u, $m)) return "https://raw.githubusercontent.com/{$m[1]}/{$m[2]}/{$m[3]}";
  return $u;
}

function fetch_url($u) {
  $ch = curl_init($u);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 6,
    CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 12,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
    CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
  ]);
  $body = curl_exec($ch);
  $ct = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$code, $ct, $body];
}

$map = []; $failList = []; $ok = 0; $bad = 0;
$all = [];
foreach ($arts as $a) {
  if (preg_match_all('#<img[^>]+src="([^"]+)"#u', $a['content'] ?? '', $m)) {
    foreach ($m[1] as $u) if (preg_match('#^https?://#', $u)) $all[$u] = 1;
  }
}
$urls = array_keys($all);
echo "unique_external=" . count($urls) . "\n";

foreach ($urls as $i => $u) {
  $target = resolve_ext($u);
  $hash = substr(md5($target), 0, 16);
  $existing = glob($extDir . '/' . $hash . '.*');
  if ($existing) { $map[$u] = '/assets/articles/_ext/' . basename($existing[0]); $ok++; continue; }
  [$code, $ct, $body] = fetch_url($target);
  if ($code === 200 && $body !== false && strlen($body) > 100 && preg_match('#^image/#i', $ct)) {
    $ext = 'png';
    if (stripos($ct, 'jpeg') !== false || stripos($ct, 'jpg') !== false) $ext = 'jpg';
    elseif (stripos($ct, 'gif') !== false) $ext = 'gif';
    elseif (stripos($ct, 'svg') !== false) $ext = 'svg';
    elseif (stripos($ct, 'webp') !== false) $ext = 'webp';
    $file = $extDir . '/' . $hash . '.' . $ext;
    if (file_put_contents($file, $body) !== false) { $map[$u] = '/assets/articles/_ext/' . $hash . '.' . $ext; $ok++; }
    else { $failList[$u] = 'write'; $bad++; }
  } else {
    $failList[$u] = "code=$code ct=$ct";
    $bad++;
  }
  if (($i + 1) % 25 === 0) echo "  progress " . ($i + 1) . "/" . count($urls) . " ok=$ok fail=$bad\n";
}

// 改写 src
$n = 0;
foreach ($arts as $k => $a) {
  $c = $a['content'] ?? '';
  foreach ($map as $u => $local) $c = str_replace('src="' . $u . '"', 'src="' . $local . '"', $c);
  // 失败的外链图：移除整个 figure（找不到 figure 则移除 img 标签）
  foreach ($failList as $u => $_) {
    $c = preg_replace('#<figure[^>]*>(?:(?!</figure>).)*' . preg_quote('src="' . $u . '"', '#') . '(?:(?!</figure>).)*</figure>#su', '', $c);
    $c = preg_replace('#<img[^>]*' . preg_quote('src="' . $u . '"', '#') . '[^>]*>#u', '', $c);
  }
  if ($c !== ($a['content'] ?? '')) { $arts[$k]['content'] = $c; $n++; }
}

$json = json_encode($arts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) { fwrite(STDERR, "encode fail: " . json_last_error_msg() . "\n"); exit(1); }
file_put_contents($f . '.tmp', $json);
rename($f . '.tmp', $f);

echo "downloaded_ok=$ok failed=$bad articles_rewritten=$n\n";
if ($failList) { echo "FAILURES:\n"; $i = 0; foreach ($failList as $u => $r) { echo "  $u => $r\n"; if (++$i >= 20) break; } }
