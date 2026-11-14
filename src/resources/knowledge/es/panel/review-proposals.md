---
id: core.panel.review-proposals
title: Revisar propuestas
summary: Revisa los eventos y artículos que la gente propone desde la web, retócalos y apruébalos, devuélvelos o recházalos.
category: content
audience: panel
order: 25
keywords: [propuesta, propuestas, revisar, revisión, aprobar, publicar, devolver, rechazar, motivo, en revisión, propuesto por, nota, pendientes, peticiones, módulos, aplicar, categoría, etiquetas]
related: [core.panel.manage-events, core.panel.manage-articles]
conditions:
  permissions:
    any: [events.review, articles.review]
---
Si tu web permite propuestas, la gente puede proponer eventos y artículos desde la web. Ninguna propuesta se publica hasta que alguien de la administración la aprueba.

## Encontrar las propuestas

- En el **Escritorio**, el cuadro **Propuestas pendientes** muestra las que esperan revisión, de la más antigua a la más reciente. Solo aparece mientras haya alguna.
- En el menú lateral, **Eventos** y **Artículos** muestran un número con las propuestas en revisión.
- Dentro de cada lista, la pestaña **En revisión** muestra solo las que esperan respuesta.
- El filtro **Propuesta** muestra las **Devueltas** y las **Rechazadas**.

Los borradores que la persona aún no ha enviado no salen en el panel.

## Revisar una propuesta

1. Pulsa la propuesta en la lista.
2. Arriba, en **Propuesta desde la web**, ves quién la propuso, cuándo la envió y la **Nota para la administración** que dejó. Si ya la devolviste antes, también ves el **Último motivo**.
3. Elige la **Categoría**: quien propone solo marca las **Etiquetas adicionales**, y sin categoría no se puede aprobar. Si la devuelves, la categoría se mantiene aunque la persona edite la propuesta.
4. Si hace falta, retoca lo que quieras del formulario. La persona que la propuso no recibe el detalle de los cambios.
5. Elige qué hacer con ella:
   - **Aprobar y publicar**: guarda los cambios del formulario y la publica tal como está.
   - **Devolver**: vuelve a su autor como borrador para que la corrija. Escribe el **Motivo**, que verá quien la propuso.
   - **Rechazar**: la cierra sin publicarla. Escribe el **Motivo** y marca la casilla de confirmación.

Quien la propuso recibe un aviso en cada caso.

## Peticiones de módulos

En los eventos, la persona que propone puede pedir cosas a los módulos instalados, como inscripciones o una reserva de espacio. Las ves en la sección **Peticiones de módulos** de la ficha:

- Cada petición muestra los valores pedidos. Puedes cambiarlos antes de aprobar.
- El interruptor **Aplicar al aprobar** decide si se aplica. Desmárcalo para aprobar el evento sin esa petición.
- Al pulsar **Aprobar y publicar**, cada petición marcada crea su configuración en el módulo con esos valores. Luego la ajustas en la pestaña del módulo.
- Si una petición no se puede aplicar, no se aprueba nada: la propuesta sigue en revisión y el aviso te dice cuál ha fallado. Si su módulo ya no está activo, desmárcala para aprobar sin ella.

> [!IMPORTANT]
> Mientras la propuesta no esté aprobada, el interruptor **Publicado** está desactivado: solo se publica con **Aprobar y publicar**. Después ya es un evento o un artículo como cualquier otro, y puedes ocultarlo o volver a publicarlo.

> [!TIP]
> En los eventos, las pestañas y secciones de los módulos, como las inscripciones, aparecen cuando apruebas la propuesta.

> [!WARNING]
> Una propuesta rechazada no se puede volver a enviar. Si solo le falta algo, mejor devuélvela.

[[action:filament.admin.resources.events.index|Ver los eventos]]
