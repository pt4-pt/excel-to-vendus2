<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração de Mapeamento - Excel para Vendus</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 300;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .content {
            padding: 40px;
        }

        .upload-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            border: 2px dashed #dee2e6;
            text-align: center;
            transition: all 0.3s ease;
        }

        .upload-section:hover {
            border-color: #4facfe;
            background: #f0f8ff;
        }

        .upload-section h3 {
            color: #495057;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }

        .upload-section p {
            color: #6c757d;
            margin-bottom: 20px;
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
            margin-bottom: 15px;
        }

        .file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-input-label {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .file-input-label:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.3);
        }

        .file-name {
            margin-top: 10px;
            font-style: italic;
            color: #28a745;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .actions {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .mappings-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }

        .table tr:hover {
            background-color: #f8f9fa;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 6 7 7 7-7'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
            padding-right: 2.5rem;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-secondary {
            background-color: #e2e3e5;
            color: #6c757d;
        }

        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .add-mapping {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 5px;
            font-weight: 500;
            color: #495057;
        }

        .navigation {
            text-align: center;
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .content {
                padding: 20px;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .table {
                font-size: 0.9rem;
            }
            
            .table th,
            .table td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Mapeamento de Campos</h1>
            <p>Configure quais colunas do Excel correspondem aos campos obrigatórios do Vendus</p>
        </div>

        <div class="content">
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-error">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Seção de Upload de Exemplo -->
            <div class="upload-section">
                <h3>Upload de Excel de Exemplo</h3>
                <p>Faça upload de um arquivo Excel de exemplo para extrair automaticamente as colunas disponíveis</p>
                
                <div class="file-input-wrapper">
                    <input type="file" id="example-file" class="file-input" accept=".xlsx,.xls,.csv">
                    <label for="example-file" class="file-input-label">
                        Escolher Arquivo Excel
                    </label>
                </div>
                
                <div id="file-name" class="file-name" style="display: none;"></div>
                <div id="upload-status" style="margin-top: 15px;"></div>
            </div>

            <div class="actions">
                <a href="{{ route('upload') }}" class="btn btn-secondary">
                    Voltar ao Upload
                </a>
                <form method="POST" action="{{ route('field-mappings.reset') }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Tem certeza que deseja restaurar os mapeamentos padrão?')">
                        Restaurar Padrão
                    </button>
                </form>
            </div>

            <!-- Guia rápida e utilitários -->
            <div style="background:#e9f7ef;border:1px solid #c3e6cb;color:#155724;border-radius:8px;padding:16px;margin-bottom:24px;">
                <div style="font-weight:600;margin-bottom:8px;">Dicas importantes para v1.2 da API Vendus</div>
                <ul style="margin-left:18px;">
                    <li>Obrigatórios: mapeie pelo menos `reference` e `title`.</li>
                    <li>Preço: para produtos com variações, informe `price` por variação (será enviado em `prices[]`).</li>
                    <li>Unidade (`unit_id`): obrigatório; mapeie uma coluna ou defina valor padrão no mapeamento.</li>
                    <li>Campos removidos: `stock_type`, `gross_price` (topo) e `supply_price` não são enviados.</li>
                    <li>Impostos: use `tax` (ID ou código). `tax_id` e isenções estão como obsoletos.</li>
                </ul>
                <div style="margin-top:12px;">
                    <button id="btn-fetch-units" class="btn btn-secondary">Buscar Unidades da Vendus</button>
                </div>
                <div id="units-list" style="margin-top:12px;display:none;background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:12px;">
                    <div style="font-weight:600;margin-bottom:8px;">Unidades encontradas</div>
                    <div id="units-content" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;"></div>
                </div>
            </div>

            <form method="POST" action="{{ route('field-mappings.store') }}">
                @csrf
                
                <div class="mappings-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Campo Vendus</th>
                                <th>Coluna Excel</th>
                                <th>Tipo</th>
                                <th>Obrigatório</th>
                                <th>Valor Padrão</th>
                                <th>Descrição</th>
                                <th>Ativo</th>
                            </tr>
                        </thead>
                        <tbody id="mappings-tbody">
                            @foreach($mappings as $index => $mapping)
                            <tr>
                                <td>
                                    <input type="hidden" name="mappings[{{ $index }}][vendus_field]" value="{{ $mapping->vendus_field }}">
                                    <strong>{{ $mapping->vendus_field_label ?? $mapping->vendus_field }}</strong>
                                    @if($mapping->vendus_field === 'unit_id')
                                        <span class="badge badge-secondary" title="Se não mapear, será resolvido automaticamente.">Auto</span>
                                    @endif
                                    <br><small class="text-muted">{{ $mapping->vendus_field }}</small>
                                </td>
                                <td>
                                    <select name="mappings[{{ $index }}][excel_column]" 
                                            class="form-control form-select excel-column-select" 
                                            data-current-value="{{ $mapping->excel_column }}">
                                        <option value="">-- Selecione uma coluna --</option>
                                        @if($mapping->excel_column)
                                            <option value="{{ $mapping->excel_column }}" selected>{{ $mapping->excel_column }}</option>
                                        @endif
                                    </select>
                                </td>
                                <td>
                                    <select name="mappings[{{ $index }}][field_type]" class="form-control form-select" required>
                                        <option value="string" {{ $mapping->field_type == 'string' ? 'selected' : '' }}>Texto</option>
                                        <option value="number" {{ $mapping->field_type == 'number' ? 'selected' : '' }}>Número</option>
                                        <option value="boolean" {{ $mapping->field_type == 'boolean' ? 'selected' : '' }}>Verdadeiro/Falso</option>
                                    </select>
                                </td>
                                <td class="checkbox-wrapper">
                                    <input type="hidden" name="mappings[{{ $index }}][is_required]" value="0">
                                    <input type="checkbox" 
                                           name="mappings[{{ $index }}][is_required]" 
                                           value="1" 
                                           {{ $mapping->is_required ? 'checked' : '' }}>
                                    @if($mapping->is_required)
                                        <span class="badge badge-danger">Obrigatório</span>
                                    @endif
                                </td>
                                <td>
                                    <input type="text" 
                                           name="mappings[{{ $index }}][default_value]" 
                                           value="{{ $mapping->default_value }}" 
                                           class="form-control" 
                                           placeholder="Valor padrão">
                                </td>
                                <td>
                                    <input type="text" 
                                           name="mappings[{{ $index }}][description]" 
                                           value="{{ $mapping->description }}" 
                                           class="form-control" 
                                           placeholder="Descrição do campo">
                                </td>
                                <td class="checkbox-wrapper">
                                    <input type="hidden" name="mappings[{{ $index }}][is_active]" value="0">
                                    <input type="checkbox" 
                                           name="mappings[{{ $index }}][is_active]" 
                                           value="1" 
                                           {{ $mapping->is_active ? 'checked' : '' }}>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-success">
                        Salvar Mapeamentos
                    </button>
                </div>
            </form>

            <div class="navigation">
                <a href="{{ route('upload') }}" class="btn btn-primary">
                    Ir para Upload de Produtos
                </a>
            </div>
        </div>
    </div>

    <script>
        let availableColumns = [];

        document.addEventListener('DOMContentLoaded', function() {
            // Funcionalidade de upload de exemplo
            const fileInput = document.getElementById('example-file');
            const fileName = document.getElementById('file-name');
            const uploadStatus = document.getElementById('upload-status');
            const btnFetchUnits = document.getElementById('btn-fetch-units');
            const unitsList = document.getElementById('units-list');
            const unitsContent = document.getElementById('units-content');

            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    fileName.textContent = `Arquivo selecionado: ${file.name}`;
                    fileName.style.display = 'block';
                    
                    // Upload do arquivo
                    uploadExampleFile(file);
                }
            });

            // Buscar unidades da Vendus
            if (btnFetchUnits) {
                btnFetchUnits.addEventListener('click', function() {
                    unitsContent.innerHTML = '<div style="color:#007bff;">🔎 Buscando unidades...</div>';
                    unitsList.style.display = 'block';
                    fetch('{{ route("units.api") }}')
                      .then(resp => resp.json())
                      .then(data => {
                        if (data.success && data.units && data.units.length) {
                            unitsContent.innerHTML = '';
                            data.units.forEach(u => {
                                const div = document.createElement('div');
                                div.style.border = '#e9ecef 1px solid';
                                div.style.borderRadius = '6px';
                                div.style.padding = '8px';
                                div.innerHTML = `<div><strong>ID:</strong> ${u.id}</div><div><strong>Nome:</strong> ${u.name || '-'} </div>`;
                                const btnUse = document.createElement('button');
                                btnUse.textContent = 'Usar este ID';
                                btnUse.className = 'btn btn-success';
                                btnUse.style.marginTop = '8px';
                                btnUse.onclick = () => setUnitDefaultValue(u.id);
                                div.appendChild(btnUse);
                                unitsContent.appendChild(div);
                            });
                        } else {
                            unitsContent.innerHTML = '<div style="color:#dc3545;">Não foi possível identificar unidades. Verifique se sua conta possui unidades configuradas.</div>';
                        }
                      })
                      .catch(err => {
                        console.error(err);
                        unitsContent.innerHTML = '<div style="color:#dc3545;">Erro ao buscar unidades.</div>';
                      });
                });
            }

            // Funcionalidade para destacar campos obrigatórios sem mapeamento
            updateRequiredFieldsHighlight();
        });

        function uploadExampleFile(file) {
            const uploadStatus = document.getElementById('upload-status');
            const formData = new FormData();
            formData.append('example_file', file);

            uploadStatus.innerHTML = '<div style="color: #007bff;">📤 Processando arquivo...</div>';

            fetch('{{ route("field-mappings.upload-example") }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    availableColumns = data.columns;
                    updateColumnDropdowns();
                    uploadStatus.innerHTML = `<div style="color: #28a745;">${data.message} (${data.columns.length} colunas encontradas)</div>`;
                } else {
                    uploadStatus.innerHTML = `<div style="color: #dc3545;">${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                uploadStatus.innerHTML = '<div style="color: #dc3545;">Erro ao processar arquivo</div>';
            });
        }

        function updateColumnDropdowns() {
            const selects = document.querySelectorAll('.excel-column-select');
            
            selects.forEach(select => {
                const currentValue = select.getAttribute('data-current-value');
                
                // Limpar opções existentes (exceto a primeira)
                while (select.children.length > 1) {
                    select.removeChild(select.lastChild);
                }
                
                // Adicionar novas opções
                availableColumns.forEach(column => {
                    const option = document.createElement('option');
                    option.value = column;
                    option.textContent = column;
                    
                    if (column === currentValue) {
                        option.selected = true;
                    }
                    
                    select.appendChild(option);
                });
                
                // Adicionar listener para atualizar destaque
                select.addEventListener('change', updateRequiredFieldsHighlight);
            });
            
            updateRequiredFieldsHighlight();
        }

        function updateRequiredFieldsHighlight() {
            const rows = document.querySelectorAll('#mappings-tbody tr');
            
            rows.forEach(row => {
                const isRequired = row.querySelector('input[name*="[is_required]"]:not([type="hidden"])').checked;
                const excelColumnSelect = row.querySelector('.excel-column-select');
                
                if (isRequired && !excelColumnSelect.value) {
                    row.style.backgroundColor = '#fff3cd';
                    row.style.border = '2px solid #ffc107';
                } else {
                    row.style.backgroundColor = '';
                    row.style.border = '';
                }
            });
        }

        function setUnitDefaultValue(unitId) {
            // Encontra a linha do unit_id e define o valor padrão
            const rows = document.querySelectorAll('#mappings-tbody tr');
            rows.forEach(row => {
                const hidden = row.querySelector('input[type="hidden"][name*="[vendus_field]"]');
                if (hidden && hidden.value === 'unit_id') {
                    const inputDefault = row.querySelector('input[name*="[default_value]"]');
                    const fieldTypeSelect = row.querySelector('select[name*="[field_type]"]');
                    if (inputDefault) {
                        inputDefault.value = unitId;
                    }
                    if (fieldTypeSelect) {
                        fieldTypeSelect.value = 'number';
                    }
                }
            });
        }
    </script>
</body>
</html>
