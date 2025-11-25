<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class FieldMapping extends Model
{
    private static array $ALLOWED_V12_FIELDS = [
        'title','reference','barcode','supplier_code','description','include_description',
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
        $items = self::where('is_active', true)
            ->whereIn('vendus_field', self::$ALLOWED_V12_FIELDS)
            ->get();
        return self::orderCollectionByDefault($items);
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
        $items = self::where('is_active', true)
                   ->whereIn('vendus_field', self::$ALLOWED_V12_FIELDS)
                   ->get();
        return self::orderCollectionByDefault($items);
    }

    /**
     * Cria mapeamentos padrão para a API Vendus
     */
    public static function createDefaultMappings()
    {
        $defaultMappings = self::getDefaultMappings();
        foreach ($defaultMappings as $mapping) {
            self::updateOrCreate(
                ['vendus_field' => $mapping['vendus_field']],
                $mapping
            );
        }
    }

    private static function getDefaultMappings(): array
    {
        return [
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
                'vendus_field' => 'description',
                'vendus_field_label' => 'Descrição',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'description' => 'Descrição detalhada do produto'
            ],
            [
                'vendus_field' => 'image',
                'vendus_field_label' => 'Imagem',
                'excel_column' => null,
                'field_type' => 'string',
                'is_required' => false,
                'description' => 'URL da imagem do produto'
            ],
            [
                'vendus_field' => 'price_group_gross',
                'vendus_field_label' => 'Preço (venda)',
                'excel_column' => null,
                'field_type' => 'number',
                'is_required' => true,
                'description' => 'Usado para construir prices'
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
            ]
        ];
    }

    public static function getDefaultOrderMap(): array
    {
        $map = [];
        $i = 1;
        foreach (self::getDefaultMappings() as $m) {
            $map[$m['vendus_field']] = $i++;
        }
        return $map;
    }

    public static function orderCollectionByDefault(Collection $items): Collection
    {
        $order = self::getDefaultOrderMap();
        return $items->sortBy(function ($m) use ($order) {
            $k = $m->vendus_field;
            return $order[$k] ?? 1000000;
        })->values();
    }
}
