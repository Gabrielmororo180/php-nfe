# PHP-NFe: API de Emissão de Nota Fiscal Eletrônica em Laravel (Arquitetura Hexagonal & SOLID)

API RESTful para emissão e cancelamento de Notas Fiscais Eletrônicas (NFe/NFCe), desenvolvida em Laravel 12 (PHP 8.2+) utilizando Arquitetura Hexagonal (Ports & Adapters), Domain-Driven Design (DDD) e os princípios SOLID.

---

> [!IMPORTANT]
> **DOCUMENTAÇÃO INTERATIVA DA API (SWAGGER UI)**
> Toda a documentação técnica dos endpoints, schemas JSON de requisição e respostas pode ser consultada e testada interativamente diretamente pelo navegador acessando:
> **`http://localhost:8000/api/documentation`**
>
> _(Para acessar localmente, inicie o servidor com `php artisan serve` e gere os arquivos de documentação executando `php artisan l5-swagger:generate`)._

---

## Principais Recursos

- **Regra de Negócio Isolada:** A camada Core/Domain é 100% PHP puro, livre de dependências de banco de dados, HTTP ou do próprio Laravel.
- **Integração Fiscal via NFePHP:** Utiliza `nfephp-org/sped-nfe` e `nfephp-org/sped-da` em PHP puro, eliminando qualquer dependência de binários nativos compilados em C (.so) em containers Docker.
- **Inversão de Dependências (DIP):** Troca simples de provedores (armazenamento local para S3 ou Gateway Fiscal) sem alterar nenhuma regra de negócio.
- **Suporte a Emitente CPF (Produtor Rural) e CNPJ:** Suporta emissão por CPF ou CNPJ com assinatura digital A1.
- **Tributação Flexível:** Suporte a CSOSN (Simples Nacional) e CSTs de ICMS (ex: 40 Isento), PIS (09) e COFINS (09).
- **Validação SEFAZ e Geração de DANFE:** Transmissão síncrona SOAP para a SEFAZ, anexação do protocolo de autorização `<protNFe>` e geração automática do DANFE em PDF.
- **Documentação Swagger UI:** Interface interativa OpenAPI 3.0 pronta para testes via navegador.

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
    │   ├── Http/Controllers/           # NFeController (HTTP Entrypoint & Swagger Attributes)
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

> [!TIP]
> Recomendo consultar a interface do **Swagger UI** em `http://localhost:8000/api/documentation` para executar testes interativos com preenchimento automático de schemas.

### 1. Emitir NFe / NFCe

- **URL:** `POST /api/nfe/emitir`
- **Header:** `Content-Type: application/json`
- **Payload de Exemplo (Dados Fictícios):**

```json
{
    "modelo": "55",
    "serie": 1,
    "numero": 101,
    "natureza_operacao": "Venda de Mercadoria",
    "valor_total": 150.0,
    "emitente": {
        "cnpj": "00000000000000",
        "razao_social": "EMPRESA EMITENTE EXEMPLO LTDA",
        "nome_fantasia": "EMPRESA TESTE",
        "inscricao_estadual": "000000000",
        "crt": "1",
        "endereco": {
            "logradouro": "RUA EXEMPLO",
            "numero": "100",
            "complemento": "SALA 1",
            "bairro": "CENTRO",
            "codigo_municipio": "3550308",
            "nome_municipio": "SAO PAULO",
            "uf": "SP",
            "cep": "01001000"
        }
    },
    "destinatario": {
        "cnpj_cpf": "11111111000111",
        "razao_social": "CLIENTE DESTINATARIO EXEMPLO LTDA",
        "indicador_ie": "9",
        "endereco": {
            "logradouro": "AVENIDA EXEMPLO",
            "numero": "200",
            "bairro": "BELA VISTA",
            "codigo_municipio": "3550308",
            "nome_municipio": "SAO PAULO",
            "uf": "SP",
            "cep": "01310000"
        }
    },
    "produtos": [
        {
            "codigo": "PROD-001",
            "descricao": "PRODUTO TESTE EXEMPLO",
            "ncm": "84713012",
            "cfop": "5102",
            "unidade_comercial": "UN",
            "quantidade_comercial": 1.0,
            "valor_unitario_comercial": 150.0,
            "valor_total_bruto": 150.0,
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

- **Resposta de Sucesso (Dados Fictícios):**

```json
{
    "sucesso": true,
    "chave_nfe": "35240800000000000000550010000001011000001010",
    "xml_path": "nfe/xml/35240800000000000000550010000001011000001010.xml",
    "pdf_path": "nfe/pdf/35240800000000000000550010000001011000001010.pdf"
}
```

---

### 2. Cancelar NFe

- **URL:** `POST /api/nfe/cancelar`
- **Header:** `Content-Type: application/json`
- **Payload de Exemplo (Dados Fictícios):**

```json
{
    "chave_nfe": "35240800000000000000550010000001011000001010",
    "protocolo": "135240001234567",
    "justificativa": "Cancelamento solicitado pelo cliente devido a erro no pedido"
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

5. **Gerar Documentação Swagger UI:**

    ```bash
    php artisan l5-swagger:generate
    ```

6. **Executar a Aplicação:**

    ```bash
    php artisan serve
    ```

7. **Acessar a Documentação Interativa:**
   Abra no seu navegador: `http://localhost:8000/api/documentation`

---

## Configuração do Certificado Digital A1 (.pfx / .p12)

### 1. Local do Certificado

Coloque o seu arquivo `.pfx` ou `.p12` no diretório `public/cert/` ou `storage/app/cert/` (diretórios ignorados no versionamento pelo `.gitignore`).

### 2. Configurar o `.env`:

```env
# 1 = Produção, 2 = Homologação (SEFAZ Testes)
NFE_ENVIRONMENT=2

# Dados da Empresa Emitente
NFE_COMPANY_NAME="EMPRESA EXEMPLO LTDA"
NFE_COMPANY_CNPJ="00000000000000"
NFE_COMPANY_UF="SP"

# Certificado Digital A1 (.pfx) e Senha
NFE_CERT_PATH="public/cert/cert_modern.pfx"
NFE_CERT_PASSWORD="sua_senha_do_certificado"
```

### 3. Armazenamento dos Arquivos Gerados

- **XMLs:** Gravados em `storage/app/private/nfe/xml/`
- **DANFE PDF:** Gravados em `storage/app/private/nfe/pdf/`

---
