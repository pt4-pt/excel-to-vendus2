<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração de Mapeamento de Faturas (Excel)</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { font-size: 2.2rem; margin-bottom: 10px; font-weight: 300; }
        .header p { font-size: 1.05rem; opacity: 0.9; }
        .content { padding: 40px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .upload-section { background: #f8f9fa; border-radius: 12px; padding: 25px; margin-bottom: 30px; border: 2px dashed #dee2e6; text-align: center; transition: all 0.3s ease; }
        .upload-section:hover { border-color: #4facfe; background: #f0f8ff; }
        .upload-section h3 { color: #495057; margin-bottom: 15px; font-size: 1.2rem; }
        .upload-section p { color: #6c757d; margin-bottom: 18px; }
        .file-input-wrapper { position: relative; display: inline-block; margin-bottom: 15px; }
        .file-input { position: absolute; opacity: 0; width: 100%; height: 100%; cursor: pointer; }
        .file-input-label { display: inline-flex; align-items: center; gap: 10px; padding: 12px 24px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border-radius: 8px; cursor: pointer; font-weight: 500; transition: all 0.3s ease; }
        .file-input-label:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(79, 172, 254, 0.3); }
        .file-name { margin-top: 10px; font-style: italic; color: #28a745; }
        .actions { display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s ease; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .mappings-table { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 30px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { background: #f8f9fa; padding: 15px; text-align: left; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6; }
        .table td { padding: 15px; border-bottom: 1px solid #dee2e6; vertical-align: middle; }
        .table tr:hover { background-color: #f8f9fa; }
        .form-control { width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 6px; font-size: 0.9rem; transition: border-color 0.3s ease; }
        .form-control:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .form-select { background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 6 7 7 7-7'/%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right 0.75rem center; background-size: 16px 12px; padding-right: 2.5rem; }
        .checkbox-wrapper { display: flex; align-items: center; justify-content: center; }
        .checkbox-wrapper input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 500; }
        .badge-danger { background-color: #f8d7da; color: #721c24; }
        .text-muted { color: #6c757d; font-size: 0.85rem; }
        @media (max-width: 768px) { .header h1 { font-size: 2rem; } .content { padding: 20px; } .actions { flex-direction: column; } .table { font-size: 0.9rem; } .table th, .table td { padding: 10px 8px; } }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Mapeamento de Campos — Faturas (Excel)</h1>
        <p>Configure quais colunas do Excel correspondem aos campos da fatura e dos itens</p>
    </div>

    <div class="content">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        <div class="upload-section">
            <h3>Upload de Excel de Exemplo</h3>
            <p>Carregue um Excel de exemplo para preencher as opções de colunas automaticamente</p>
            <div class="file-input-wrapper">
                <input type="file" id="example-file" class="file-input" accept=".xlsx,.xls,.csv">
                <label for="example-file" class="file-input-label">Escolher Arquivo Excel</label>
            </div>
            <div id="file-name" class="file-name" style="display:none;"></div>
            <div id="upload-status" style="margin-top: 15px;"></div>
        </div>

        <div class="actions">
            <a href="{{ route('upload') }}" class="btn btn-secondary">Voltar ao Upload</a>
            <form method="POST" action="{{ route('document-mappings.reset') }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn btn-warning" onclick="return confirm('Tem certeza que deseja restaurar os padrões?')">Restaurar Padrão</button>
            </form>
        </div>

        <form method="POST" action="{{ route('document-mappings.store') }}">
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
                    @foreach($mappings as $index => $m)
                        <tr>
                            <td>
                                <input type="hidden" name="mappings[{{ $index }}][vendus_field]" value="{{ is_array($m) ? $m['vendus_field'] : $m->vendus_field }}">
                                <strong>{{ is_array($m) ? ($m['vendus_field_label'] ?? $m['vendus_field']) : ($m->vendus_field_label ?? $m->vendus_field) }}</strong>
                                <br><small class="text-muted">{{ is_array($m) ? $m['vendus_field'] : $m->vendus_field }}</small>
                            </td>
                            <td>
                                <select name="mappings[{{ $index }}][metadata_key]" class="form-control form-select excel-column-select" data-current-value="{{ is_array($m) ? ($m['metadata_key'] ?? '') : ($m->metadata_key ?? '') }}">
                                    <option value="">-- Selecione uma coluna --</option>
                                    @php $currentCol = is_array($m) ? ($m['metadata_key'] ?? null) : ($m->metadata_key ?? null); @endphp
                                    @if($currentCol)
                                        <option value="{{ $currentCol }}" selected>{{ $currentCol }}</option>
                                    @endif
                                </select>
                            </td>
                            <td>
                                <select name="mappings[{{ $index }}][field_type]" class="form-control form-select" required>
                                    @php $type = is_array($m) ? $m['field_type'] : $m->field_type; @endphp
                                    <option value="string" {{ $type == 'string' ? 'selected' : '' }}>Texto</option>
                                    <option value="number" {{ $type == 'number' ? 'selected' : '' }}>Número</option>
                                    <option value="boolean" {{ $type == 'boolean' ? 'selected' : '' }}>Verdadeiro/Falso</option>
                                </select>
                            </td>
                            <td class="checkbox-wrapper">
                                @php $req = is_array($m) ? ($m['is_required'] ?? false) : ($m->is_required ?? false); @endphp
                                <input type="hidden" name="mappings[{{ $index }}][is_required]" value="0">
                                <input type="checkbox" name="mappings[{{ $index }}][is_required]" value="1" {{ $req ? 'checked' : '' }}>
                                @if($req)
                                    <span class="badge badge-danger" style="margin-left:8px;">Obrigatório</span>
                                @endif
                            </td>
                            <td>
                                <input type="text" name="mappings[{{ $index }}][default_value]" value="{{ is_array($m) ? ($m['default_value'] ?? '') : ($m->default_value ?? '') }}" class="form-control" placeholder="Valor padrão">
                            </td>
                            <td>
                                <input type="text" name="mappings[{{ $index }}][description]" value="{{ is_array($m) ? ($m['description'] ?? '') : ($m->description ?? '') }}" class="form-control" placeholder="Descrição do campo">
                            </td>
                            <td class="checkbox-wrapper">
                                @php $active = is_array($m) ? ($m['is_active'] ?? true) : ($m->is_active ?? true); @endphp
                                <input type="hidden" name="mappings[{{ $index }}][is_active]" value="0">
                                <input type="checkbox" name="mappings[{{ $index }}][is_active]" value="1" {{ $active ? 'checked' : '' }}>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-success">Salvar Mapeamentos</button>
            </div>
        </form>

        <div class="navigation" style="text-align:center; margin-top:30px;">
            <a href="{{ route('upload') }}" class="btn btn-primary">Ir para Upload</a>
        </div>
    </div>
</div>

<script>
    let availableColumns = [];
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('example-file');
        const fileName = document.getElementById('file-name');
        const uploadStatus = document.getElementById('upload-status');

        if (fileInput) {
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    fileName.textContent = `Arquivo selecionado: ${file.name}`;
                    fileName.style.display = 'block';
                    uploadExampleFile(file);
                }
            });
        }

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
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
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
            while (select.children.length > 1) { select.removeChild(select.lastChild); }
            availableColumns.forEach(column => {
                const option = document.createElement('option');
                option.value = column; option.textContent = column;
                if (column === currentValue) { option.selected = true; }
                select.appendChild(option);
            });
            select.addEventListener('change', updateRequiredFieldsHighlight);
        });
        updateRequiredFieldsHighlight();
    }

    function updateRequiredFieldsHighlight() {
        const rows = document.querySelectorAll('#mappings-tbody tr');
        rows.forEach(row => {
            const isRequiredInput = row.querySelector('input[name*="[is_required]"]:not([type="hidden"])');
            const excelColumnSelect = row.querySelector('.excel-column-select');
            const isRequired = isRequiredInput && isRequiredInput.checked;
            if (isRequired && excelColumnSelect && !excelColumnSelect.value) {
                row.style.backgroundColor = '#fff3cd';
                row.style.border = '2px solid #ffc107';
            } else {
                row.style.backgroundColor = '';
                row.style.border = '';
            }
        });
    }
</script>
</body>
</html>