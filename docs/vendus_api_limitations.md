# Limitações da API Vendus

## Variações de Produtos

Após uma investigação completa da API Vendus v1.1, foram identificadas as seguintes limitações em relação ao suporte para variações de produtos:

### Limitações Confirmadas

1. **Não existe endpoint `/variants/`**
   - Tentativas de acessar este endpoint resultam em erro 404: "Endpoint variants (1) not found"

2. **Campo `variants` não é permitido no endpoint de produtos**
   - Tentativas de enviar produtos com o campo `variants` resultam em erro 403
   - Mensagem de erro: "O campo variants não é permitido"

3. **Campos permitidos na API de produtos**
   ```
   reference, barcode, supplier_code, title, description, include_description, 
   supply_price, gross_price, prices, unit_id, type_id, class_id, lot_control, 
   stock_control, stock_type, tax_id, tax_exemption, tax_exemption_law, 
   category_id, brand_id, image, status
   ```

### Solução Implementada

Devido a estas limitações, o sistema atual implementa a seguinte solução:

1. **Cada variação é enviada como um produto separado**
   - Não há alternativa na API atual para enviar variações de forma agrupada

2. **Nomenclatura padronizada para identificar variações**
   - Formato: `"[Produto] - Tamanho [X] (Var. Y/Z)"`
   - Exemplo: "Camiseta Básica - Tamanho M (Var. 1/3)"

3. **Referências únicas para cada variação**
   - Cada variação recebe um código de referência único
   - Mantém rastreabilidade entre o sistema e a plataforma Vendus

4. **Códigos de barras específicos**
   - Cada variação mantém seu próprio código de barras
   - Facilita a identificação no ponto de venda

## Testes Realizados

### Teste 1: Endpoint `/variants/`
- **Resultado**: Erro 404 - Endpoint não existe

### Teste 2: Campo `variants` vazio no payload
- **Resultado**: Erro 403 - Campo não permitido

### Teste 3: Campo `variants` com dados no payload
- **Resultado**: Erro 403 - Campo não permitido

## Conclusão

A API Vendus v1.1 **não suporta nativamente o conceito de variações de produtos**. A solução atual, que envia cada variação como um produto separado com nomenclatura padronizada, é a abordagem mais eficiente possível dentro das limitações da API.

---

*Documentação criada em: [DATA]*