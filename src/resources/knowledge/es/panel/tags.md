---
id: core.panel.tags
title: Organizar con etiquetas
summary: Crea categorías y etiquetas para clasificar eventos, artículos y galerías, y para que se puedan filtrar en la web.
category: content
audience: panel
order: 50
keywords: [etiquetas, etiqueta, categorías, categoría, clasificar, filtrar, subcategoría, tags, organizar, color]
related: [core.panel.manage-events, core.panel.manage-articles, core.panel.manage-galleries]
conditions:
  permissions:
    all: [tags.view_any]
---
Las etiquetas clasifican el contenido. En la web, los visitantes las usan para filtrar eventos, artículos y galerías.

## Categorías y etiquetas adicionales

Hay dos niveles:

- **Categorías**: las etiquetas sin **Etiqueta padre**. Todo evento, artículo y galería necesita una, en su campo **Categoría**.
- **Etiquetas adicionales**: las que tienen una **Etiqueta padre**. En la lista salen como «Subcategoría de…». Se añaden en el campo **Etiquetas adicionales**.

## Crear una etiqueta

1. En el menú lateral, abre **Catálogos** y elige **Etiquetas**.
2. Pulsa **Crear etiqueta**.
3. Escribe el **Nombre**. El **Slug** se rellena solo.
4. Para una etiqueta adicional, elige su **Etiqueta padre**. Para una categoría, déjalo vacío.
5. En **Aplica a**, marca dónde se puede usar: **Eventos**, **Artículos** o **Galerías**.
6. Si quieres, elige un **Color** y un **Orden**.
7. Pulsa **Crear**.

> [!IMPORTANT]
> La **Etiqueta padre** solo se elige al crear la etiqueta. Después ya no se puede cambiar.

## Borrar una etiqueta

Solo se puede borrar una etiqueta que no tenga subcategorías y que no use ningún evento, artículo ni galería. Si no ves **Borrar** en el menú **⋯** de su fila, quítala antes de ese contenido.

La lista enseña cuántos eventos, artículos y galerías usan cada etiqueta.

[[action:filament.admin.resources.tags.create|Crear una etiqueta|if=tags.create]]
[[action:filament.admin.resources.tags.index|Ver las etiquetas]]
