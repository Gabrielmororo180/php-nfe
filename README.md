# PHP-NFe: API de Emissão de Nota Fiscal Eletrônica em Laravel (Arquitetura Hexagonal & SOLID)

API RESTful para emissão e cancelamento de Notas Fiscais Eletrônicas (NFe/NFCe), desenvolvida em Laravel 12 (PHP 8.2+) utilizando Arquitetura Hexagonal (Ports & Adapters), Domain-Driven Design (DDD) e os princípios SOLID.

---

## Principais Recursos

- Regra de Negócio Isolada: A camada Core/Domain é 100% PHP puro, livre de dependências de banco de dados, HTTP ou do próprio Laravel.
- Integração Fiscal via NFePHP: Utiliza nfephp-org/sped-nfe e nfephp-org/sped-da (PHP puro), eliminando problemas de compilação de binários nativos C (.so) em containers Docker.
- Inversão de Dependências (DIP): Troca simples de provedores (ex: alterar de armazenamento local para AWS S3 ou substituir a biblioteca fiscal) sem alterar a regra de negócio.
- Endpoints Prontos para Uso: Interfaces de entrada HTTP para emissão e cancelamento com validações estritas via Form Requests.

---

## Arquitetura do Projeto

```text
app/
├── Core/                               # Regra de Negócio Pura (PHP 8.2+)
│   ├── Domain/                         # Entidades, Value Objects e Invariantes do Negócio
│   │   ├── Entities/                   # NFe, Produto, Emitente, Destinatario, Impostos, etc.
│   │   └── Exceptions/                 # Exceções do Domínio
│   │
│   └── Application/                    # Casos de Uso (Actions) & Portas (Interfaces)
│       ├── Ports/Outbound/             # Interfaces de Saída (NFeFiscalGateway, FileStorage)
│       ├── DTOs/                       # Objetos de Transferência de Dados Imutáveis
│       └── UseCases/                   # EmitirNFeUseCase, CancelarNFeUseCase
│
└── Infrastructure/                     # Adapters Concretos & Laravel Glue
    ├── Primary/                        # Driving Adapters (NFeController, Requests, Routes)
    └── Secondary/                      # Driven Adapters (NFePhpFiscalAdapter, LocalStorage)
```

---

## Endpoints da API

### 1. Emitir NFe / NFCe
- URL: `POST /api/nfe/emitir`
- Header: `Content-Type: application/json`
- Payload de Exemplo:

```json
{
  "modelo": "55",
  "serie": 1,
  "numero": 101,
  "natureza_operacao": "Venda de Mercadoria",
  "valor_total": 150.00,
  "emitente": {
    "cnpj": "12345678000195",
    "razao_social": "Empresa Emitente LTDA",
    "nome_fantasia": "Empresa Teste",
    "inscricao_estadual": "123456789",
    "crt": "1",
    "endereco": {
      "logradouro": "Rua das Flores",
      "numero": "100",
      "bairro": "Centro",
      "codigo_municipio": "3550308",
      "nome_municipio": "São Paulo",
      "uf": "SP",
      "cep": "01001000"
    }
  },
  "destinatario": {
    "cnpj_cpf": "98765432000100",
    "razao_social": "Cliente Destinatario LTDA",
    "endereco": {
      "logradouro": "Av Paulista",
      "numero": "1000",
      "bairro": "Bela Vista",
      "codigo_municipio": "3550308",
      "nome_municipio": "São Paulo",
      "uf": "SP",
      "cep": "01310100"
    }
  },
  "produtos": [
    {
      "codigo": "PROD-001",
      "descricao": "Item de Teste NFe",
      "ncm": "84713012",
      "cfop": "5102",
      "unidade_comercial": "UN",
      "quantidade_comercial": 1.0,
      "valor_unitario_comercial": 150.00,
      "valor_total_bruto": 150.00,
      "icms_cst": "102"
    }
  ]
}
```

### 2. Cancelar NFe
- URL: `POST /api/nfe/cancelar`
- Header: `Content-Type: application/json`
- Payload de Exemplo:

```json
{
  "id_lote": "1",
  "c_orgao": "35",
  "cnpj": "12345678000195",
  "chave_nfe": "35240812345678000195550010000001011000001010",
  "data_hora_evento": "2026-08-04T13:00:00-03:00",
  "numero_protocolo": "135240001234567",
  "justificativa": "Cancelamento solicitado pelo cliente devido a erro no pedido"
}
```

---

## Requisitos e Instalação Local

1. Clonar o Repositório:
   ```bash
   git clone https://github.com/usuario/php-nfe.git
   cd php-nfe
   ```

2. Instalar Dependências:
   ```bash
   composer install
   ```

3. Configurar Ambiente:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Executar a Aplicação:
   ```bash
   php artisan serve
   ```

---

## Configuração do Certificado Digital A1 (.pfx) e Testes

### 1. Onde colocar o Certificado Digital?
Coloque o seu arquivo `.pfx` ou `.p12` no diretório de storage da aplicação (ex: `storage/app/cert/certificado.pfx`). Este diretório já se encontra ignorado no `.gitignore`.

### 2. Configurar o `.env`:
```env
# 1 = Produção, 2 = Homologação
NFE_ENVIRONMENT=2

# Dados da Empresa Emitente
NFE_COMPANY_NAME="Sua Empresa LTDA"
NFE_COMPANY_CNPJ="12345678000195"
NFE_COMPANY_UF="SP"

# Certificado Digital A1 (.pfx)
NFE_CERT_PATH="/caminho/absoluto/ou/relativo/para/certificado.pfx"
NFE_CERT_PASSWORD="sua_senha_do_certificado"
```

### 3. Modos de Teste:
- Sem Certificado (Modo Mock Local): Se a variável `NFE_CERT_PATH` estiver vazia, o sistema gera a estrutura do XML da NFe v4.00 e simula a transmissão, salvando os arquivos em `storage/app/nfe/xml/`. Útil para validar contratos da API HTTP localmente.
- Com Certificado (Homologação Real SEFAZ): Com a chave `NFE_CERT_PATH` e `NFE_CERT_PASSWORD` preenchidas, a aplicação assina digitalmente a nota com o certificado A1, transmite via SOAP para a SEFAZ de homologação, gera o DANFE em PDF e salva os arquivos em `storage/app/nfe/`.

---

## Documentação Complementar

- [`AGENT.md`](file:///home/gabriel/Documents/git/php-nfe/AGENT.md) — Diretrizes arquiteturais e regras para desenvolvimento.
- [`CONCEITOS_ARQUITETURA.md`](file:///home/gabriel/Documents/git/php-nfe/CONCEITOS_ARQUITETURA.md) — Guia didático explicando o papel de Entidades, DTOs, Ports e Adapters.
- [`.system/PLANO_MIGRACAO.md`](file:///home/gabriel/Documents/git/php-nfe/.system/PLANO_MIGRACAO.md) — Roteiro de migração e histórico de etapas.
