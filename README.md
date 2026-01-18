# Association website

Website template for associations with:

- **Public frontend**: Events, articles, photo galleries for visitors
- **Admin panel**: Content management for members

## Tech stack

| Layer               | Technology                                    |
|---------------------|-----------------------------------------------|
| Backend framework   | Laravel 11+                                   |
| Admin panel         | Filament 3                                    |
| Frontend bridge     | Inertia.js                                    |
| Frontend framework  | Vue 3 (Composition API with `<script setup>`) |
| Frontend language   | TypeScript (strict mode)                      |
| Build tool          | Vite 5                                        |
| CSS framework       | Tailwind CSS                                  |
| State management    | Pinia                                         |
| Database (dev/prod) | PostgreSQL 16                                 |
| Database (testing)  | SQLite in-memory                              |
| Containerization    | Docker + Docker Compose                       |

## Requirements

- Docker
- Docker Compose
- Make

## Installation

1. Clone the repository:
   ```bash
   git clone <repository-url>
   cd <project-folder>
   ```

2. Copy the environment file and configure:
   ```bash
   cp src/.env.example src/.env
   ```

3. Configure your association in `.env`:
   ```env
   APP_NAME="Your association"
   APP_DESCRIPTION="Brief description of your association"
   ```

4. Start the containers:
   ```bash
   make up
   ```

5. Install dependencies (inside container):
   ```bash
   make shell
   composer install
   npm install
   ```

6. Generate an application key and run migrations:
   ```bash
   php artisan key:generate
   php artisan migrate --seed
   ```

7. Build frontend assets:
   ```bash
   npm run build
   ```

## Commands

| Command          | Description                        |
|------------------|------------------------------------|
| `make up`        | Start containers                   |
| `make down`      | Stop containers                    |
| `make shell`     | Enter PHP container                |
| `make test`      | Run all tests                      |
| `make test-unit` | Run unit tests only                |
| `make check`     | Run ALL linters (PHP + JS + types) |
| `make fresh`     | Fresh migration with seeders       |

## Project structure

```
src/app/
├── Domain/           # Entities, Value Objects, Enums, Exceptions, Repository Interfaces
├── Application/      # DTOs, Service Interfaces
├── Infrastructure/   # Eloquent Models, Repositories, External Services
├── Http/             # Controllers, Middleware
├── Filament/         # Admin Resources, Widgets
├── Policies/         # Authorization Policies
└── Providers/        # Service Providers

resources/js/
├── pages/            # Inertia pages (Home, Events, Articles, Gallery, About)
├── components/       # Vue components (ui, layout, events, articles, gallery)
├── composables/      # Vue composables
├── stores/           # Pinia stores
├── types/            # TypeScript types
├── locales/          # i18n translations
├── layouts/          # Page layouts
└── utils/            # Utility functions

tests/
├── Unit/             # Unit tests (Domain, Application)
├── Integration/      # Integration tests (Infrastructure)
└── Feature/          # Feature tests (Controllers)
```

## Architecture

This project follows **Clean Architecture** principles:

```
Domain → Application → Infrastructure → Presentation
```

- **Domain**: Business entities, value objects, and repository interfaces
- **Application**: DTOs and service interfaces
- **Infrastructure**: Eloquent implementations, external services
- **Presentation**: Controllers, Filament resources, Vue components

## Contributing

1. **TDD**: Write tests BEFORE implementation
2. **Strict typing**: `declare(strict_types=1)` in PHP, `strict: true` in TypeScript
3. **No `any` type** in TypeScript
4. **All code in English**: variables, functions, classes, comments, commits
5. **SOLID principles**: Apply consistently

## License

Proprietary – All rights reserved.
