# Upgrading from 1.0.0 to 2.0.0

Version 2.0.0 makes `otps.generated_at` exclusively application-managed. On
some MySQL and MariaDB configurations, the 1.0.0 `TIMESTAMP NOT NULL`
declaration could become:

```sql
timestamp NOT NULL
DEFAULT current_timestamp()
ON UPDATE current_timestamp()
```

In that schema, updating a counter or any other column could move an OTP's
generation time and extend its validity.

## Before upgrading

Back up the database and inspect the `otps.generated_at` definition. Stored
times are preserved by the migration, but an already shifted value cannot be
reliably reconstructed from `created_at` or other columns.

Treat OTPs active during the deployment as potentially affected. Invalidate
them using your application's normal security/deployment procedure and issue
new OTPs after the migration. The package intentionally does not guess or
rewrite their original generation times.

## Upgrade steps

1. Require the new major version:

   ```bash
   composer require trianity/laravel-otp:^2.0
   ```

2. Run the application's migrations:

   ```bash
   php artisan migrate
   ```

3. Generate and validate a new OTP through the normal application flow.

The service provider calls `loadMigrationsFrom()` for the package's
`database/migrations` directory. Therefore Laravel discovers the new
`2026_10_05_000001_remove_generated_at_automatic_update.php` migration even
when the 1.0.0 creation migration is already recorded as run. Package
migrations are not published by the `otp` vendor-publish tag; that tag contains
only configuration and translations.

Do not force-publish or edit the old migration, and do not use
`migrate:fresh` on an existing application.

## Schema and runtime changes

The corrective migration changes only `otps.generated_at`, from `TIMESTAMP` to
`DATETIME NOT NULL`. It retains second-level precision, existing values, the
model's Carbon cast, the default `otps` table name, and the application's
default database connection. Version 1.0.0 has no configurable OTP table name
or dedicated OTP connection, so 2.0.0 does not introduce one as part of this
fix.

Generation and regeneration explicitly set `generated_at` from Laravel's
Carbon application clock. Validation counters, generation counters after their
associated generation write, failed attempts, expiration writes, and unrelated
column updates do not change it. A `useSameToken` generation call remains a
regeneration: it renews `generated_at` and validity while keeping the stored
token.

An OTP remains valid at the exact `generated_at + validity` instant and expires
after that instant. Attempts do not extend this window. The token generation
algorithm, response API, limits, one-time-use behaviour, and configuration keys
are unchanged.

## Rollback

The corrective migration has an intentionally empty `down()` method. Rolling
back the migration batch removes Laravel's migration-history entry but leaves
the safe `DATETIME` definition in place. Converting it back to `TIMESTAMP`
could silently reintroduce automatic updates on affected servers. Re-running
`php artisan migrate` safely reapplies the idempotent column definition.
