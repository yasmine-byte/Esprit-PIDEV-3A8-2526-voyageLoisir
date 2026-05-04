# Voyage - Travel & Accommodation Management Platform

A comprehensive Symfony 7.4-based web application for managing travel accommodations, activities, reservations, and travel-related services with integrated AI features and payment processing.

## 🎯 Project Overview

**Voyage** is a full-featured travel platform that enables users to:
- Browse and manage accommodations (Hébergement)
- Discover and book activities (Activités)
- Plan trips and vacations
- Make and manage reservations
- Write and interact with travel blogs
- Rate and review accommodations and experiences
- Communicate with support through a ticketing system

The platform includes both **public-facing** and **admin** interfaces with dual authentication, AI-powered features, and external integrations for payments, translations, and notifications.

## 🏗️ Architecture

This is a **Symfony 7.4 monolith** built with:

### Core Components
- **Framework**: Symfony 7.4 with Doctrine ORM
- **Database**: Doctrine migrations for schema management
- **Templating**: Twig
- **Frontend**: Asset Mapper with Stimulus.js

### Web Interfaces
1. **Admin/Back-office** (`/admin*`)
   - Administrative dashboard and management
   - Protected by the main firewall
   - Template location: `templates/admin/`

2. **Front/Public** (`/`)
   - Public-facing website
   - User-focused interface
   - Template location: `templates/home/`

3. **API Endpoints** (`/api/**`)
   - JSON API for blog, reviews, and complaints
   - Client-side and mobile app usage
   - Location: `src/Controller/Api/`

### Business Modules
- **Activite** - Activities and experiences
- **Hebergement** - Accommodations and rooms (Chambre)
- **Destination** - Travel destinations
- **Reservation** - Booking management (activities & accommodations)
- **Blog** - Content publishing with engagement features
- **Avis** - Reviews and ratings
- **Reclamation** - Complaints and support tickets
- **Users/Role** - Authentication and authorization
- **Transport** - Transportation information
- **Voyage** - Trip planning

## 🔐 Key Features

### Authentication & Authorization
- **Dual authentication model**: Separate admin and front login paths
- **Role-based access control** via `Users` entity and `Role` collection
- **Active account verification** via `UserChecker`
- Configured in `config/packages/security.yaml`

### Blog Features
- Draft and publication workflow
- AI-powered content translation
- Engagement tracking (ratings, comments, views)
- Comment moderation with bad-word filtering
- Session-backed "vision board" (`VisionBoardService`)
- View tracking system (`BlogViews` entity)
- Rating system (`BlogRating` entity)

### Integrations
- **Firebase**: Real-time notifications and messaging
- **Stripe**: Payment processing
- **Google OAuth2**: Social login
- **Mailjet**: Email delivery
- **External Translation API**: Blog content translation
- **Face-API.js**: Face recognition capabilities
- **Facebook Graph**: Social sharing

### Additional Features
- PDF generation (dompdf)
- Pagination support (KnpPaginator)
- Email verification workflow
- Admin commenting and reporting system
- User favorites and wishlist
- Availability management for accommodations

## 📋 Requirements

- **PHP**: 8.2 or higher
- **Composer**: For PHP dependency management
- **Node.js**: For frontend assets (optional, for development)
- **Database**: MySQL/MariaDB (Doctrine-compatible)

## 🚀 Installation & Setup

### 1. Environment Setup
```bash
# Install PHP dependencies
composer install

# Clear application cache
php bin/console cache:clear
```

### 2. Database Configuration
```bash
# Create database (if not exists)
php bin/console doctrine:database:create --if-not-exists

# Run migrations
php bin/console doctrine:migrations:migrate --no-interaction
```

### 3. Environment Variables
Create a `.env.local` file in the project root with necessary configuration:
```env
DATABASE_URL=mysql://user:password@localhost:3306/voyage
DEFAULT_URI=http://localhost:8000
FCM_PROJECT_ID=your_firebase_project_id
# ... other required variables
```

## 🧪 Testing

Run all tests:
```bash
php bin/phpunit
```

Run a specific test file:
```bash
php bin/phpunit tests/SomeTest.php
```

Run a specific test method:
```bash
php bin/phpunit --filter testMethodName tests/SomeTest.php
```

Tests are configured via `phpunit.dist.xml` and executable at `bin/phpunit`.



## 📁 Project Structure

```
├── src/
│   ├── Controller/          # Route handlers
│   │   ├── Admin/          # Admin-specific controllers
│   │   └── Api/            # JSON API endpoints
│   ├── Entity/             # Doctrine entities (domain models)
│   ├── Repository/         # Data access layer
│   ├── Form/               # Symfony form types
│   ├── Service/            # Business logic & services
│   ├── Security/           # Authentication & security
│   └── EventListener/      # Symfony event listeners
│
├── config/
│   ├── services.yaml       # Service configuration & parameters
│   ├── packages/           # Bundle-specific configs
│   └── routes/             # Route definitions
│
├── templates/
│   ├── admin/              # Admin interface templates
│   ├── home/               # Public interface templates
│   └── base.html.twig      # Base template
│
├── migrations/             # Database migrations
├── tests/                  # PHPUnit test suite
├── public/                 # Web root (index.php, assets)
├── assets/                 # Frontend assets (JS, CSS)
└── var/                    # Cache, logs, uploads
```

## 🛠️ Development Conventions

### Route & Template Naming
- Controllers and templates are organized by domain module
- Routes should be namespaced under the module name (e.g., `/activite/`, `/blog/`, `/admin/`)
- Templates follow the same module structure for easy discovery

### Security Rules
- Access is enforced via path-segment patterns in `security.yaml`
- Route access control is order-dependent in `access_control` rules
- Always verify firewall patterns when adding new routes to prevent unintended access

### User Roles
- Users are linked to roles through the `Role` entity collection (not string-based)
- `getRoles()` method always includes `ROLE_USER` by default
- Role assignment is done through the `Users` entity relationship

### Input Validation
- Entities include Symfony Validator constraints
- Controllers perform explicit request-level validation with localized messages
- Maintain both validation layers when extending forms or actions

### Service Configuration
- Service parameters are injected via `config/services.yaml`
- Bad-word moderation terms, API credentials, and URLs are parameterized
- Avoid hardcoding configuration values

### Blog Authoring
- Blog author is stored as a string identifier (`Blog::authorId`)
- This is resolved from current user identity helpers at runtime
- Not a direct Doctrine relation to `Users` entity

## 📦 Key Dependencies

### Symfony Framework
- symfony/framework-bundle, symfony/console, symfony/security-bundle
- symfony/form, symfony/validator, symfony/translation
- symfony/doctrine-messenger for async messaging

### Data Management
- doctrine/orm, doctrine/doctrine-bundle, doctrine/doctrine-migrations-bundle
- phpstan/phpdoc-parser for advanced type analysis

### External Services
- kreait/firebase-php - Firebase Cloud Messaging
- stripe/stripe-php - Payment processing
- league/oauth2-google - Google OAuth2 authentication
- mailjet/mailjet-apiv3-php - Email delivery
- dompdf/dompdf - PDF generation

### Frontend
- symfony/stimulus-bundle - Modern JS interactions
- symfony/asset-mapper - Asset management
- knplabs/knp-paginator-bundle - Pagination

## 🌍 Environment Variables (Key)

Essential variables needed in `.env.local`:
- `DATABASE_URL` - Database connection string
- `DEFAULT_URI` - Public base URL
- `FCM_PROJECT_ID` - Firebase Cloud Messaging project ID
- `BLOG_TRANSLATION_PROVIDER_URL` - Translation service URL
- `BLOG_TRANSLATION_PROVIDER_API_KEY` - Translation service API key
- Stripe, Google, and other service credentials as needed

## 📝 Database Schema

The application manages the following main entities:
- **Users** - User accounts with roles
- **Role** - User permission roles
- **Hebergement** - Accommodations with rooms (Chambre)
- **Activite** - Activities and experiences
- **Destination** - Geographic destinations
- **Voyage** - Trip/vacation records
- **Reservation** - Accommodation bookings
- **ReservationActivite** - Activity bookings
- **Blog** - Blog posts with author tracking
- **Commentaire** - Comments on blog posts
- **BlogRating** - Ratings on blog posts
- **Avis** - Reviews with TypeAvis classification
- **Reclamation** - Support tickets and complaints
- **Transport** - Transportation options

Database migrations are automatically managed in the `migrations/` directory.

## 🔄 Common Workflows

### Deploying a New Version
1. Update code and commit
2. Run tests: `php bin/phpunit`
3. Check code quality: `php bin/phpstan analyze`
4. Run migrations: `php bin/console doctrine:migrations:migrate`
5. Clear cache: `php bin/console cache:clear`

### Adding a New Feature
1. Create entity in `src/Entity/`
2. Create migration: `php bin/console make:migration`
3. Create controller in `src/Controller/`
4. Create forms if needed in `src/Form/`
5. Add templates in appropriate `templates/` subdirectory
6. Add tests in `tests/`
7. Update routes in `config/routes.yaml`

### Managing Permissions
1. Define roles in database through admin interface
2. Link users to roles via `Users` entity
3. Update `access_control` rules in `security.yaml`
4. Use `@IsGranted` annotation in controllers for fine-grained access

## 📚 Documentation Files

- **Copilot Instructions** - See `.github/copilot-instructions.md` for development guidelines
- **Configuration** - Key settings in `config/services.yaml` and `config/packages/`
- **Migrations** - Schema evolution tracked in `migrations/` directory

## 🤝 Contributing

When contributing to this project:
1. Follow the established module-based organization
2. Include proper validation at both entity and controller levels
3. Update tests for any new functionality
4. Run linting checks before committing
5. Keep service configuration parameterized


