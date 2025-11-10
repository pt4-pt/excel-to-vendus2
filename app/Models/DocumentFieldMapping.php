<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentFieldMapping extends Model
{
    protected $fillable = [
        'vendus_field',
        'vendus_field_label',
        'metadata_key',
        'field_type',
        'is_required',
        'default_value',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Retorna todos os mapeamentos ativos
     */
    public static function getMappedFields()
    {
        return self::where('is_active', true)->orderBy('vendus_field')->get();
    }

    /**
     * Cria mapeamentos padrão de faturas
     */
    public static function createDefaultMappings(): void
    {
        $defaults = [
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
                'is_required' => true,
                'default_value' => 'Consumidor final',
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
            // Campos de itens
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
        ];

        foreach ($defaults as $mapping) {
            self::firstOrCreate(
                ['vendus_field' => $mapping['vendus_field']],
                $mapping
            );
        }
    }
}