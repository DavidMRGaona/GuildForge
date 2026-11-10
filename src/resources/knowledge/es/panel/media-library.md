---
id: core.panel.media-library
title: Biblioteca de medios
summary: Mira qué imágenes hay guardadas, quién las usa y borra las que ya no usa nadie para liberar espacio.
category: content
audience: panel
order: 40
keywords: [biblioteca, medios, imágenes, archivos, sin usar, borrar, Cloudinary, espacio, huérfanas, fotos]
related: [core.panel.manage-galleries]
conditions:
  permissions:
    any: [media.view_any]
---
La biblioteca de medios muestra las imágenes guardadas en Cloudinary, qué registros las usan y cuáles ya no usa nadie. Sirve para liberar espacio sin miedo a romper una página.

## Ver la biblioteca

En el menú lateral, abre **Contenido** y elige **Biblioteca de medios**. Arriba ves cuántos archivos hay, el espacio que ocupan, cuántos están sin usar y cuándo se leyó Cloudinary por última vez.

- Cambia entre **Cuadrícula** y **Lista**.
- Filtra por **Origen** (eventos, artículos, galerías…) y por **Estado**.
- Pulsa **Ver ficha** en un archivo para ver su tamaño, sus dimensiones, su carpeta y los registros que lo usan. Si puedes editar uno de esos registros, la ficha te lleva a él con **Editar**.
- Pulsa **Actualizar** para que la biblioteca vuelva a leer Cloudinary. Tarda unos minutos en ponerse al día. También se actualiza sola cada día.

## Qué aparece y qué no

La biblioteca muestra las imágenes que suben la web y los módulos: eventos, artículos, galerías, hero slides, logos y favicon del sitio, y las de los módulos activos.

No aparecen los **avatares** de las cuentas ni nada que sea privado: no se muestran y nunca se pueden borrar desde aquí.

## Estados

| Estado | Qué significa |
|--------|---------------|
| **En uso** | Algún registro usa el archivo. No se puede borrar. |
| **Reciente** | Nadie lo usa, pero se subió hace menos de 7 días. Es un margen de seguridad: puede que lo hayas subido para algo que aún no has guardado. |
| **Sin usar** | Nadie lo usa y pasaron más de 7 días desde que se subió. Es el único estado que se puede borrar. |
| **Origen desconocido** | Ningún origen activo reconoce su carpeta, así que no se sabe quién podría usarlo. |

> [!IMPORTANT]
> Un archivo en **Origen desconocido** nunca se puede borrar, aunque parezca que nadie lo usa. Suele ser un archivo de un módulo que está desactivado o de una carpeta que no pertenece a la web.

Los registros en la papelera cuentan como uso: un archivo de un evento o un artículo borrado, pero recuperable, sigue **En uso**.

## Borrar archivos

1. Busca archivos con estado **Sin usar**. Puedes filtrar por **Estado**.
2. Pulsa **Borrar** en uno, o marca varios y pulsa **Borrar seleccionados**.
3. Marca la casilla que confirma que entiendes que se borran para siempre y pulsa **Borrar para siempre**.

> [!IMPORTANT]
> Borrar un archivo lo elimina de Cloudinary y no se puede deshacer.

Antes de borrar, la biblioteca comprueba de nuevo que nadie use cada archivo. Si algo falla, el archivo se conserva y el aviso final explica por qué no se ha borrado. Mientras algún origen de módulo esté roto o duplicado, la biblioteca no borra nada, porque no puede asegurar que el archivo esté libre.

Puede que no veas el botón **Borrar** o que salga desactivado:

- **El borrado está desactivado en esta instalación.** Una persona administradora debe activarlo con el ajuste `MEDIA_LIBRARY_DELETE_ENABLED=true` en la configuración del servidor. Hasta entonces la biblioteca solo se consulta.
- **No tienes permiso para borrar archivos.** El permiso «Borrar archivos sin usar» no se da a ningún rol por defecto: se concede a mano en **Personas → Roles**. Ver la biblioteca es un permiso aparte.
- **Solo se pueden borrar archivos sin usar.**

## Registro de borrados

Pulsa **Borrados** para ver los últimos archivos borrados: cuál era, quién lo borró y cuándo. Si el archivo ya no existía en Cloudinary, aparece como «ya no existía».
