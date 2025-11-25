<?php

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
if (class_exists(\Dotenv\Dotenv::class)) {
    \Dotenv\Dotenv::createImmutable($root)->safeLoad();
}

$apiKey = getenv('VENDUS_API_KEY') ?: ($_ENV['VENDUS_API_KEY'] ?? null);
$base = getenv('VENDUS_PRODUCTS_API_URL') ?: (getenv('VENDUS_API_URL') ?: ($_ENV['VENDUS_API_URL'] ?? ''));
$clean = rtrim(trim($base), '/');
if ($clean === '') { $clean = 'https://www.vendus.pt/ws/v1.2'; }
if (!preg_match('#/products$#', $clean)) { $clean .= '/products'; }
$productsUrl = $clean;

if (!$apiKey) { fwrite(STDERR, "Falta VENDUS_API_KEY no .env\n"); exit(1); }

echo "Endpoint: $productsUrl\n";

function req($method, $url, $params, $useBearer, $apiKey) {
    if ($method === 'GET' && !empty($params)) { $q = http_build_query($params); $url .= (str_contains($url, '?') ? '&' : '?') . $q; }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $headers = ['Accept: application/json'];
    if ($useBearer) { $headers[] = 'Authorization: Bearer ' . $apiKey; }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if (!$useBearer) { curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ':'); }
    if ($method === 'DELETE') { curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE'); }
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, $body];
}

function extractItems($body) {
    $json = json_decode($body, true);
    if (!is_array($json)) return [];
    if (isset($json['products']) && is_array($json['products'])) return $json['products'];
    if (isset($json['data']) && is_array($json['data'])) return $json['data'];
    if (isset($json['items']) && is_array($json['items'])) return $json['items'];
    if (isset($json['results']) && is_array($json['results'])) return $json['results'];
    return (isset($json[0]) ? $json : []);
}

$page = 1; $limit = 200; $ids = []; $loops = 0; $lastStatus = null;
while (true) {
    $items = [];
    foreach ([["page"=>$page,"limit"=>$limit],["page"=>$page,"per_page"=>$limit],["page"=>$page],["limit"=>$limit],[]] as $params) {
        [$s1, $b1] = req('GET', $productsUrl, $params, false, $apiKey);
        $lastStatus = $s1;
        if ($s1 === 200) { $items = extractItems($b1); }
        if (!empty($items)) { break; }
        [$s2, $b2] = req('GET', $productsUrl, $params, true, $apiKey);
        $lastStatus = $s2;
        if ($s2 === 200) { $items = extractItems($b2); }
        if (!empty($items)) { break; }
    }
    if (empty($items)) break;
    foreach ($items as $it) { if (is_array($it) && isset($it['id'])) { $ids[] = (int) $it['id']; } }
    $page++; $loops++; if ($loops > 100) break;
}

if (empty($ids)) { echo "Nenhum produto encontrado (status recente: " . ($lastStatus ?? 'n/a') . ")\n"; exit(0); }
echo "Encontrados: " . count($ids) . "\n";
foreach ($ids as $id) {
    $url = $productsUrl . '/' . $id;
    [$sd1, $bd1] = req('DELETE', $url, [], false, $apiKey);
    if ($sd1 === 200 || $sd1 === 204) { echo "Apagado: $id\n"; continue; }
    [$sd2, $bd2] = req('DELETE', $url, [], true, $apiKey);
    if ($sd2 === 200 || $sd2 === 204) { echo "Apagado: $id\n"; continue; }
    echo "Falhou: $id status $sd2\n";
}