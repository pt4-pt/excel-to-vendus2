<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Services\VendusService;
use Illuminate\Support\Facades\Http;

class TestVariations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:variations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa comportamento da API Vendus v1.2 com variações (esperado: não suportado)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testando comportamento de variações na API Vendus v1.2...');
        
        // Produto de teste com variações
        $testProductWithVariants = [
            'reference' => 'TEST-VARIANTS-' . time(),
            'title' => 'Produto Teste com Variações',
            'unit_id' => 292626421,
            'status' => 'on',
            'prices' => [
                ['gross_price' => '20.00']
            ],
            'variants' => [
                [
                    'variant' => [
                        'title' => 'Size'
                    ],
                    'product_variants' => [
                        [
                            'text' => 'M',
                            'barcode' => '123456789012',
                            'code' => 'TEST-VARIANTS-M',
                            'price' => '20.00'
                        ],
                        [
                            'text' => 'L',
                            'barcode' => '123456789013',
                            'code' => 'TEST-VARIANTS-L',
                            'price' => '22.00'
                        ]
                    ]
                ]
            ]
        ];
        
        $this->line('Produto de teste com variações:');
        $this->line(json_encode($testProductWithVariants, JSON_PRETTY_PRINT));
        $this->line('');
        
        // Testa diretamente com a API
        $apiKey = env('VENDUS_API_KEY');
        $apiUrl = env('VENDUS_API_URL');
        
        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->withOptions([
                    'verify' => false,
                    'curl' => [
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                    ]
                ])
                ->post($apiUrl, $testProductWithVariants);

            if ($response->successful()) {
                $this->info('SUCESSO! A API Vendus aceita produtos com variações!');
                $this->line('Resposta da API:');
                $this->line(json_encode($response->json(), JSON_PRETTY_PRINT));
                
                return 0;
            } else {
                $this->error('ERRO! A API rejeitou o produto com variações.');
                $this->line('Status Code: ' . $response->status());
                $this->line('Resposta:');
                $this->line($response->body());
                
                // Agora testa sem variações para comparar
                $this->line('');
                $this->info('Testando o mesmo produto SEM variações (v1.2)...');
                
                $testProductWithoutVariants = [
                    'reference' => 'TEST-NO-VARIANTS-' . time(),
                    'title' => 'Produto Teste sem Variações',
                    'unit_id' => 292626421,
                    'status' => 'on',
                    'prices' => [
                        ['gross_price' => '20.00']
                    ]
                ];
                
                $response2 = Http::withToken($apiKey)
                    ->timeout(30)
                    ->withOptions([
                        'verify' => false,
                        'curl' => [
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false,
                        ]
                    ])
                    ->post($apiUrl, $testProductWithoutVariants);
                
                if ($response2->successful()) {
                    $this->info('Produto SEM variações foi aceito.');
                    $this->warn('Conclusão: A API não suporta variações ou o formato está incorreto.');
                } else {
                    $this->error('Produto SEM variações também foi rejeitado.');
                    $this->line('Status Code: ' . $response2->status());
                    $this->line('Resposta:');
                    $this->line($response2->body());
                }
                
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('Erro de conexão: ' . $e->getMessage());
            return 1;
        }
    }
}