<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldMapping extends Model
{
    protected $fillable = [
        'vendus_field',
        'vendus_field_label',
        'excel_column',
        'field_type',
        'is_required',
        'default_value',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Obtém todos os mapeamentos ativos
     */
    public static function getActiveMappings()
    {
        return self::where('is_active', true)->get();
    }

    /**
     * Obtém mapeamento por campo Vendus
     */
    public static function getByVendusField($field)
    {
        return self::where('vendus_field', $field)
                   ->where('is_active', true)
                   ->first();
    }

    /**
     * Obtém todos os campos obrigatórios
     */
    public static function getRequiredFields()
    {
        return self::where('is_required', true)
                   ->where('is_active', true)
                   ->pluck('vendus_field')
                   ->toArray();
    }

    /**
     * Obtém mapeamentos com colunas Excel definidas
     */
    public static function getMappedFields()
    {
        return self::where('is_active', true)
                   ->whereNotNull('excel_column')
                   ->get();
    }

    /**
     * Cria mapeamentos padrão para a API Vendus
     */
    public static function createDefaultMappings()
    {
        $defaultMappings = [
            // Campos básicos obrigatórios
            [
                'vendus_field' => 'title',
                'vendus_field_label' => 'Nome do Produto',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => true,
                'description' => 'Nome/título do produto que aparecerá na loja'
            ],
            [
                'vendus_field' => 'supply_price',
                'vendus_field_label' => 'Preço de Custo',
                'excel_column' => null,
                'field_type' => 'number',
                'is_required' => true,
                'description' => 'Preço de custo/fornecimento do produto'
            ],
            [
                'vendus_field' => 'gross_price',
                'vendus_field_label' => 'Preço de Venda',
                'excel_column' => null,
                'field_type' => 'number',
                'is_required' => true,
                'description' => 'Preço bruto de venda do produto'
            ],
            [
                'vendus_field' => 'unit_id',
                'vendus_field_label' => 'Unidade',
                'excel_column' => null,
                'field_type' => 'number',
                'is_required' => true,
                'default_value' => '1',
                'description' => 'ID da unidade de medida (1 = peça/unidade)'
            ],
            [
                'vendus_field' => 'status',
                'vendus_field_label' => 'Status',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => true,
                'default_value' => 'on',
                'description' => 'Status do produto (on = ativo, off = inativo)'
            ],
            
            // Campos de identificação
            [
                'vendus_field' => 'reference',
                'vendus_field_label' => 'Referência',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'description' => 'Código de referência interno do produto'
            ],
            [
                'vendus_field' => 'barcode',
                'vendus_field_label' => 'Código de Barras',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'description' => 'Código de barras do produto'
            ],
            [
                'vendus_field' => 'supplier_code',
                'vendus_field_label' => 'Código do Fornecedor',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'description' => 'Código do produto no fornecedor'
            ],
            
            // Campos de descrição
            [
                'vendus_field' => 'description',
                'vendus_field_label' => 'Descrição',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'description' => 'Descrição detalhada do produto'
            ],
            [
                'vendus_field' => 'include_description',
                'vendus_field_label' => 'Incluir Descrição',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'default_value' => 'no',
                'description' => 'Se deve incluir descrição (yes/no)'
            ],
            
            // Campos de categorização
            [
                'vendus_field' => 'type_id',
                'vendus_field_label' => 'Tipo',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'description' => 'ID do tipo de produto (P = Produto, S = Serviço)'
            ],
            [
                'vendus_field' => 'class_id',
                'vendus_field_label' => 'Classe',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'description' => 'ID da classe do produto'
            ],
            [
                'vendus_field' => 'category_id',
                'vendus_field_label' => 'Categoria',
                'excel_column' => null,
                'field_type' => 'number',
                'is_required' => false,
                'description' => 'ID da categoria do produto'
            ],
            [
                'vendus_field' => 'brand_id',
                'vendus_field_label' => 'Marca',
                'excel_column' => null,
                'field_type' => 'number',
                'is_required' => false,
                'description' => 'ID da marca do produto'
            ],
            
            // Campos de controle de estoque
            [
                'vendus_field' => 'lot_control',
                'vendus_field_label' => 'Controle de Lote',
                'excel_column' => null,
                'field_type' => 'boolean',
                'is_required' => false,
                'default_value' => 'false',
                'description' => 'Se o produto tem controle de lote (true/false)'
            ],
            [
                'vendus_field' => 'stock_control',
                'vendus_field_label' => 'Controle de Estoque',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'default_value' => '1',
                'description' => 'Tipo de controle de estoque (0 = sem controle, 1 = com controle)'
            ],
            [
                'vendus_field' => 'stock_type',
                'vendus_field_label' => 'Tipo de Estoque',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'default_value' => 'M',
                'description' => 'Tipo de estoque (M = Manual, A = Automático)'
            ],
            
            // Campos fiscais
            [
                'vendus_field' => 'tax_id',
                'vendus_field_label' => 'Taxa',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'description' => 'ID da taxa aplicável ao produto'
            ],
            [
                'vendus_field' => 'tax_exemption',
                'vendus_field_label' => 'Isenção Fiscal',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'description' => 'Código de isenção fiscal'
            ],
            [
                'vendus_field' => 'tax_exemption_law',
                'vendus_field_label' => 'Lei de Isenção',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'description' => 'Lei que fundamenta a isenção fiscal'
            ],
            
            // Campo de imagem
            [
                'vendus_field' => 'image',
                'vendus_field_label' => 'Imagem',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'description' => 'URL da imagem do produto'
            ]
        ];

        foreach ($defaultMappings as $mapping) {
            self::updateOrCreate(
                ['vendus_field' => $mapping['vendus_field']],
                $mapping
            );
        }
    }
}
