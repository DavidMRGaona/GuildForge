---
id: core.panel.help-guides
title: Escribir las guías de ayuda del sitio
summary: Crea guías propias para tu asociación y oculta o destaca las guías oficiales, sin tocar nada más.
category: content
audience: panel
order: 70
keywords: [guías, guia, ayuda, base de conocimiento, normas, normas de la casa, escribir una guía, ocultar guía, destacar guía, útil, votos, búsquedas sin resultados]
related: [core.panel.tour]
conditions:
  permissions:
    all: [knowledge.manage]
---
La ayuda trae guías oficiales para cada parte de la web y del panel. Además, {{site_name}} puede escribir las suyas, como unas normas de la casa o cómo se reserva el local, y decidir cuáles de las oficiales se ven.

## Ver las guías

En el menú lateral, abre **Contenido** y elige **Guías de ayuda**. La lista reúne todas:

- **Origen**: **Oficial** o **Módulo …** para las que vienen con la aplicación, y **Del sitio** para las vuestras.
- **Estado**: las vuestras están **Publicada** o en **Borrador**; las oficiales, **Visible** u **Oculta**.
- **Útil** y **No útil**: los votos de «¿Te ha sido útil?» de los últimos 30 días.

Debajo de la lista, **Búsquedas de ayuda sin resultados** enseña lo que la gente buscó sin encontrar nada. Es una buena pista de qué guía falta o de qué palabras de búsqueda añadir.

## Escribir una guía

1. Pulsa **Crear guía de ayuda**.
2. Escribe el **Título** y un **Resumen** de una frase.
3. Elige el **Público**: **Web (personas con cuenta)** o **Panel de administración**. Después elige su **Categoría**.
4. En **Palabras de búsqueda**, añade lo que la gente escribiría para encontrarla.
5. Escribe el **Texto de la guía**. Con el panel lateral del editor puedes añadir:
   - un **Botón a una página**, que lleva a una página de la web o del panel;
   - un marcador, como **Nombre del sitio**, que se cambia solo si cambia el nombre.
6. En **Quién la ve**, deja todo vacío para que la vea todo el público elegido, o limítala con permisos, comprobaciones o **Socios**.
7. Deja marcada **Publicada** y pulsa **Crear**.

> [!TIP]
> Desmarca **Publicada** para guardarla como borrador. Nadie la verá hasta que la publiques.

El título se puede cambiar cuando quieras: los enlaces a la guía siguen funcionando.

## Ocultar o destacar una guía oficial

Las guías oficiales no se editan, pero puedes decidir cómo se ven en este sitio:

- **Ocultar**: nadie la ve, tampoco desde los botones de ayuda de las páginas. **Mostrar** la recupera.
- **Destacar**: elige **Destacada** para que salga en la portada de la ayuda, **No destacada** para quitarla de ahí, o **Como viene** para dejar lo que decide la guía.

## Borrar una guía del sitio

Pulsa **Borrar** en su fila, marca la casilla de confirmación y confirma. Se borran la guía, sus votos y sus enlaces, y no se puede deshacer. Si solo quieres retirarla un tiempo, desmarca **Publicada**.

## Si algo no sale bien

- **No encuentras una categoría**: la lista depende del **Público**. Elige primero el público.
- **Un botón no aparece en la guía**: la página ya no existe (por ejemplo, porque se desactivó su módulo) o el lector no cumple la condición del botón.
- **Una guía con una comprobación de un módulo ha desaparecido**: si el módulo se desactiva, la comprobación deja de pasar y la guía se oculta.

[[action:filament.admin.resources.guias-de-ayuda.create|Escribir una guía|if=knowledge.manage]]
[[action:filament.admin.resources.guias-de-ayuda.index|Ver las guías de ayuda]]
