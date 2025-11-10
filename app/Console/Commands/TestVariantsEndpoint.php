<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Services\VendusService;
use Illuminate\Support\Facades\Http;

class TestVariantsEndpoint extends Command
{
    protected $signature = 'test:variants-endpoint';
    protected $description = 'Testa o endpoint de variants da API Vendus v1.1';

    public function handle()
    {
        $this->info('Testando o endpoint de variants da API Vendus v1.1...');
        
        $vendusService = new VendusService();
        
        try {
            // Primeiro, vamos tentar listar variants existentes
            $this->info('Tentando listar variants existentes...');
            $this->testListVariants($vendusService);
            
            // Depois, vamos tentar criar um produto com variants
            $this->info('Tentando criar um produto com variants...');
            $this->testCreateProductWithVariants($vendusService);
            
        } catch (\Exception $e) {
            $this->error('Erro durante o teste: ' . $e->getMessage());
        }
        
        $this->info('Teste do endpoint de variants concluido!');
    }
    
    private function testListVariants($vendusService)
    {
        try {
            // Tenta acessar o endpoint de variants usando a mesma autenticação do VendusService
            $url = 'https://www.vendus.pt/ws/v1.1/variants/';
            
            $response = Http::withToken(env('VENDUS_API_KEY'))
                ->timeout(30)
                ->withOptions([
                    'verify' => false,
                    'curl' => [
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                    ]
                ])
                ->get($url);
            
            $this->line("Status HTTP: {$response->status()}");
            
            if ($response->successful()) {
                $data = $response->json();
                $this->info('Endpoint de variants acessivel!');
                $this->line('Resposta: ' . json_encode($data, JSON_PRETTY_PRINT));
            } else {
                $this->warn("Endpoint retornou codigo {$response->status()}");
                $this->line('Resposta: ' . $response->body());
            }
            
        } catch (\Exception $e) {
            $this->error('Erro ao acessar endpoint de variants: ' . $e->getMessage());
        }
    }
    
    private function testCreateProductWithVariants($vendusService)
    {
        try {
            // Primeiro, cria um produto base
            $productData = [
                'reference' => 'TEST_VAR_' . time(),
                'title' => 'Produto Teste com Variants',
                'description' => 'Produto para testar o sistema de variants',
                'supply_price' => 10.00,
                'gross_price' => 20.00,
                'category_id' => 1,
                'brand_id' => 1,
                'status' => 'on'
            ];
            
            $this->line('Criando produto base...');
            $this->line('Dados: ' . json_encode($productData, JSON_PRETTY_PRINT));
            
            $payload = $vendusService->buildProductPayload($productData);
            $result = $vendusService->sendProduct($payload);
            
            if ($result['success']) {
                $productId = $result['data']['id'] ?? null;
                $this->info("Produto base criado com ID: {$productId}");
                
                if ($productId) {
                    // Agora tenta criar variants para este produto
                    $this->testCreateVariants($vendusService, $productId);
                }
            } else {
                $this->error('Falha ao criar produto base: ' . $result['message']);
            }
            
        } catch (\Exception $e) {
            $this->error('Erro ao criar produto com variants: ' . $e->getMessage());
        }
    }
    
    private function testCreateVariants($vendusService, $productId)
    {
        try {
            // Tenta criar variants usando o endpoint de variants com Bearer Token
            $variantData = [
                'product_id' => $productId,
                'title' => 'Variacao Tamanho M',
                'reference' => 'TEST_VAR_' . time() . '_M',
                'barcode' => '123456789012',
                'supply_price' => 10.00,
                'gross_price' => 20.00,
                'attributes' => [
                    'size' => 'M'
                ]
            ];
            
            $this->line('Tentando criar variant...');
            $this->line('Dados: ' . json_encode($variantData, JSON_PRETTY_PRINT));
            
            $url = 'https://www.vendus.pt/ws/v1.1/variants/';
            
            $response = Http::withToken(env('VENDUS_API_KEY'))
                ->timeout(30)
                ->withOptions([
                    'verify' => false,
                    'curl' => [
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                    ]
                ])
                ->post($url, $variantData);
            
            $this->line("Status HTTP: {$response->status()}");
            
            if ($response->successful()) {
                $data = $response->json();
                $this->info('Variant criado com sucesso!');
                $this->line('Resposta: ' . json_encode($data, JSON_PRETTY_PRINT));
            } else {
                $this->warn("Falha ao criar variant - codigo {$response->status()}");
                $this->line('Resposta: ' . $response->body());
            }
            
        } catch (\Exception $e) {
            $this->error('Erro ao criar variant: ' . $e->getMessage());
        }
    }
}