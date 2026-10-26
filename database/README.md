# PostgreSQL provisioning

All schema changes are Laravel migrations under `api/database/migrations`; do not maintain a duplicate SQL schema.

For local development, use PostgreSQL's `createuser --pwprompt infotech_app` and `createdb --owner=infotech_app infotech_school`. Enter a unique private password at the prompt, then configure the ignored `api/.env`. Do not put the password on the command line or in SQL files.

For PostgreSQL integration tests, create a separate disposable `infotech_school_test` database. Set DB_DATABASE to that test database using environment variables, and run `php vendor/bin/phpunit -c phpunit.pgsql.xml` from `api/`. This suite creates/refreshes tables. Never point tests at school records. PostgreSQL extension/driver availability is checked with `php -m` and connectivity with `php artisan db:show`.

Production should use a migration owner for DDL and a separate runtime role with only required table/sequence privileges. Audit table privileges should allow SELECT/INSERT and prevent UPDATE/DELETE. Backups, retention, recovery checks and managed server configuration must be resolved before production use.
