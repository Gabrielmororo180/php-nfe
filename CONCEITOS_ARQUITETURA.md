# Guia da Arquitetura Hexagonal: Entidades, DTOs, Ports e Adapters

Este guia explica detalhadamente o papel, a necessidade e a função de cada componente da Arquitetura Hexagonal (Ports & Adapters) e dos princípios SOLID no projeto php-nfe.

---

## 1. O Problema que Estamos Resolvendo

### Arquitetura Tradicional (MVC Acoplado)
Em projetos tradicionais, a regra de negócio fica espalhada entre Controllers HTTP e Models do Eloquent:

```text
[ Requisição HTTP ] ──► [ Controller ] ──► [ Eloquent Model ] ──► [ Banco de Dados ]
                                 │
                                 └──► [ Biblioteca SEFAZ (Direta) ]
```

Problemas dessa abordagem:
1. Acoplamento Máximo: Se a SEFAZ mudar a biblioteca, você precisa alterar a Controller e o banco.
2. Impossível de Testar: Para testar uma simples emissão, você precisa de banco de dados rodando e conexão com a internet.
3. Falta de Padronização: Reutilizar a regra de emissão em um Comando Artisan ou Fila (Queue) exige duplicar código ou criar helpers confusos.

---

### Arquitetura Hexagonal (Ports & Adapters)
Na Arquitetura Hexagonal, a regra de negócio fica no centro, completamente isolada do mundo externo:

```text
                                   ┌───────────────────────────────────┐
                                   │           CORE (PHP PURO)         │
                                   │                                   │
┌─────────────────────────┐        │   ┌───────────────────────────┐   │        ┌─────────────────────────┐
│ Primary Adapters        │        │   │  Application (Use Cases)  │   │        │ Secondary Adapters      │
│ (Entrada)               │        │   └─────────────┬─────────────┘   │        │ (Saída)                 │
│                         │        │                 │                 │        │                         │
│  - Controllers HTTP     ├───────►│  [Ports In]     │    [Ports Out]  ├───────►│  - NFePHP Adapter       │
│  - Comandos Artisan     │ (DTOs) │  (Interfaces)   ▼    (Interfaces) │ (DTOs) │  - Storage Adapter      │
│  - Event Listeners      │        │   ┌───────────────────────────┐   │        │  - Eloquent Repositories│
└─────────────────────────┘        │   │     Domain (Entities)     │   │        └─────────────────────────┘
                                   │   └───────────────────────────┘   │
                                   └───────────────────────────────────┘
```

---

## 2. Detalhamento de Cada Componente

---

### 1. Entidades de Domínio (Entities)

#### O que são?
As Entidades são o coração do sistema. Elas representam os conceitos e regras do negócio no mundo real (ex: o que é uma Nota Fiscal, o que é um Produto).

#### Por que existem?
Garantem que um objeto do negócio jamais exista em um estado inválido (Invariantes de Domínio).

#### Exemplos no Projeto:
- [`NFe.php`](file:///home/gabriel/Documents/git/php-nfe/app/Core/Domain/Entities/NFe.php): Valida se há pelo menos 1 produto e se o valor total é positivo.
- [`EventoCancelamento.php`](file:///home/gabriel/Documents/git/php-nfe/app/Core/Domain/Entities/EventoCancelamento.php): Valida se a chave tem 44 dígitos e a justificativa no mínimo 15 caracteres.

Regra de Ouro: Entidades usam PHP 8.2+ Puro. Não sabem o que é Laravel, MySQL, JSON ou HTTP.

---

### 2. DTOs (Data Transfer Objects)

#### O que são?
DTOs são objetos simples de valor imutável criados exclusivamente para transportar dados entre as camadas.

#### Por que não usar a $request da Controller ou as Entities diretamente?
1. Isolamento de Contrato: Se o nome do campo JSON enviado pelo frontend mudar de cnpj_destinatario para tax_id, você altera apenas a Controller/DTO, sem quebrar a regra de negócio.
2. Tipagem Forte: O DTO garante tipos estritos (string, float, int), prevenindo erros de execução.

#### Exemplos no Projeto:
- [`EmitirNFeInputDto.php`](file:///home/gabriel/Documents/git/php-nfe/app/Core/Application/DTOs/EmitirNFeInputDto.php): Transporta os dados do payload HTTP para o Caso de Uso.
- [`EmitirNFeOutputDto.php`](file:///home/gabriel/Documents/git/php-nfe/app/Core/Application/DTOs/EmitirNFeOutputDto.php): Retorna o resultado compilado da emissão (sucesso, chaveNFe, xmlPath, pdfPath).

---

### 3. Ports (Portas / Interfaces)

#### O que são?
Ports são interfaces PHP que funcionam como tomadas de energia. Elas especificam O QUE a aplicação precisa, sem se importar com COMO será feito.

#### Por que usamos Ports? (Inversão de Dependência - DIP)
Imagine um notebook: ele possui uma porta USB-C. Você pode conectar um pendrive, um HD externo ou um teclado. O notebook só se importa com o contrato da porta USB-C, não com a marca do periférico.

#### Exemplos no Projeto:
- [`NFeFiscalGatewayInterface.php`](file:///home/gabriel/Documents/git/php-nfe/app/Core/Application/Ports/Outbound/NFeFiscalGatewayInterface.php): Define o contrato para emissão e cancelamento de NFe na SEFAZ.
- [`FileStorageServiceInterface.php`](file:///home/gabriel/Documents/git/php-nfe/app/Core/Application/Ports/Outbound/FileStorageServiceInterface.php): Define o contrato para armazenamento de arquivos XML e PDF.

---

### 4. Use Cases (Casos de Uso / Actions)

#### O que são?
Classes que representam uma ação específica do sistema (Princípio da Responsabilidade Única - SRP).

#### O que o Use Case faz na prática?
1. Recebe um DTO de Entrada.
2. Instancia as Entidades de Domínio (valida regras).
3. Aciona os Ports de Saída (Interfaces).
4. Retorna um DTO de Saída.

#### Exemplos no Projeto:
- [`EmitirNFeUseCase.php`](file:///home/gabriel/Documents/git/php-nfe/app/Core/Application/UseCases/EmitirNFeUseCase.php): Orquestra a emissão da nota e a gravação de arquivos.
- [`CancelarNFeUseCase.php`](file:///home/gabriel/Documents/git/php-nfe/app/Core/Application/UseCases/CancelarNFeUseCase.php): Orquestra o cancelamento fiscal na SEFAZ.

---

### 5. Adapters (Adaptadores de Infraestrutura)

#### O que são?
São as implementações concretas (os "plugs" que se encaixam nas tomadas Ports). É onde vive o código específico do Laravel ou de bibliotecas de terceiros.

1. Primary Adapters (Entrada):
   - Controllers HTTP (`NFeController`)
   - Comandos Artisan / Jobs em Fila
2. Secondary Adapters (Saída):
   - `NFePhpFiscalAdapter`: Implementa a `NFeFiscalGatewayInterface` usando a biblioteca `nfephp-org/sped-nfe`.
   - `LocalFileStorageAdapter`: Implementa a `FileStorageServiceInterface` usando a Facade `Storage` do Laravel.

---

## 3. Fluxo de Execução Completo

```text
[ Cliente HTTP / Postman / Frontend ]
                 │ (JSON)
                 ▼
┌─────────────────────────────────────────────────────────────────┐
│ Primary Adapter: NFeController                                  │
│ 1. Recebe a requisição HTTP                                     │
│ 2. Monta o DTO (EmitirNFeInputDto)                              │
│ 3. Executa o Use Case: $useCase->execute($dto)                 │
└────────────────────────┬────────────────────────────────────────┘
                         │ (EmitirNFeInputDto)
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Use Case: EmitirNFeUseCase                                      │
│ 1. Instancia Entidade NFe (valida regras do negócio)            │
│ 2. Executa $fiscalGateway->emitir($nfe) [Via Interface]         │
│ 3. Executa $storageService->salvarXml(...) [Via Interface]      │
│ 4. Retorna EmitirNFeOutputDto                                   │
└────────────────┬────────────────────────────────┬───────────────┘
                 │                                │
                 ▼                                ▼
┌────────────────────────────────┐    ┌───────────────────────────┐
│ Secondary Adapter:             │    │ Secondary Adapter:        │
│ NFePhpFiscalAdapter            │    │ LocalFileStorageAdapter   │
│ (Comunica com a SEFAZ via      │    │ (Grava arquivos no disco  │
│  nfephp-org/sped-nfe)          │    │  com Storage::disk)       │
└────────────────────────────────┘    └───────────────────────────┘
```

---

## Benefícios Diretos no Seu Projeto

1. Facilidade em Mudar de Tecnologia: Se futuramente for necessário alterar o armazenamento local para AWS S3, basta criar a classe `S3StorageAdapter` implementando `FileStorageServiceInterface` e alterar uma linha no `AppServiceProvider`.
2. Zero Dependência Externa no Domínio: A regra de negócio não fica refém de atualizações do Laravel ou da SEFAZ.
3. Testes Unitários Rápido: Permite testar toda a lógica de negócio sem necessidade de subir banco de dados ou ambiente externo.
