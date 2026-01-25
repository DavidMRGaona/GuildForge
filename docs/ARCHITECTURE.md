# GuildForge architecture

This document describes the Clean Architecture implementation used in GuildForge.

## Layers overview

```
Domain (innermost) → Application → Infrastructure → Presentation (outermost)
```

- **Domain**: Business rules, entities, value objects, domain events
- **Application**: Use cases, service interfaces, DTOs
- **Infrastructure**: Framework code, database, external services
- **Presentation**: HTTP controllers, API resources, admin panel

**Rule**: Inner layers never depend on outer layers.

## Folder structure

```
app/
├── Domain/                 # Domain layer (innermost)
│   ├── Entities/          # Business objects with identity
│   ├── ValueObjects/      # Immutable, self-validating types
│   ├── Events/            # Domain events (UserRegistered, etc.)
│   ├── Repositories/      # Repository interfaces (NOT implementations)
│   └── Exceptions/        # Domain-specific exceptions
│
├── Application/           # Application layer
│   ├── DTOs/              # Data transfer objects
│   │   └── Response/      # Response DTOs for API/views
│   ├── Services/          # Service interfaces (Query, Command)
│   └── Factories/         # DTO factories interfaces
│
├── Infrastructure/        # Infrastructure layer
│   ├── Persistence/
│   │   └── Eloquent/
│   │       ├── Models/    # Eloquent models (NOT domain entities)
│   │       └── Repositories/  # Repository implementations
│   └── Services/          # Service implementations
│
├── Http/                  # Presentation layer (interface adapters)
│   ├── Controllers/       # Thin controllers
│   ├── Requests/          # FormRequest validation
│   ├── Resources/         # JSON/API resources
│   └── Middleware/        # HTTP middleware
│
├── Filament/             # Admin presentation layer
│   ├── Resources/        # Filament CRUD resources
│   ├── Pages/            # Custom admin pages
│   └── Widgets/          # Dashboard widgets
│
└── Providers/            # Laravel service providers (bootstrapping)
```

## Why `Http` instead of `Presentation`?

The folder is named `app/Http` following Laravel conventions rather than `app/Presentation` for these reasons:

1. **Laravel ecosystem**: Tools, packages, and documentation expect the `Http/` convention
2. **Developer familiarity**: Laravel developers immediately understand `Http/Controllers`
3. **Framework integration**: Artisan commands, route generation, and IDE support assume `Http/`
4. **Pragmatism over purity**: Renaming adds friction without improving architecture

The name doesn't change the architectural role. `Http/` IS the presentation layer:
- Controllers must be thin (delegate to services)
- No business logic in controllers
- Controllers depend on Application interfaces, never Infrastructure directly

## Layer dependency rules

```
Domain → Application → Infrastructure → Presentation
   ↑          ↑              ↑
   └──────────┴──────────────┴── Dependencies point inward
```

| Layer | Can depend on | Cannot depend on |
|-------|---------------|------------------|
| Domain | Nothing | Application, Infrastructure, Http |
| Application | Domain | Infrastructure, Http |
| Infrastructure | Domain, Application | Http |
| Http (Presentation) | Domain, Application, Infrastructure | - |

## Query services pattern

Query services handle read operations and return response DTOs (CQRS-lite approach).

```php
// Interface in Application/Services/
interface EventQueryServiceInterface
{
    /** @return array<int, EventResponseDTO> */
    public function getUpcomingEvents(int $limit = 10): array;
    public function findPublishedBySlug(string $slug): ?EventResponseDTO;
}

// Implementation in Infrastructure/Services/
final readonly class EventQueryService implements EventQueryServiceInterface
{
    public function __construct(
        private ResponseDTOFactoryInterface $factory,
    ) {}

    public function getUpcomingEvents(int $limit = 10): array
    {
        return EventModel::query()
            ->where('is_published', true)
            ->where('start_date', '>=', now())
            ->orderBy('start_date')
            ->limit($limit)
            ->get()
            ->map(fn (EventModel $model) => $this->factory->createEventDTO($model))
            ->all();
    }
}
```

Controllers depend on Query Service interfaces, not Eloquent models:

```php
public function __construct(
    private readonly EventQueryServiceInterface $eventQuery,
) {}
```

## Command services pattern

Command services handle write operations (mutations) and often dispatch domain events.

```php
// Interface in Application/Services/
interface AuthServiceInterface
{
    public function register(RegisterUserDTO $dto): UserResponseDTO;
    public function login(string $email, string $password): UserResponseDTO;
}

// Implementation in Infrastructure/Services/
final readonly class AuthService implements AuthServiceInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private ResponseDTOFactoryInterface $factory,
    ) {}

    public function register(RegisterUserDTO $dto): UserResponseDTO
    {
        $user = $this->userRepository->create($dto);
        event(new UserRegistered($user->id()->toString(), $user->email()));
        return $this->factory->createUserDTO($user);
    }
}
```

## Repository pattern

Repositories abstract data access and convert between domain entities and persistence models.

```php
// Interface in Domain/Repositories/
interface UserRepositoryInterface
{
    public function findById(UserId $id): ?User;
    public function findModelById(UserId $id): ?UserModel;
    public function findByEmail(string $email): ?UserModel;
    public function save(User $user): void;
    public function create(CreateUserDTO $dto): User;
}

// Implementation in Infrastructure/Persistence/Eloquent/Repositories/
final readonly class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(UserId $id): ?User
    {
        $model = UserModel::find($id->toString());
        return $model ? $this->toDomain($model) : null;
    }

    public function create(CreateUserDTO $dto): User
    {
        $model = UserModel::create([
            'id' => (string) Str::uuid(),
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => Hash::make($dto->password),
        ]);

        return $this->toDomain($model);
    }

    private function toDomain(UserModel $model): User
    {
        return new User(
            id: new UserId($model->id),
            name: $model->name,
            email: $model->email,
            displayName: $model->display_name,
            pendingEmail: $model->pending_email,
            avatarPublicId: $model->avatar_public_id,
            emailVerifiedAt: $model->email_verified_at,
            anonymizedAt: $model->anonymized_at,
            createdAt: $model->created_at,
            updatedAt: $model->updated_at,
        );
    }
}
```

**Key principles:**
- Repository interfaces in Domain layer (no framework dependencies)
- Implementations in Infrastructure layer (Eloquent-specific)
- Convert Eloquent models to domain entities using `toDomain()` method
- Some methods return models directly for framework compatibility (auth, notifications)

## Data flow

```
Request → Controller → QueryService → Factory → ResponseDTO → Resource → JSON/Inertia
```

1. Controller receives request
2. Calls query service method
3. Query service uses factory to create DTOs
4. Returns array of DTOs to controller
5. Controller wraps in resource for JSON transformation

## Domain entities

Domain entities are business objects with identity, located in `app/Domain/Entities/`.

```php
// Domain/Entities/User.php
final class User
{
    public function __construct(
        private readonly UserId $id,
        private readonly string $name,
        private readonly string $email,
        private readonly ?string $displayName = null,
        private readonly ?string $pendingEmail = null,
        private readonly ?string $avatarPublicId = null,
        private readonly ?DateTimeImmutable $emailVerifiedAt = null,
        private readonly ?DateTimeImmutable $anonymizedAt = null,
        private readonly ?DateTimeImmutable $createdAt = null,
        private readonly ?DateTimeImmutable $updatedAt = null,
    ) {}

    public function id(): UserId { return $this->id; }
    public function name(): string { return $this->name; }
    public function email(): string { return $this->email; }
    public function isEmailVerified(): bool { return $this->emailVerifiedAt !== null; }
    public function isAnonymized(): bool { return $this->anonymizedAt !== null; }
    public function getDisplayableName(): string { return $this->displayName ?? $this->name; }
}
```

**Key characteristics:**
- Immutable (`final class` with `readonly` properties)
- Value objects for identity (`UserId`, `EventId`, etc.)
- No framework dependencies
- Business logic methods (e.g., `isEmailVerified()`, `getDisplayableName()`)
- Use `DateTimeImmutable` for dates

## Domain events

Domain events are dispatched when significant changes occur:

```php
// Domain/Events/UserRegistered.php
final readonly class UserRegistered
{
    public function __construct(
        public string $userId,
        public string $email,
    ) {}
}
```

| Event | When dispatched |
|-------|-----------------|
| `UserRegistered` | After user registration |
| `UserLoggedIn` | After successful login |
| `UserLoggedOut` | After logout |
| `UserPasswordChanged` | After password change |
| `UserProfileUpdated` | After profile update |

## Pragmatic decisions

Not all Clean Architecture "violations" are problems. Some are pragmatic trade-offs:

### UserModel implements FilamentUser

```php
final class UserModel extends Model implements FilamentUser
```

**Why this is OK**: Avoids duplicating user entities. Filament is admin tooling, not a domain concern.

### Filament handling models directly

Filament resources write to Eloquent models directly for simple CRUD. Complex business logic should still use services.

### When to use strict architecture

Use proper Clean Architecture (services, DTOs, domain events) when:
- Complex business logic (multi-step workflows)
- Domain events needed (UserRegistered, etc.)
- External integrations (payment gateways, email)
- Public APIs
- Testing critical paths

## Application service pattern

Application services orchestrate business operations and provide a facade for complex domain operations. They depend on repositories and other domain services.

### Example: User service

```php
// Interface in Application/Services/
interface UserServiceInterface
{
    public function canAccessPanel(string $userId): bool;
    public function anonymize(string $userId): void;
    public function isAdmin(string $userId): bool;
}

// Implementation in Infrastructure/Services/
final readonly class UserService implements UserServiceInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RoleRepositoryInterface $roleRepository,
    ) {}

    public function canAccessPanel(string $userId): bool
    {
        $user = $this->userRepository->findById(new UserId($userId));
        if (!$user) {
            return false;
        }

        // Check if user has admin or editor roles
        $roles = $this->roleRepository->getRolesForUser(new UserId($userId));
        return $roles->hasAnyRole(['admin', 'editor']);
    }

    public function anonymize(string $userId): void
    {
        $model = $this->userRepository->findModelById(new UserId($userId));
        if (!$model) {
            throw UserNotFoundException::withId($userId);
        }

        // Anonymize user data (GDPR compliance)
        $model->name = 'Anonymized User';
        $model->email = "deleted_{$userId}@example.com";
        $model->display_name = null;
        $model->avatar_public_id = null;
        $model->anonymized_at = now();
        $model->save();
    }
}
```

### Example: Role service

```php
// Interface in Application/Authorization/Services/
interface RoleServiceInterface
{
    public function all(): array;
    public function findById(string $id): ?RoleResponseDTO;
    public function create(CreateRoleDTO $dto): RoleResponseDTO;
    public function update(string $id, UpdateRoleDTO $dto): RoleResponseDTO;
    public function delete(string $id): void;
    public function assignPermissions(string $roleId, array $permissionKeys): void;
    public function syncPermissions(string $roleId, array $permissionKeys): void;
}

// Implementation in Infrastructure/Authorization/Services/
final readonly class RoleService implements RoleServiceInterface
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository,
        private PermissionRepositoryInterface $permissionRepository,
        private ResponseDTOFactoryInterface $factory,
    ) {}

    public function syncPermissions(string $roleId, array $permissionKeys): void
    {
        $permissionIds = $this->permissionRepository->findIdsByKeys($permissionKeys);
        $this->roleRepository->syncPermissions(new RoleId($roleId), $permissionIds);
    }
}
```

**Use application services when:**
- Complex business logic spans multiple entities/repositories
- Operations coordinate between multiple domain services
- Framework-specific operations needed (accessing Eloquent models directly)
- Business operations that don't fit cleanly into a single repository

## Authorization system

The application implements a custom role-based permission system (RBAC) with clean architecture principles.

### Architecture

```
User → UserRole (pivot) → Role → RolePermission (pivot) → Permission
```

**Key components:**

1. **Domain entities**: `User`, `Role`, `Permission` (in `app/Domain/Authorization/Entities/`)
2. **Value objects**: `UserId`, `RoleId`, `PermissionId`, `RoleName`, `PermissionKey`
3. **Repositories**: `UserRepositoryInterface`, `RoleRepositoryInterface`, `PermissionRepositoryInterface`
4. **Services**: `AuthorizationServiceInterface`, `RoleServiceInterface`, `UserServiceInterface`

### Authorization service

Central service for checking permissions and roles:

```php
// Interface in Application/Authorization/Services/
interface AuthorizationServiceInterface
{
    public function can(object $user, string $permissionKey): bool;
    public function canAny(object $user, array $permissionKeys): bool;
    public function hasRole(object $user, string $roleName): bool;
    public function hasAnyRole(object $user, array $roleNames): bool;
    public function assignRole(object $user, string $roleName): void;
    public function syncRoles(object $user, array $roleNames): void;
}
```

**Usage in policies:**

```php
use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;

class ArticlePolicy
{
    use AuthorizesWithPermissions;

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'articles.create');
    }

    public function update(User $user, Article $article): bool
    {
        return $this->checkAnyPermission($user, ['articles.update.all', 'articles.update.own']);
    }
}
```

### Permission registry

Modules and core features register permissions for discovery and synchronization:

```php
// Interface in Application/Authorization/Services/
interface PermissionRegistryInterface
{
    public function register(PermissionDefinitionDTO $permission): void;
    public function all(): array;
}

// Usage in core or module service providers
$permissionRegistry->register(new PermissionDefinitionDTO(
    key: 'articles.create',
    name: 'Create articles',
    description: 'Allows creating new articles',
    group: 'articles'
));
```

**Synchronization command:**

```bash
php artisan permissions:sync
```

This command discovers all registered permissions and creates/updates them in the database.

### Protected roles

Some roles (e.g., `admin`) are marked as `is_protected = true` and cannot be deleted. This prevents accidental removal of critical system roles.

## Service binding

```php
// app/Providers/AppServiceProvider.php
$this->app->singleton(SettingsServiceInterface::class, SettingsService::class);
$this->app->bind(EventRepositoryInterface::class, EloquentEventRepository::class);
$this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
$this->app->bind(UserServiceInterface::class, UserService::class);

// app/Providers/AuthorizationServiceProvider.php
$this->app->singleton(PermissionRegistryInterface::class, PermissionRegistry::class);
$this->app->bind(AuthorizationServiceInterface::class, AuthorizationService::class);
$this->app->bind(RoleServiceInterface::class, RoleService::class);
$this->app->bind(PermissionRepositoryInterface::class, EloquentPermissionRepository::class);
$this->app->bind(RoleRepositoryInterface::class, EloquentRoleRepository::class);
```
