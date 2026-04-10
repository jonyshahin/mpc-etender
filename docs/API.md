# API / Route Registry

> Update after adding any route.

## Authentication (Fortify)

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/login` | `login` | Login page |
| POST | `/login` | `login.store` | Authenticate user |
| POST | `/logout` | `logout` | Logout |
| GET | `/register` | `register` | Registration page |
| POST | `/register` | `register.store` | Create account |
| GET | `/forgot-password` | `password.request` | Forgot password page |
| POST | `/forgot-password` | `password.email` | Send reset link |
| GET | `/reset-password/{token}` | `password.reset` | Reset password page |
| POST | `/reset-password` | `password.update` | Update password |
| GET | `/email/verify` | `verification.notice` | Verify email prompt |
| GET | `/email/verify/{id}/{hash}` | `verification.verify` | Verify email |
| POST | `/email/verification-notification` | `verification.send` | Resend verification |
| GET | `/two-factor-challenge` | `two-factor.login` | 2FA challenge page |
| POST | `/two-factor-challenge` | `two-factor.login.store` | Verify 2FA code |

## Application

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/` | `home` | Welcome page |
| GET | `/dashboard` | `dashboard` | Authenticated dashboard |

## Settings

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/settings/profile` | `profile.edit` | Edit profile page |
| PATCH | `/settings/profile` | `profile.update` | Update profile |
| DELETE | `/settings/profile` | `profile.destroy` | Delete account |
| GET | `/settings/security` | `security.edit` | Security settings page |
| PUT | `/settings/password` | `user-password.update` | Change password |

## Vendor Portal (prefix: `/vendor`)

### Public (guest:vendor middleware)

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/vendor/register` | `vendor.register` | Vendor registration form |
| POST | `/vendor/register` | `vendor.register.store` | Submit registration |
| GET | `/vendor/login` | `vendor.login` | Vendor login form |
| POST | `/vendor/login` | `vendor.login.store` | Authenticate vendor |

### Authenticated (auth:vendor middleware)

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| POST | `/vendor/logout` | `vendor.logout` | Vendor logout |
| GET | `/vendor/dashboard` | `vendor.dashboard` | Vendor dashboard |
| GET | `/vendor/profile` | `vendor.profile.edit` | Edit vendor profile |
| PUT | `/vendor/profile` | `vendor.profile.update` | Update vendor profile |
| GET | `/vendor/documents` | `vendor.documents.index` | List vendor documents |
| POST | `/vendor/documents` | `vendor.documents.store` | Upload document |
| DELETE | `/vendor/documents/{document}` | `vendor.documents.destroy` | Delete pending document |
| GET | `/vendor/categories` | `vendor.categories.index` | View/select categories |
| PUT | `/vendor/categories` | `vendor.categories.update` | Update category selections |

## Vendor Management (admin, prefix: `/admin/vendors`)

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/admin/vendors` | `admin.vendors.index` | List all vendors |
| GET | `/admin/vendors/{vendor}` | `admin.vendors.show` | Vendor detail view |
| PUT | `/admin/vendors/{vendor}/prequalify` | `admin.vendors.prequalify` | Approve vendor |
| PUT | `/admin/vendors/{vendor}/reject` | `admin.vendors.reject` | Reject vendor |
| PUT | `/admin/vendors/{vendor}/suspend` | `admin.vendors.suspend` | Suspend vendor |

## Administration (prefix: `/admin`)

All routes require `auth` + `verified` middleware. Permission checks via Form Requests.

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/admin/dashboard` | `admin.dashboard` | Admin dashboard with stats |
| GET | `/admin/users` | `admin.users.index` | List users |
| POST | `/admin/users` | `admin.users.store` | Create user |
| GET | `/admin/users/{user}/edit` | `admin.users.edit` | Edit user form |
| PUT | `/admin/users/{user}` | `admin.users.update` | Update user |
| DELETE | `/admin/users/{user}` | `admin.users.destroy` | Deactivate user |
| GET | `/admin/projects` | `admin.projects.index` | List projects |
| POST | `/admin/projects` | `admin.projects.store` | Create project |
| GET | `/admin/projects/{project}/edit` | `admin.projects.edit` | Edit project form |
| PUT | `/admin/projects/{project}` | `admin.projects.update` | Update project |
| POST | `/admin/projects/{project}/assign-users` | `admin.projects.assign-users` | Assign users to project |
| GET | `/admin/roles` | `admin.roles.index` | List roles |
| POST | `/admin/roles` | `admin.roles.store` | Create role |
| PUT | `/admin/roles/{role}` | `admin.roles.update` | Update role |
| GET | `/admin/roles/{role}/permissions` | `admin.roles.permissions` | View role permissions |
| PUT | `/admin/roles/{role}/permissions` | `admin.roles.permissions.update` | Update role permissions |
| GET | `/admin/categories` | `admin.categories.index` | List categories (tree) |
| POST | `/admin/categories` | `admin.categories.store` | Create category |
| PUT | `/admin/categories/{category}` | `admin.categories.update` | Update category |
| DELETE | `/admin/categories/{category}` | `admin.categories.destroy` | Delete category |
| GET | `/admin/settings` | `admin.settings.index` | View system settings |
| PUT | `/admin/settings` | `admin.settings.update` | Update settings |
| GET | `/admin/audit-logs` | `admin.audit-logs.index` | View audit logs |

## Package dashboards

| URI | Package |
|-----|---------|
| `/horizon` | Laravel Horizon (queue monitoring) |
| `/pulse` | Laravel Pulse (app monitoring) |

## Custom middleware aliases

| Alias | Class | Purpose |
|-------|-------|---------|
| `project.access` | `EnsureProjectAccess` | Verify user is assigned to the route's project |
