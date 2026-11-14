---
id: core.panel.footer
title: Componer el pie de página
summary: Reparte el pie de la web en columnas y elige qué bloques van en cada una, como enlaces, texto, próximos eventos, botón, logotipos, marca y redes o contacto.
category: content
audience: panel
order: 65
keywords: [pie de página, footer, columnas, bloques, enlaces del pie, redes sociales, contacto, texto del pie, copyright, menú del pie, próximos eventos, botón, logotipos, patrocinadores, biblioteca de medios]
related: [core.panel.menu, core.panel.legal-pages, core.panel.media-library]
conditions:
  permissions:
    all: [footer.manage]
---
El pie de página es la franja oscura del final de cada página de la web. Se compone en **Sitio web** → **Pie de página**.

> [!IMPORTANT]
> Mientras no añadas ningún bloque, la web muestra el pie de siempre: los enlaces del menú **Pie de página**, el copyright y las redes sociales.

## Añadir columnas y bloques

1. Pulsa **Añadir columna**. Puedes tener hasta cuatro.
2. Dentro de la columna, pulsa **Añadir bloque** y elige uno:
   - **Enlaces**: los enlaces de un menú del pie.
   - **Texto**: un texto con formato, enlaces e imágenes.
   - **Marca y redes**: el logotipo, el nombre de la asociación, un texto breve y las redes sociales.
   - **Contacto**: el email, el teléfono y la dirección de **Nosotros**, y un enlace al formulario de contacto.
   - **Próximos eventos**: los siguientes eventos publicados, de 1 a 5 (3 por defecto), con su fecha y lugar, y un enlace a todos los eventos.
   - **Botón**: un texto que enlaza a una dirección, con estilo principal o secundario.
   - **Logotipos**: una fila de logotipos de patrocinadores, federaciones u otras asociaciones.
   - Los módulos activos pueden añadir los suyos, como la suscripción al boletín.
3. Si quieres, escribe un **Título**: sale encima del bloque.
4. Ordena columnas y bloques arrastrándolos y pulsa **Guardar**.

Debajo de las columnas siempre sale una barra con el copyright y los enlaces a las páginas legales publicadas.

> [!TIP]
> En el móvil las columnas se ven una debajo de otra y en tableta de dos en dos. Con dos o tres columnas el pie queda más equilibrado.

## Próximos eventos

Muestra los siguientes eventos publicados por fecha de inicio. Los eventos de hoy siguen saliendo hasta que acaba el día, y las fechas y horas se ven en la zona horaria de la web. Los borradores nunca salen. Si no hay eventos próximos, el bloque lo dice y deja el enlace a todos los eventos, si lo has dejado activado.

## Botón

Escribe el **Texto del botón** y el **Enlace**. El enlace puede ser:

- una dirección que empiece por `https://`, como `https://example.com/unete`;
- o una ruta de esta web, como `/eventos` (con una sola barra al principio).

No se admiten otras direcciones (por ejemplo `http://`, `mailto:` o `javascript:`): el panel no te deja guardar un botón con un enlace así y, si alguno llegara por otra vía, el botón no se muestra. Elige el estilo **Principal** para la acción más importante o **Secundario** para una acción discreta.

## Logotipos

Pulsa **Añadir logotipo** (hasta 8) y sube una imagen o pulsa **Elegir de la biblioteca** para usar una que ya está en la biblioteca de medios. Cada logotipo necesita un **Texto alternativo** (obligatorio: lo leen los lectores de pantalla; di a quién pertenece el logotipo) y puede tener un enlace con las mismas reglas que el botón.

Los logotipos salen sobre un fondo claro para que se vean bien tengan el color que tengan.

## Las imágenes del pie en la biblioteca de medios

Las imágenes subidas desde el pie (logotipos y las del editor del bloque de texto) salen en la **Biblioteca de medios** con el origen **Pie de página**, y su ficha dice dónde se usan. Una imagen de otra carpeta que solo aparece enlazada en un texto del pie también cuenta como en uso, así que no se podrá borrar mientras esté ahí. Al quitar un logotipo del pie y guardar, la imagen se borra sola si nadie más la usa.

## Menús de enlaces

Hay cuatro menús de pie. Sus enlaces se editan en **Sitio web** → **Navegación**, en la pestaña de cada menú. En **Menús de enlaces** puedes darles un nombre, por ejemplo «La asociación» o «Comunidad», para reconocerlos al elegirlos en un bloque de **Enlaces**. En la web solo se ve el título del bloque.

## Bloques que no se pueden mostrar

- Si un bloque dice **Ahora no se puede mostrar**, su módulo no está listo (por ejemplo, el boletín sin configurar). Se guarda tal cual, pero no sale en la web hasta que vuelva a estar disponible.
- Si dice **Bloque no disponible**, es de un módulo que no está activo. Se conserva al guardar y vuelve a salir si activas el módulo. Bórralo si ya no lo quieres.

[[action:filament.admin.pages.pie-de-pagina|Abrir el pie de página]]
[[action:filament.admin.resources.menu-items.index|Editar los menús|if=menu_items.view_any]]
