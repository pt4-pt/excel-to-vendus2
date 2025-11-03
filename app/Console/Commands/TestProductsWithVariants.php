<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Services\VendusService;
use Illuminate\Support\Facades\Http;

class TestProductsWithVariants extends Command
{
    protected $signature = 'test:products-with-variants';
    protected $description = 'Testa se o campo variants funciona no endpoint de produtos da API Vendus';

    public function handle()
    {
        $this->info('🧪 Testando o campo variants no endpoint de produtos...');
        
        // Teste 1: Produto com campo variants vazio
        $this->line('📝 Teste 1: Produto com campo variants vazio');
        $this->testProductWithEmptyVariants();
        
        // Teste 2: Produto com campo variants preenchido
        $this->line('📝 Teste 2: Produto com campo variants preenchido');
        $this->testProductWithVariants();
        
        $this->info('✨ Teste concluido!');
    }
    
    private function testProductWithEmptyVariants()
    {
        $productData = [
            'reference' => 'TEST_EMPTY_VAR_' . time(),
            'title' => 'Produto Teste - Variants Vazio',
            'description' => 'Teste com campo variants vazio',
            'supply_price' => 10,
            'gross_price' => 20,
            'category_id' => 1,
            'brand_id' => 1,
            'status' => 'on',
            'variants' => []
        ];
        
        $this->line('Dados: ' . json_encode($productData, JSON_PRETTY_PRINT));
        
        $response = Http::withToken(env('VENDUS_API_KEY'))
            ->timeout(30)
            ->withOptions(['verify' => false])
            ->post(env('VENDUS_API_URL'), $productData);
            
        $this->line('Status HTTP: ' . $response->status());
        
        if ($response->successful()) {
            $this->line('✅ Sucesso! Produto criado com ID: ' . $response->json('id'));
        } else {
            $this->line('⚠️ Falha - codigo ' . $response->status());
            $this->line('Resposta: ' . $response->body());
        }
        
        $this->line('');
    }
    
    private function testProductWithVariants()
    {
        $productData = [
            'reference' => 'TEST_WITH_VAR_' . time(),
            'title' => 'Produto Teste - Com Variants',
            'description' => 'Teste com campo variants preenchido',
            'supply_price' => 10,
            'gross_price' => 20,
            'category_id' => 1,
            'brand_id' => 1,
            'status' => 'on',
            'variants' => [
                [
                    'reference' => 'TEST_WITH_VAR_' . time() . '_M',
                    'title' => 'Tamanho M',
                    'barcode' => '123456789012',
                    'supply_price' => 10,
                    'gross_price' => 20,
                    'attributes' => [
                        'size' => 'M'
                    ]
                ],
                [
                    'reference' => 'TEST_WITH_VAR_' . time() . '_L',
                    'title' => 'Tamanho L',
                    'barcode' => '123456789013',
                    'supply_price' => 10,
                    'gross_price' => 22,
                    'attributes' => [
                        'size' => 'L'
                    ]
                ]
            ]
        ];
        
        $this->line('Dados: ' . json_encode($productData, JSON_PRETTY_PRINT));
        
        $response = Http::withToken(env('VENDUS_API_KEY'))
            ->timeout(30)
            ->withOptions(['verify' => false])
            ->post(env('VENDUS_API_URL'), $productData);
            
        $this->line('Status HTTP: ' . $response->status());
        
        if ($response->successful()) {
            $this->line('✅ Sucesso! Produto criado com ID: ' . $response->json('id'));
            $responseData = $response->json();
            if (isset($responseData['variants'])) {
                $this->line('🎯 Campo variants retornado na resposta!');
                $this->line('Variants: ' . json_encode($responseData['variants'], JSON_PRETTY_PRINT));
            } else {
                $this->line('⚠️ Campo variants nao retornado na resposta');
            }
        } else {
            $this->line('⚠️ Falha - codigo ' . $response->status());
            $this->line('Resposta: ' . $response->body());
        }
        
        $this->line('');
    }
}