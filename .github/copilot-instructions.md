# Copilot instructions for this repository

## Build, test, and lint commands

Use PHP 8.2+ and Composer.

```bash
composer install
php bin/console cache:clear
```

Database workflow (Doctrine + migrations):

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
```

Run tests (PHPUnit is configured via `phpunit.dist.xml` and `bin/phpunit`):

```bash
php bin/phpunit
```

Run a single test file / test method:

```bash
php bin/phpunit tests/SomeTest.php
php bin/phpunit --filter testMethodName tests/SomeTest.php
```

Linting commands available in this Symfony app:

```bash
php bin/console lint:container
php bin/console lint:yaml config
php bin/console lint:twig templates
```

## High-level architecture

This is a Symfony 7.4 monolith (Doctrine ORM + Twig) with two main web surfaces and a small JSON API layer:

1. **Admin/back-office surface** under `/admin*` using admin Twig templates (`templates/admin/**`, `templates/base_front.html.twig`), protected by the `main` firewall.
2. **Front/public surface** under `/` using public templates (`templates/home/**`, `templates/base.html.twig`), handled by the `front` firewall.
3. **API endpoints** under `/api/**` for blog/avis/reclamation features (`src/Controller/Api/**`), returning JSON for client-side/mobile style usage.

Core domain is split by business modules (Activite, Hebergement, Destination, Reservation, Blog, Avis, Reclamation, Users/Role), with the standard Symfony layering:

- Controllers in `src/Controller/**`
- Doctrine entities in `src/Entity/**`
- Data access and custom queries in `src/Repository/**`
- Form classes in `src/Form/**`
- Feature services in `src/Service/**`

Notable cross-cutting flows:

- **Dual authentication model**: distinct admin and front login/logout paths configured in `config/packages/security.yaml`, with a shared `Users` entity and `UserChecker` active-account gate.
- **Blog publication workflow**: blog posts are created as draft or “publication requested”, then surfaced/administered through both `BlogController` and `AdminController`.
- **Blog engagement features**: ratings, comments, moderation, view tracking, and a session-backed “vision board” (`VisionBoardService`).
- **External integrations**: blog translation and Facebook Graph publishing are encapsulated in services and configured via env-backed parameters in `config/services.yaml`.

## Key conventions in this codebase

1. **Route + template naming are domain-aligned**: controller route prefixes and Twig folders typically mirror the module name (`activite`, `hebergement`, `blog`, `admin/...`), so add new pages within the same domain namespace.
2. **Security is path-segment driven**: access is primarily enforced by firewall patterns and `access_control` order in `security.yaml`; when adding routes, update path rules deliberately to avoid exposing admin endpoints.
3. **User identity uses `Users` + `Role` relation (not a plain string roles column)**: role assignment is done through the `Role` entity collection, while `getRoles()` always includes `ROLE_USER`.
4. **Input validation is mixed by context**: entities include Symfony Validator constraints, and many controllers also perform explicit request-level validation with localized flash/error messages; preserve both layers when extending forms/actions.
5. **Service configuration is parameterized in `config/services.yaml`**: bad-word moderation terms, translation provider settings, public base URL, and Facebook credentials are injected as constructor args rather than hardcoded.
6. **Blog authoring stores a string author identifier (`Blog::authorId`)** resolved from current user identity helpers, not a Doctrine relation to `Users`; keep this model consistent unless doing a full schema migration.
