# Create a Super Admin / Managing Director

Use the existing configured API installation in an interactive terminal:

```powershell
cd E:\info-tech-school-management\api
php -d extension=pdo_pgsql -d extension=gd artisan school:create-super-admin
```

If the required PHP extensions are already enabled globally, `php artisan school:create-super-admin` is sufficient.

The command asks for full name, email, password and password confirmation. Both password prompts are hidden, with visible-input fallback disabled. It accepts the existing staff password policy (12–1024 characters), requires matching confirmation and a valid unique email, and normalizes email to lowercase. Invalid input returns a failure without creating a user. Noninteractive execution is rejected; there are no password arguments, default credentials or automatic account seeders.

The existing `User` hashed cast handles the password. The command attaches only the existing `super_admin` role (`Super Admin / Managing Director`) through `user_role_assignments` and records an audit event without a password. User creation, role assignment and audit insertion share a database transaction. No existing user's password or role is changed. The command can be used on a configured production installation by a trusted server operator; it is not restricted to the local-only development-account command.

Existing migrations and role/reference seeders must already be applied. If the role is missing, the command fails; it does not invent another role/RBAC system or seed an account. Mobile permission grants remain owned by the existing project seeders. No real Super Admin account was created during implementation; tests use fictional data in the isolated PostgreSQL test database.
