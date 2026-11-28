---
id: core.panel.media-library
title: Biblioteca de medios
summary: Mira qué imágenes hay guardadas, quién las usa y borra las que ya no usa nadie para liberar espacio.
category: content
audience: panel
order: 45
keywords: [biblioteca, medios, imágenes, archivos, sin usar, borrar, Cloudinary, espacio, huérfanas, fotos, elegir, reutilizar, compartir]
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
- Si la última actualización no termina bien (no se pudo leer Cloudinary entero o falló algún origen), arriba aparece un aviso y «Última reconciliación» conserva la fecha de la última que sí terminó. Mientras tanto, los totales pueden estar desfasados.

## Qué aparece y qué no

La biblioteca muestra las imágenes que suben la web y los módulos: eventos, artículos, galerías, hero slides, logos y favicon del sitio, y las de los módulos activos.

Si pegas el enlace de una imagen de la web en un texto (la descripción de un evento, un artículo, una guía propia, una página legal o la historia de «Nosotros»), ese texto cuenta como uso «En el texto», aunque la imagen sea de otra carpeta. Así no se borra una imagen que se ve en una página.

Lo mismo pasa con las imágenes que insertas con el editor de esos textos: cuentan como uso «En el texto» del registro donde las pusiste. Salen con su miniatura en el origen **Textos** (las de los artículos, en **Textos de artículos**, y las de «Nosotros», en el de los ajustes del sitio). Si las quitas de todos los textos, pasan a **Sin usar** y se pueden borrar como las demás.

Las tarjetas salen de la subida más reciente a la más antigua (**Más recientes** en «Ordenar por»), y cada una dice cuándo se subió. También puedes ordenarlas por **Tamaño** o por **Usos**.

No aparecen los **avatares** de las cuentas: no se muestran y nunca se pueden borrar desde aquí.

## Imágenes ocultas

Algunas imágenes salen en la lista sin miniatura. Su archivo sigue ahí con su tamaño, su carpeta y sus usos, pero en lugar de la imagen aparece un ojo tachado, la ficha se titula «Imagen oculta» y no permite abrir el original ni copiar su dirección. Tampoco se ofrecen en **Elegir de la biblioteca**. Su estado y el borrado no cambian.

Una imagen sale oculta por una de estas razones:

- **Su origen no la enseña.** La biblioteca solo muestra las imágenes de los orígenes que dicen que se pueden ver: los de la web (eventos, artículos, propuestas desde la web, textos, textos de artículos, galerías, hero slides, ajustes del sitio, pie de página y banners de perfil) y los de los módulos que ya lo declaran. Las de un módulo que todavía no lo declara salen ocultas hasta que se actualice.
- **Es de origen desconocido.** No se sabe de dónde viene, así que no se enseña.
- **La biblioteca no está al día con su origen.** Pasa justo después de actualizar la web o un módulo, si la última actualización de ese origen falló o si no se pudo poner al día uno de sus registros al guardarlo. Se arregla sola con la siguiente actualización; también puedes pulsar **Actualizar**.
- **Uno de sus usos la oculta**, como la insignia de un logro secreto, la imagen de una propuesta que su autor aún no ha enviado o el banner de un perfil que no ven todas las personas registradas. Si un archivo lo comparten un registro visible y uno oculto, se oculta para los dos.
- **Nadie la usa y su origen puede guardar imágenes privadas.** Pasa, por ejemplo, con las imágenes del foro: las de un tema borrado de una categoría para socios, o las que se subieron para un mensaje que nunca se envió, se quedan sin uso y podrían ser privadas. También con las imágenes subidas desde la web para una propuesta (origen «Propuestas desde la web»): una que se quedó sin uso pudo ser de un borrador que solo veía su autor. Lo mismo pasa con las imágenes del texto de los artículos (origen «Textos de artículos»), porque el editor de las propuestas de la web las guarda en la misma carpeta.

Una imagen oculta tampoco se nombra por su archivo: el aviso de borrado la llama «Imagen oculta».

En los demás orígenes que se ven, los archivos sin usar conservan su miniatura para que puedas revisarlos antes de borrarlos. Un archivo sin usar que sale oculto también se puede borrar, como cualquier otro, cuando pasan 7 días desde que se subió.

Sirve para que quien gestiona la biblioteca no vea por descuido una imagen privada o una sorpresa, no para guardar un secreto: la imagen sigue siendo pública en Cloudinary.

## Estados

| Estado | Qué significa |
|--------|---------------|
| **En uso** | Algún registro usa el archivo. No se puede borrar. |
| **Reciente** | Nadie lo usa, pero se subió hace menos de 7 días. Es un margen de seguridad: puede que lo hayas subido para algo que aún no has guardado. |
| **Sin usar** | Nadie lo usa y pasaron más de 7 días desde que se subió. Es el único estado que se puede borrar. |
| **Origen desconocido** | Ningún origen activo reconoce su carpeta, así que no se sabe quién podría usarlo. |

> [!IMPORTANT]
> Un archivo en **Origen desconocido** nunca se puede borrar, aunque parezca que nadie lo usa. Suele ser un archivo de un módulo que está desactivado o de una carpeta que no pertenece a la web.

Si un módulo guarda sus registros en una papelera, los registros que se pueden recuperar de ella siguen contando como uso.

## Borrar archivos

1. Busca archivos con estado **Sin usar**. Puedes filtrar por **Estado**.
2. Pulsa **Borrar** en uno, o marca varios y pulsa **Borrar seleccionados**. En la cuadrícula, **Seleccionar los sin usar** marca de una vez los de la página que estás viendo. Se borran como mucho 50 cada vez.
3. Marca la casilla que confirma que entiendes que se borran para siempre y pulsa **Borrar para siempre**.

> [!IMPORTANT]
> Borrar un archivo lo elimina de Cloudinary y no se puede deshacer.

Antes de borrar, la biblioteca comprueba de nuevo que nadie use cada archivo. Si algo falla, el archivo se conserva y el aviso final explica por qué no se ha borrado. Mientras algún origen de módulo esté roto o duplicado, la biblioteca no borra nada, porque no puede asegurar que el archivo esté libre.

Puede que no veas el botón **Borrar** o que salga desactivado:

- **El borrado está desactivado en esta instalación.** Una persona administradora debe activarlo con el ajuste `MEDIA_LIBRARY_DELETE_ENABLED=true` en la configuración del servidor. Hasta entonces la biblioteca solo se consulta.
- **No tienes permiso para borrar archivos.** El permiso «Borrar archivos sin usar» no se da a ningún rol por defecto: se concede a mano en **Personas → Roles**. Ver la biblioteca es un permiso aparte.
- **Solo se pueden borrar archivos sin usar.**

## Elegir una imagen de la biblioteca

En los formularios con imagen (eventos, artículos, fotos de galerías, hero slides, logos y favicons del sitio, imagen de «Nosotros» y los de los módulos que lo ofrezcan), junto al campo aparece **Elegir de la biblioteca**. Sirve para usar una imagen que ya está en la web sin volver a subirla.

1. Pulsa **Elegir de la biblioteca**.
2. Busca la imagen por título o carpeta, o filtra por **Origen** y **Estado**.
3. Pulsa una miniatura para verla en grande, con sus medidas y dónde se usa.
4. Pulsa **Usar esta imagen** y guarda el formulario.

Solo aparecen las imágenes que ese campo admite (por ejemplo, el favicon solo admite PNG, ICO o SVG) y las de orígenes que no borran archivos por su cuenta. Las de **Origen desconocido** y las **imágenes ocultas** no se ofrecen. Para elegir hace falta el permiso de ver la biblioteca. Subir un archivo nuevo sigue funcionando como siempre.

## Imágenes compartidas

Una imagen elegida de la biblioteca se comparte: los dos registros usan el mismo archivo. Por eso, cuando quitas o cambias la imagen de un registro (o lo borras), el archivo anterior solo se borra si ya no lo usa nadie más. Si lo usa otro registro, o no se puede comprobar, se conserva y aparecerá en la biblioteca como **Sin usar** cuando nadie lo use.

Estos borrados automáticos aparecen en **Borrados** como «al quitarlo de un registro».

## Registro de borrados

Pulsa **Borrados** para ver los últimos archivos borrados: cuál era, quién lo borró y cuándo. Si el archivo ya no existía en Cloudinary, aparece como «ya no existía». Un archivo que la biblioteca ocultaba al borrarlo aparece como «Imagen oculta», sin su nombre y sin quién lo borró («Por —»): podría delatar de quién era un borrador.
