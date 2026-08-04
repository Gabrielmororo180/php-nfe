# PHP-NFe: API de Emissão de Nota Fiscal Eletrônica em Laravel (Arquitetura Hexagonal & SOLID)

API RESTful para emissão e cancelamento de Notas Fiscais Eletrônicas (NFe/NFCe), desenvolvida em Laravel 12 (PHP 8.2+) utilizando Arquitetura Hexagonal (Ports & Adapters), Domain-Driven Design (DDD) e os princípios SOLID.

---

## Principais Recursos

- **Regra de Negócio Isolada:** A camada Core/Domain é 100% PHP puro, livre de dependências de banco de dados, HTTP ou do próprio Laravel.
- **Integração Fiscal via NFePHP:** Utiliza `nfephp-org/sped-nfe` e `nfephp-org/sped-da` em PHP puro, eliminando qualquer dependência de binários nativos compilados em C (.so) em containers Docker.
- **Inversão de Dependências (DIP):** Troca simples de provedores (armazenamento local para S3 ou Gateway Fiscal) sem alterar nenhuma regra de negócio.
- **Suporte a Emitente CPF (Produtor Rural) e CNPJ:** Suporta emissão por CPF ou CNPJ com assinatura digital A1.
- **Tributação Flexível:** Suporte a CSOSN (Simples Nacional) e CSTs de ICMS (ex: 40 Isento), PIS (09) e COFINS (09).
- **Validação SEFAZ e Geração de DANFE:** Transmissão síncrona SOAP para a SEFAZ, anexação do protocolo de autorização `<protNFe>` e geração automática do DANFE em PDF.

---

## Arquitetura do Projeto (Hexagonal / Ports & Adapters)

O projeto segue estritamente a separação entre o **Core** (Regra de Negócio Pura) e os **Adapters** (Infraestrutura):

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
└── Infrastructure/                     # Adaptadores Concretos & Conexões Externas
    ├── Primary/                        # Driving Adapters (Pontos de ENTRADA do Sistema)
    │   ├── Http/Controllers/           # NFeController (HTTP Entrypoint)
    │   ├── Http/Requests/              # EmitirNFeRequest, CancelarNFeRequest
    │   └── Routes/                     # api.php
    │
    └── Secondary/                      # Driven Adapters (Pontos de SAÍDA do Sistema)
        ├── Fiscal/                     # NFePhpFiscalAdapter (Integração NFePHP & SEFAZ SOAP)
        └── Storage/                    # LocalFileStorageAdapter (Gravação em Disco)
```

### Diferença entre Primary e Secondary Adapters

- **Primary Adapters (Driving / Entradas):** São os componentes que **iniciam** uma ação e chamam o sistema. Recebem estímulos externos (requisições HTTP, CLI, webhooks), validam os dados e invocam os Casos de Uso do Core (ex: `NFeController`, `EmitirNFeRequest`).
- **Secondary Adapters (Driven / Saídas):** São os componentes **chamados pelo sistema** para realizar ações no mundo externo. Implementam as interfaces (Outbound Ports) para se comunicar com a SEFAZ, serviços de arquivos e bancos de dados (ex: `NFePhpFiscalAdapter`, `LocalFileStorageAdapter`).

---

## Endpoints da API

### 1. Emitir NFe / NFCe
- **URL:** `POST /api/nfe/emitir`
- **Header:** `Content-Type: application/json`
- **Payload de Exemplo:**

```json
{
  "modelo": "55",
  "serie": 1,
  "numero": 101,
  "natureza_operacao": "Venda de Mercadoria",
  "valor_total": 150.00,
  "emitente": {
    "cnpj": "39395316349",
    "razao_social": "FRANSISCA MARIA GORETE MONTEIRO DE ALBUQUERQUE",
    "nome_fantasia": "GRANJA GAMELEIRA",
    "inscricao_estadual": "195933320",
    "crt": "1",
    "endereco": {
      "logradouro": "LOC SAIONARA - DATA CURRALINHO",
      "numero": "0",
      "complemento": "ZONA RURAL",
      "bairro": "ZONA RURAL",
      "codigo_municipio": "2200301",
      "nome_municipio": "Alto Longa",
      "uf": "PI",
      "cep": "64360000"
    }
  },
  "destinatario": {
    "cnpj_cpf": "05179979323",
    "razao_social": "NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL",
    "indicador_ie": "9",
    "endereco": {
      "logradouro": "RUA TESTE",
      "numero": "123",
      "bairro": "CENTRO",
      "codigo_municipio": "2200301",
      "nome_municipio": "Alto Longa",
      "uf": "PI",
      "cep": "64360000"
    }
  },
  "produtos": [
    {
      "codigo": "PROD-001",
      "descricao": "PRODUTO TESTE NFE",
      "ncm": "84713012",
      "cfop": "5102",
      "unidade_comercial": "UN",
      "quantidade_comercial": 1.0,
      "valor_unitario_comercial": 150.00,
      "valor_total_bruto": 150.00,
      "imposto": {
        "icms": {
          "cst": "40"
        },
        "pis": {
          "cst": "09"
        },
        "cofins": {
          "cst": "09"
        }
      }
    }
  ]
}
```

- **Resposta de Sucesso:**

```json
{
  "sucesso": true,
  "chave_nfe": "22260800039395316349550010000001011855080331",
  "xml_path": "nfe/xml/22260800039395316349550010000001011855080331.xml",
  "pdf_path": "nfe/pdf/22260800039395316349550010000001011855080331.pdf"
}
```

---

### 2. Cancelar NFe
- **URL:** `POST /api/nfe/cancelar`
- **Header:** `Content-Type: application/json`
- **Payload de Exemplo:**

```json
{
  "chave_nfe": "22260800039395316349550010000001011855080331",
  "protocolo": "122260000000001",
  "justificativa": "Cancelamento de teste por emissao indevida"
}
```

---

## Requisitos e Instalação Local

1. **Clonar o Repositório:**
   ```bash
   git clone https://github.com/usuario/php-nfe.git
   cd php-nfe
   ```

2. **Instalar Dependências:**
   ```bash
   composer install
   ```

3. **Instalar a Extensão SOAP (Linux):**
   ```bash
   sudo apt-get install -y php-soap
   ```

4. **Configurar Ambiente:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Executar a Aplicação:**
   ```bash
   php artisan serve
   ```

---

## Configuração do Certificado Digital A1 (.pfx / .p12)

### 1. Local do Certificado
Coloque o seu arquivo `.pfx` ou `.p12` no diretório `public/cert/` ou `storage/app/cert/` (diretórios ignorados no versionamento pelo `.gitignore`).

### 2. Configurar o `.env`:
```env
# 1 = Produção, 2 = Homologação (SEFAZ Testes)
NFE_ENVIRONMENT=2

# Dados da Empresa / Produtor Rural Emitente
NFE_COMPANY_NAME="FRANSISCA MARIA GORETE MONTEIRO DE ALBUQUERQUE"
NFE_COMPANY_CNPJ="39395316349"
NFE_COMPANY_UF="PI"

# Certificado Digital A1 (.pfx) e Senha
NFE_CERT_PATH="public/cert/cert_modern.pfx"
NFE_CERT_PASSWORD="sua_senha_do_certificado"
```

### 3. Armazenamento dos Arquivos Gerados
- **XMLs:** Gravados em `storage/app/private/nfe/xml/`
- **DANFE PDF:** Gravados em `storage/app/private/nfe/pdf/`

---

## Documentação Complementar

- [`AGENT.md`](file:///home/gabriel/Documents/git/php-nfe/AGENT.md) — Diretrizes arquiteturais e regras para desenvolvimento.
- [`CONCEITOS_ARQUITETURA.md`](file:///home/gabriel/Documents/git/php-nfe/CONCEITOS_ARQUITETURA.md) — Guia didático explicando a separação de Entidades, DTOs, Ports, Primary Adapters e Secondary Adapters.
- [`.system/PLANO_MIGRACAO.md`](file:///home/gabriel/Documents/git/php-nfe/.system/PLANO_MIGRACAO.md) — Roteiro de migração e histórico de etapas concluídas.
