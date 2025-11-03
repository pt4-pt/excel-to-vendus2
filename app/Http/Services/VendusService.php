<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\FieldMapping;
use Exception;

class VendusService
{
    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = env('VENDUS_API_KEY');
        $this->apiUrl = env('VENDUS_API_URL');
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
            // Constrói o payload usando os mapeamentos e valores padrão
            $payload = $this->buildProductPayload($data);
            
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

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status_code' => $response->status(),
                    'data' => $response->json(),
                    'message' => 'Produto enviado com sucesso'
                ];
            } else {
                // Extrai mensagem de erro mais específica da resposta da API
                $errorMessage = 'Erro desconhecido';
                $responseData = $response->json();
                
                if (isset($responseData['errors']) && is_array($responseData['errors']) && !empty($responseData['errors'])) {
                    $firstError = $responseData['errors'][0];
                    if (isset($firstError['message'])) {
                        $errorMessage = $firstError['message'];
                        // Adiciona código do erro se disponível
                        if (isset($firstError['code'])) {
                            $errorMessage = "[{$firstError['code']}] {$errorMessage}";
                        }
                    }
                } elseif (isset($responseData['message'])) {
                    $errorMessage = $responseData['message'];
                }
                
                $this->logError($data['reference'], $response->status(), $errorMessage, $response->body());
                
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
                        $value = 292626421; // Unidade padrão válida
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
                        if ($vendusField === 'supply_price' || $vendusField === 'gross_price') {
                            $value = number_format((float)$value, 2, '.', '');
                        } else {
                            $value = (int)$value;
                        }
                        break;
                    case 'boolean':
                        $value = $value ? 'on' : 'off';
                        break;
                    case 'string':
                    default:
                        $value = (string)$value;
                        break;
                }
                
                $payload[$vendusField] = $value;
            }
        }
        
        // Garante que campos essenciais estejam presentes
        if (!isset($payload['unit_id'])) {
            $payload['unit_id'] = 292626421; // ID válido obtido da conta Vendus
        }
        if (!isset($payload['status'])) {
            $payload['status'] = 'on';
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

        // Campos obrigatórios (atualizados conforme API)
        $requiredFields = ['reference', 'title', 'supply_price', 'gross_price'];
        
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[] = "Campo obrigatório '{$field}' está vazio";
            }
        }

        // Validação de preços
        if (!empty($data['supply_price']) && !is_numeric($data['supply_price'])) {
            $errors[] = "Campo 'supply_price' deve ser numérico";
        }

        if (!empty($data['gross_price']) && !is_numeric($data['gross_price'])) {
            $errors[] = "Campo 'gross_price' deve ser numérico";
        }

        // Validação de variantes
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
                if (!is_numeric($variant['price'])) {
                    $errors[] = "Variante {$index}: campo 'price' deve ser numérico";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Registra erros no log
     *
     * @param string $reference
     * @param int $statusCode
     * @param string $error
     * @param string $response
     * @return void
     */
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
        
        // Log específico para erros da Vendus
        $logFile = storage_path('logs/vendus_errors.log');
        file_put_contents($logFile, json_encode($logMessage) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Obtém as unidades disponíveis da API Vendus
     *
     * @return array
     */
    public function getUnits(): array
    {
        try {
            // Tenta diferentes endpoints possíveis para unidades
            $possibleEndpoints = [
                str_replace('/products', '/unit', $this->apiUrl),
                str_replace('/products', '/units', $this->apiUrl),
                str_replace('/products', '/measurement-units', $this->apiUrl),
                str_replace('/products', '/product-units', $this->apiUrl),
            ];

            foreach ($possibleEndpoints as $endpoint) {
                Log::info('Tentando endpoint: ' . $endpoint);
                
                $response = Http::withToken($this->apiKey)
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
                    Log::warning('Endpoint falhou', [
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
                'message' => 'Não foi possível encontrar o endpoint correto para unidades'
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
}