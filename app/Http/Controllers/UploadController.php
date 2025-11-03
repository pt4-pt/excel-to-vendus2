<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Services\VendusService;
use Exception;

class UploadController extends Controller
{
    private VendusService $vendusService;

    public function __construct(VendusService $vendusService)
    {
        $this->vendusService = $vendusService;
    }

    /**
     * Exibe a página de upload
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('upload');
    }

    /**
     * Processa o arquivo Excel e envia os produtos para a API
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            // Validação do arquivo
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls|max:10240' // 10MB max
            ]);

            $file = $request->file('excel_file');
            
            // Lê o arquivo Excel
            $data = Excel::toArray([], $file);
            
            if (empty($data) || empty($data[0])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Arquivo Excel esta vazio ou invalido'
                ], 400);
            }

            $rows = $data[0]; // Primeira planilha
            $header = array_shift($rows); // Remove o cabecalho
            
            // Mapeia os cabecalhos para indices
            $headerMap = $this->mapHeaders($header);
            
            if (!$headerMap) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cabecalhos do Excel nao correspondem ao formato esperado'
                ], 400);
            }

            // Agrupa os dados por "Ref. Vendus"
            $groupedProducts = $this->groupProductsByReference($rows, $headerMap);
            
            $totalProducts = count($groupedProducts);
            $results = [];
            $processedReferences = [];

            // Aviso para muitos produtos
            if ($totalProducts > 100) {
                $results[] = [
                    'type' => 'warning',
                    'message' => 'Este processo pode demorar alguns minutos devido ao grande número de produtos (' . $totalProducts . ')'
                ];
            }

            // Processa cada produto
            foreach ($groupedProducts as $reference => $productRows) {
                // Evita duplicações
                if (in_array($reference, $processedReferences)) {
                    $results[] = [
                        'type' => 'skipped',
                        'reference' => $reference,
                        'message' => 'Produto ignorado - referência duplicada'
                    ];
                    continue;
                }

                $processedReferences[] = $reference;

                // Constrói os dados do produto
                $productData = $this->buildProductData($productRows, $headerMap);
                
                // Valida os dados
                $validation = $this->vendusService->validateProductData($productData);
                
                if (!$validation['valid']) {
                    $results[] = [
                        'type' => 'error',
                        'reference' => $reference,
                        'message' => 'Dados inválidos: ' . implode(', ', $validation['errors'])
                    ];
                    continue;
                }

                // Como a API nao suporta variants, enviamos cada variacao como produto separado
                // mas com nomenclatura melhorada para identificar que sao variacoes
                $variantCount = 0;
                $successCount = 0;
                $errorMessages = [];

                // Se ha apenas uma variacao, envia como produto principal
                if (count($productData['variants']) == 1) {
                    $variant = $productData['variants'][0];
                    $variantCount = 1;
                    
                    $variantProductData = [
                        'reference' => $productData['reference'], // Usa a referencia original
                        'title' => $productData['title'],
                        'class_name' => $productData['class_name'],
                        'supply_price' => $productData['supply_price'],
                        'gross_price' => $variant['price'],
                        'barcode' => $variant['upc_no'], // Adiciona codigo de barras
                        'variants' => []
                    ];
                    
                    $payload = $this->vendusService->buildProductPayload($variantProductData);
                    $apiResult = $this->vendusService->sendProduct($payload);
                    
                    if ($apiResult['success']) {
                        $successCount = 1;
                    } else {
                        $errorMessages[] = "Produto unico: {$apiResult['message']}";
                    }
                } else {
                    // Multiplas variacoes - envia cada uma com nomenclatura clara
                    foreach ($productData['variants'] as $index => $variant) {
                        $variantCount++;
                        
                        // Nomenclatura melhorada: [Produto] - Tamanho [X] (Var. Y/Z)
                        $variantTitle = $productData['title'] . ' - Tamanho ' . $variant['size'];
                        if (count($productData['variants']) > 1) {
                            $variantTitle .= ' (Var. ' . ($index + 1) . '/' . count($productData['variants']) . ')';
                        }
                        
                        $variantProductData = [
                            'reference' => $variant['code'], // Codigo unico da variacao
                            'title' => $variantTitle,
                            'class_name' => $productData['class_name'],
                            'supply_price' => $productData['supply_price'],
                            'gross_price' => $variant['price'],
                            'barcode' => $variant['upc_no'], // Codigo de barras da variacao
                            'description' => "Variacao de tamanho {$variant['size']} do produto {$productData['title']}",
                            'variants' => []
                        ];
                        
                        $payload = $this->vendusService->buildProductPayload($variantProductData);
                        $apiResult = $this->vendusService->sendProduct($payload);
                        
                        if ($apiResult['success']) {
                            $successCount++;
                        } else {
                            $errorMessages[] = "Variacao " . $variant['size'] . " (#" . ($index + 1) . "): " . $apiResult['message'];
                        }
                    }
                }

                // Resultado consolidado com mensagens melhoradas
                if ($successCount == $variantCount) {
                    if ($variantCount == 1) {
                        $results[] = [
                            'type' => 'success',
                            'reference' => $reference,
                            'message' => "Produto enviado com sucesso",
                            'variants_count' => $variantCount
                        ];
                    } else {
                        $results[] = [
                            'type' => 'success',
                            'reference' => $reference,
                            'message' => "Produto com {$variantCount} variacoes enviado com sucesso",
                            'variants_count' => $variantCount
                        ];
                    }
                } elseif ($successCount > 0) {
                    $results[] = [
                        'type' => 'partial',
                        'reference' => $reference,
                        'message' => "Produto parcialmente enviado: {$successCount}/{$variantCount} variacoes. Erros: " . implode('; ', $errorMessages),
                        'variants_count' => $variantCount
                    ];
                } else {
                    $results[] = [
                        'type' => 'error',
                        'reference' => $reference,
                        'message' => "Falha ao enviar produto com {$variantCount} variacoes. Erros: " . implode('; ', $errorMessages),
                        'variants_count' => $variantCount
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Processamento concluído',
                'total_products' => $totalProducts,
                'results' => $results
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna pré-visualização dos produtos do Excel
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function preview(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls|max:10240'
            ]);

            $file = $request->file('excel_file');
            $data = Excel::toArray([], $file);
            
            if (empty($data) || empty($data[0])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Arquivo Excel está vazio ou inválido'
                ], 400);
            }

            $rows = $data[0];
            $header = array_shift($rows);
            $headerMap = $this->mapHeaders($header);
            
            if (!$headerMap) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cabeçalhos do Excel não correspondem ao formato esperado'
                ], 400);
            }

            $groupedProducts = $this->groupProductsByReference($rows, $headerMap);
            $preview = [];

            foreach ($groupedProducts as $reference => $productRows) {
                $firstRow = $productRows[0];
                $preview[] = [
                    'reference' => $reference,
                    'name' => $firstRow[$headerMap['nome']] ?? '',
                    'category' => $firstRow[$headerMap['cat']] ?? '',
                    'variants_count' => count($productRows)
                ];
            }

            return response()->json([
                'success' => true,
                'preview' => $preview,
                'total_products' => count($preview)
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao gerar pré-visualização: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mapeia os cabeçalhos do Excel para índices
     *
     * @param array $header
     * @return array|false
     */
    private function mapHeaders(array $header): array|false
    {
        $expectedHeaders = [
            'ref_vendus' => ['Ref. Vendus', 'ref. vendus', 'ref_vendus'],
            'cat' => ['Cat', 'cat', 'categoria', 'Categoria'],
            'nome' => ['Nome', 'nome', 'name', 'Name'],
            'size' => ['Size', 'size', 'tamanho', 'Tamanho'],
            'upc_no' => ['UPC No.', 'upc no.', 'upc_no', 'UPC', 'barcode'],
            'cost' => ['Cost', 'cost', 'custo', 'Custo'],
            'pvp' => ['PVP', 'pvp', 'preço', 'Preço', 'price']
        ];

        $headerMap = [];

        foreach ($expectedHeaders as $key => $variations) {
            $found = false;
            foreach ($header as $index => $headerValue) {
                if (in_array(trim($headerValue), $variations)) {
                    $headerMap[$key] = $index;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false; // Cabeçalho obrigatório não encontrado
            }
        }

        return $headerMap;
    }

    /**
     * Agrupa as linhas por referência do produto
     *
     * @param array $rows
     * @param array $headerMap
     * @return array
     */
    private function groupProductsByReference(array $rows, array $headerMap): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $reference = trim($row[$headerMap['ref_vendus']] ?? '');
            
            if (empty($reference)) {
                continue; // Ignora linhas sem referência
            }

            if (!isset($grouped[$reference])) {
                $grouped[$reference] = [];
            }

            $grouped[$reference][] = $row;
        }

        return $grouped;
    }

    /**
     * Constrói os dados do produto a partir das linhas agrupadas
     *
     * @param array $productRows
     * @param array $headerMap
     * @return array
     */
    private function buildProductData(array $productRows, array $headerMap): array
    {
        $firstRow = $productRows[0];
        
        $productData = [
            'reference' => trim($firstRow[$headerMap['ref_vendus']]),
            'title' => trim($firstRow[$headerMap['nome']]),
            'class_name' => trim($firstRow[$headerMap['cat']]),
            'supply_price' => $this->parsePrice($firstRow[$headerMap['cost']]),
            'gross_price' => $this->parsePrice($firstRow[$headerMap['pvp']]),
            'variants' => []
        ];

        // Adiciona as variantes
        foreach ($productRows as $row) {
            $size = trim($row[$headerMap['size']] ?? '');
            $upcNo = trim($row[$headerMap['upc_no']] ?? '');
            $price = $this->parsePrice($row[$headerMap['pvp']]);

            if (!empty($size) && !empty($upcNo)) {
                $productData['variants'][] = [
                    'size' => $size,
                    'upc_no' => $upcNo,
                    'code' => $productData['reference'] . '-' . $size,
                    'price' => $price
                ];
            }
        }

        return $productData;
    }

    /**
     * Converte string de preço para float
     *
     * @param mixed $price
     * @return float
     */
    private function parsePrice($price): float
    {
        if (is_numeric($price)) {
            return (float) $price;
        }

        // Remove caracteres não numéricos exceto ponto e vírgula
        $cleanPrice = preg_replace('/[^\d.,]/', '', $price);
        
        // Substitui vírgula por ponto se for o separador decimal
        if (strpos($cleanPrice, ',') !== false && strpos($cleanPrice, '.') === false) {
            $cleanPrice = str_replace(',', '.', $cleanPrice);
        }

        return (float) $cleanPrice;
    }
}