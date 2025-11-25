<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FieldMapping;
use App\Http\Services\VendusService;

class FieldMappingController extends Controller
{
    /**
     * Exibe a página de configuração de mapeamentos
     */
    public function index()
    {
        $mappings = FieldMapping::orderCollectionByDefault(
            FieldMapping::orderBy('vendus_field')->get()
        );
        
        // Se não há mapeamentos, cria os padrão
        if ($mappings->isEmpty()) {
            FieldMapping::createDefaultMappings();
            $mappings = FieldMapping::orderCollectionByDefault(
                FieldMapping::orderBy('vendus_field')->get()
            );
        }
        
        return view('field-mappings.index', compact('mappings'));
    }

    /**
     * Salva ou atualiza os mapeamentos
     */
    public function store(Request $request)
    {
        $request->validate([
            'mappings' => 'required|array',
            'mappings.*.vendus_field' => 'required|string',
            'mappings.*.excel_column' => 'nullable|string',
            'mappings.*.field_type' => 'required|in:string,number,boolean',
            'mappings.*.is_required' => 'boolean',
            'mappings.*.default_value' => 'nullable|string',
            'mappings.*.description' => 'nullable|string',
            'mappings.*.is_active' => 'boolean'
        ]);

        try {
            // Atualiza os mapeamentos existentes
            foreach ($request->mappings as $mappingData) {
                FieldMapping::updateOrCreate(
                    ['vendus_field' => $mappingData['vendus_field']],
                    [
                        'excel_column' => $mappingData['excel_column'] ?: null,
                        'field_type' => $mappingData['field_type'],
                        'is_required' => $mappingData['is_required'] ?? false,
                        'default_value' => $mappingData['default_value'] ?? null,
                        'description' => $mappingData['description'] ?? null,
                        'is_active' => $mappingData['is_active'] ?? true
                    ]
                );
            }

            return redirect()->route('field-mappings.index')
                           ->with('success', 'Mapeamentos salvos com sucesso!');

        } catch (\Exception $e) {
            return redirect()->back()
                           ->with('error', 'Erro ao salvar mapeamentos: ' . $e->getMessage())
                           ->withInput();
        }
    }

    /**
     * Restaura os mapeamentos padrão
     */
    public function resetToDefault()
    {
        try {
            FieldMapping::truncate();
            FieldMapping::createDefaultMappings();
            
            return redirect()->route('field-mappings.index')
                           ->with('success', 'Mapeamentos restaurados para o padrão!');
        } catch (\Exception $e) {
            return redirect()->back()
                           ->with('error', 'Erro ao restaurar mapeamentos: ' . $e->getMessage());
        }
    }

    /**
     * API para obter mapeamentos ativos (usado pelo upload)
     */
    public function getActiveMappings()
    {
        $mappings = FieldMapping::getMappedFields();
        
        return response()->json([
            'success' => true,
            'mappings' => $mappings->keyBy('vendus_field')
        ]);
    }

    /**
     * Adiciona um novo mapeamento
     */
    public function addMapping(Request $request)
    {
        $request->validate([
            'vendus_field' => 'required|string|unique:field_mappings,vendus_field',
            'vendus_field_label' => 'required|string',
            'excel_column' => 'nullable|string',
            'field_type' => 'required|in:string,number,boolean',
            'is_required' => 'boolean',
            'default_value' => 'nullable|string',
            'description' => 'nullable|string'
        ]);

        try {
            FieldMapping::create($request->all());
            
            return redirect()->route('field-mappings.index')
                           ->with('success', 'Campo Vendus adicionado com sucesso!');
        } catch (\Exception $e) {
            return redirect()->back()
                           ->with('error', 'Erro ao adicionar campo: ' . $e->getMessage())
                           ->withInput();
        }
    }

    /**
     * Remove um mapeamento
     */
    public function destroy($id)
    {
        try {
            $mapping = FieldMapping::findOrFail($id);
            $mapping->delete();
            
            return redirect()->route('field-mappings.index')
                           ->with('success', 'Campo removido com sucesso!');
        } catch (\Exception $e) {
            return redirect()->back()
                           ->with('error', 'Erro ao remover campo: ' . $e->getMessage());
        }
    }

    /**
     * Processa upload de Excel de exemplo para extrair colunas
     */
    public function uploadExample(Request $request)
    {
        $request->validate([
            'example_file' => 'required|file|mimes:xlsx,xls,csv|max:10240'
        ]);

        try {
            $file = $request->file('example_file');
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader(
                \PhpOffice\PhpSpreadsheet\IOFactory::identify($file->getPathname())
            );
            
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Pega a primeira linha (cabeçalhos)
            $highestColumn = $worksheet->getHighestColumn();
            $headers = [];
            
            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $cellValue = $worksheet->getCell($col . '1')->getValue();
                if (!empty($cellValue)) {
                    $headers[] = trim($cellValue);
                }
            }

            return response()->json([
                'success' => true,
                'columns' => $headers,
                'message' => 'Colunas extraídas com sucesso!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar arquivo: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Lista unidades disponíveis na conta Vendus para auxiliar configuração de unit_id
     */
    public function getUnits(VendusService $vendusService)
    {
        try {
            $result = $vendusService->getUnits();
            if (!($result['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Não foi possível obter unidades',
                    'error' => $result['error'] ?? null
                ], 400);
            }

            $data = $result['data'];
            $units = [];

            // Tenta normalizar diferentes formatos de retorno
            if (is_array($data)) {
                foreach ($data as $item) {
                    if (is_array($item)) {
                        if (isset($item['id']) && is_numeric($item['id'])) {
                            $units[] = [
                                'id' => (int) $item['id'],
                                'name' => $item['name'] ?? ($item['title'] ?? null),
                                'code' => $item['code'] ?? null,
                            ];
                        } else {
                            foreach ($item as $sub) {
                                if (is_array($sub) && isset($sub['id']) && is_numeric($sub['id'])) {
                                    $units[] = [
                                        'id' => (int) $sub['id'],
                                        'name' => $sub['name'] ?? ($sub['title'] ?? null),
                                        'code' => $sub['code'] ?? null,
                                    ];
                                }
                            }
                        }
                    }
                }
            }

            if (empty($units)) {
                return response()->json([
                    'success' => true,
                    'units' => [],
                    'raw' => $data,
                    'message' => 'Nenhuma unidade identificada. Verifique o conteúdo bruto.'
                ]);
            }

            return response()->json([
                'success' => true,
                'units' => $units,
                'count' => count($units)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter unidades: ' . $e->getMessage()
            ], 500);
        }
    }
}
