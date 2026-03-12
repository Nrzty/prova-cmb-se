# 🚒 Sistema de Gestão de Ocorrências — Corpo de Bombeiros de Sergipe

> **API REST** construída em **Laravel 11** + **PHP 8.4** para receber eventos de sistemas externos, consolidar ocorrências de incidentes, controlar seu ciclo de vida completo e processar tudo de forma assíncrona com auditoria total.

---

## 📋 Índice

1. [⚡ Quick Start (Docker)](#⚡-quick-start-docker) — **Comece aqui!**
2. [🐳 Rodando com Docker](#-rodando-com-docker-recomendado)
3. [💻 Rodando Localmente](#-rodando-localmente-sem-docker)
4. [🧪 Testando a API](#-testando-a-api)
5. [📊 Arquitetura do Sistema](#-arquitetura-do-sistema)
6. [🔐 Estratégias Técnicas](#-estratégias-técnicas)
7. [🎯 Decisões de Design](#-decisões-de-design)
8. [⚠️ O que Ficou de Fora](#-o-que-ficou-de-fora)
9. [🚀 Como Evoluir](#-como-evoluir-na-corporação)

---

## ⚡ Quick Start (Docker)

**Tempo estimado: 3-5 minutos**

```bash
# 1. Clone
git clone https://github.com/Nrzty/PROVA-CBM-SE-BACK.git
cd PROVA-CBM-SE-BACK

# 2. Suba
docker compose up -d --build

# 3. Configure (primeira vez)
docker compose exec app sh -lc "
  [ -f .env ] || cp .env.example .env
  php artisan key:generate
  php artisan migrate --force
"

# 4. Teste
curl -H "X-API-Key: seu-api-key" http://localhost:8000/api/occurrences
```

**Pronto!** API em `http://localhost:8000`

> Nota: Se porta 8000 estiver em uso, use `APP_PORT=8010 docker compose up -d`

---

## 🐳 Rodando com Docker (Recomendado)

### Stack Completa

| Serviço | Versão | Porta | Função |
|---------|--------|-------|--------|
| PHP + Nginx | 8.4 | 8000 | API |
| PostgreSQL | 15 | 5432 | Banco de dados |
| Redis | 7 | 6379 | Fila de jobs + Cache |
| Node.js | 18 | — | Assets (Vite) |

### Pré-requisito

[Docker Desktop](https://www.docker.com/products/docker-desktop) instalado e rodando.

### Instalação

#### 1. Clone o repositório

```bash
git clone https://github.com/Nrzty/PROVA-CBM-SE-BACK.git
cd PROVA-CBM-SE-BACK
```

#### 2. Suba os containers

```bash
docker compose up -d --build
```

Aguarde até todos ficarem `healthy`:

```bash
docker compose ps
# NAME      STATUS
# app       healthy
# postgres  healthy
# redis     healthy
```

#### 3. Execute o setup (primeira vez)

```bash
docker compose exec app sh -lc "
  [ -f .env ] || cp .env.example .env
  php artisan key:generate
  php artisan migrate --force
"
```

#### 4. Verifique

```bash
# Deve retornar HTTP 200 com JSON
curl -H "X-API-Key: seu-api-key" http://localhost:8000/api/occurrences
```

---

### Comandos Úteis

```bash
# Ver status dos containers
docker compose ps

# Logs em tempo real
docker compose logs -f app

# Entrar no container
docker compose exec app bash

# Rodar testes
docker compose exec app php artisan test

# Reprocessar jobs que falharam
docker compose exec app php artisan queue:retry all

# Parar (dados mantidos)
docker compose stop

# Destruir tudo
docker compose down -v
```

---

## 💻 Rodando Localmente (Sem Docker)

### Pré-requisitos

Todos esses serviços devem estar **rodando**:

```bash
# Verificar PHP 8.4+
php -v

# Verificar Composer
composer --version

# Verificar Node.js 18+
node -v

# PostgreSQL rodando (padrão: localhost:5432)
psql -h localhost -U postgres -c "SELECT 1"

# Redis rodando (padrão: localhost:6379)
redis-cli ping  # Esperado: PONG
```

### Instalação

#### 1. Clone e instale dependências

```bash
git clone https://github.com/Nrzty/PROVA-CBM-SE-BACK.git
cd PROVA-CBM-SE-BACK

composer install
npm install
```

#### 2. Configure o ambiente

```bash
cp .env.example .env
```

Edite `.env` com suas credenciais de banco:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=prova_cbm
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

#### 3. Crie o banco e execute migrações

```bash
# Criar banco (no psql ou CLI)
createdb prova_cbm

# Setup Laravel
php artisan key:generate
php artisan migrate --force
```

#### 4. Rode o servidor

```bash
composer run dev
```

Isso inicia em paralelo:
- Servidor PHP em `http://localhost:8000`
- Fila Redis processando jobs
- Vite compilando assets

Para parar: `Ctrl+C`

---

## 🧪 Testando a API

### Autenticação

Todas as requisições precisam do header:

```
X-API-Key: seu-api-key
```

Configure no `.env` com `API_KEY=seu-api-key`

---

### Exemplos de Requisições

#### 1. Receber evento externo

```bash
curl -X POST http://localhost:8000/api/integrations/occurrences \
  -H "Content-Type: application/json" \
  -H "X-API-Key: seu-api-key" \
  -H "Idempotency-Key: evento-001" \
  -d '{
    "externalId": "EXT-2026-001",
    "type": "incendio_urbano",
    "description": "Incêndio em edifício comercial",
    "reportedAt": "2026-03-12T10:30:00Z"
  }'
```

**Resposta esperada (202):**

```json
{
  "commandId": "uuid-gerado",
  "status": "accepted"
}
```

---

#### 2. Listar ocorrências

```bash
# Todas
curl http://localhost:8000/api/occurrences \
  -H "X-API-Key: seu-api-key"

# Filtrar por status
curl "http://localhost:8000/api/occurrences?status=reported" \
  -H "X-API-Key: seu-api-key"

# Filtrar por tipo
curl "http://localhost:8000/api/occurrences?type=incendio_urbano" \
  -H "X-API-Key: seu-api-key"
```

---

#### 3. Iniciar ocorrência

```bash
curl -X POST http://localhost:8000/api/occurrences/{id}/start \
  -H "X-API-Key: seu-api-key" \
  -H "Idempotency-Key: start-001"
```

**Precisa estar com status `reported`**

---

#### 4. Resolver ocorrência

```bash
curl -X POST http://localhost:8000/api/occurrences/{id}/resolve \
  -H "X-API-Key: seu-api-key" \
  -H "Idempotency-Key: resolve-001"
```

**Precisa estar com status `started`**

---

#### 5. Criar despacho

```bash
curl -X POST http://localhost:8000/api/occurrences/{id}/dispatches \
  -H "Content-Type: application/json" \
  -H "X-API-Key: seu-api-key" \
  -H "Idempotency-Key: dispatch-001" \
  -d '{
    "resourceCode": "ABT-12"
  }'
```

---

#### 6. Fechar despacho

```bash
curl -X POST http://localhost:8000/api/dispatches/{id}/close \
  -H "X-API-Key: seu-api-key" \
  -H "Idempotency-Key: close-dispatch-001"
```

---

### Rodar Testes Automatizados

```bash
# Docker
docker compose exec app php artisan test

# Local
php artisan test

# Com cobertura
php artisan test --coverage
```

**Esperado:** Todos os testes passarem ✅

---

### Códigos de Resposta

| Status | Significado |
|--------|------------|
| `202` | Evento aceito para processamento assíncrono |
| `200` | Sucesso |
| `201` | Recurso criado |
| `400` | Dados inválidos |
| `401` | Sem autenticação |
| `404` | Recurso não encontrado |
| `409` | Conflito de idempotência |
| `422` | Operação inválida para o estado |
| `500` | Erro interno |

---

## 📊 Arquitetura do Sistema

### Fluxo Geral

![Arquitetura do Sistema de Integração de Eventos](docs/images/Diagrama_do_Sistema.png)

---

![Fluxo de Integração Externa](docs/images/fluxo_de_integracao.png)


---

### As 4 Camadas

#### 1. **Controllers** (`app/Http/Controllers/`)

Recebem requisições HTTP, validam com Form Requests, chamam Services.

- `OccurrenceIntegrationController` → POST /api/integrations/occurrences
- `OccurrenceController` → GET, POST /api/occurrences
- `OccurrenceDispatchController` → POST /api/dispatches

#### 2. **Services** (`app/Services/Api/`)

Contém lógica de negócio: registrar eventos, iniciar ocorrências, processar jobs.

- `RegisterOccurrenceCommandService` — recebe do externo
- `OccurrenceService` — operações do ciclo de vida
- `ProcessOccurrenceCreatedService` — processa criação
- `DispatchService` — cria/fecha despachos

#### 3. **Models** (`app/Models/`)

Definem tabelas e relacionamentos.

- `Occurrence` — um incidente
- `Dispatche` — alocação de recurso
- `EventInbox` — fila de eventos
- `AuditLog` — histórico de ações

#### 4. **Jobs** (`app/Jobs/`)

Executam assincronamente na fila Redis com retry automático.

- `ProcessOccurrenceCreatedJob` — cria Occurrence
- `ProcessOccurrenceStartJob` — inicia
- `ProcessOccurrenceResolveJob` — resolve
- `ProcessDispatchCreatedJob` — cria despacho
- `ProcessDispatchClosedJob` — fecha despacho

---

## 🔐 Estratégias Técnicas

### 1. Idempotência (Sem Duplicatas)

**Problema:** Sistema externo reenvia requisição por falha de rede → cria 2x.

**Solução:** Header `Idempotency-Key` + Índice Único no Banco

```sql
UNIQUE (idempotency_key, type, source)
```

**Fluxo:**

| Caso | Resultado |
|------|-----------|
| 1ª requisição com chave X | ✅ Cria, processa normalmente |
| 2ª requisição com chave X (mesmo payload) | ✅ Retorna `DUPLICATED`, não processa |
| 2ª requisição com chave X (payload diferente) | ❌ HTTP 409 CONFLICT |

**Implementação:**

```php
try {
    EventInbox::create([
        'idempotency_key' => $key,
        'type' => $type->value,
        'source' => $source->value,
        'payload' => $payload,
    ]);
} catch (QueryException $e) {
    if (DatabaseErrorHelper::isUniqueViolation($e)) {
        $existing = EventInbox::where('idempotency_key', $key)->first();
        if ($existing->payload === $payload) {
            return IntegrationResult::DUPLICATED;  // OK
        }
        throw new IdempotencyConflictException();  // 409
    }
}
```

---

### 2. Concorrência (Sem Race Conditions)

**Problema:** 2 operadores tentam fechar o mesmo dispatch simultaneamente.

**Solução:** 3 camadas de proteção

#### Camada 1: Lock Pessimista (Database)

```php
EventInbox::where('id', $id)->lockForUpdate()->first();
// Bloqueia até fim da transação
```

#### Camada 2: Status Validation (Aplicação)

```php
if ($event->status !== EventInboxStatus::PENDING->value) {
    return;  // Já foi processado, pula
}
```

#### Camada 3: Fila Sequencial (Redis)

Um worker por vez = ordem garantida.

---

### 3. Auditoria (Rastreabilidade Total)

Toda mudança de status gera registro em `audit_logs`:

```php
AuditLog::create([
    'entity_type' => Occurrence::class,
    'entity_id' => $occurrence->id,
    'action' => 'STARTED',  // O quê
    'before' => ['status' => 'reported'],
    'after' => ['status' => 'started'],
    'meta' => ['source' => 'WEB_OPERATOR'],  // Quem
    // Timestamps automáticos (quando)
]);
```

**Resultado:** Histórico completo de cada ocorrência.

---

### 4. Processamento Assíncrono

**Why 202 Accepted?**

- API retorna imediatamente (não bloqueia)
- Job processa depois na fila
- Se falhar, tenta 3x com backoff

```
Tentativa 1: falha → aguarda 10s
Tentativa 2: falha → aguarda 30s
Tentativa 3: falha → marca como FAILED
```

---

## 🎯 Decisões de Design

| Decisão | Justificativa |
|---------|---------------|
| **HTTP 202** na integração | Assíncrono; "recebido, não processado ainda" |
| **Event Inbox** como buffer | Desacopla recebimento de processamento |
| **Lock pessimista** | Atomicidade garantida |
| **Índice Unique** | Idempotência no banco |
| **Retry com backoff** | Recuperação de falhas transitórias |
| **Transações em tudo** | Estado consistente sempre |
| **Enums** para tipos | Type-safety, sem magic strings |
| **DTOs** para transfer | Validação e transformação clara |

---

## ⚠️ O que Ficou de Fora

| Item | Por Que | Como Adicionar |
|------|--------|-----------------|
| Rate limiting | Volume baixo | Middleware `RateLimiter` |
| Notificações | Foco no core | Jobs de email/SMS |
| Relatórios | Dados existem | Queries + Grafana |
| Soft deletes | AuditLog já rastreia | Trait `SoftDeletes` |
| Autenticação JWT | API Key suficiente | Sanctum do Laravel |

---

## 📚 Recursos

- [Laravel 11 Docs](https://laravel.com/docs/11.x)
- [Laravel Queues](https://laravel.com/docs/11.x/queues)
- [Docker Docs](https://docs.docker.com)
- [PostgreSQL Docs](https://www.postgresql.org/docs)
- [Redis Docs](https://redis.io/docs)

---

## 📝 Notas Importantes

- **Primeira vez?** Siga o [Quick Start](#⚡-quick-start-docker)
- **Problemas?** Veja `docker compose logs -f app`
- **Quer testar?** Use os [exemplos](#-testando-a-api)
- **Quer entender?** Leia o `ANALISE_COMPLETA.md`

---

