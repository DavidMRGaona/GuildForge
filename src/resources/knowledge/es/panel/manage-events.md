---
id: core.panel.manage-events
title: Crear y publicar eventos
summary: Crea un evento con sus fechas, su lugar y sus precios, y publícalo para que salga en el calendario.
category: content
audience: panel
order: 10
keywords: [evento, eventos, crear evento, nuevo evento, publicar, calendario, fecha, precio, ubicación, imagen, borrador, ocultar evento, borrar evento]
related: [core.panel.tags, core.panel.manage-articles]
conditions:
  permissions:
    all: [events.create]
---
Los eventos salen en el calendario y en la lista de eventos de la web, pero solo cuando están publicados.

## Crear un evento

1. En el menú lateral, abre **Contenido** y elige **Eventos**.
2. Pulsa **Crear evento**.
3. Escribe el **Título**. El **Slug**, la parte final de la dirección de la página, se rellena solo.
4. En **Fecha y lugar**, elige la **Fecha de inicio** y la **Fecha de fin**. El fin tiene que ser posterior al inicio. Si quieres, rellena la **Ubicación**.
5. Escribe la **Descripción**.
6. Si quieres, sube una **Imagen** (hasta 2 MB) y añade **Etiquetas adicionales**.
7. Elige la **Categoría**. Si quieres, rellena los **Precios**.
8. En **Enlaces de descarga** puedes añadir documentos, como reglamentos o listas, con su **Etiqueta** y su **URL**.
9. Activa **Publicado** si ya quieres que se vea.
10. Pulsa **Crear**. Con **Crear y crear otro** guardas y empiezas otro evento.

> [!TIP]
> Deja **Publicado** desactivado para guardarlo como borrador. Nadie lo verá en la web hasta que lo actives.

## Cambiar o quitar un evento

- **Editar**: pulsa el evento en la lista, cambia lo que necesites y pulsa **Guardar cambios**.
- **Ocultar**: desactiva **Publicado** y guarda. El evento deja de verse, pero no se pierde.
- **Borrar**: dentro del evento, pulsa **Borrar** y confirma.

> [!WARNING]
> Borrar un evento lo elimina del todo. Si solo quieres que no se vea, mejor desactiva **Publicado**.

## Encontrar eventos

La lista se ordena por fecha, con los más lejanos primero. Usa el buscador para encontrar un título o un lugar, y el filtro **Publicado** para ver solo los publicados o los borradores.

Si tu web tiene módulos de actividades, como inscripciones, el formulario puede traer secciones extra al final.

[[action:filament.admin.resources.events.create|Crear un evento]]
