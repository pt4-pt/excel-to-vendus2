<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldMapping extends Model
{
    private static array $ALLOWED_V12_FIELDS = [
        'reference','barcode','supplier_code','title','description','include_description',
        'unit_id','type_id','variant_id','class_id','prices','stock','tax','lot_control',
        'category_id','brand_id','image','status','stores',
        'price','supply','price_group_id','price_group_gross',
        'stock_control','stock_type','stock_store_id','product_variant_id','stock_stock','stock_stock_alert',
        'tax_id','tax_exemption','tax_exemption_law'
    ];
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
        return self::where('is_active', true)
            ->whereIn('vendus_field', self::$ALLOWED_V12_FIELDS)
            ->get();
    }

    /**
     * Obtém mapeamento por campo Vendus
     */
    public static function getByVendusField($field)
    {
        return self::where('vendus_field', $field)
                   ->where('is_active', true)
                   ->whereIn('vendus_field', self::$ALLOWED_V12_FIELDS)
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
                   ->whereIn('vendus_field', self::$ALLOWED_V12_FIELDS)
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
                'vendus_field' => 'price',
                'vendus_field_label' => 'Preço (venda) (prices.gross)',
                'excel_column' => null,
                'field_type' => 'number',
                'is_required' => true,
                'description' => 'Usado para construir prices.gross'
            ],
            [
                'vendus_field' => 'supply',
                'vendus_field_label' => 'Preço de Custo (prices.supply)',
                'excel_column' => null,
                'field_type' => 'number',
                'is_required' => false,
                'description' => 'Usado para construir prices.supply'
            ],
            
            [
                'vendus_field' => 'unit_id',
                'vendus_field_label' => 'Unidade',
                'excel_column' => null,
                'field_type' => 'number',
                'is_required' => false,
                'default_value' => null,
                'description' => 'ID da unidade de medida. Será resolvido automaticamente se não configurado.'
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
                'vendus_field_label' => 'Stock - Control',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'default_value' => true,
                'description' => 'Usado para construir stock.control'
            ],
            [
                'vendus_field' => 'stock_type',
                'vendus_field_label' => 'Stock - Type',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'description' => 'Usado para construir stock.type'
            ],
            [
                'vendus_field' => 'stock_store_id',
                'vendus_field_label' => 'Stock - Store ID',
                'excel_column' => null,
                'field_type' => 'number',
                'is_required' => false,
                'description' => 'Usado para construir stock.stores[].id'
            ],
            [
                'vendus_field' => 'product_variant_id',
                'vendus_field_label' => 'Stock - Product Variant ID',
                'excel_column' => null,
                'field_type' => 'number',
                'is_required' => false,
                'description' => 'Usado para construir stock.stores[].product_variant_id'
            ],
            [
                'vendus_field' => 'stock_stock',
                'vendus_field_label' => 'Stock - Quantidade',
                'excel_column' => null,
                'field_type' => 'number',
                'is_required' => false,
                'description' => 'Usado para construir stock.stores[].stock'
            ],
            [
                'vendus_field' => 'stock_stock_alert',
                'vendus_field_label' => 'Stock - Alerta',
                'excel_column' => null,
                'field_type' => 'number',
                'is_required' => false,
                'description' => 'Usado para construir stock.stores[].stock_alert'
            ],
            [
                'vendus_field' => 'price_group_id',
                'vendus_field_label' => 'Grupo de Preço - ID',
                'excel_column' => null,
                'field_type' => 'number',
                'is_required' => false,
                'description' => 'Usado para construir prices.groups[].id'
            ],
            [
                'vendus_field' => 'price_group_gross',
                'vendus_field_label' => 'Grupo de Preço - Preço',
                'excel_column' => null,
                'field_type' => 'number',
                'is_required' => false,
                'description' => 'Usado para construir prices.groups[].gross'
            ],
            [
                'vendus_field' => 'tax_id',
                'vendus_field_label' => 'Imposto - ID',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'description' => 'Usado para construir tax.id'
            ],
            [
                'vendus_field' => 'tax_exemption',
                'vendus_field_label' => 'Imposto - Isenção',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'description' => 'Usado para construir tax.exemption'
            ],
            [
                'vendus_field' => 'tax_exemption_law',
                'vendus_field_label' => 'Imposto - Lei de Isenção',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'description' => 'Usado para construir tax.exemption_law'
            ],
            // Campos fiscais
            [
                'vendus_field' => 'tax',
                'vendus_field_label' => 'Taxa',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'description' => 'Taxa aplicável ao produto (ID ou código)'
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
