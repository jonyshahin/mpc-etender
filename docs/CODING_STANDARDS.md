# Coding Standards

## PHP

- Run `./vendor/bin/pint` before every commit. CI enforces it.
- **Controllers**: thin. Accept Form Request, call Service, return Inertia response. No business logic, no complex queries.
- **Services** (`app/Services/`): all business logic lives here. Constructor-injected. One public method = one business operation. PHPDoc on every public method.
- **Models** (`app/Models/`): define `$fillable` explicitly (never `$guarded = []`), `$casts` (cast enums to PHP enum classes, dates to `datetime`, JSON to `array`). Relationships have return type hints. Add scopes as needed for common filters.
- **Form Requests** (`app/Http/Requests/`): handle validation AND authorization. Never validate inline in a controller.
- **Policies** (`app/Policies/`): one per model, registered in `AuthServiceProvider`. Encode row-level rules.
- **Enums**: PHP 8.4 backed string enums in `app/Enums/`. Use these instead of DB enums for migration flexibility.
- **No raw SQL.** Use Eloquent or the Query Builder. Index decisions live in migrations.
- **All file uploads** go through `FileUploadService`. Never call `Storage::` directly in a controller.
- **All notifications** go through `NotificationService`. Never call `Notification::send()` directly in a controller.

## React / TypeScript

- Functional components only. Hooks only. No class components.
- Tailwind utility classes only — no custom CSS files unless absolutely required.
- All user-facing strings via i18n: `t('key')` in React, `__('key')` in PHP.
- RTL support: use Tailwind `rtl:` variants for directional styles.
- Props typed via TypeScript interfaces. JSDoc on every component.

## Database

- All tables use UUID primary keys via the `HasUuids` trait.
- Migration filename includes a comment header explaining the table's purpose.
- Foreign keys: `cascadeOnDelete()` for required parents, `nullOnDelete()` for optional.
- Status/type columns are `string(30)` — validation enforced by the matching PHP enum.

## Tests

- Pest PHP, not PHPUnit syntax.
- Factory for every model with realistic construction-procurement data.
- Feature test for every route. Unit test for every Service method.
- Target: ≥80% coverage. CI gate: `php artisan test --coverage --min=80`.

## Git

- Commit messages: `[Module] Brief description`
  - Examples: `[Tender] Add BOQ section CRUD`, `[Bid] Encrypt pricing on save`, `[Setup] Configure Reverb`
- Update `docs/CHANGELOG.md` at the end of each sprint.
- Update `docs/API.md` after adding any route.
