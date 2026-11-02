---
id: core.panel.updates
title: Actualizar módulos y el core
summary: Comprueba si hay versiones nuevas de los módulos, aplícalas con el modo mantenimiento y consulta qué versión del core tienes.
category: modules
audience: panel
order: 20
keywords: [actualizar, actualizaciones, versión nueva, nueva versión, actualizar módulos, actualizar todos, core, versión, notas de la versión, historial, novedades]
related: [core.panel.maintenance, core.panel.modules]
conditions:
  permissions:
    all: [updates.apply]
---
Las actualizaciones están en el grupo **Sistema** del menú lateral.

## Actualizar los módulos

1. Activa el modo mantenimiento.
2. Abre **Sistema** y elige **Actualizaciones de módulos**.
3. Pulsa **Comprobar actualizaciones**.
4. En **Actualizaciones disponibles**, pulsa **Ver detalles** para leer las notas de la versión y comprobar que es compatible.
5. Pulsa **Actualizar** en un módulo, o **Actualizar todos**.
6. Espera a que termine. Mientras, el botón dice «Actualizando...», y la página se actualiza sola y te avisa al acabar.
7. Desactiva el modo mantenimiento.

> [!IMPORTANT]
> Sin el modo mantenimiento activo, los botones de actualizar están desactivados.

> [!WARNING]
> Las actualizaciones de tipo **Mayor** pueden cambiar cómo funciona el módulo. Lee siempre sus notas antes de actualizar.

Si una actualización falla, la web vuelve a la versión anterior del módulo y te dice el error. **Historial de actualizaciones** guarda cada intento.

En **Versiones no compatibles** salen las versiones nuevas que esta web aún no puede usar. No tienen botón de actualizar.

## El core

**Sistema → Actualización del core** muestra la **Versión desplegada** y si hay cambios nuevos en camino. Pulsa **Comprobar actualizaciones** para saberlo.

Esta página no instala nada: las versiones nuevas del core llegan solas. Si hay cambios en camino y el modo mantenimiento está desactivado, la página te avisa.

[[action:filament.admin.pages.module-updates-page|Ver las actualizaciones de módulos]]
[[action:filament.admin.pages.core-updates-page|Ver la versión del core]]
