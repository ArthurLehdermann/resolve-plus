# Resolve+

**Do problema à solução, com preço, profissional e garantia no mesmo lugar.**

Marketplace de serviços residenciais e prediais. A plataforma participa da transação inteira: orçamento, propostas comparáveis, pagamento protegido, garantia digital e prontuário do imóvel.

| | |
|---|---|
| **Repositório** | https://github.com/ArthurLehdermann/resolve-plus |
| **Stack** | Laravel 12, PHP 8.4, PostgreSQL, Redis — [ADR-001](docs/adr/ADR-001-stack.md) |

---

## Setup local (Docker)

Requisitos: Docker Compose e a imagem `arthurlehdermann/alpine-nginx-php8.4`.

```bash
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

A API sobe em http://localhost:8080 (`/up` é o health check).

Categorias e tabela de preço do MVP vêm do seeder (sem elas não dá pra criar solicitação nem estimar preço):

```bash
docker compose exec app php artisan db:seed
```

Testes rodam em **Postgres de verdade** (o schema usa `jsonb` e índices parciais, não roda em SQLite), num banco dedicado `resolve_plus_test` — `phpunit.xml` força esse nome justamente para a suíte (`RefreshDatabase`) não truncar o banco da aplicação. O `docker compose up` cria o banco de teste no primeiro boot (`docker/init-test-db.sh`); em ambiente que já existia, criar na mão com `docker compose exec db psql -U resolve_plus -c 'CREATE DATABASE resolve_plus_test'`.

```bash
docker compose exec app vendor/bin/pint --test
docker compose exec app php artisan test
```

---

## Documentação

Índice em [`docs/`](docs/):

| Pasta | Conteúdo |
|-------|----------|
| [`docs/foundation/`](docs/foundation/) | Invariantes, event storm, state machine |
| [`docs/specifications/`](docs/specifications/) | Visão, funcionalidades, modelo, arquitetura, APIs |
| [`docs/adr/`](docs/adr/) | Decisões (stack, financeiro, garantia, prazo de aceite, gateway/Pix) |

Organização do código por domínio: [`docs/specifications/05-arquitetura.md`](docs/specifications/05-arquitetura.md).

---

Desenvolvido por [BigWorks](https://bigworks.com.br).
