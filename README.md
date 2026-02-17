## Testing notes (project-specific)

- Unit tests: placed under `tests/Unit` and use mocked `ProductRepositoryInterface` to exercise controller logic without external services.
- Feature tests: placed under `tests/Feature` and use SQLite in-memory (`RefreshDatabase`) for speed. This satisfies the exercise requirement and keeps CI fast.
- Elasticsearch: feature tests mock the `ElasticsearchService` so they don't require a running ES instance. If you need end-to-end verification against a real Elasticsearch, run the stack via Docker Compose and execute the integration test described below.

Running tests

Inside the project (or inside the `app` container):

```bash
# run all tests
php artisan test

# run only unit tests
# Project README

Este repositório contém uma API de exemplo para gerenciamento de `Product` (CRUD) com integração opcional ao Elasticsearch.

O README foi simplificado para focar na execução do projeto, rotas expostas e exemplos de requisição — informações genéricas sobre Laravel foram removidas.

## Quick start (Docker)

Requisitos: `docker` e `docker-compose`.

# README do Projeto (em Português)

Este repositório contém uma API de gerenciamento de produtos (CRUD) com integração opcional ao Elasticsearch.

Este README explica em detalhe como executar o sistema localmente usando Docker, quais dependências são necessárias e fornece exemplos de requisições para todas as rotas.

---

## Pré-requisitos

- Docker (versão recente)
- Docker Compose
- Opcional: Git, PHP 8.4+ e Composer se preferir executar localmente sem Docker

Observação: em ambientes Windows/WSL é comum encontrar problemas ao montar diretórios do host dentro do container (especialmente com a pasta `vendor`). Veja a seção de Troubleshooting abaixo.

---

## Instruções rápidas (Docker)

1) A partir da raiz do repositório (opção 1):

```bash
docker-compose -f teste_app/docker-compose.yml up -d --build
```

2) Ou a partir da pasta `teste_app` (opção 2):

```bash
cd teste_app
docker-compose up -d --build
```

Esses comandos irão criar os serviços: `app` (PHP-FPM), `nginx`, `mysql`, `redis`, `elasticsearch`, `kibana`, `adminer`.

3) Após os containers subirem, execute migrações e seeders (no mesmo diretório do `docker-compose.yml` usado):

```bash
docker-compose exec app php artisan migrate --force
docker-compose exec app php artisan db:seed --class=DatabaseSeeder --force
```

4) Se `vendor` não estiver presente no container, instale dependências:

```bash
docker-compose exec app bash -lc "composer install --no-interaction --prefer-dist --optimize-autoloader"
```

5) Verifique status dos containers:

```bash
docker ps
docker-compose ps
```

---

## Variáveis de ambiente

Copie o arquivo de exemplo e ajuste conforme necessário:

```bash
cp .env.example .env
# editar .env com credenciais e configurações necessárias
```

Principais variáveis a verificar:

- `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `REDIS_HOST`
- `ELASTICSEARCH_HOSTS` (ex.: http://elasticsearch:9200)
- `S3_*` se for usar upload de imagens para S3

---

## Rotas e exemplos de requisição

Base URL: `http://localhost:8080`

- Listar produtos

  - Método: GET
  - Endpoint: `/api/products`
  - Parâmetros opcionais: `per_page`, `q`
  - Exemplo:

    ```bash
    curl -v "http://localhost:8080/api/products?per_page=10"
    ```
- Buscar produtos (Elasticsearch)

  - Método: GET
  - Endpoint: `/api/search/products`
  - Parâmetros aceitos: `q`, `sku`, `name`, `category`, `min_price`, `max_price`, `price`, `status`, `sort`, `order`, `page`, `per_page`, `created_at`
  - Exemplo:

    ```bash
    curl -v "http://localhost:8080/api/search/products?sku=MANUAL-1&min_price=9.99"
    ```
- Criar produto

  - Método: POST
  - Endpoint: `/api/products`
  - Body (JSON): `sku`, `name`, `price`, `description` (opcional), `category` (opcional), `status` (`active`|`inactive`)
  - Exemplo:

    ```bash
    curl -v -X POST http://localhost:8080/api/products \
      -H "Content-Type: application/json" \
      -d '{"sku":"S1","name":"Produto 1","price":9.99,"category":"books","status":"active"}'
    ```
  - Retorno: `201 Created` com o objeto criado.
- Obter produto por ID

  - Método: GET
  - Endpoint: `/api/products/{id}`
  - Exemplo:

    ```bash
    curl -v http://localhost:8080/api/products/<id>
    ```
  - Retorno: `200 OK` ou `404 Not Found`.
- Atualizar produto

  - Método: PUT
  - Endpoint: `/api/products/{id}`
  - Body: mesmo esquema do POST
  - Exemplo:

    ```bash
    curl -v -X PUT http://localhost:8080/api/products/<id> \
      -H "Content-Type: application/json" \
      -d '{"sku":"S1","name":"Produto 1 atualizado","price":19.99}'
    ```
  - Retorno: `200 OK` com objeto atualizado ou `404` se não encontrado.
- Deletar produto

  - Método: DELETE
  - Endpoint: `/api/products/{id}`
  - Exemplo:

    ```bash
    curl -v -X DELETE http://localhost:8080/api/products/<id>
    ```
  - Retorno: `204 No Content` ou `404`.
- Upload de imagem

  - Método: POST
  - Endpoint: `/api/products/{id}/image`
  - Body: `multipart/form-data` com campo `image`
  - Exemplo:

    ```bash
    curl -v -X POST http://localhost:8080/api/products/<id>/image \
      -F "image=@/caminho/para/arquivo.jpg"
    ```
  - Retorno: `200 OK` com o produto atualizado (contendo `image_path` e possivelmente `image_url`).

---

## Dependências do projeto

- PHP extensões necessárias (instaladas na imagem Docker): pdo_mysql, mbstring, exif, pcntl, bcmath, gd, intl, zip, opcache, redis (PECL).
- Composer (dependências PHP listadas em `composer.json`)
- Serviços Docker: MySQL, Redis, Elasticsearch, Nginx

As dependências são instaladas na imagem `app` definida no `Dockerfile`; localmente você só precisa do Composer se não usar Docker.

---

## Testes

Rodar todos os testes dentro do container `app`:

```bash
docker-compose exec app php artisan test
```

Rodar um teste específico:

```bash
docker-compose exec app php artisan test --filter ProductApiTest
```

Observação: os testes de integração que precisam de MySQL/Elasticsearch exigem que os containers estejam ativos e as migrações executadas.

---

## Jobs / Fila

Para processar jobs (ex.: `SyncProductToIndex`), rode um worker:

```bash
docker-compose exec -d app php artisan queue:work --sleep=3 --tries=3
```

Em produção recomendamos usar supervisord/systemd ou um serviço de processos para gerenciar vários workers.

---

## Troubleshooting comum

- Erro "Failed to open stream: Invalid argument" ao incluir arquivos do `vendor` (Windows/WSL):

  - Causa: bind-mount do host sobrescreve `vendor` da imagem e pode gerar problemas de I/O.
  - Solução: usar o volume nomeado `vendor_data` (já configurado no `docker-compose`) ou executar `composer install` dentro do container `app`.
- Erro ao puxar imagens durante `docker-compose build` (BuildKit / credentials):

  - Rode `docker login` para autenticar ou desative temporariamente BuildKit para depuração.
- Nginx retornando 404: verifique se o `default.conf` personalizado está montado em `/etc/nginx/conf.d/default.conf` e se o `app` está escutando na porta 9000.

Comandos úteis de diagnóstico:

```bash
docker-compose ps
docker logs --tail 200 nginx
docker logs --tail 200 app
docker exec -it app ps aux | grep php-fpm
docker exec -it app ss -lntp | grep 9000
curl -s http://elasticsearch:9200/_cluster/health | jq '.'
```

---

## Sugestões e próximos passos

- Padronizar formato de erros (por exemplo: `{ "error": "mensagem", "code": "..." }`).
- Adicionar seeders de produto com dados de demonstração.
- Documentar o processo de CI para builds e testes end-to-end.
