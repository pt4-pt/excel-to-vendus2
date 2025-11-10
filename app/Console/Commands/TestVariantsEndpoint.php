<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Services\VendusService;
use Illuminate\Support\Facades\Http;

class TestVariantsEndpoint extends Command
{
    protected $signature = 'test:variants-endpoint';
    protected $description = 'Testa criação de produto com prices[] na API Vendus v1.2';

    public function handle()
    {
        $this->info('Testando criação de produto na API Vendus v1.2...');
        
        $vendusService = new VendusService();
        
        try {
            // Cria um produto simples com prices[] usando o service
            $this->info('Tentando criar um produto com prices[]...');
            $this->testCreateProductV12($vendusService);
            
        } catch (\Exception $e) {
            $this->error('Erro durante o teste: ' . $e->getMessage());
        }
        
        $this->info('Teste do endpoint de variants concluido!');
    }
    
    private function testCreateProductV12($vendusService)
    {
        try {
            // Cria um produto base mínimo com prices[]
            $productData = [
                'reference' => 'TEST_V12_' . time(),
                'title' => 'Produto Teste v1.2',
                'description' => 'Produto para testar prices[] na v1.2',
                'price' => 20.00, // usado pelo service para montar prices[]
                'status' => 'on'
            ];
            
            $this->line('Criando produto base...');
            $this->line('Dados: ' . json_encode($productData, JSON_PRETTY_PRINT));
            
            $payload = $vendusService->buildProductPayload($productData);
            $result = $vendusService->sendProduct($payload);
            
            if ($result['success']) {
                $productId = $result['data']['id'] ?? null;
                $this->info("Produto base criado com ID: {$productId}");
            } else {
                $this->error('Falha ao criar produto base: ' . $result['message']);
            }
            
        } catch (\Exception $e) {
            $this->error('Erro ao criar produto com variants: ' . $e->getMessage());
        }
    }
}