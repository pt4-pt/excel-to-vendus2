# Sistema de Upload de Produtos - Integração Vendus

Sistema web completo em Laravel 11 com TailwindCSS para upload de arquivos Excel e integração com a API da Vendus.

## 🚀 Funcionalidades

- ✅ Upload de arquivos Excel (.xlsx, .xls)
- ✅ Leitura e processamento automático dos dados
- ✅ Agrupamento de produtos por "Ref. Vendus"
- ✅ Validação completa dos dados
- ✅ Integração com API da Vendus
- ✅ Pré-visualização dos produtos antes do envio
- ✅ Barra de progresso animada
- ✅ Relatório detalhado dos resultados
- ✅ Log de erros automático
- ✅ Interface moderna e responsiva

## 📋 Requisitos

- PHP 8.3+
- Composer
- Node.js & NPM
- Laravel 11

## 🛠️ Instalação

1. **Clone o projeto** (se aplicável) ou use o diretório atual
2. **Instale as dependências PHP:**
   ```bash
   composer install
   ```

3. **Instale as dependências Node.js:**
   ```bash
   npm install
   ```

4. **Configure o arquivo .env:**
   ```env
   VENDUS_API_KEY=sua_chave_api_aqui
   VENDUS_API_URL=https://www.vendus.pt/ws/v1.1/products
   ```

5. **Compile os assets:**
   ```bash
   npm run build
   ```

6. **Inicie o servidor:**
   ```bash
   php artisan serve
   ```

7. **Acesse o sistema:**
   - URL: http://127.0.0.1:8000/upload

## 📊 Formato do Excel

O arquivo Excel deve conter as seguintes colunas (na primeira linha):

| Ref. Vendus | Cat | Nome | Size | UPC No. | Cost | PVP |
|-------------|-----|------|------|---------|------|-----|
| 01489-NTSGB | Camisas | BRIXTON Builders Bowery Perf Flannel | M | 199027005829 | 44.00 | 110.00 |
| 01489-NTSGB | Camisas | BRIXTON Builders Bowery Perf Flannel | L | 199027005836 | 44.00 | 110.00 |

### Regras:
- Cada linha representa uma variação do produto (tamanho)
- Produtos com a mesma "Ref. Vendus" são agrupados automaticamente
- Todos os campos são obrigatórios
- Preços devem ser numéricos

## 🔧 Estrutura do Projeto

```
app/
├── Http/
│   ├── Controllers/
│   │   └── UploadController.php      # Controller principal
│   └── Services/
│       └── VendusService.php         # Serviço de integração com API
resources/
└── views/
    └── upload.blade.php              # Interface do usuário
routes/
└── web.php                           # Rotas da aplicação
```

## 🎯 Como Usar

1. **Acesse a página de upload:** `/upload`
2. **Selecione o arquivo Excel** (arrastar e soltar ou clicar)
3. **Pré-visualize os produtos** (opcional)
4. **Clique em "Enviar Produtos"**
5. **Acompanhe o progresso** na barra animada
6. **Visualize o relatório** com os resultados

## 📝 Funcionalidades Avançadas

### Validações Implementadas
- ✅ Formato de arquivo (apenas .xlsx, .xls)
- ✅ Tamanho máximo (10MB)
- ✅ Campos obrigatórios
- ✅ Validação de preços numéricos
- ✅ Validação de códigos de barras

### Tratamento de Erros
- ✅ Log automático em `storage/logs/vendus_errors.log`
- ✅ Mensagens de erro detalhadas
- ✅ Prevenção de duplicações
- ✅ Timeout de requisições (30s)

### Performance
- ✅ Aviso para arquivos com +100 produtos
- ✅ Processamento otimizado
- ✅ Interface responsiva
- ✅ Feedback visual em tempo real

## 🔐 Configuração da API

No arquivo `.env`, configure:

```env
VENDUS_API_KEY=sua_chave_api_vendus
VENDUS_API_URL=https://www.vendus.pt/ws/v1.1/products
```

## 📋 Exemplo de Payload JSON

O sistema gera automaticamente o seguinte formato para a API:

```json
{
  "reference": "01489-NTSGB",
  "title": "BRIXTON Builders Bowery Perf Flannel – NIGHT SAGE/BLACK",
  "class_name": "Camisas",
  "supply_price": "44.00",
  "gross_price": "110.00",
  "variants": [
    {
      "variant": {
        "title": "Size"
      },
      "product_variants": [
        {
          "text": "M",
          "barcode": "199027005829",
          "code": "01489-NTSGB-M",
          "price": "110.00"
        }
      ]
    }
  ]
}
```

## 🐛 Troubleshooting

### Erro de Compilação CSS
Se encontrar erros relacionados ao TailwindCSS:
```bash
npm install -D @tailwindcss/postcss
npm run build
```

### Erro de Permissões
Certifique-se que o diretório `storage/logs` tem permissões de escrita.

### Erro de API
Verifique se a `VENDUS_API_KEY` está configurada corretamente no `.env`.

## 🎨 Tecnologias Utilizadas

- **Backend:** Laravel 11, PHP 8.3
- **Frontend:** TailwindCSS, JavaScript Vanilla
- **Excel:** Maatwebsite/Excel
- **HTTP Client:** Laravel HTTP Client
- **Build:** Vite

## 📞 Suporte

Para dúvidas ou problemas:
1. Verifique os logs em `storage/logs/`
2. Consulte a documentação da API Vendus
3. Teste com arquivos Excel menores primeiro

---

**Desenvolvido com ❤️ usando Laravel 11 + TailwindCSS**
