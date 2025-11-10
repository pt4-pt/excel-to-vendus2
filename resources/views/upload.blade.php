<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Upload - Vendus Integration</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <img src="{{ asset('images/logo.svg') }}" alt="Logo Vendus Integration" class="w-16 h-16 mx-auto mb-4">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                Importar para Vendus
            </h1>
            <p class="text-lg text-gray-600">Importe Produtos (Excel) ou Faturas (Excel)</p>
            
            <!-- Link para Mapeamento -->
            <div class="mt-4">
                <a id="mappingLink" href="{{ route('field-mappings.index') }}" data-products-url="{{ route('field-mappings.index') }}" data-invoices-url="{{ route('document-mappings.index') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors duration-200">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <span id="mappingLinkText">Configurar Mapeamento de Campos</span>
                </a>
            </div>
        </div>

        <!-- Main Card -->
        <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Upload Section -->
            <div class="p-6 border-b border-gray-200">
                <h2 id="uploadHeader" class="text-2xl font-semibold text-gray-800 mb-4">Upload do Arquivo Excel</h2>
                
                <form id="uploadForm" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <!-- Tipo de Importação -->
                    <div class="mb-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de importação</label>
                        <div class="flex items-center space-x-6">
                            <label class="inline-flex items-center">
                                <input type="radio" name="import_type" value="products" class="form-radio text-blue-600" checked>
                                <span class="ml-2 text-gray-800">Produtos</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="import_type" value="invoices" class="form-radio text-blue-600">
                                <span class="ml-2 text-gray-800">Faturas</span>
                            </label>
                        </div>
                    </div>
                    <div class="flex items-center justify-center w-full">
                        <label for="excel_file" class="flex flex-col items-center justify-center w-full h-64 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors duration-200">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6" id="dropZone">
                                <svg class="w-10 h-10 mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <p class="mb-2 text-sm text-gray-500">
                                    <span class="font-semibold">Clique para fazer upload</span> ou arraste o arquivo aqui
                                </p>
                                <p id="fileHint" class="text-xs text-gray-500">Apenas arquivos Excel (.xlsx, .xls) até 10MB</p>
                            </div>
                            <input id="excel_file" name="excel_file" type="file" class="hidden" accept=".xlsx,.xls" />
                        </label>
                    </div>

                    <!-- File Info -->
                    <div id="fileInfo" class="hidden bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-blue-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
                            </svg>
                            <span id="fileName" class="text-sm font-medium text-blue-800"></span>
                            <span id="fileSize" class="text-sm text-blue-600 ml-2"></span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex space-x-4">
                        <button type="button" id="previewBtn" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-lg transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            Pré-visualizar Produtos
                        </button>
                        <button type="submit" id="submitBtn" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-6 rounded-lg transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 12l2 2 4-4"></path>
                            </svg>
                            <span id="submitLabel">Enviar Produtos</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Progress Bar -->
            <div id="progressSection" class="hidden p-6 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between mb-2">
                    <span id="progressLabel" class="text-sm font-medium text-gray-700">Processando produtos...</span>
                    <span id="progressText" class="text-sm text-gray-500">0%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div id="progressBar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
            </div>

            <!-- Preview Section -->
            <div id="previewSection" class="hidden p-6 border-b border-gray-200">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Pré-visualização dos Produtos</h3>
                <div id="previewContent"></div>
            </div>

            <!-- Results Section -->
            <div id="resultsSection" class="hidden p-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Resultado do Processamento</h3>
                <div id="resultsContent"></div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('uploadForm');
            const fileInput = document.getElementById('excel_file');
            const fileInfo = document.getElementById('fileInfo');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            const previewBtn = document.getElementById('previewBtn');
            const submitBtn = document.getElementById('submitBtn');
            const submitLabel = document.getElementById('submitLabel');
            const progressSection = document.getElementById('progressSection');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const progressLabel = document.getElementById('progressLabel');
            const previewSection = document.getElementById('previewSection');
            const previewContent = document.getElementById('previewContent');
            const resultsSection = document.getElementById('resultsSection');
            const resultsContent = document.getElementById('resultsContent');
            const fileHint = document.getElementById('fileHint');
            const uploadHeader = document.getElementById('uploadHeader');
            const importTypeInputs = document.querySelectorAll('input[name="import_type"]');
            const mappingLink = document.getElementById('mappingLink');
            const mappingLinkText = document.getElementById('mappingLinkText');

            function getImportType() {
                const sel = Array.from(importTypeInputs).find(i => i.checked);
                return sel ? sel.value : 'products';
            }

            function updateMode() {
                const type = getImportType();
                if (type === 'products') {
                    fileInput.accept = '.xlsx,.xls';
                    fileHint.textContent = 'Apenas arquivos Excel (.xlsx, .xls) até 10MB';
                    uploadHeader.textContent = 'Upload do Arquivo Excel';
                    previewBtn.disabled = !(fileInput.files && fileInput.files[0]);
                    previewBtn.textContent = 'Pré-visualizar Produtos';
                    submitLabel.textContent = 'Enviar Produtos';
                    progressLabel.textContent = 'Processando produtos...';
                    // Link de mapeamento para produtos
                    if (mappingLink) {
                        mappingLink.href = mappingLink.dataset.productsUrl;
                    }
                    if (mappingLinkText) {
                        mappingLinkText.textContent = 'Configurar Mapeamento de Produtos';
                    }
                } else {
                    fileInput.accept = '.xlsx,.xls';
                    fileHint.textContent = 'Apenas arquivos Excel (.xlsx, .xls) até 10MB';
                    uploadHeader.textContent = 'Upload do Arquivo Excel';
                    previewBtn.disabled = !(fileInput.files && fileInput.files[0]);
                    previewBtn.textContent = 'Pré-visualizar Faturas';
                    submitLabel.textContent = 'Enviar Faturas';
                    progressLabel.textContent = 'Processando faturas...';
                    // Link de mapeamento para faturas
                    if (mappingLink) {
                        mappingLink.href = mappingLink.dataset.invoicesUrl;
                    }
                    if (mappingLinkText) {
                        mappingLinkText.textContent = 'Configurar Mapeamento de Faturas';
                    }
                }
            }

            importTypeInputs.forEach(i => i.addEventListener('change', updateMode));
            updateMode();

            // File input change handler
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    fileName.textContent = file.name;
                    fileSize.textContent = `(${(file.size / 1024 / 1024).toFixed(2)} MB)`;
                    fileInfo.classList.remove('hidden');
                    previewBtn.disabled = false;
                    submitBtn.disabled = false;
                } else {
                    fileInfo.classList.add('hidden');
                    previewBtn.disabled = true;
                    submitBtn.disabled = true;
                }
            });

            // Drag and drop handlers
            const dropZone = document.getElementById('dropZone').parentElement;
            
            dropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                dropZone.classList.add('border-blue-400', 'bg-blue-50');
            });

            dropZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                dropZone.classList.remove('border-blue-400', 'bg-blue-50');
            });

            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                dropZone.classList.remove('border-blue-400', 'bg-blue-50');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    fileInput.dispatchEvent(new Event('change'));
                }
            });

            // Preview button handler (produtos e faturas)
            previewBtn.addEventListener('click', function() {
                const type = getImportType();
                const formData = new FormData();
                formData.append('excel_file', fileInput.files[0]);
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

                const url = type === 'products' ? '/upload/preview' : '/upload/preview-invoices';

                fetch(url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (type === 'products') {
                            showProductPreview(data.preview, data.total_products);
                        } else {
                            showInvoicePreview(data.preview, data.total_invoices);
                        }
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    showAlert('Erro ao gerar pré-visualização: ' + error.message, 'error');
                });
            });

            // Form submit handler
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(form);
                // Assegura que o tipo de importação é enviado
                formData.set('import_type', getImportType());
                
                // Show progress
                progressSection.classList.remove('hidden');
                previewSection.classList.add('hidden');
                resultsSection.classList.add('hidden');
                
                // Simulate progress (since we don't have real-time progress from server)
                let progress = 0;
                const progressInterval = setInterval(() => {
                    progress += Math.random() * 15;
                    if (progress > 90) progress = 90;
                    updateProgress(progress);
                }, 500);

                fetch('/upload', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    clearInterval(progressInterval);
                    updateProgress(100);
                    
                    setTimeout(() => {
                        progressSection.classList.add('hidden');
                        showResults(data);
                    }, 1000);
                })
                .catch(error => {
                    clearInterval(progressInterval);
                    progressSection.classList.add('hidden');
                    showAlert('Erro no processamento: ' + error.message, 'error');
                });
            });

            function updateProgress(percent) {
                progressBar.style.width = percent + '%';
                progressText.textContent = Math.round(percent) + '%';
            }

            function showProductPreview(preview, totalProducts) {
                let html = `
                    <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-blue-800 font-medium">Total de produtos encontrados: ${totalProducts}</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-auto border-collapse border border-gray-300">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="border border-gray-300 px-4 py-2 text-left text-sm font-medium text-gray-700">Referência</th>
                                    <th class="border border-gray-300 px-4 py-2 text-left text-sm font-medium text-gray-700">Nome</th>
                                    <th class="border border-gray-300 px-4 py-2 text-left text-sm font-medium text-gray-700">Categoria</th>
                                    <th class="border border-gray-300 px-4 py-2 text-left text-sm font-medium text-gray-700">Nº Variações</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                preview.forEach((product, index) => {
                    html += `
                        <tr class="${index % 2 === 0 ? 'bg-white' : 'bg-gray-50'}">
                            <td class="border border-gray-300 px-4 py-2 text-sm text-gray-600">${product.reference}</td>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-gray-600">${product.name}</td>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-gray-600">${product.category}</td>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-gray-600">${product.variants_count}</td>
                        </tr>
                    `;
                });

                html += `
                            </tbody>
                        </table>
                    </div>
                `;

                previewContent.innerHTML = html;
                previewSection.classList.remove('hidden');
            }

            function showInvoicePreview(preview, totalInvoices) {
                let html = `
                    <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-blue-800 font-medium">Total de faturas encontradas: ${totalInvoices}</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-auto border-collapse border border-gray-300">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="border border-gray-300 px-4 py-2 text-left text-sm font-medium text-gray-700">Chave</th>
                                    <th class="border border-gray-300 px-4 py-2 text-left text-sm font-medium text-gray-700">Cliente</th>
                                    <th class="border border-gray-300 px-4 py-2 text-left text-sm font-medium text-gray-700">NIF</th>
                                    <th class="border border-gray-300 px-4 py-2 text-left text-sm font-medium text-gray-700">Série</th>
                                    <th class="border border-gray-300 px-4 py-2 text-left text-sm font-medium text-gray-700">Data</th>
                                    <th class="border border-gray-300 px-4 py-2 text-left text-sm font-medium text-gray-700">Itens</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                preview.forEach((inv, index) => {
                    html += `
                        <tr class="${index % 2 === 0 ? 'bg-white' : 'bg-gray-50'}">
                            <td class="border border-gray-300 px-4 py-2 text-sm text-gray-600">${inv.key}</td>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-gray-600">${inv.customer_name || ''}</td>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-gray-600">${inv.customer_nif || ''}</td>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-gray-600">${inv.series || ''}</td>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-gray-600">${inv.date || ''}</td>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-gray-600">${inv.items_count}</td>
                        </tr>
                    `;
                });

                html += `
                            </tbody>
                        </table>
                    </div>
                `;

                previewContent.innerHTML = html;
                previewSection.classList.remove('hidden');
            }

            function showResults(data) {
                let html = '';

                if (data.success) {
                    if (data.document_id || (data.document && data.document.id)) {
                        const docId = data.document_id || (data.document ? data.document.id : '');
                        html += `
                            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                                <p class="text-green-800 font-medium">${data.message || 'Fatura enviada com sucesso'}</p>
                                ${docId ? `<p class="text-green-700 text-sm">ID do documento: ${docId}</p>` : ''}
                            </div>
                        `;
                    } else {
                        html += `
                            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                                <p class="text-green-800 font-medium">${data.message}</p>
                                ${typeof data.total_products !== 'undefined' ? `<p class="text-green-700 text-sm">Total de produtos processados: ${data.total_products}</p>` : ''}
                            </div>
                        `;
                    }

                    if (data.results && data.results.length > 0) {
                        html += '<div class="space-y-2">';
                        
                        data.results.forEach(result => {
                            let bgColor, textColor;
                            
                            switch(result.type) {
                                case 'success':
                                    bgColor = 'bg-green-50 border-green-200';
                                    textColor = 'text-green-800';
                                    break;
                                case 'error':
                                    bgColor = 'bg-red-50 border-red-200';
                                    textColor = 'text-red-800';
                                    break;
                                case 'warning':
                                    bgColor = 'bg-yellow-50 border-yellow-200';
                                    textColor = 'text-yellow-800';
                                    break;
                                case 'skipped':
                                    bgColor = 'bg-gray-50 border-gray-200';
                                    textColor = 'text-gray-800';
                                    break;
                                default:
                                    bgColor = 'bg-blue-50 border-blue-200';
                                    textColor = 'text-blue-800';
                            }

                            html += `
                                <div class="p-3 ${bgColor} border rounded-lg">
                                    <div class="flex items-start">
                                        <div class="flex-1">
                                            <p class="${textColor} font-medium">
                                                ${result.reference ? `${result.reference}: ` : ''}${result.message}
                                            </p>
                                            ${result.variants_count ? `<p class="${textColor} text-sm opacity-75">Variações: ${result.variants_count}</p>` : ''}
                                            ${result.status_code ? `<p class="${textColor} text-sm opacity-75">Status: ${result.status_code}</p>` : ''}
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        
                        html += '</div>';
                    }
                } else {
                    html += `
                        <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                            <p class="text-red-800 font-medium">${data.message}</p>
                        </div>
                    `;
                }

                resultsContent.innerHTML = html;
                resultsSection.classList.remove('hidden');
            }

            function showAlert(message, type = 'info') {
                const alertColors = {
                    'success': 'bg-green-50 border-green-200 text-green-800',
                    'error': 'bg-red-50 border-red-200 text-red-800',
                    'warning': 'bg-yellow-50 border-yellow-200 text-yellow-800',
                    'info': 'bg-blue-50 border-blue-200 text-blue-800'
                };

                const alertDiv = document.createElement('div');
                alertDiv.className = `fixed top-4 right-4 p-4 border rounded-lg ${alertColors[type]} z-50 max-w-md`;
                alertDiv.innerHTML = `
                    <div class="flex items-center justify-between">
                        <p class="font-medium">${message}</p>
                        <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-lg">&times;</button>
                    </div>
                `;

                document.body.appendChild(alertDiv);

                setTimeout(() => {
                    if (alertDiv.parentElement) {
                        alertDiv.remove();
                    }
                }, 5000);
            }
        });
    </script>
</body>
</html>