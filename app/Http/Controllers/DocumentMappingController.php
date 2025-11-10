<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DocumentFieldMapping;
use Illuminate\Support\Facades\Schema;

class DocumentMappingController extends Controller
{
    /**
     * Exibe a página de configuração de mapeamentos de faturas (Excel)
     */
    public function index()
    {
        if (Schema::hasTable('document_field_mappings')) {
            // Garante que novos campos padrão existam sem sobrescrever os existentes
            DocumentFieldMapping::createDefaultMappings();
            $mappings = DocumentFieldMapping::orderBy('vendus_field')->get();
        } else {
            $mappings = collect([
                [
                    'vendus_field' => 'document_number',
                    'vendus_field_label' => 'Número do Documento',
                    'metadata_key' => null,
                    'field_type' => 'string',
                    'is_required' => false,
                    'default_value' => null,
                    'description' => 'Número/Chave para agrupar linhas da mesma fatura (opcional)',
                    'is_active' => true,
                ],
                [
                    'vendus_field' => 'series',
                    'vendus_field_label' => 'Série',
                    'metadata_key' => null,
                    'field_type' => 'string',
                    'is_required' => false,
                    'default_value' => null,
                    'description' => 'Série de numeração da fatura',
                    'is_active' => true,
                ],
                [
                    'vendus_field' => 'customer_name',
                    'vendus_field_label' => 'Cliente - Nome',
                    'metadata_key' => null,
                    'field_type' => 'string',
                    'is_required' => false,
                    'default_value' => null,
                    'description' => 'Nome do cliente',
                    'is_active' => true,
                ],
                [
                    'vendus_field' => 'customer_nif',
                    'vendus_field_label' => 'Cliente - NIF',
                    'metadata_key' => null,
                    'field_type' => 'string',
                    'is_required' => false,
                    'default_value' => null,
                    'description' => 'Número de contribuinte (NIF)',
                    'is_active' => true,
                ],
                [
                    'vendus_field' => 'date',
                    'vendus_field_label' => 'Data do Documento',
                    'metadata_key' => null,
                    'field_type' => 'string',
                    'is_required' => false,
                    'default_value' => null,
                    'description' => 'Data da fatura (YYYY-MM-DD)',
                    'is_active' => true,
                ],
                [
                    'vendus_field' => 'notes',
                    'vendus_field_label' => 'Notas',
                    'metadata_key' => null,
                    'field_type' => 'string',
                    'is_required' => false,
                    'default_value' => null,
                    'description' => 'Observações adicionais',
                    'is_active' => true,
                ],
                // Itens
                [
                    'vendus_field' => 'item_reference',
                    'vendus_field_label' => 'Item - Referência',
                    'metadata_key' => null,
                    'field_type' => 'string',
                    'is_required' => true,
                    'default_value' => null,
                    'description' => 'Referência do produto lido do Excel',
                    'is_active' => true,
                ],
                [
                    'vendus_field' => 'item_title',
                    'vendus_field_label' => 'Item - Título',
                    'metadata_key' => null,
                    'field_type' => 'string',
                    'is_required' => true,
                    'default_value' => null,
                    'description' => 'Nome/descrição do produto lido do Excel',
                    'is_active' => true,
                ],
                [
                    'vendus_field' => 'item_quantity',
                    'vendus_field_label' => 'Item - Quantidade',
                    'metadata_key' => null,
                    'field_type' => 'number',
                    'is_required' => true,
                    'default_value' => '1',
                    'description' => 'Quantidade por linha da fatura',
                    'is_active' => true,
                ],
                [
                    'vendus_field' => 'item_price',
                    'vendus_field_label' => 'Item - Preço',
                    'metadata_key' => null,
                    'field_type' => 'string',
                    'is_required' => true,
                    'default_value' => null,
                    'description' => 'Preço unitário (bruto) por item',
                    'is_active' => true,
                ],
                [
                    'vendus_field' => 'item_tax',
                    'vendus_field_label' => 'Item - Imposto/IVA',
                    'metadata_key' => null,
                    'field_type' => 'string',
                    'is_required' => false,
                    'default_value' => null,
                    'description' => 'Imposto/IVA aplicável (ex.: IVA 23%)',
                    'is_active' => true,
                ],
            ]);
        }
        return view('document-mappings.index', compact('mappings'));
    }

    /**
     * Salva mapeamentos de faturas
     */
    public function store(Request $request)
    {
        $data = $request->input('mappings', []);
        foreach ($data as $mapping) {
            DocumentFieldMapping::updateOrCreate(
                ['vendus_field' => $mapping['vendus_field'] ?? ''],
                [
                    'vendus_field_label' => $mapping['vendus_field_label'] ?? ($mapping['vendus_field'] ?? ''),
                    'metadata_key' => $mapping['metadata_key'] ?? null,
                    'field_type' => $mapping['field_type'] ?? 'string',
                    'is_required' => (bool)($mapping['is_required'] ?? false),
                    'default_value' => $mapping['default_value'] ?? null,
                    'description' => $mapping['description'] ?? null,
                    'is_active' => (bool)($mapping['is_active'] ?? true),
                ]
            );
        }

        return redirect()->route('document-mappings.index')->with('success', 'Mapeamentos de faturas salvos com sucesso');
    }

    /**
     * Restaura mapeamentos padrão
     */
    public function resetToDefault()
    {
        // Limpa a tabela e recria os padrões
        DocumentFieldMapping::query()->delete();
        DocumentFieldMapping::createDefaultMappings();
        return redirect()->route('document-mappings.index')->with('success', 'Mapeamentos de faturas restaurados ao padrão');
    }
}