---
id: core.panel.menu
title: Editar el menú del sitio
summary: Añade, ordena y oculta los enlaces de la cabecera y del pie de página de la web.
category: content
audience: panel
order: 60
keywords: [menú, navegación, enlaces, cabecera, pie de página, submenú, ordenar menú, ocultar enlace, añadir enlace, enlace externo]
related: [core.panel.legal-pages, core.panel.site-settings]
conditions:
  permissions:
    all: [menu_items.view_any]
---
El menú de la web tiene dos partes: la **Cabecera** y el **Pie de página**. Cada una es una pestaña de la lista.

## Añadir un enlace

1. En el menú lateral, abre **Administración** y elige **Navegación**. La página se llama «Elementos de menú».
2. Pulsa **Crear elemento de menú**.
3. Elige la **Ubicación**: cabecera o pie de página.
4. Para un submenú, elige el **Elemento padre**. Déjalo vacío para un enlace de primer nivel.
5. Escribe la **Etiqueta**: el texto del enlace.
6. Elige la **Página** de destino. Para un enlace a otra web, escribe la **URL externa**.
7. En **Abrir en**, elige **Misma pestaña** o **Nueva pestaña**.
8. Pon el **Orden**: los números más bajos salen primero.
9. Elige la **Visibilidad**:
   - **Público**: lo ve todo el mundo.
   - **Solo autenticados**: solo quien ha iniciado sesión.
   - **Solo invitados**: solo quien no ha iniciado sesión.
   - **Por permiso**: solo quien tenga al menos uno de los **Permisos requeridos**.
10. Deja **Activo** activado y pulsa **Crear**.

## Ocultar o quitar un enlace

- **Ocultar**: desactiva **Activo** y guarda. El enlace deja de salir, pero lo conservas.
- **Borrar**: ábrelo, pulsa **Borrar** y confirma.

> [!IMPORTANT]
> Los enlaces que trae la web de serie (en la columna **Módulo** pone «Core») no se pueden borrar. Si no los quieres, desactívalos.

> [!TIP]
> Si al editar un enlace ves «Página no disponible», su página ya no existe, por ejemplo porque se deshabilitó un módulo. Elige otra página o desactiva el enlace.

[[action:filament.admin.resources.menu-items.create|Añadir un enlace|if=menu_items.create]]
[[action:filament.admin.resources.menu-items.index|Ver el menú]]
