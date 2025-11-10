<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\FieldMapping;
use Exception;

class VendusService
{
    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = (string) env('VENDUS_API_KEY');
        // Normaliza e garante que o endpoint aponte para /products
        $configuredUrl = (string) env('VENDUS_API_URL');
        $this->apiUrl = $this->ensureProductsEndpoint($configuredUrl);
    }

    /**
     * Envia um produto para a API da Vendus
     *
     * @param array $data
     * @return array
     */
    public function sendProduct(array $data): array
    {
        try {
            if (empty($this->apiKey)) {
                throw new Exception('VENDUS_API_KEY não configurada no .env');
            }
            // Constrói o payload usando os mapeamentos e valores padrão
            $payload = $this->buildProductPayload($data);
            // Log leve para depuração de unit_id
            try {
                Log::info('Enviando produto para Vendus', [
                    'reference' => $payload['reference'] ?? ($data['reference'] ?? null),
                    'title' => $payload['title'] ?? ($data['title'] ?? null),
                    'unit_id' => $payload['unit_id'] ?? null,
                    'has_prices' => isset($payload['prices']),
                    'price_top' => $payload['price'] ?? null,
                    'prices_payload' => $payload['prices'] ?? null,
                ]);
            } catch (\Exception $e) {}
            
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->withOptions([
                    'verify' => false, // Desabilita verificação SSL para desenvolvimento
                    'curl' => [
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                    ]
                ])
                ->post($this->apiUrl, $payload);

            $body = $response->json();
            $location = $response->header('Location');
            $hasId = is_array($body) && (isset($body['id']) || isset($body['product_id']));
            $created = $response->successful() && ($hasId || ($location && str_contains($location, '/products')));

            if ($created) {
                return [
                    'success' => true,
                    'status_code' => $response->status(),
                    'data' => $response->json(),
                    'message' => 'Produto enviado com sucesso'
                ];
            } else {
                // Fallback: tentar criação com Basic Auth (alguns ambientes exigem)
                $response2 = Http::withBasicAuth($this->apiKey, '')
                    ->timeout(30)
                    ->withOptions([
                        'verify' => false,
                        'curl' => [
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false,
                        ]
                    ])
                    ->post($this->apiUrl, $payload);

                $body2 = $response2->json();
                $location2 = $response2->header('Location');
                $hasId2 = is_array($body2) && (isset($body2['id']) || isset($body2['product_id']));
                $created2 = $response2->successful() && ($hasId2 || ($location2 && str_contains($location2, '/products')));

                if ($created2) {
                    return [
                        'success' => true,
                        'status_code' => $response2->status(),
                        'data' => $response2->json(),
                        'message' => 'Produto enviado com sucesso'
                    ];
                }

                // Prossegue com tratamento de erro usando a última resposta
                $response = $response2;
                $body = $body2;
                $location = $location2;
                $hasId = $hasId2;

                $errorMessage = 'Erro desconhecido';
                $responseData = $body;
                
                if (isset($responseData['errors']) && is_array($responseData['errors']) && !empty($responseData['errors'])) {
                    $firstError = $responseData['errors'][0];
                    if (isset($firstError['message'])) {
                        $errorMessage = $firstError['message'];

                        if (isset($firstError['code'])) {
                            $errorMessage = "[{$firstError['code']}] {$errorMessage}";

                            if ($firstError['code'] === 'P005') {
                                $errorMessage .= ' — Verifique o unit_id: mapeie a Unidade com um ID válido ou defina VENDUS_DEFAULT_UNIT_ID no .env.';
                            }

                            if ($firstError['code'] === 'A001') {
                                $foundId = $this->findProductId($payload);
                                if ($foundId) {
                                        $update = $this->updateProduct($foundId, $payload);
                                        if ($update['success'] ?? false) {
                                            return [
                                                'success' => true,
                                                'status_code' => $update['status_code'] ?? 200,
                                                'data' => $update['data'] ?? [],
                                                'message' => 'Produto atualizado com sucesso (referência já existia)'
                                            ];
                                        } else {
                                            $errorMessage .= ' — tentativa de atualização falhou: ' . ($update['message'] ?? 'desconhecido');
                                        }
                                } else {
                                    $errorMessage .= ' — referência existente, mas não foi possível obter ID para atualização.';
                                }
                            }
                        }
                    }
                } elseif (isset($responseData['message'])) {
                    $errorMessage = $responseData['message'];
                } elseif ($response->successful() && !$hasId) {
                    $errorMessage = 'Resposta 2xx sem ID de produto. Verifique VENDUS_API_URL (deve terminar em /products), credenciais e payload.';
                }
                
                $ref = $data['reference'] ?? ($payload['reference'] ?? ($data['title'] ?? 'unknown'));
                $this->logError($ref, $response->status(), $errorMessage, $response->body());
                
                return [
                    'success' => false,
                    'status_code' => $response->status(),
                    'error' => $errorMessage,
                    'message' => "Erro ao enviar produto: {$errorMessage}"
                ];
            }
        } catch (Exception $e) {
            $this->logError($data['reference'] ?? 'unknown', 0, $e->getMessage(), '');
            
            return [
                'success' => false,
                'status_code' => 0,
                'error' => $e->getMessage(),
                'message' => "Erro de conexão: {$e->getMessage()}"
            ];
        }
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

            // 1) Tentativa com Bearer Token
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
                // 2) Fallback com Basic Auth
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

            $documentsUrl = $this->getDocumentsEndpoint();

            // Monta payload básico a partir do array recebido
            $header = (array) ($invoice['header'] ?? []);
            $items = (array) ($invoice['items'] ?? []);

            $payload = [
                'series' => $header['series'] ?? null,
                'customer_name' => $header['customer_name'] ?? null,
                'customer_nif' => $header['customer_nif'] ?? null,
                'date' => $header['date'] ?? null,
                'notes' => $header['notes'] ?? null,
                // Alguns formatos utilizam 'items', outros 'products'; enviamos ambos para ampliar compatibilidade
                'items' => array_map(function ($i) {
                    return [
                        'reference' => $i['reference'] ?? null,
                        'title' => $i['title'] ?? null,
                        'qty' => $i['quantity'] ?? ($i['qty'] ?? 1),
                        'gross_price' => isset($i['gross_price']) ? number_format((float)$i['gross_price'], 2, '.', '') : null,
                        'tax' => $i['tax'] ?? null,
                    ];
                }, $items),
                'products' => array_map(function ($i) {
                    return [
                        'reference' => $i['reference'] ?? null,
                        'title' => $i['title'] ?? null,
                        'qty' => $i['quantity'] ?? ($i['qty'] ?? 1),
                        'gross_price' => isset($i['gross_price']) ? number_format((float)$i['gross_price'], 2, '.', '') : null,
                        'tax' => $i['tax'] ?? null,
                    ];
                }, $items),
            ];

            // Remove chaves nulas para evitar rejeições
            $payload = array_filter($payload, function ($v) {
                if ($v === null) return false;
                if (is_array($v)) return !empty($v);
                return true;
            });

            Log::info('Enviando fatura (JSON) para Vendus', [
                'endpoint' => $documentsUrl,
                'items' => count($payload['items'] ?? []),
                'customer_name' => $payload['customer_name'] ?? null,
                'series' => $payload['series'] ?? null,
            ]);

            // 1) Tentativa com Bearer Token
            $resp = Http::withToken($this->apiKey)
                ->timeout(30)
                ->withOptions([
                    'verify' => false,
                    'curl' => [
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                    ]
                ])
                ->post($documentsUrl, $payload);

            if ($resp->successful()) {
                return [
                    'success' => true,
                    'status_code' => $resp->status(),
                    'data' => $resp->json(),
                    'message' => 'Fatura enviada com sucesso'
                ];
            }

            // 2) Fallback com Basic Auth
            $resp2 = Http::withBasicAuth($this->apiKey, '')
                ->timeout(30)
                ->withOptions([
                    'verify' => false,
                    'curl' => [
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                    ]
                ])
                ->post($documentsUrl, $payload);

            if ($resp2->successful()) {
                return [
                    'success' => true,
                    'status_code' => $resp2->status(),
                    'data' => $resp2->json(),
                    'message' => 'Fatura enviada com sucesso'
                ];
            }

            $this->logError('invoice', $resp2->status(), 'Falha ao enviar fatura (JSON)', $resp2->body());
            return [
                'success' => false,
                'status_code' => $resp2->status(),
                'message' => 'Falha ao enviar fatura',
                'error' => $resp2->body(),
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
        $productsUrl = rtrim($this->apiUrl, '/');
        // Substitui /products por /documents ou usa default
        if (preg_match('#/products$#', $productsUrl)) {
            return preg_replace('#/products$#', '/documents', $productsUrl);
        }
        $base = preg_replace('#/products/?#', '', $productsUrl);
        if ($base === '') {
            return 'https://www.vendus.pt/ws/v1.2/documents';
        }
        return rtrim($base, '/') . '/documents';
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
                $url = $this->apiUrl . '?' . $param . '=' . urlencode($barcode);
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
            $url = $this->apiUrl . '?' . $param . '=' . urlencode($reference);
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
        $url = rtrim($this->apiUrl, '/') . '/' . $productId;

        try {
            // Evita conflito de referência durante atualização
            if (isset($payload['reference'])) {
                unset($payload['reference']);
            }
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
                switch ($fieldType) {
                    case 'number':
                        $value = (int)$value;
                        break;
                    case 'boolean':
                        $value = $value ? 'on' : 'off';
                        break;
                    case 'string':
                    default:
                        $value = (string)$value;
                        break;
                }
                
                if ($vendusField !== 'supply_price' && $vendusField !== 'gross_price' && $vendusField !== 'price') {
                    $payload[$vendusField] = $value;
                }
            }
        }
        
        // Garante que campos essenciais estejam presentes
        // unit_id: v1.2 exige ID válido da sua conta. Resolver automaticamente se ausente ou inválido.
        if (!isset($payload['unit_id']) || !is_numeric($payload['unit_id']) || (int)$payload['unit_id'] <= 0) {
            $resolvedUnit = $this->getDefaultUnitId();
            if ($resolvedUnit !== null) {
                $payload['unit_id'] = $resolvedUnit;
            }
        } else {
            // Normaliza para inteiro
            $payload['unit_id'] = (int) $payload['unit_id'];
        }
        // Se veio um unit_id numérico porém não pertence às unidades da conta, tenta corrigir para o default
        try {
            $allowed = $this->getAllowedUnitIds();
            if (!empty($allowed)) {
                if (!isset($payload['unit_id']) || !in_array((int)$payload['unit_id'], $allowed, true)) {
                    $fallback = $this->getDefaultUnitId();
                    if ($fallback !== null && in_array($fallback, $allowed, true)) {
                        $payload['unit_id'] = $fallback;
                    }
                }
            }
        } catch (\Exception $e) {
            // Falha em obter lista de unidades não impede envio; API retornará erro mais claro
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

        $prices = [];
        // Prioriza gross_price do variant/produto
        $gross = null;
        if (isset($productData['gross_price'])) {
            $gross = number_format((float) $productData['gross_price'], 2, '.', '');
        } elseif (isset($productData['price'])) { // alguns dados de variação usam 'price'
            $gross = number_format((float) $productData['price'], 2, '.', '');
        }
        if ($gross !== null) {
            $priceEntry = ['gross_price' => $gross];
            $groupId = env('VENDUS_PRICE_GROUP_ID');
            if ($groupId !== null && $groupId !== '' && is_numeric($groupId)) {
                $priceEntry['id'] = (int) $groupId;
            }
            $prices[] = $priceEntry;
        }
        if (!empty($prices)) {
            $payload['prices'] = $prices;
        }
        // Fallback: também define preço no campo de topo, para compatibilidade
        if ($gross !== null) {
            $payload['price'] = $gross;
        }
        unset($payload['supply_price']);
        unset($payload['gross_price']);
        unset($payload['stock_type']);

        // Converte possíveis campos antigos para os atuais
        if (isset($payload['tax_id']) && !isset($payload['tax'])) {
            $payload['tax'] = $payload['tax_id'];
            unset($payload['tax_id']);
        }

        // Whitelist de campos permitidos em v1.2 (observado via respostas da API)
        $allowed = [
            'reference','barcode','supplier_code','title','description','include_description',
            'unit_id','type_id','variant_id','class_id','prices','stock','tax','lot_control',
            'category_id','brand_id','image','status','stores'
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

        $requiredFields = ['reference', 'title'];
        
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[] = "Campo obrigatório '{$field}' está vazio";
            }
        }

        if (empty($data['prices'])) {
            $hasVariants = !empty($data['variants']) && is_array($data['variants']);
            if (!$hasVariants) {
                if (empty($data['gross_price']) && empty($data['price'])) {
                    $errors[] = "Preço de venda (prices[].gross_price) está vazio";
                } elseif (!empty($data['gross_price']) && !is_numeric($data['gross_price'])) {
                    $errors[] = "Preço de venda deve ser numérico";
                } elseif (!empty($data['price']) && !is_numeric($data['price'])) {
                    $errors[] = "Preço de venda deve ser numérico";
                }
            }
        } else {
            foreach ($data['prices'] as $idx => $p) {
                if (empty($p['gross_price'])) {
                    $errors[] = "prices[{$idx}].gross_price é obrigatório";
                } elseif (!is_numeric($p['gross_price'])) {
                    $errors[] = "prices[{$idx}].gross_price deve ser numérico";
                }
            }
        }

        $mappingDefaultUnit = null;
        try {
            $unitMapping = FieldMapping::getByVendusField('unit_id');
            if ($unitMapping && $unitMapping->default_value !== null && $unitMapping->default_value !== '' && is_numeric($unitMapping->default_value)) {
                $mappingDefaultUnit = (int) $unitMapping->default_value;
            }
        } catch (Exception $e) {
        }

        $allowedUnits = [];
        try {
            $allowedUnits = $this->getAllowedUnitIds();
        } catch (\Exception $e) {
        }

        if (isset($data['unit_id']) && $data['unit_id'] !== '') {
            if (!is_numeric($data['unit_id'])) {
                $errors[] = "unit_id deve ser numérico";
            } else {
                $unitVal = (int) $data['unit_id'];
                if (!empty($allowedUnits) && !in_array($unitVal, $allowedUnits, true)) {
                    $errors[] = "unit_id inválido: o valor não pertence às suas Unidades Vendus";
                }
            }
        } else {
            $resolvedUnit = $mappingDefaultUnit ?? $this->getDefaultUnitId();
            if ($resolvedUnit === null) {
                $errors[] = "Unidade (unit_id) não configurada. Configure o mapeamento 'Unidade' (valor padrão numérico) ou defina VENDUS_DEFAULT_UNIT_ID no .env";
            } elseif (!empty($allowedUnits) && !in_array($resolvedUnit, $allowedUnits, true)) {
                $errors[] = "unit_id padrão inválido: o valor do mapeamento/ENV não pertence às suas Unidades Vendus";
            }
        }

        if (!empty($data['variants'])) {
            foreach ($data['variants'] as $index => $variant) {
                if (empty($variant['size'])) {
                    $errors[] = "Variante {$index}: campo 'size' é obrigatório";
                }
                if (empty($variant['upc_no'])) {
                    $errors[] = "Variante {$index}: campo 'upc_no' é obrigatório";
                }
                if (empty($variant['code'])) {
                    $errors[] = "Variante {$index}: campo 'code' é obrigatório";
                }
                if (!isset($variant['price']) || $variant['price'] === '') {
                    $errors[] = "Variante {$index}: campo 'price' é obrigatório";
                } elseif (!is_numeric($variant['price'])) {
                    $errors[] = "Variante {$index}: campo 'price' deve ser numérico";
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
            $base = preg_replace('#/products$#', '', $this->apiUrl);
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