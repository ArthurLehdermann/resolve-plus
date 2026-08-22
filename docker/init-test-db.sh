#!/bin/sh
# Cria o banco dedicado da suíte de testes (phpunit.xml força
# DB_DATABASE=resolve_plus_test). Sem ele, `php artisan test` roda contra o
# banco da aplicação e RefreshDatabase trunca tudo.
#
# Roda automaticamente no primeiro boot do Postgres (docker-entrypoint-initdb.d,
# só em volume novo). Em ambiente que já existe, rodar na mão:
#   docker compose exec db psql -U "$POSTGRES_USER" -c 'CREATE DATABASE resolve_plus_test'
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
	SELECT 'CREATE DATABASE resolve_plus_test'
	WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'resolve_plus_test')\gexec
EOSQL
