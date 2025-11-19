<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\FieldMapping;
use App\Models\DocumentFieldMapping;
use Exception;
use Illuminate\Http\Client\RequestException;

class VendusService
{
    private string $apiKey;
    private string $productsApiUrl;
    private string $documentsApiUrl;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = (string) env('VENDUS_API_KEY');
        $this->productsApiUrl = (string) env('VENDUS_PRODUCTS_API_URL');
        $this->documentsApiUrl = (string) env('VENDUS_DOCUMENTS_API_URL');
        $this->apiUrl = (string) env('VENDUS_API_URL');
    }

    /**
     * Mapeia valores de imposto para tax_id aceites pela API (NOR, INT, RED, ISE)
     */
    private function mapTaxId($tax, string $country = 'PT'): ?string
    {
        if ($tax === null || $tax === '') {
            return null;
        }
        // Já é um código válido
        $asStr = is_string($tax) ? strtoupper(trim($tax)) : null;
        if ($asStr && in_array($asStr, ['NOR','RED','INT','ISE'], true)) {
            return $asStr;
        }
        // Converter percentagens comuns
        if (is_numeric($tax)) {
            $pct = (float) $tax;
            if ($pct >= 22 && $pct <= 23.5) return 'NOR';
            if ($pct >= 12 && $pct <= 14) return 'INT';
            if ($pct >= 5 && $pct <= 7) return 'RED';
            if ($pct <= 0.001) return 'ISE';
        }
        // Fallback por país (Portugal: NOR)
        return $country === 'PT' ? 'NOR' : null;
    }

    /**
     * Envia um produto para a API da Vendus
     */
    public function sendProduct(array $productData): array
    {
        $this->ensureProductsApiUrl();

        Log::info("Payload JSON (create) para Vendus\nEndpoint: " . $this->productsApiUrl . "\n" .
            json_encode($productData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );

        Log::info('Tentativa envio produto (v1.2)', [
            'endpoint' => $this->productsApiUrl,
            'auth' => 'Basic'
        ]);
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':'),
        ])
            ->timeout(30)
            ->withOptions([
                'verify' => false,
                'curl' => [
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                ]
            ])
            ->post($this->productsApiUrl, $productData);

        if ($response->successful()) {
            $responseData = $response->json();
            if (isset($responseData['id'])) {
                return ['success' => true, 'data' => $responseData];
            }

            $errorMessage = 'Resposta 2xx sem ID de produto. Verifique VENDUS_PRODUCTS_API_URL, credenciais e payload.';
            Log::error($errorMessage, ['response' => $responseData]);
            return ['success' => false, 'message' => $errorMessage];
        }

        Log::info('Tentativa envio produto (v1.2)', [
            'endpoint' => $this->productsApiUrl,
            'auth' => 'Bearer'
        ]);
        $response2 = Http::withToken($this->apiKey)
            ->timeout(30)
            ->withOptions([
                'verify' => false,
                'curl' => [
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                ]
            ])
            ->post($this->productsApiUrl, $productData);

        if ($response2->successful()) {
            $data2 = $response2->json();
            if (isset($data2['id'])) {
                return ['success' => true, 'data' => $data2];
            }
            Log::warning('Resposta 2xx sem ID de produto (Bearer)', ['response' => $data2]);
            return ['success' => false, 'message' => 'Resposta 2xx sem ID de produto.'];
        }

        

        try {
            $contentType = $response->header('Content-Type') ?? '';
            $json = null;
            try { $json = $response->json(); } catch (\Throwable $t) { $json = null; }

            if ($response->status() === 400 || $response->status() === 409) {
                $errors = is_array($json) && isset($json['errors']) ? $json['errors'] : [];
                $shouldUpdate = false;
                foreach ((array)$errors as $error) {
                    if (is_array($error)) {
                        $code = $error['code'] ?? '';
                        $msg = strtolower((string)($error['message'] ?? $error['detail'] ?? ''));
                        if ($code === 'A001' || str_contains($msg, 'refer') || str_contains($msg, 'já existe')) {
                            $shouldUpdate = true; break;
                        }
                    }
                }
                if ($shouldUpdate) {
                    return $this->updateProductByReference($productData);
                }
            }

            Log::error('Erro ao enviar produto para a Vendus API.', [
                'status' => $response->status(),
                'content_type' => $contentType,
                'headers' => [
                    'Location' => $response->header('Location'),
                    'Content-Location' => $response->header('Content-Location'),
                ],
                'json' => $json,
                'raw_body' => substr((string)$response->body(), 0, 2000),
                'product_data' => $productData
            ]);
            return [
                'success' => false,
                'message' => 'Erro ao enviar produto para a Vendus API.',
                'errors' => is_array($json) && isset($json['errors']) ? $json['errors'] : []
            ];
        } catch (RequestException $e) {
            $errorMessage = 'Erro de comunicação ao enviar produto para a Vendus API.';
            Log::error($errorMessage, [
                'exception_message' => $e->getMessage(),
                'product_data' => $productData
            ]);
            return ['success' => false, 'message' => $errorMessage];
        }
    }

    /**
     * Atualiza um produto existente na API da Vendus, procurando-o pela referência.
     */
    private function updateProductByReference(array $productData): array
    {
        $this->ensureProductsApiUrl();

        // Assumindo que a referência do produto é única e pode ser usada para encontrá-lo
        $reference = $productData['reference'];
        $searchResponse = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':'),
        ])
            ->timeout(30)
            ->withOptions([
                'verify' => false,
                'curl' => [
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                ]
            ])
            ->get($this->productsApiUrl, ['reference' => $reference]);

        if ($searchResponse->successful() && !empty($searchResponse->json())) {
            $existingProducts = $searchResponse->json();
            $productId = $existingProducts[0]['id']; // Pega o ID do primeiro produto encontrado

            $updateUrl = $this->productsApiUrl . '/' . $productId;
            $updateResponse = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':'),
            ])
                ->timeout(30)
                ->withOptions([
                    'verify' => false,
                    'curl' => [
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                    ]
                ])
                ->patch($updateUrl, $productData);

            if (!$updateResponse->successful()) {
                $updateResponse2 = Http::withToken($this->apiKey)
                    ->timeout(30)
                    ->withOptions([
                        'verify' => false,
                        'curl' => [
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false,
                        ]
                    ])
                    ->patch($updateUrl, $productData);
                $updateResponse = $updateResponse2;
            }

            if ($updateResponse->successful()) {
                return ['success' => true, 'data' => $updateResponse->json(), 'action' => 'updated'];
            }

            $errorMessage = 'Erro ao atualizar produto na Vendus API.';
            Log::error($errorMessage, [
                'status' => $updateResponse->status(),
                'response' => $updateResponse->json(),
                'product_data' => $productData
            ]);
            return ['success' => false, 'message' => $errorMessage, 'errors' => $updateResponse->json()['errors'] ?? []];
        }

        $errorMessage = 'Produto não encontrado para atualização ou erro na busca.';
        Log::error($errorMessage, [
            'status' => $searchResponse->status(),
            'response' => $searchResponse->json(),
            'reference' => $reference
        ]);
        return ['success' => false, 'message' => $errorMessage];
    }

    /**
     * Garante que a URL da API de produtos está corretamente formatada.
     */
    private function ensureProductsApiUrl(): void
    {
        if (str_ends_with($this->productsApiUrl, '/')) {
            $this->productsApiUrl = rtrim($this->productsApiUrl, '/');
        }
        $this->productsApiUrl = $this->ensureProductsEndpoint($this->productsApiUrl);
    }

    public function getVariantSectionIdByTitle(string $title): ?int
    {
        $this->ensureProductsApiUrl();
        $base = preg_replace('#/products$#', '', (string) $this->productsApiUrl);
        $url = $base . '/products/variants';
        try {
            $resp = Http::withBasicAuth($this->apiKey, '')
                ->timeout(30)
                ->withOptions(['verify' => false])
                ->get($url);
            if ($resp->successful()) {
                $json = $resp->json();
                $items = [];
                if (is_array($json)) {
                    if (isset($json['data']) && is_array($json['data'])) { $items = $json['data']; }
                    elseif (isset($json['variants']) && is_array($json['variants'])) { $items = $json['variants']; }
                    else { $items = $json; }
                    $needle = $this->normalizeText($title);
                    $found = null;
                    foreach ($items as $item) {
                        $t = $this->normalizeText((string)($item['title'] ?? ''));
                        if ($t === $needle && isset($item['id']) && is_numeric($item['id'])) { $found = (int) $item['id']; break; }
                    }
                    if ($found === null) {
                        foreach ($items as $item) {
                            $t = $this->normalizeText((string)($item['title'] ?? ''));
                            if ((str_contains($t, $needle) || str_contains($needle, $t)) && isset($item['id']) && is_numeric($item['id'])) { $found = (int) $item['id']; break; }
                        }
                    }
                    if ($found !== null) { return $found; }
                }
            } else {
                $resp2 = Http::withToken($this->apiKey)
                    ->timeout(30)
                    ->withOptions(['verify' => false])
                    ->get($url);
                if ($resp2->successful()) {
                    $json2 = $resp2->json();
                    $items = [];
                    if (is_array($json2)) {
                        if (isset($json2['data']) && is_array($json2['data'])) { $items = $json2['data']; }
                        elseif (isset($json2['variants']) && is_array($json2['variants'])) { $items = $json2['variants']; }
                        else { $items = $json2; }
                        $needle = $this->normalizeText($title);
                        $found = null;
                        foreach ($items as $item) {
                            $t = $this->normalizeText((string)($item['title'] ?? ''));
                            if ($t === $needle && isset($item['id']) && is_numeric($item['id'])) { $found = (int) $item['id']; break; }
                        }
                        if ($found === null) {
                            foreach ($items as $item) {
                                $t = $this->normalizeText((string)($item['title'] ?? ''));
                                if ((str_contains($t, $needle) || str_contains($needle, $t)) && isset($item['id']) && is_numeric($item['id'])) { $found = (int) $item['id']; break; }
                            }
                        }
                        if ($found !== null) { return $found; }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Erro ao obter variant sections', ['url' => $url, 'error' => $e->getMessage()]);
        }
        return null;
    }

    public function getVariantValuesForSection(int $sectionId): array
    {
        $this->ensureProductsApiUrl();
        $base = preg_replace('#/products$#', '', (string) $this->productsApiUrl);
        $url = $base . '/products/variants';
        try {
            $resp = Http::withBasicAuth($this->apiKey, '')
                ->timeout(30)
                ->withOptions(['verify' => false])
                ->get($url, ['parent_id' => $sectionId]);
            if ($resp->successful()) {
                $json = $resp->json();
                $map = [];
                $items = [];
                if (is_array($json)) {
                    if (isset($json['data']) && is_array($json['data'])) { $items = $json['data']; }
                    elseif (isset($json['variants']) && is_array($json['variants'])) { $items = $json['variants']; }
                    else { $items = $json; }
                    foreach ($items as $item) {
                        $text = (string) ($item['text'] ?? $item['title'] ?? '');
                        if ($text !== '' && isset($item['id']) && is_numeric($item['id'])) {
                            $map[$this->normalizeText($text)] = (int) $item['id'];
                        }
                    }
                }
                return $map;
            } else {
                $resp2 = Http::withToken($this->apiKey)
                    ->timeout(30)
                    ->withOptions(['verify' => false])
                    ->get($url, ['parent_id' => $sectionId]);
                if ($resp2->successful()) {
                    $json2 = $resp2->json();
                    $map = [];
                    $items = [];
                    if (is_array($json2)) {
                        if (isset($json2['data']) && is_array($json2['data'])) { $items = $json2['data']; }
                        elseif (isset($json2['variants']) && is_array($json2['variants'])) { $items = $json2['variants']; }
                        else { $items = $json2; }
                        foreach ($items as $item) {
                            $text = (string) ($item['text'] ?? $item['title'] ?? '');
                            if ($text !== '' && isset($item['id']) && is_numeric($item['id'])) {
                                $map[$this->normalizeText($text)] = (int) $item['id'];
                            }
                        }
                    }
                    return $map;
                }
            }
        } catch (\Exception $e) {
            Log::error('Erro ao obter variant values', ['url' => $url, 'error' => $e->getMessage()]);
        }
        return [];
    }

    private function normalizeText(string $text): string
    {
        $t = trim($text);
        $t = mb_strtolower($t, 'UTF-8');
        $t2 = @iconv('UTF-8', 'ASCII//TRANSLIT', $t);
        if ($t2 !== false) { $t = $t2; }
        $t = preg_replace('/\s+/', ' ', $t);
        return $t;
    }

    private function getProductVariantsChildren(int $parentId): array
    {
        $this->ensureProductsApiUrl();
        $base = preg_replace('#/products$#', '', (string) $this->productsApiUrl);
        $url = $base . '/products/variants';
        try {
            $resp = Http::withBasicAuth($this->apiKey, '')
                ->timeout(30)
                ->withOptions(['verify' => false])
                ->get($url, ['parent_id' => $parentId]);
            if ($resp->successful()) {
                $json = $resp->json();
                if (isset($json['data']) && is_array($json['data'])) return $json['data'];
                if (isset($json['variants']) && is_array($json['variants'])) return $json['variants'];
                if (is_array($json)) return $json;
            } else {
                $resp2 = Http::withToken($this->apiKey)
                    ->timeout(30)
                    ->withOptions(['verify' => false])
                    ->get($url, ['parent_id' => $parentId]);
                if ($resp2->successful()) {
                    $json2 = $resp2->json();
                    if (isset($json2['data']) && is_array($json2['data'])) return $json2['data'];
                    if (isset($json2['variants']) && is_array($json2['variants'])) return $json2['variants'];
                    if (is_array($json2)) return $json2;
                }
            }
        } catch (\Exception $e) {
            Log::error('Erro ao obter filhos de variants', ['parent_id' => $parentId, 'error' => $e->getMessage()]);
        }
        return [];
    }

    private function getVariantChildSectionIdByTitle(int $parentId, string $title): ?int
    {
        $children = $this->getProductVariantsChildren($parentId);
        $needle = $this->normalizeText($title);
        foreach ($children as $item) {
            $t = $this->normalizeText((string)($item['title'] ?? ''));
            if ($t === $needle && isset($item['id']) && is_numeric($item['id'])) return (int) $item['id'];
        }
        return null;
    }

    private function getVariantValueIdUnderParent(int $parentId, string $text): ?int
    {
        $children = $this->getProductVariantsChildren($parentId);
        $needleRaw = trim((string)$text);
        $needle = mb_strtolower($needleRaw, 'UTF-8');
        foreach ($children as $item) {
            $txtRaw = (string) ($item['text'] ?? $item['title'] ?? '');
            $txt = mb_strtolower(trim($txtRaw), 'UTF-8');
            if ($txt !== '' && $txt === $needle && isset($item['id']) && is_numeric($item['id'])) return (int) $item['id'];
        }
        $needleAlt = mb_strtolower(str_replace('-', '/', preg_replace('/\s+/', '', $needleRaw)), 'UTF-8');
        foreach ($children as $item) {
            $txtRaw = (string) ($item['text'] ?? $item['title'] ?? '');
            $txtAlt = mb_strtolower(str_replace('-', '/', preg_replace('/\s+/', '', $txtRaw)), 'UTF-8');
            if ($txtAlt !== '' && $txtAlt === $needleAlt && isset($item['id']) && is_numeric($item['id'])) return (int) $item['id'];
        }
        Log::warning('Variante não encontrada sob parent', ['parent_id' => $parentId, 'needle' => $needleRaw]);
        return null;
    }

    private function getVariantsEndpoints(int $productId): array
    {
        $base = preg_replace('#/products$#', '', (string) $this->productsApiUrl);
        $v12 = $base;
        $v11 = preg_replace('#/v1\.2$#', '/v1.1', $v12);
        $endpoints = [
            $v12 . '/products/' . $productId,
            $v11 . '/products/' . $productId
        ];
        return array_values(array_unique(array_filter($endpoints)));
    }

    private function buildVariantsPayload(string $title, array $items, bool $wrapped = true, ?int $variantId = null): array
    {
        $pvs = [];
        foreach ($items as $it) {
            $text = isset($it['variante']) ? (string) $it['variante'] : (isset($it['size']) ? (string) $it['size'] : (string) ($it['variant'] ?? ($it['variant_text'] ?? ($it['text'] ?? ''))));
            $barcode = isset($it['upc_no']) ? (string) $it['upc_no'] : (string) ($it['barcode'] ?? '');
            $code = isset($it['code']) ? (string) $it['code'] : '';
            $priceVal = '0.00';
            $pv = [
                'text' => $text,
                'barcode' => $barcode,
                'code' => $code,
                'price' => $priceVal
            ];
            if (isset($it['composite_id']) && is_numeric($it['composite_id'])) {
                $pv['composite_ids'] = [ (string) $it['composite_id'] ];
            }
            $pvs[] = $pv;
        }
        $variant = ['title' => (string) $title];
        if ($variantId !== null) { $variant['id'] = $variantId; }
        $variantBlock = [
            'variant' => $variant,
            'product_variants' => $pvs
        ];
        if ($wrapped) {
            return ['variants' => [$variantBlock]];
        }
        return $variantBlock;
    }

    private function normalizeVariantKey(string $text): string
    {
        return mb_strtolower(trim($text), 'UTF-8');
    }

    private function extractVariantIdFromResponse($data): ?int
    {
        if (is_array($data)) {
            if (isset($data['id']) && is_numeric($data['id'])) {
                return (int) $data['id'];
            }
            if (isset($data['variant_id']) && is_numeric($data['variant_id'])) {
                return (int) $data['variant_id'];
            }
            if (isset($data['variants']) && is_array($data['variants'])) {
                foreach ($data['variants'] as $block) {
                    if (isset($block['variant']['id']) && is_numeric($block['variant']['id'])) {
                        return (int) $block['variant']['id'];
                    }
                }
            }
        }
        return null;
    }

    private function createOrLocateProduct(array $payload): array
    {
        $this->ensureProductsApiUrl();
        $id = $this->findProductId($payload);
        if ($id !== null) {
            return ['success' => true, 'id' => $id, 'action' => 'found'];
        }
        $res = $this->sendProduct($payload);
        if ($res['success']) {
            $createdId = $res['data']['id'] ?? null;
            if (is_numeric($createdId)) {
                return ['success' => true, 'id' => (int) $createdId, 'action' => 'created'];
            }
        }
        return ['success' => false, 'message' => $res['message'] ?? 'Falha ao criar produto', 'errors' => $res['errors'] ?? []];
    }

    public function attachVariantsToProduct(int $productId, string $title, array $items, ?int $sectionId = null): array
    {
        $payload = $this->buildVariantsPayload($title, $items, true, $sectionId);
        if ($sectionId !== null) {
            $payload['variant_id'] = (string) $sectionId;
        }
        $payload['class_id'] = 'MOD';
        $update = $this->updateProduct($productId, $payload);
        if ($update['success']) {
            $vid = $sectionId;
            return ['success' => true, 'data' => $update['data'], 'variant_id' => $vid];
        }
        return ['success' => false, 'message' => $update['message'] ?? 'Falha ao anexar variantes'];
    }

    public function createProductWithVariants(array $basePayload, string $variantTitle, array $variantItems): array
    {
        $sectionId = $this->getVariantSectionIdByTitle($variantTitle);
        $sizeSectionId = $sectionId ? $this->getVariantChildSectionIdByTitle($sectionId, 'Size') : null;
        if ($sectionId !== null && $sizeSectionId === null) {
            $sizeSectionId = $this->getVariantChildSectionIdByTitle($sectionId, 'Tamanho');
        }
        $itemsWithComposite = [];
        foreach ($variantItems as $it) {
            $text = isset($it['variante']) ? (string) $it['variante'] : (isset($it['size']) ? (string) $it['size'] : (string) ($it['variant'] ?? ($it['variant_text'] ?? ($it['text'] ?? ''))));
            $cid = null;
            if ($sizeSectionId !== null) {
                $cid = $this->getVariantValueIdUnderParent($sizeSectionId, $text);
            }
            $itm = $it;
            if ($cid !== null) { $itm['composite_id'] = $cid; }
            $itemsWithComposite[] = $itm;
        }

        $payload = $basePayload;
        if ($sectionId !== null) {
            $payload['variant_id'] = (string) $sectionId;
        }
        $payload['class_id'] = 'MOD';
        $stores = [];
        foreach ($itemsWithComposite as $it) {
            $valueId = isset($it['composite_id']) ? (string) $it['composite_id'] : '';
            if ($valueId !== '') {
                $stores[] = [
                    'id' => '292626436',
                    'product_variant_id' => $valueId,
                    'stock' => '10',
                    'stock_alert' => '1'
                ];
            }
        }
        if (!empty($stores)) {
            $payload['stock'] = ['control' => '1', 'type' => 'M', 'stores' => $stores];
        }

        $createResp = $this->sendProduct($payload);
        
        if ($createResp['success']) {
            $createdId = $createResp['data']['id'] ?? null;
            if (is_numeric($createdId)) {
                $pid = (int) $createdId;
                $data = $createResp['data'];
                if ($sectionId !== null) {
                    $updVid = $this->updateProduct($pid, ['variant_id' => (string)$sectionId, 'class_id' => 'MOD']);
                    if ($updVid['success']) { $data['variant_id'] = (string)$sectionId; }
                }
                $this->attachVariantsToProduct($pid, $variantTitle, $itemsWithComposite, $sectionId);
                return ['success' => true, 'data' => $data, 'action' => 'created'];
            }
            return ['success' => true, 'data' => $createResp['data'], 'action' => 'created'];
        }

        $productRes = $this->createOrLocateProduct($basePayload);
        if (!$productRes['success']) {
            return $productRes;
        }
        $productId = (int) $productRes['id'];
        $patchPayload = [];
        if ($sectionId !== null) {
            $patchPayload['variant_id'] = (string) $sectionId;
        }
        $patchPayload['class_id'] = 'MOD';
        $update = $this->updateProduct($productId, $patchPayload);
        if ($update['success']) {
            $this->attachVariantsToProduct($productId, $variantTitle, $itemsWithComposite, $sectionId);
            $stores = [];
            foreach ($itemsWithComposite as $it) {
                $valueId = isset($it['composite_id']) ? (string) $it['composite_id'] : '';
                if ($valueId !== '') {
                    $stores[] = [
                        'id' => '292626436',
                        'product_variant_id' => $valueId,
                        'stock' => '10',
                        'stock_alert' => '0'
                    ];
                }
            }
            if (!empty($stores)) {
                $patch = ['stock' => ['control' => '1', 'type' => 'M', 'stores' => $stores]];
                $upd2 = $this->updateProduct($productId, $patch);
                if (!$upd2['success']) {
                    Log::warning('Falha ao atualizar stock por variantes (fallback)', ['product_id' => $productId, 'message' => $upd2['message'] ?? null]);
                }
            }
            return ['success' => true, 'data' => ['product_id' => $productId, 'variant_id' => $sectionId], 'action' => $productRes['action']];
        }
        return ['success' => false, 'message' => $update['message'] ?? 'Falha ao atualizar produto com variant_id', 'id' => $productId];
    }
    /**
     * Envia um documento (PDF) para a API de Documentos da Vendus
     * Tenta com Bearer Token e, em seguida, com Basic Auth.
     *
     * @param string $filePath Caminho local do arquivo PDF
     * @param array $options   Opções adicionais (ex.: filename)
     * @return array
     */
    public function sendDocument(string $filePath, array $options = []): array
    {
        try {
            if (empty($this->apiKey)) {
                throw new Exception('VENDUS_API_KEY não configurada no .env');
            }

            $documentsUrl = $this->getDocumentsEndpoint();
            $filename = isset($options['filename']) && $options['filename'] !== ''
                ? (string) $options['filename']
                : basename($filePath);

            Log::info('Enviando fatura para Vendus', [
                'endpoint' => $documentsUrl,
                'filename' => $filename,
            ]);

            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->withOptions([
                    'verify' => false,
                    'curl' => [
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                    ]
                ])
                ->attach('file', fopen($filePath, 'r'), $filename)
                ->post($documentsUrl, []);

            $ok = $response->successful();
            $body = $response->json();
            $location = $response->header('Location');
            $hasId = is_array($body) && (isset($body['id']) || isset($body['document_id']));
            $created = $ok && ($hasId || ($location && str_contains((string)$location, '/documents')));

            if (!$created) {
                $response2 = Http::withBasicAuth($this->apiKey, '')
                    ->timeout(30)
                    ->withOptions([
                        'verify' => false,
                        'curl' => [
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false,
                        ]
                    ])
                    ->attach('file', fopen($filePath, 'r'), $filename)
                    ->post($documentsUrl, []);

                $ok = $response2->successful();
                $body = $response2->json();
                $location = $response2->header('Location');
                $hasId = is_array($body) && (isset($body['id']) || isset($body['document_id']));
                $created = $ok && ($hasId || ($location && str_contains((string)$location, '/documents')));

                if (!$created) {
                    $this->logError($filename, $response2->status(), 'Falha ao enviar documento', $response2->body());
                    return [
                        'success' => false,
                        'status_code' => $response2->status(),
                        'message' => 'Falha ao enviar fatura',
                        'error' => $response2->body(),
                    ];
                }

                return [
                    'success' => true,
                    'status_code' => $response2->status(),
                    'data' => $body,
                    'message' => 'Fatura enviada com sucesso'
                ];
            }

            return [
                'success' => true,
                'status_code' => $response->status(),
                'data' => $body,
                'message' => 'Fatura enviada com sucesso'
            ];

        } catch (Exception $e) {
            $this->logError(basename($filePath), 0, $e->getMessage(), '');
            return [
                'success' => false,
                'status_code' => 0,
                'error' => $e->getMessage(),
                'message' => 'Erro de conexão ao enviar fatura'
            ];
        }
    }

    /**
     * Envia uma fatura (JSON) para a API de Documentos da Vendus
     */
    public function sendInvoice(array $invoice): array
    {
        try {
            if (empty($this->apiKey)) {
                throw new Exception('VENDUS_API_KEY não configurada no .env');
            }

            $header = (array) ($invoice['header'] ?? []);
            $items = (array) ($invoice['items'] ?? []);
            // Contexto opcional: store e register via header ou .env
            $storeId = (isset($header['store_id']) && is_numeric($header['store_id']))
                ? (int) $header['store_id']
                : ($this->resolveStoreIdFromInvoice($invoice) ?? env('VENDUS_STORE_ID'));
            $registerId = (isset($header['register_id']) && is_numeric($header['register_id']))
                ? (int) $header['register_id']
                : ($this->resolveRegisterIdFromInvoice($invoice, is_numeric($storeId) ? (int)$storeId : null) ?? env('VENDUS_REGISTER_ID'));
            $nif = $header['customer_nif'] ?? null;
            $nifForCommercial = ($nif === '999999990' || $nif === 999999990) ? null : $nif;
            $clientId = is_numeric($header['client_id'] ?? null) ? (int) $header['client_id'] : null;
            $clientEmail = $header['customer_email'] ?? null;
            $clientExternalRef = $header['external_reference'] ?? null;
            $clientObj = null;
            if ($clientId || $nifForCommercial || ($clientExternalRef && $clientExternalRef !== '') || ($clientEmail && $clientEmail !== '')) {
                $clientObj = array_filter([
                    'id' => $clientId,
                    'fiscal_id' => $nifForCommercial,
                    'name' => $header['customer_name'] ?? null,
                    'country' => $header['customer_country'] ?? 'PT',
                    'email' => $clientEmail,
                    'external_reference' => $clientExternalRef,
                ], function ($v) {
                    if ($v === null) return false;
                    if (is_string($v)) return $v !== '';
                    return true;
                });
            }

            // Payload comercial removido – foco total em ws v1.2

            $legacyPayload = [
                'type' => isset($header['type']) && is_string($header['type']) && $header['type'] !== '' ? (string)$header['type'] : 'FT',
                'serie_id' => is_numeric($header['series'] ?? null) ? (int) $header['series'] : null,
                'date' => $header['date'] ?? null,
                'notes' => $header['notes'] ?? ($header['external_reference'] ? ('Ref: ' . $header['external_reference']) : null),
                'external_reference' => $header['external_reference'] ?? null,
                // Pedir mensagens de erro detalhadas, quando suportado
                'errors_full' => isset($header['errors_full']) && $header['errors_full'] !== null && $header['errors_full'] !== '' ? (string)$header['errors_full'] : 'yes',
                'date_due' => $header['date_due'] ?? null,
                'date_supply' => $header['date_supply'] ?? null,
                'discount_code' => $header['discount_code'] ?? null,
                'discount_amount' => is_numeric($header['discount_amount'] ?? null) ? (float)$header['discount_amount'] : null,
                'discount_percentage' => is_numeric($header['discount_percentage'] ?? null) ? (float)$header['discount_percentage'] : null,
                'mode' => $header['mode'] ?? null,
                'stock_operation' => $header['stock_operation'] ?? null,
                'ifthenpay' => $header['ifthenpay'] ?? null,
                'eupago' => $header['eupago'] ?? null,
                'print_discount' => $header['print_discount'] ?? null,
                'output' => $header['output'] ?? null,
                'output_template_id' => is_numeric($header['output_template_id'] ?? null) ? (int)$header['output_template_id'] : null,
                'tx_id' => $header['tx_id'] ?? null,
                'rest_room' => is_numeric($header['rest_room'] ?? null) ? (int)$header['rest_room'] : null,
                'rest_table' => is_numeric($header['rest_table'] ?? null) ? (int)$header['rest_table'] : null,
                'occupation' => is_numeric($header['occupation'] ?? null) ? (int)$header['occupation'] : null,
                'stamp_retention_amount' => is_numeric($header['stamp_retention_amount'] ?? null) ? (float)$header['stamp_retention_amount'] : null,
                'irc_retention_id' => $header['irc_retention_id'] ?? null,
                'mgmAmount' => is_numeric($header['mgmAmount'] ?? null) ? (float)$header['mgmAmount'] : null,
                'related_document_id' => is_numeric($header['related_document_id'] ?? null) ? (int)$header['related_document_id'] : null,
                'return_qrcode' => $header['return_qrcode'] ?? null,
                'doc_to_generate' => $header['doc_to_generate'] ?? null,
                'ncr_id' => $header['ncr_id'] ?? null,

                'client' => $clientObj,
                'store_id' => $storeId ? (int) $storeId : null,
                'register_id' => $registerId ? (int) $registerId : null,

                'items' => array_map(function ($i) use ($header) {
                    $qty = $i['quantity'] ?? ($i['qty'] ?? 1);
                    $unitOrGross = $i['gross_price'] ?? ($i['unit_price'] ?? ($i['price'] ?? null));
                    $taxRaw = $i['tax_id'] ?? ($i['tax'] ?? null);
                    $mappedTax = $this->mapTaxId($taxRaw, $header['customer_country'] ?? 'PT');

                    $line = [
                        'reference' => $i['reference'] ?? null,
                        'title' => $i['title'] ?? null,
                        'qty' => is_numeric($qty) ? (float)$qty : 1,
                        'gross_price' => isset($unitOrGross) ? number_format((float)$unitOrGross, 2, '.', '') : null,
                        'tax_id' => $mappedTax,
                    ];

                    return array_filter($line, function ($v) {
                        if ($v === null) return false;
                        if (is_string($v)) return $v !== '';
                        return true;
                    });
                }, $items),
            ];

            $legacyPayload = array_filter($legacyPayload, function ($v) {
                if ($v === null) return false;
                if (is_array($v)) return !empty($v);
                return true;
            });

            $endpoints = $this->getPossibleSalesDocumentsEndpoints();
            $lastAttemptEndpoint = null;
            $lastAttemptAuth = null;

            // Apenas endpoints WS v1.2 e payload legado; prioriza Basic Auth
            $lastStatus = null;
            $lastResponseBody = null;
            foreach ($endpoints as $endpoint) {
                $authOptions = [false, true]; // false => Basic, true => Bearer
                foreach ($authOptions as $useBearerLegacy) {
                    $client2 = $useBearerLegacy ? Http::withToken($this->apiKey) : Http::withBasicAuth($this->apiKey, '');
                    $lastAttemptEndpoint = $endpoint;
                    $lastAttemptAuth = $useBearerLegacy ? 'Bearer' : 'Basic';
                    try {
                        $resp2 = $client2
                            ->timeout(30)
                            ->withOptions([
                                'verify' => false,
                                'curl' => [
                                    CURLOPT_SSL_VERIFYPEER => false,
                                    CURLOPT_SSL_VERIFYHOST => false,
                                ]
                            ])
                            ->withHeaders([
                                'Content-Type' => 'application/json',
                                'Accept' => 'application/json',
                            ])
                            ->post($endpoint, $legacyPayload);

                        Log::info('Tentativa envio fatura (ws v1.2)', [
                            'endpoint' => $endpoint,
                            'auth' => $useBearerLegacy ? 'Bearer' : 'Basic',
                            'status' => $resp2->status(),
                        ]);
                        $lastStatus = $resp2->status();
                        $lastResponseBody = $resp2->body();
                        Log::info('Resposta API Vendus (ws v1.2)', [
                            'endpoint' => $endpoint,
                            'auth' => $useBearerLegacy ? 'Bearer' : 'Basic',
                            'status' => $resp2->status(),
                            'content_type' => $resp2->header('Content-Type'),
                            'headers' => [
                                'Location' => $resp2->header('Location'),
                                'Content-Location' => $resp2->header('Content-Location'),
                            ],
                            'raw_body' => substr((string)$lastResponseBody, 0, 2000),
                        ]);

                        if ($resp2->successful()) {
                            $data = $resp2->json();
                            $location = $resp2->header('Location') ?? $resp2->header('Content-Location') ?? null;
                            $contentType = $resp2->header('Content-Type') ?? '';
                            $createdId = null;
                            $docNum = null;
                            if (is_array($data)) {
                                $createdId = $data['id'] ?? ($data['document_id'] ?? null);
                                $docNum = $data['document_number'] ?? ($data['number'] ?? null);
                                if (!$createdId && isset($data['document']) && is_array($data['document'])) {
                                    $createdId = $data['document']['id'] ?? null;
                                    $docNum = $docNum ?? ($data['document']['number'] ?? null);
                                }
                            }

                            if ($createdId || ($location && preg_match('#/(sales/)?documents/\\d+$#', (string)$location))) {
                                return [
                                    'success' => true,
                                    'status_code' => $resp2->status(),
                                    'data' => $data,
                                    'message' => 'Fatura enviada com sucesso',
                                    'endpoint_used' => $endpoint,
                                    'auth_used' => $useBearerLegacy ? 'Bearer' : 'Basic',
                                ];
                            }

                            // Sucesso HTTP mas sem criação detectável – continuar a tentar outros endpoints
                            Log::warning('Sucesso HTTP sem ID/Location ao enviar fatura (ws v1.2)', [
                                'endpoint' => $endpoint,
                                'status' => $resp2->status(),
                                'headers' => [
                                    'Location' => $resp2->header('Location'),
                                    'Content-Location' => $resp2->header('Content-Location'),
                                    'Content-Type' => $contentType,
                                ],
                                'body' => $data,
                                'raw_body' => substr((string)$lastResponseBody, 0, 2000),
                            ]);
                        }
                        else {
                            // Falha HTTP – regista detalhes do erro para diagnóstico
                            $contentType = $resp2->header('Content-Type') ?? '';
                            $json = null;
                            try { $json = $resp2->json(); } catch (\Throwable $t) { $json = null; }
                            Log::error('Erro HTTP ao enviar fatura (ws v1.2)', [
                                'endpoint' => $endpoint,
                                'auth' => $useBearerLegacy ? 'Bearer' : 'Basic',
                                'status' => $resp2->status(),
                                'headers' => [
                                    'Location' => $resp2->header('Location'),
                                    'Content-Location' => $resp2->header('Content-Location'),
                                    'Content-Type' => $contentType,
                                ],
                                'errors' => is_array($json) && isset($json['errors']) ? $json['errors'] : null,
                                'raw_body' => substr($resp2->body() ?? '', 0, 1000),
                            ]);
                        }
                    } catch (\Exception $e) {
                        // Continua tentando
                    }
                }
            }

            // Se chegou aqui, todas as tentativas falharam; reporta o último endpoint tentado, se houver
            $lastEndpoint = $lastAttemptEndpoint ?: (end($endpoints) ?: 'desconhecido');
            $statusToReport = $lastStatus ?? 400;
            $this->logError('invoice', $statusToReport, 'Falha ao enviar fatura (todas tentativas)', 'Verifique endpoint e payload. Último endpoint: ' . $lastEndpoint . ' | auth: ' . ($lastAttemptAuth ?: 'desconhecido') . ($lastResponseBody ? (' | body: ' . substr((string)$lastResponseBody, 0, 500)) : ''));
            return [
                'success' => false,
                'status_code' => $statusToReport,
                'message' => 'Falha ao enviar fatura',
                'error' => 'Não foi possível enviar a fatura após tentar múltiplos endpoints',
                'endpoint_used' => $lastEndpoint,
                'auth_used' => $lastAttemptAuth,
            ];

        } catch (Exception $e) {
            $this->logError('invoice', 0, 'Exceção ao enviar fatura (JSON)', $e->getMessage());
            return [
                'success' => false,
                'status_code' => 0,
                'message' => 'Erro ao enviar fatura: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Resolve o endpoint de documentos com base na URL configurada
     */
    private function getDocumentsEndpoint(): string
    {
        $this->ensureDocumentsApiUrl();
        return $this->documentsApiUrl;
    }

    /**
     * Garante que a URL da API de documentos está corretamente formatada.
     */
    private function ensureDocumentsApiUrl(): void
    {
        if (str_ends_with($this->documentsApiUrl, '/')) {
            $this->documentsApiUrl = rtrim($this->documentsApiUrl, '/');
        }
    }
    private function getPossibleSalesDocumentsEndpoints(): array
    {
        $this->ensureDocumentsApiUrl();
        $base = rtrim($this->documentsApiUrl, '/');
        $list = [];
        $list[] = $base;
        if (!preg_match('#/sales/documents$#', $base)) {
            $list[] = $base . '/sales/documents';
        }
        if (!preg_match('#/documents$#', $base)) {
            $list[] = $base . '/documents';
        }
        $apiBase = rtrim((string) $this->apiUrl, '/');
        if ($apiBase !== '') {
            $list[] = $apiBase . '/ws/v1.2/sales/documents';
            $list[] = $apiBase . '/ws/v1.2/documents';
            $list[] = $apiBase . '/ws/v1.1/sales/documents';
            $list[] = $apiBase . '/ws/v1.1/documents';
            $list[] = $apiBase . '/ws/v1.0/sales/documents';
            $list[] = $apiBase . '/ws/v1.0/documents';
        }
        return array_values(array_unique(array_filter($list)));
    }

    private function findProductId(array $payload): ?int
    {
        $ref = (string)($payload['reference'] ?? '');
        $barcode = (string)($payload['barcode'] ?? '');

        $id = $ref !== '' ? $this->findProductIdByReference($ref) : null;
        if ($id !== null) {
            return $id;
        }

        if ($barcode !== '') {
            $queries = ['barcode', 'upc', 'ean'];
            foreach ($queries as $param) {
                $url = $this->productsApiUrl . '?' . $param . '=' . urlencode($barcode);
                try {
                    $resp = Http::withToken($this->apiKey)
                        ->timeout(20)
                        ->withOptions([
                            'verify' => false,
                            'curl' => [
                                CURLOPT_SSL_VERIFYPEER => false,
                                CURLOPT_SSL_VERIFYHOST => false,
                            ]
                        ])
                        ->get($url);

                    if ($resp->successful()) {
                        $data = $resp->json();
                        $found = $this->extractFirstProductId($data);
                        if ($found !== null) {
                            return $found;
                        }
                    }
                } catch (\Exception $e) {
                }
                // Fallback: tenta com Basic Auth
                try {
                    $resp2 = Http::withBasicAuth($this->apiKey, '')
                        ->timeout(20)
                        ->withOptions([
                            'verify' => false,
                            'curl' => [
                                CURLOPT_SSL_VERIFYPEER => false,
                                CURLOPT_SSL_VERIFYHOST => false,
                            ]
                        ])
                        ->get($url);

                    if ($resp2->successful()) {
                        $data2 = $resp2->json();
                        $found2 = $this->extractFirstProductId($data2);
                        if ($found2 !== null) {
                            return $found2;
                        }
                    }
                } catch (\Exception $e) {
                }
            }
        }

        return null;
    }

    private function findProductIdByReference(string $reference): ?int
    {
        if ($reference === '') {
            return null;
        }
        // Tenta com diferentes parâmetros aceites e com dois métodos de auth
        $queries = [
            'reference',
            'code',
            'search',
            'q',
        ];

        foreach ($queries as $param) {
            $url = $this->productsApiUrl . '?' . $param . '=' . urlencode($reference);
            try {
                // 1) Bearer Token
                $resp = Http::withToken($this->apiKey)
                    ->timeout(20)
                    ->withOptions([
                        'verify' => false,
                        'curl' => [
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false,
                        ]
                    ])
                    ->get($url);

                if ($resp->successful()) {
                    $data = $resp->json();
                    $id = $this->extractFirstProductId($data);
                    if ($id !== null) {
                        return $id;
                    }
                }

                // 2) Basic Auth (alguns endpoints de listagem exigem)
                $resp2 = Http::withBasicAuth($this->apiKey, '')
                    ->timeout(20)
                    ->withOptions([
                        'verify' => false,
                        'curl' => [
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false,
                        ]
                    ])
                    ->get($url);
                if ($resp2->successful()) {
                    $data2 = $resp2->json();
                    $id2 = $this->extractFirstProductId($data2);
                    if ($id2 !== null) {
                        return $id2;
                    }
                }
            } catch (\Exception $e) {
            }
        }
        return null;
    }

    private function extractFirstProductId($data): ?int
    {
        if (is_array($data)) {
            if (isset($data['id']) && is_numeric($data['id'])) {
                return (int) $data['id'];
            }
            
            foreach ($data as $item) {
                if (is_array($item) && isset($item['id']) && is_numeric($item['id'])) {
                    return (int) $item['id'];
                }
            }
            
            foreach (['data', 'products', 'items'] as $key) {
                if (isset($data[$key]) && is_array($data[$key])) {
                    foreach ($data[$key] as $item) {
                        if (is_array($item) && isset($item['id']) && is_numeric($item['id'])) {
                            return (int) $item['id'];
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * Atualiza um produto existente via PUT.
     */
    public function updateProduct(int $productId, array $payload): array
    {
        $url = rtrim($this->productsApiUrl, '/') . '/' . $productId;

        try {
            // Evita conflito de referência durante atualização
            if (isset($payload['reference'])) {
                unset($payload['reference']);
            }
            Log::info("Payload JSON (update) para Vendus\nEndpoint: " . $url . "\n" .
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            );
            $resp = Http::withToken($this->apiKey)
                ->timeout(30)
                ->withOptions([
                    'verify' => false,
                    'curl' => [
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                    ]
                ])
                ->put($url, $payload);

            if ($resp->successful()) {
                return [
                    'success' => true,
                    'status_code' => $resp->status(),
                    'data' => $resp->json(),
                    'message' => 'Produto atualizado'
                ];
            }

            // 2) PUT com Basic Auth
            $resp2 = Http::withBasicAuth($this->apiKey, '')
                ->timeout(30)
                ->withOptions([
                    'verify' => false,
                    'curl' => [
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                    ]
                ])
                ->put($url, $payload);
            if ($resp2->successful()) {
                return [
                    'success' => true,
                    'status_code' => $resp2->status(),
                    'data' => $resp2->json(),
                    'message' => 'Produto atualizado'
                ];
            }

            // 3) PATCH com Bearer Token
            $resp3 = Http::withToken($this->apiKey)
                ->timeout(30)
                ->withOptions([
                    'verify' => false,
                    'curl' => [
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                    ]
                ])
                ->patch($url, $payload);
            if ($resp3->successful()) {
                return [
                    'success' => true,
                    'status_code' => $resp3->status(),
                    'data' => $resp3->json(),
                    'message' => 'Produto atualizado'
                ];
            }

            // 4) POST no endpoint de ID (fallback)
            $resp4 = Http::withToken($this->apiKey)
                ->timeout(30)
                ->withOptions([
                    'verify' => false,
                    'curl' => [
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                    ]
                ])
                ->post($url, $payload);
            if ($resp4->successful()) {
                return [
                    'success' => true,
                    'status_code' => $resp4->status(),
                    'data' => $resp4->json(),
                    'message' => 'Produto atualizado'
                ];
            }

            return [
                'success' => false,
                'status_code' => $resp4->status(),
                'error' => $resp4->body(),
                'message' => 'Falha ao atualizar produto'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'status_code' => 0,
                'error' => $e->getMessage(),
                'message' => 'Erro de conexão ao atualizar produto'
            ];
        }
    }

    /**
     * Garante que a URL da API aponte para o endpoint de produtos
     */
    private function ensureProductsEndpoint(string $url): string
    {
        $clean = rtrim(trim($url), '/');
        if ($clean === '') {
            return 'https://www.vendus.pt/ws/v1.2/products';
        }
        if (preg_match('#/products$#', $clean)) {
            return $clean;
        }
        // Loga correção automática de endpoint
        Log::warning('VENDUS_API_URL sem /products. Corrigindo automaticamente.', ['configured_url' => $url, 'final_url' => $clean . '/products']);
        return $clean . '/products';
    }

    private function normalizeBoolean($value): bool
    {
        if (is_bool($value)) return $value;
        if (is_null($value)) return false;
        if (is_int($value)) return $value === 1;
        if (is_string($value)) {
            $v = strtolower(trim($value));
            if ($v === '' ) return false;
            $truthy = ['true','1','on','yes','sim'];
            $falsy  = ['false','0','off','no','nao','não'];
            if (in_array($v, $truthy, true)) return true;
            if (in_array($v, $falsy, true)) return false;
            // qualquer outra string não vazia: considerar verdadeiro?
            // Para evitar erros, considerar falso se não reconhecido
            return false;
        }
        return (bool)$value;
    }


    /**
     * Constrói o payload do produto para a API do Vendus usando mapeamentos dinâmicos
     *
     * @param array $productData
     * @return array
     */
    public function buildProductPayload(array $productData): array
    {
        // Obtém os mapeamentos ativos (campos Vendus com suas colunas Excel correspondentes)
        $mappings = FieldMapping::getMappedFields();
        
        $payload = [];
        
        foreach ($mappings as $mapping) {
            $vendusField = $mapping->vendus_field;
            $excelColumn = $mapping->excel_column;
            $fieldType = $mapping->field_type;
            $isRequired = $mapping->is_required;
            $defaultValue = $mapping->default_value;
            
            $value = null;
            
            // Primeiro, verifica se o campo Vendus existe diretamente nos dados
            if (isset($productData[$vendusField])) {
                $value = $productData[$vendusField];
            }
            // Se não encontrou diretamente e tem coluna Excel mapeada, busca pela coluna
            elseif ($excelColumn) {
                // Busca o valor nos dados do produto (case-insensitive)
                foreach ($productData as $key => $val) {
                    if (strtolower($key) === strtolower($excelColumn)) {
                        $value = $val;
                        break;
                    }
                }
            }
            
            // Se não encontrou o valor e tem valor padrão, usa o padrão
            if ($value === null && $defaultValue !== null) {
                $value = $defaultValue;
            }
            
            // Se é obrigatório e não tem valor, aplica lógica específica
            if ($isRequired && ($value === null || $value === '')) {
                // Valores padrão específicos para campos obrigatórios
                switch ($vendusField) {
                    case 'unit_id':
                        // Não forçar um ID fixo; será resolvido automaticamente abaixo
                        $value = null;
                        break;
                    case 'status':
                        $value = 'on'; // Status ativo
                        break;
                    default:
                        if ($defaultValue !== null) {
                            $value = $defaultValue;
                        } else {
                            // Se é obrigatório e não tem valor nem mapeamento, pula este produto
                            Log::warning("Campo obrigatório '{$vendusField}' não tem mapeamento ou valor para produto", [
                                'product_data' => $productData,
                                'vendus_field' => $vendusField,
                                'excel_column' => $excelColumn
                            ]);
                            continue 2; // Continue the outer foreach loop
                        }
                }
            }
            
            // Aplica conversão de tipo se necessário
            if ($value !== null) {
                if ($vendusField === 'reference') {
                    $value = (string)$value;
                } else {
                    switch ($fieldType) {
                        case 'number':
                            $value = (int)$value;
                            break;
                        case 'boolean':
                            $value = $this->normalizeBoolean($value) ? 'true' : 'false';
                            break;
                        case 'string':
                        default:
                            $value = (string)$value;
                            break;
                    }
                }
                
                if ($vendusField !== 'supply_price' && $vendusField !== 'gross_price' && $vendusField !== 'price') {
                    $payload[$vendusField] = $value;
                }
            }
        }
        
        if (isset($payload['unit_id'])) {
            $payload['unit_id'] = (string) $payload['unit_id'];
        }
        if (!isset($payload['status'])) {
            $payload['status'] = 'on';
        }
        // Inclui referência se veio dos dados, mesmo sem mapeamento ativo
        if (!isset($payload['reference']) && isset($productData['reference'])) {
            $payload['reference'] = (string) $productData['reference'];
        }
        // Garante título mesmo sem mapeamento
        if (!isset($payload['title']) && isset($productData['title'])) {
            $payload['title'] = (string) $productData['title'];
        }
        // Inclui barcode se presente
        if (!isset($payload['barcode']) && isset($productData['barcode'])) {
            $payload['barcode'] = (string) $productData['barcode'];
        }

        $pricesObj = [];
        $supply = null;
        if (isset($productData['supply']) || isset($productData['supply_price'])) {
            $supply = isset($productData['supply']) ? $productData['supply'] : $productData['supply_price'];
            $supply = number_format((float) $supply, 2, '.', '');
        }
        $gross = null;
        if (isset($productData['gross'])) {
            $gross = number_format((float) $productData['gross'], 2, '.', '');
        } elseif (isset($productData['gross_price'])) {
            $gross = number_format((float) $productData['gross_price'], 2, '.', '');
        } elseif (isset($productData['price'])) {
            $gross = number_format((float) $productData['price'], 2, '.', '');
        }
        if ($supply !== null) {
            $pricesObj['supply'] = (string) $supply;
        }
        if ($gross !== null) {
            $pricesObj['gross'] = (string) $gross;
        }
        $groupId = isset($productData['price_group_id']) ? $productData['price_group_id'] : null;
        $groupGross = isset($productData['price_group_gross']) ? $productData['price_group_gross'] : null;
        if ($groupId !== null && $groupId !== '' && $groupGross !== null && $groupGross !== '') {
            $pricesObj['groups'] = [
                [
                    'id' => (string) $groupId,
                    'gross' => (string) number_format((float) $groupGross, 2, '.', ''),
                ]
            ];
        }
        if (!empty($pricesObj)) {
            $payload['prices'] = $pricesObj;
        }
        unset($payload['supply_price']);
        unset($payload['gross_price']);
        unset($payload['stock_type']);

        // Converte possíveis campos antigos para os atuais
        $taxObj = [];
        if (isset($productData['tax_id']) && $productData['tax_id'] !== '') {
            $taxObj['id'] = (string) $productData['tax_id'];
        }
        if (isset($productData['tax_exemption']) && $productData['tax_exemption'] !== '') {
            $taxObj['exemption'] = (string) $productData['tax_exemption'];
        }
        if (isset($productData['tax_exemption_law']) && $productData['tax_exemption_law'] !== '') {
            $taxObj['exemption_law'] = (string) $productData['tax_exemption_law'];
        }
        if (!empty($taxObj)) {
            $payload['tax'] = $taxObj;
        }

        $stockObj = [];
        if (isset($productData['stock_control']) && $productData['stock_control'] !== '') {
            $stockObj['control'] = (string) $productData['stock_control'];
        }
        if (isset($productData['stock_type']) && $productData['stock_type'] !== '') {
            $stockObj['type'] = (string) $productData['stock_type'];
        }
        $storeId = $productData['stock_store_id'] ?? null;
        $pvId = $productData['product_variant_id'] ?? null;
        $stockQty = $productData['stock_stock'] ?? null;
        $stockAlert = $productData['stock_stock_alert'] ?? null;
        if ($storeId !== null || $pvId !== null || $stockQty !== null || $stockAlert !== null) {
            $entry = [];
            if ($storeId !== null) $entry['store_id'] = (string) $storeId;
            if ($pvId !== null) $entry['product_variant_id'] = (string) $pvId;
            if ($stockQty !== null) $entry['stock'] = (string) number_format((float) $stockQty, 2, '.', '');
            if ($stockAlert !== null) $entry['stock_alert'] = (string) number_format((float) $stockAlert, 2, '.', '');
            $stockObj['stores'] = [$entry];
        }
        if (!empty($stockObj)) {
            $payload['stock'] = $stockObj;
        }

        if (isset($productData['lot_control'])) {
            $payload['lot_control'] = (string) $productData['lot_control'];
        }

        // 'stores' é opcional; incluir apenas quando houver dados
        if (!isset($payload['stores'])) {
            // não definir stores vazio para evitar serialização estranha
        }

        // Whitelist de campos permitidos em v1.2 (observado via respostas da API)
        $allowed = [
            'reference','barcode','supplier_code','title','description','include_description',
            'unit_id','type_id','variant_id','class_id','prices','stock','tax','lot_control',
            'category_id','brand_id','image','status','stores','variants','modifiers'
        ];
        foreach (array_keys($payload) as $key) {
            if (!in_array($key, $allowed, true)) {
                unset($payload[$key]);
            }
        }
        
        return $payload;
    }

    /**
     * Valida os dados do produto antes do envio
     *
     * @param array $data
     * @return array
     */
    public function validateProductData(array $data): array
    {
        $errors = [];

        if (empty($data['title'])) {
            $errors[] = "Campo obrigatório 'title' está vazio";
        }
        if (empty($data['unit_id'])) {
            $errors[] = "Campo obrigatório 'unit_id' está vazio";
        }

        if (isset($data['prices']) && is_array($data['prices'])) {
            if (isset($data['prices']['gross']) && $data['prices']['gross'] !== '') {
                $g = $data['prices']['gross'];
                if (!is_numeric($g)) {
                    $errors[] = "prices.gross deve ser numérico";
                }
            }
            if (isset($data['prices']['supply']) && $data['prices']['supply'] !== '') {
                $s = $data['prices']['supply'];
                if (!is_numeric($s)) {
                    $errors[] = "prices.supply deve ser numérico";
                }
            }
            if (isset($data['prices']['groups']) && is_array($data['prices']['groups'])) {
                foreach ($data['prices']['groups'] as $idx => $grp) {
                    if (isset($grp['gross']) && $grp['gross'] !== '' && !is_numeric($grp['gross'])) {
                        $errors[] = "prices.groups[{$idx}].gross deve ser numérico";
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function logError(string $reference, int $statusCode, string $error, string $response): void
    {
        $logMessage = [
            'timestamp' => now()->toISOString(),
            'reference' => $reference,
            'status_code' => $statusCode,
            'error' => $error,
            'response' => $response
        ];

        Log::channel('single')->error('Vendus API Error', $logMessage);
        
        $logFile = storage_path('logs/vendus_errors.log');
        file_put_contents($logFile, json_encode($logMessage) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public function getUnits(): array
    {
        try {
            // Constrói lista de endpoints possíveis cobrindo v1.2, v1.1 e v1.0
            $base = preg_replace('#/products$#', '', $this->productsApiUrl);
            $v12 = $base; // ex.: https://www.vendus.pt/ws/v1.2
            $v11 = preg_replace('#/v1\.2$#', '/v1.1', $v12);
            $v10 = preg_replace('#/v1\.1$#', '/v1.0', $v11);

            $possibleEndpoints = array_values(array_unique(array_filter([
                $v12 . '/units',
                $v12 . '/unit',
                $v12 . '/product-units',
                $v12 . '/measurement-units',
                $v11 . '/units',
                $v11 . '/unit',
                $v11 . '/product-units',
                $v11 . '/measurement-units',
                $v10 . '/units',
                $v10 . '/unit',
            ])));

            foreach ($possibleEndpoints as $endpoint) {
                Log::info('Tentando endpoint de unidades: ' . $endpoint);

                // Vendus costuma requerer Basic Auth (api_key como usuário)
                $response = Http::withBasicAuth($this->apiKey, '')
                    ->timeout(30)
                    ->withOptions([
                        'verify' => false,
                        'curl' => [
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false,
                        ]
                    ])
                    ->get($endpoint);

                if ($response->successful()) {
                    $units = $response->json();
                    Log::info('Unidades obtidas da API Vendus', ['endpoint' => $endpoint, 'units' => $units]);
                    return [
                        'success' => true,
                        'data' => $units,
                        'endpoint' => $endpoint,
                        'message' => 'Unidades obtidas com sucesso'
                    ];
                } else {
                    Log::warning('Endpoint de unidades falhou', [
                        'endpoint' => $endpoint,
                        'status_code' => $response->status(),
                        'response' => $response->body()
                    ]);
                }
            }

            // Se nenhum endpoint funcionou, retorna erro
            return [
                'success' => false,
                'error' => 'Nenhum endpoint de unidades encontrado',
                'message' => 'Não foi possível encontrar o endpoint correto para unidades. Verifique VENDUS_API_URL e versão da API.'
            ];

        } catch (Exception $e) {
            Log::error('Erro de conexão ao obter unidades', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Erro de conexão ao obter unidades'
            ];
        }
    }

    private function resolveStoreIdFromInvoice(array $invoice): ?int
    {
        $header = (array) ($invoice['header'] ?? []);
        $name = null;
        if (isset($header['store_title']) && is_string($header['store_title'])) {
            $name = trim($header['store_title']);
        } elseif (isset($header['register_title']) && is_string($header['register_title'])) {
            $name = trim($header['register_title']);
        }
        if ($name === null || $name === '') {
            return null;
        }
        $pair = $this->getRegisterAndStoreByTitle($name);
        return $pair ? ($pair['store_id'] ?? null) : null;
    }

    private function getStoreIdByName(string $name): ?int
    {
        $base = preg_replace('#/products$#', '', (string) $this->productsApiUrl);
        $v12 = $base;
        $v11 = preg_replace('#/v1\.2$#', '/v1.1', $v12);
        $v10 = preg_replace('#/v1\.1$#', '/v1.0', $v11);
        $possibleEndpoints = array_values(array_unique(array_filter([
            $v12 . '/stores',
            $v12 . '/store',
            $v11 . '/stores',
            $v10 . '/stores',
        ])));
        foreach ($possibleEndpoints as $endpoint) {
            try {
                $resp = Http::withBasicAuth($this->apiKey, '')
                    ->timeout(30)
                    ->withOptions([
                        'verify' => false,
                        'curl' => [
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false,
                        ]
                    ])
                    ->get($endpoint);
                if ($resp->successful()) {
                    $data = $resp->json();
                    if (is_array($data)) {
                        if (isset($data['id']) && isset($data['title'])) {
                            $title = is_string($data['title']) ? trim($data['title']) : '';
                            if ($title !== '' && strtolower($title) === strtolower($name) && is_numeric($data['id'])) {
                                return (int) $data['id'];
                            }
                        }
                        foreach ($data as $item) {
                            if (is_array($item) && isset($item['title']) && isset($item['id'])) {
                                $title = is_string($item['title']) ? trim($item['title']) : '';
                                if ($title !== '' && strtolower($title) === strtolower($name) && is_numeric($item['id'])) {
                                    return (int) $item['id'];
                                }
                            }
                        }
                        if (isset($data['data']) && is_array($data['data'])) {
                            foreach ($data['data'] as $item) {
                                if (is_array($item) && isset($item['title']) && isset($item['id'])) {
                                    $title = is_string($item['title']) ? trim($item['title']) : '';
                                    if ($title !== '' && strtolower($title) === strtolower($name) && is_numeric($item['id'])) {
                                        return (int) $item['id'];
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
            }
        }
        return null;
    }

    private function resolveRegisterIdFromInvoice(array $invoice, ?int $storeId): ?int
    {
        $header = (array) ($invoice['header'] ?? []);
        $name = null;
        if (isset($header['register_title']) && is_string($header['register_title'])) {
            $name = trim($header['register_title']);
        } elseif (isset($header['store_title']) && is_string($header['store_title'])) {
            $name = trim($header['store_title']);
        }
        if ($name && $name !== '') {
            $pair = $this->getRegisterAndStoreByTitle($name);
            if ($pair && isset($pair['register_id']) && is_numeric($pair['register_id'])) {
                return (int) $pair['register_id'];
            }
        }
        if ($storeId !== null) {
            return $this->getApiRegisterIdForStore($storeId);
        }
        return null;
    }

    private function getRegisterAndStoreByTitle(string $title): ?array
    {
        $base = preg_replace('#/products$#', '', (string) $this->productsApiUrl);
        $v12 = $base;
        $v11 = preg_replace('#/v1\.2$#', '/v1.1', $v12);
        $v10 = preg_replace('#/v1\.1$#', '/v1.0', $v11);
        $possibleEndpoints = array_values(array_unique(array_filter([
            $v12 . '/registers',
            $v11 . '/registers',
            $v10 . '/registers',
        ])));
        foreach ($possibleEndpoints as $endpoint) {
            try {
                $resp = Http::withBasicAuth($this->apiKey, '')
                    ->timeout(30)
                    ->withOptions([
                        'verify' => false,
                        'curl' => [
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false,
                        ]
                    ])
                    ->get($endpoint);
                if ($resp->successful()) {
                    $data = $resp->json();
                    $items = [];
                    if (is_array($data)) {
                        if (isset($data['data']) && is_array($data['data'])) {
                            $items = $data['data'];
                        } else {
                            $items = $data;
                        }
                    }
                    foreach ($items as $item) {
                        if (!is_array($item)) continue;
                        $t = isset($item['title']) && is_string($item['title']) ? trim($item['title']) : '';
                        if ($t !== '' && strtolower($t) === strtolower($title)) {
                            $regId = isset($item['id']) && is_numeric($item['id']) ? (int) $item['id'] : null;
                            $storeId = isset($item['store_id']) && is_numeric($item['store_id']) ? (int) $item['store_id'] : null;
                            if ($regId !== null || $storeId !== null) {
                                return [
                                    'register_id' => $regId,
                                    'store_id' => $storeId,
                                ];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
            }
        }
        return null;
    }

    private function getRegisterIdByName(string $name, ?int $storeId): ?int
    {
        $base = preg_replace('#/products$#', '', (string) $this->productsApiUrl);
        $v12 = $base;
        $v11 = preg_replace('#/v1\.2$#', '/v1.1', $v12);
        $v10 = preg_replace('#/v1\.1$#', '/v1.0', $v11);
        $possibleEndpoints = array_values(array_unique(array_filter([
            $v12 . '/registers',
            $v12 . '/store-registers',
            $storeId ? ($v12 . '/stores/' . $storeId . '/registers') : null,
            $v11 . '/registers',
            $v10 . '/registers',
        ])));
        foreach ($possibleEndpoints as $endpoint) {
            try {
                $resp = Http::withBasicAuth($this->apiKey, '')
                    ->timeout(30)
                    ->withOptions([
                        'verify' => false,
                        'curl' => [
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false,
                        ]
                    ])
                    ->get($endpoint);
                if ($resp->successful()) {
                    $data = $resp->json();
                    if (is_array($data)) {
                        foreach ($data as $item) {
                            if (is_array($item)) {
                                $title = isset($item['title']) && is_string($item['title']) ? trim($item['title']) : (isset($item['name']) && is_string($item['name']) ? trim($item['name']) : '');
                                if ($title !== '' && strtolower($title) === strtolower($name) && isset($item['id']) && is_numeric($item['id'])) {
                                    return (int) $item['id'];
                                }
                            }
                        }
                        if (isset($data['data']) && is_array($data['data'])) {
                            foreach ($data['data'] as $item) {
                                if (is_array($item)) {
                                    $title = isset($item['title']) && is_string($item['title']) ? trim($item['title']) : (isset($item['name']) && is_string($item['name']) ? trim($item['name']) : '');
                                    if ($title !== '' && strtolower($title) === strtolower($name) && isset($item['id']) && is_numeric($item['id'])) {
                                        return (int) $item['id'];
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
            }
        }
        return null;
    }

    private function getApiRegisterIdForStore(int $storeId): ?int
    {
        $base = preg_replace('#/products$#', '', (string) $this->productsApiUrl);
        $v12 = $base;
        $possibleEndpoints = array_values(array_unique(array_filter([
            $v12 . '/stores/' . $storeId . '/registers',
            $v12 . '/registers?store_id=' . $storeId,
        ])));
        foreach ($possibleEndpoints as $endpoint) {
            try {
                $resp = Http::withBasicAuth($this->apiKey, '')
                    ->timeout(30)
                    ->withOptions([
                        'verify' => false,
                        'curl' => [
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false,
                        ]
                    ])
                    ->get($endpoint);
                if ($resp->successful()) {
                    $data = $resp->json();
                    if (is_array($data)) {
                        foreach ($data as $item) {
                            if (is_array($item)) {
                                $type = isset($item['type']) ? $item['type'] : (isset($item['register_type']) ? $item['register_type'] : null);
                                if (is_string($type) && strtoupper(trim($type)) === 'API' && isset($item['id']) && is_numeric($item['id'])) {
                                    return (int) $item['id'];
                                }
                            }
                        }
                        if (isset($data['data']) && is_array($data['data'])) {
                            foreach ($data['data'] as $item) {
                                if (is_array($item)) {
                                    $type = isset($item['type']) ? $item['type'] : (isset($item['register_type']) ? $item['register_type'] : null);
                                    if (is_string($type) && strtoupper(trim($type)) === 'API' && isset($item['id']) && is_numeric($item['id'])) {
                                        return (int) $item['id'];
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
            }
        }
        return null;
    }

    /**
     * Retorna a lista de IDs de unidades permitidas na conta Vendus (cacheada por 24h)
     *
     * @return array<int>
     */
    private function getAllowedUnitIds(): array
    {
        return Cache::remember('vendus.allowed_unit_ids', 60 * 60 * 24, function () {
            $result = $this->getUnits();
            if (!($result['success'] ?? false)) {
                return [];
            }
            $data = $result['data'];
            $ids = [];
            if (is_array($data)) {
                foreach ($data as $item) {
                    if (is_array($item)) {
                        if (isset($item['id']) && is_numeric($item['id'])) {
                            $ids[] = (int) $item['id'];
                        } else {
                            foreach ($item as $sub) {
                                if (is_array($sub) && isset($sub['id']) && is_numeric($sub['id'])) {
                                    $ids[] = (int) $sub['id'];
                                }
                            }
                        }
                    }
                }
            }
            return array_values(array_unique($ids));
        });
    }

    /**
     * Resolve automaticamente um unit_id válido, usando env ou cacheando a resposta da API.
     * Retorna null se não conseguir resolver.
     */
    private function getDefaultUnitId(): ?int
    {
        $envUnit = env('VENDUS_DEFAULT_UNIT_ID');
        if ($envUnit !== null && $envUnit !== '' && is_numeric($envUnit)) {
            return (int) $envUnit;
        }

        try {
            return Cache::remember('vendus.default_unit_id', 60 * 60 * 24, function () {
                $result = $this->getUnits();
                if (!($result['success'] ?? false)) {
                    return null;
                }
                $data = $result['data'];
                // Procurar por um campo 'id' numérico em cada item
                if (is_array($data)) {
                    foreach ($data as $item) {
                        if (is_array($item)) {
                            if (isset($item['id'])) {
                                $id = $item['id'];
                                if (is_numeric($id)) {
                                    return (int) $id;
                                }
                            }
                            // Alguns endpoints retornam nested objects
                            foreach ($item as $sub) {
                                if (is_array($sub) && isset($sub['id'])) {
                                    $id = $sub['id'];
                                    if (is_numeric($id)) {
                                        return (int) $id;
                                    }
                                }
                            }
                        }
                    }
                }
                return null;
            });
        } catch (Exception $e) {
            Log::warning('Não foi possível resolver unit_id automaticamente', ['error' => $e->getMessage()]);
            return null;
        }
    }
}