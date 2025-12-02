<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Services\VendusService;
use Illuminate\Support\Facades\Log;
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
            $type = (string) $request->input('import_type', 'products');

            if ($type === 'invoices') {
                // Validação para Excel de fatura
                $request->validate([
                    'excel_file' => 'required|file|mimes:xlsx,xls|max:10240' // 10MB max
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
                $headerMap = $this->mapInvoiceHeaders($header);

                if ($headerMap === false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cabeçalhos do Excel não correspondem ao formato esperado para faturas. Configure o mapeamento em "Mapeamentos de Faturas".'
                    ], 400);
                }

                $groupedInvoices = $this->groupInvoicesByKey($rows, $headerMap);
                $results = [];
                $totalInvoices = count($groupedInvoices);

                if ($totalInvoices > 50) {
                    $results[] = [
                        'type' => 'warning',
                        'message' => 'Processamento de muitas faturas (' . $totalInvoices . ') pode demorar alguns minutos'
                    ];
                }

                foreach ($groupedInvoices as $key => $invoiceRows) {
                    $invoiceData = $this->buildInvoiceData($invoiceRows, $headerMap);
                    // Adiciona referência externa para rastrear a criação no ERP
                    if (!isset($invoiceData['header'])) { $invoiceData['header'] = []; }
                    $invoiceData['header']['external_reference'] = (string) $key;

                    $apiResult = $this->vendusService->sendSupplierFromInvoice($invoiceData);
                    if ($apiResult['success'] ?? false) {
                        $results[] = [
                            'type' => 'success',
                            'message' => 'Fornecedor criado com sucesso',
                            'reference' => (string) $key,
                            'status_code' => $apiResult['status_code'] ?? 200,
                            'endpoint' => $apiResult['endpoint_used'] ?? null,
                            'auth' => $apiResult['auth_used'] ?? null,
                        ];
                    } else {
                        $results[] = [
                            'type' => 'error',
                            'message' => $apiResult['message'] ?? 'Falha ao criar fornecedor',
                            'reference' => (string) $key,
                            'status_code' => $apiResult['status_code'] ?? 400,
                            'endpoint' => $apiResult['endpoint_used'] ?? null,
                            'auth' => $apiResult['auth_used'] ?? null,
                        ];
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Processamento de fornecedores concluído',
                    'total_invoices' => $totalInvoices,
                    'results' => $results
                ]);
            }

            // Validação do arquivo (Produtos)
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

                // Constrói os dados do produto e o payload conforme v1.2
                $productData = $this->buildProductData($productRows, $headerMap);
                Log::info("Produto (Excel) mapeado\n" . json_encode($productData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                $payloadBase = $this->vendusService->buildProductPayload($productData);
                Log::info("Payload JSON (base) para Vendus\n" . json_encode($payloadBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                
                // Valida o payload (title, unit_id, prices, etc.)
                $validation = $this->vendusService->validateProductData($payloadBase);
                
                if (!$validation['valid']) {
                    $results[] = [
                        'type' => 'error',
                        'reference' => $reference,
                        'message' => 'Dados inválidos: ' . implode(', ', $validation['errors'])
                    ];
                    continue;
                }

                $variantItems = $productData['variants'];
                $variantCount = count($variantItems);
                $basePrice = $variantCount > 0 ? (float) ($variantItems[0]['price'] ?? 0) : (float) 0;
                if (!isset($payloadBase['prices']) || !is_array($payloadBase['prices']) || !isset($payloadBase['prices']['gross'])) {
                    $payloadBase['prices'] = [ 'gross' => round($basePrice, 2) ];
                }
                Log::info("Payload JSON (base ajustado) para Vendus\n" . json_encode($payloadBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                $variantTitle = isset($productData['variant_title']) && $productData['variant_title'] !== '' ? (string) $productData['variant_title'] : 'Size';
                $res = $this->vendusService->createProductWithVariants($payloadBase, $variantTitle, $variantItems);
                if ($res['success']) {
                    $results[] = [
                        'type' => 'success',
                        'reference' => $reference,
                        'message' => "Produto com {$variantCount} variacoes enviado com sucesso",
                        'variants_count' => $variantCount
                    ];
                } else {
                    $results[] = [
                        'type' => 'error',
                        'reference' => $reference,
                        'message' => $res['message'] ?? 'Falha ao enviar produto com variantes',
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
     * Pré-visualiza faturas a partir do Excel
     */
    public function previewInvoices(Request $request)
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
            $headerMap = $this->mapInvoiceHeaders($header);

            if ($headerMap === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cabeçalhos do Excel não correspondem ao formato esperado para faturas. Configure o mapeamento em "Mapeamentos de Faturas".'
                ], 400);
            }

            $grouped = $this->groupInvoicesByKey($rows, $headerMap);
            $preview = [];

            foreach ($grouped as $key => $invoiceRows) {
                $first = $invoiceRows[0];
                $preview[] = [
                    'key' => (string) $key,
                    'customer_name' => $this->getCell($first, $headerMap['customer_name'] ?? null),
                    'customer_nif' => $this->getCell($first, $headerMap['customer_nif'] ?? null),
                    'series' => $this->getCell($first, $headerMap['series'] ?? null),
                    'date' => $this->getCell($first, $headerMap['date'] ?? null),
                    'items_count' => count($invoiceRows),
                ];
            }

            return response()->json([
                'success' => true,
                'preview' => $preview,
                'total_invoices' => count($grouped)
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao gerar pré-visualização: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Mapeia cabeçalhos de faturas conforme configurações ativas
     */
    private function mapInvoiceHeaders(array $header): array|false
    {
        // Usa mapeamentos de DocumentFieldMapping (metadata_key = nome da coluna Excel)
        $mappings = \App\Models\DocumentFieldMapping::getMappedFields();

        $map = [];
        $normalizedHeader = array_map(fn($h) => trim(strtolower((string)$h)), $header);

        foreach ($mappings as $m) {
            $excelCol = trim(strtolower((string)($m->metadata_key ?? '')));
            if ($excelCol === '') {
                continue; // sem coluna configurada
            }
            $index = array_search($excelCol, $normalizedHeader, true);
            if ($index !== false) {
                $map[$m->vendus_field] = $index;
            }
        }

        // Valida campos mínimos de itens
        $requiredItems = ['item_reference','item_title','item_quantity','item_price'];
        foreach ($requiredItems as $key) {
            if (!array_key_exists($key, $map)) {
                return false;
            }
        }

        // document_number é opcional para agrupamento; se ausente, agrupamos tudo junto
        return $map;
    }

    /**
     * Agrupa linhas por chave de fatura (document_number) ou tudo junto
     */
    private function groupInvoicesByKey(array $rows, array $headerMap): array
    {
        $groups = [];
        $keyIndex = $headerMap['document_number'] ?? null;
        $defaultKey = 'INV-' . date('Ymd-His');

        foreach ($rows as $row) {
            $key = $keyIndex !== null ? $this->getCell($row, $keyIndex) : $defaultKey;
            if ($key === null || $key === '') {
                $key = $defaultKey;
            }
            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }
            $groups[$key][] = $row;
        }
        return $groups;
    }

    /**
     * Constrói dados de fatura com cabeçalho e itens
     */
    private function buildInvoiceData(array $invoiceRows, array $headerMap): array
    {
        $first = $invoiceRows[0];
        $header = [
            'series' => $this->getCell($first, $headerMap['series'] ?? null),
            'customer_name' => $this->getCell($first, $headerMap['customer_name'] ?? null) ?: 'Consumidor final',
            'customer_nif' => $this->getCell($first, $headerMap['customer_nif'] ?? null) ?: null,
            'date' => $this->getCell($first, $headerMap['date'] ?? null) ?: null,
            'notes' => $this->getCell($first, $headerMap['notes'] ?? null) ?: null,
            'store_title' => $this->getCell($first, $headerMap['store_title'] ?? null) ?: null,
            'register_title' => $this->getCell($first, $headerMap['register_title'] ?? null) ?: null,
            'register_id' => $this->getCell($first, $headerMap['register_id'] ?? null) ?: null,
            'store_id' => $this->getCell($first, $headerMap['store_id'] ?? null) ?: null,
            'type' => $this->getCell($first, $headerMap['type'] ?? null) ?: null,
            'discount_code' => $this->getCell($first, $headerMap['discount_code'] ?? null) ?: null,
            'discount_amount' => $this->getCell($first, $headerMap['discount_amount'] ?? null) ?: null,
            'discount_percentage' => $this->getCell($first, $headerMap['discount_percentage'] ?? null) ?: null,
            'date_due' => $this->getCell($first, $headerMap['date_due'] ?? null) ?: null,
            'mode' => $this->getCell($first, $headerMap['mode'] ?? null) ?: null,
            'date_supply' => $this->getCell($first, $headerMap['date_supply'] ?? null) ?: null,
            'ncr_id' => $this->getCell($first, $headerMap['ncr_id'] ?? null) ?: null,
            'external_reference' => $this->getCell($first, $headerMap['external_reference'] ?? null) ?: null,
            'stock_operation' => $this->getCell($first, $headerMap['stock_operation'] ?? null) ?: null,
            'ifthenpay' => $this->getCell($first, $headerMap['ifthenpay'] ?? null) ?: null,
            'eupago' => $this->getCell($first, $headerMap['eupago'] ?? null) ?: null,
            'print_discount' => $this->getCell($first, $headerMap['print_discount'] ?? null) ?: null,
            'output' => $this->getCell($first, $headerMap['output'] ?? null) ?: null,
            'output_template_id' => $this->getCell($first, $headerMap['output_template_id'] ?? null) ?: null,
            'tx_id' => $this->getCell($first, $headerMap['tx_id'] ?? null) ?: null,
            'errors_full' => $this->getCell($first, $headerMap['errors_full'] ?? null) ?: null,
            'rest_room' => $this->getCell($first, $headerMap['rest_room'] ?? null) ?: null,
            'rest_table' => $this->getCell($first, $headerMap['rest_table'] ?? null) ?: null,
            'occupation' => $this->getCell($first, $headerMap['occupation'] ?? null) ?: null,
            'stamp_retention_amount' => $this->getCell($first, $headerMap['stamp_retention_amount'] ?? null) ?: null,
            'irc_retention_id' => $this->getCell($first, $headerMap['irc_retention_id'] ?? null) ?: null,
            'mgmAmount' => $this->getCell($first, $headerMap['mgmAmount'] ?? null) ?: null,
            'related_document_id' => $this->getCell($first, $headerMap['related_document_id'] ?? null) ?: null,
            'return_qrcode' => $this->getCell($first, $headerMap['return_qrcode'] ?? null) ?: null,
            'doc_to_generate' => $this->getCell($first, $headerMap['doc_to_generate'] ?? null) ?: null,
        ];

        $items = [];
        foreach ($invoiceRows as $row) {
            $items[] = [
                'reference' => (string) $this->getCell($row, $headerMap['item_reference']),
                'title' => (string) $this->getCell($row, $headerMap['item_title']),
                'quantity' => (float) ($this->getCell($row, $headerMap['item_quantity'])),
                'gross_price' => (float) ($this->getCell($row, $headerMap['item_price'])),
                'tax' => $this->getCell($row, $headerMap['item_tax'] ?? null),
            ];
        }

        return [
            'header' => $header,
            'items' => $items,
        ];
    }

    /**
     * Helper para recuperar célula com segurança
     */
    private function getCell(array $row, ?int $index): ?string
    {
        if ($index === null) return null;
        return isset($row[$index]) ? (string) $row[$index] : null;
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
                    'category' => isset($headerMap['cat']) ? ($firstRow[$headerMap['cat']] ?? '') : '',
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
        $normalize = function (string $v): string {
            $v = trim($v);
            $v = mb_strtolower($v, 'UTF-8');
            $v = iconv('UTF-8', 'ASCII//TRANSLIT', $v);
            $v = preg_replace('/[\._\-\/:]/', ' ', $v);
            $v = preg_replace('/\s+/', ' ', $v);
            $v = preg_replace('/[^a-z0-9 ]+/', '', $v);
            return trim($v);
        };

        $normalizedHeaders = [];
        foreach ($header as $i => $h) {
            $normalizedHeaders[$i] = $normalize((string) $h);
        }

        $requiredSyns = [
            'ref_vendus' => ['ref vendus','refvendus','referencia','referencia vendus','ref'],
            'nome' => ['nome','name','titulo','title','produto'],
            'size' => ['size','tamanho','variante','variant'],
            'upc_no' => ['upc no','upc','barcode','ean','ean13','codigo barras','codigo de barras'],
            'pvp' => ['pvp','preco','preco venda','preco de venda','preco unitario','preco pvp','preco preco']
        ];

        $optionalSyns = [
            'cat' => ['cat','categoria'],
            'cost' => ['cost','custo'],
            'tipo_variacao' => ['tipo variacao','tipo de variacao','variation type','variant type'],
            'store_id' => ['loja id','store id','id loja','loja'],
            'stock' => ['stock','quantidade','qty'],
            'stock_alert' => ['stock alert','alerta stock','alerta']
        ];

        $headerMap = [];

        foreach ($requiredSyns as $key => $syns) {
            $foundIdx = null;
            $synsNorm = array_map($normalize, $syns);
            foreach ($normalizedHeaders as $idx => $hn) {
                if (in_array($hn, $synsNorm, true)) { $foundIdx = $idx; break; }
            }
            if ($foundIdx === null) {
                foreach ($normalizedHeaders as $idx => $hn) {
                    foreach ($synsNorm as $s) { if ($s !== '' && str_contains($hn, $s)) { $foundIdx = $idx; break 2; } }
                }
            }
            if ($foundIdx === null) { return false; }
            $headerMap[$key] = $foundIdx;
        }

        foreach ($optionalSyns as $key => $syns) {
            $synsNorm = array_map($normalize, $syns);
            foreach ($normalizedHeaders as $idx => $hn) {
                if (in_array($hn, $synsNorm, true)) { $headerMap[$key] = $idx; break; }
            }
            if (!isset($headerMap[$key])) {
                foreach ($normalizedHeaders as $idx => $hn) {
                    foreach ($synsNorm as $s) { if ($s !== '' && str_contains($hn, $s)) { $headerMap[$key] = $idx; break 2; } }
                }
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
            $ref = trim((string)($row[$headerMap['ref_vendus']] ?? ''));
            $name = trim((string)($row[$headerMap['nome']] ?? ''));
            if ($ref === '' && $name === '') { continue; }
            $sz = trim((string)($row[$headerMap['size']] ?? ''));
            $baseRef = $ref;
            $baseName = $name;
            if ($sz !== '') {
                $s = preg_quote($sz, '/');
                $baseRef = preg_replace('/(\.|-|_|\s)'.$s.'$/i', '', $baseRef);
                $baseRef = preg_replace('/\s*\(\s*'.$s.'\s*\)\s*$/i', '', $baseRef);
                $baseName = preg_replace('/\s*-\s*Tamanho\s*'.$s.'\s*$/i', '', $baseName);
                $baseName = preg_replace('/\s*\(\s*'.$s.'\s*\)\s*$/i', '', $baseName);
            }
            if (strpos($ref, '.') !== false) { $baseRef = substr($ref, 0, strpos($ref, '.')); }
            $key = trim($baseRef) !== '' ? trim($baseRef) : trim($baseName);
            if ($key === '') { $key = $ref ?: $name; }
            if (!isset($grouped[$key])) { $grouped[$key] = []; }
            $grouped[$key][] = $row;
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
        $rawRef = trim((string)($firstRow[$headerMap['ref_vendus']] ?? ''));
        $base = $rawRef;
        if (strpos($base, '.') !== false) {
            $base = substr($base, 0, strpos($base, '.'));
        }
        $productData = [
            'reference' => $base,
            'title' => trim((string)$firstRow[$headerMap['nome']]),
            'variants' => []
        ];
        $productPrice = $this->parsePrice($firstRow[$headerMap['pvp']]);
        if (is_numeric($productPrice)) { $productData['price'] = (float) $productPrice; }
        if (isset($headerMap['cost'])) {
            $costVal = $this->parsePrice($firstRow[$headerMap['cost']] ?? '');
            if (is_numeric($costVal)) { $productData['supply_price'] = (float) $costVal; }
        }
        if (isset($headerMap['tipo_variacao'])) {
            $vt = '';
            foreach ($productRows as $r) {
                $v = trim((string)($r[$headerMap['tipo_variacao']] ?? ''));
                if ($v !== '') { $vt = $v; break; }
            }
            if ($vt !== '') {
                $parts = preg_split('/\s+/', $vt);
                $cut = count($parts);
                for ($i = 0; $i < count($parts); $i++) { if (str_contains($parts[$i], '/')) { $cut = $i; break; } }
                if ($cut < count($parts)) { $vt = trim(implode(' ', array_slice($parts, 0, $cut))); }
                $productData['variant_title'] = $vt;
            }
        }

        // Adiciona as variantes
        foreach ($productRows as $row) {
            $size = trim($row[$headerMap['size']] ?? '');
            $upcNo = trim($row[$headerMap['upc_no']] ?? '');
            $price = $this->parsePrice($row[$headerMap['pvp']]);
            $storeId = isset($headerMap['store_id']) ? trim((string)($row[$headerMap['store_id']] ?? '')) : '';
            $stockQty = isset($headerMap['stock']) ? (string) ($row[$headerMap['stock']] ?? '') : '';
            $stockAlert = isset($headerMap['stock_alert']) ? (string) ($row[$headerMap['stock_alert']] ?? '') : '';

            if ($size !== '' && $upcNo !== '') {
                $rowRef = trim((string)($row[$headerMap['ref_vendus']] ?? ''));
                $code = $rowRef !== '' ? $rowRef : ($productData['reference'] . '.' . str_replace('/', '-', $size));
                $productData['variants'][] = [
                    'size' => $size,
                    'upc_no' => $upcNo,
                    'code' => $code,
                    'price' => $price,
                    'store_id' => $storeId,
                    'stock' => $stockQty,
                    'stock_alert' => $stockAlert
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
