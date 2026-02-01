# CI/CD para distribución de módulos

Esta guía explica cómo configurar y usar el sistema de CI/CD para distribuir módulos de GuildForge via GitHub Releases.

## Arquitectura

El sistema usa un **workflow reutilizable centralizado** en el repositorio principal (`runesword`) que es llamado desde cada repositorio de módulo:

```
┌─────────────────────────────────────────────────────────┐
│  Repositorio principal (runesword)                       │
│  .github/workflows/reusable-module-release.yml          │
│  - Lógica compartida de validación, build, release      │
└────────────────────────────┬────────────────────────────┘
                             │ calls
         ┌───────────────────┼───────────────────┐
         │                   │                   │
         ▼                   ▼                   ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ guildforge-     │ │ guildforge-     │ │ guildforge-     │
│ announcements   │ │ tournaments     │ │ memberships     │
│ release.yml     │ │ release.yml     │ │ release.yml     │
│ (15 líneas)     │ │ (15 líneas)     │ │ (15 líneas)     │
└─────────────────┘ └─────────────────┘ └─────────────────┘
```

## Crear un release

Para crear un nuevo release de un módulo:

```bash
# 1. Actualiza la versión en module.json
{
  "name": "announcements",
  "version": "1.0.0",  # ← Incrementar según semver
  ...
}

# 2. Commit los cambios
git add module.json
git commit -m "chore: bump version to 1.0.0"

# 3. Crear y push el tag
git tag v1.0.0
git push origin main --tags
```

El workflow automáticamente:
1. Valida que `module.json` existe y tiene formato correcto
2. Verifica que el tag coincide con la versión en `module.json`
3. Ejecuta linting (Pint) y tests si existen
4. Genera un ZIP con la estructura correcta
5. Calcula checksum SHA256
6. Crea un GitHub Release con los assets

## Configurar un nuevo módulo

### 1. Crear el workflow en el módulo

Crea `.github/workflows/release.yml` en el repositorio del módulo:

```yaml
name: Module release

on:
  push:
    tags: ['v*.*.*']

jobs:
  release:
    uses: DavidMRGaona/runesword/.github/workflows/reusable-module-release.yml@main
    with:
      module_name: 'NOMBRE_DEL_MODULO'  # ← Cambiar
    permissions:
      contents: write
```

### 2. Asegurar que module.json es válido

El `module.json` debe tener:

```json
{
  "name": "nombre-del-modulo",
  "version": "1.0.0",
  "description": "Descripción del módulo",
  "dependencies": []
}
```

**Importante:** El `name` en `module.json` debe coincidir exactamente con el `module_name` del workflow.

## Estructura del ZIP generado

```
announcements-1.0.0/
├── module.json
├── src/
├── database/
├── resources/
├── routes/
├── lang/
├── config/
└── README.md

❌ Excluidos automáticamente:
- tests/
- .git/
- .github/
- node_modules/
- .env*
- *.log
- .phpunit*
- phpunit.xml
```

## Instalar un módulo

1. Ve a la página de Releases del módulo en GitHub
2. Descarga el archivo `{module_name}-{version}.zip`
3. En el panel de administración de GuildForge → Módulos → Instalar desde ZIP
4. Sube el archivo ZIP

### Verificar integridad (opcional)

```bash
# Descarga también el archivo .sha256
sha256sum -c announcements-1.0.0.zip.sha256
```

## Prereleases

Las versiones con sufijo (ej: `1.0.0-beta.1`, `2.0.0-rc.1`) se marcan automáticamente como prerelease en GitHub.

## Opciones del workflow

El workflow reutilizable acepta estos parámetros:

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `module_name` | string | (requerido) | Nombre del módulo en kebab-case |
| `php_version` | string | `8.4` | Versión de PHP para tests |
| `run_tests` | boolean | `true` | Ejecutar tests antes del release |

Ejemplo con opciones:

```yaml
jobs:
  release:
    uses: DavidMRGaona/runesword/.github/workflows/reusable-module-release.yml@main
    with:
      module_name: 'my-module'
      php_version: '8.3'
      run_tests: false
    permissions:
      contents: write
```

## Módulos configurados

| Módulo | Estado |
|--------|--------|
| guildforge-announcements | ✅ Configurado |
| guildforge-cookie-consent | ✅ Configurado |
| guildforge-event-registrations | ✅ Configurado |
| guildforge-game-tables | ✅ Configurado |
| guildforge-memberships | ✅ Configurado |
| guildforge-tournaments | ✅ Configurado |

## Troubleshooting

### El workflow falla en validación

**Error:** `module.json not found`
- Asegúrate de que `module.json` existe en la raíz del repositorio

**Error:** `Module name mismatch`
- El `name` en `module.json` debe coincidir exactamente con `module_name` del workflow

**Error:** `Tag version doesn't match module.json version`
- El tag (sin el prefijo `v`) debe coincidir con la versión en `module.json`
- Tag `v1.0.0` → version `"1.0.0"`

### Los tests fallan

- Verifica que `composer.json` tiene las dependencias correctas
- Asegúrate de que los tests pasan localmente antes de crear el tag

### El release no tiene assets

- Revisa los logs del job "Create release" para ver errores
- Verifica que el workflow tiene permisos `contents: write`
