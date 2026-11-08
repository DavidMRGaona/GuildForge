---
id: core.panel.mail
title: Configurar el correo y ver estadísticas
summary: Elige cómo se envían los correos de la web, pruébalo y revisa qué correos han salido y cuáles han fallado.
category: site-settings
audience: panel
order: 30
keywords: [correo, email, correos, envío de correos, remitente, smtp, no llegan los correos, correo de prueba, estadísticas de correo, fallidos, rebotados, límites, cuotas]
related: [core.panel.site-settings, core.panel.dashboard-settings]
conditions:
  permissions:
    any: [mail.configure, mail.view_stats]
---
La web envía correos para verificar cuentas, recuperar contraseñas y avisar de novedades. Hay dos pantallas en **Sistema → Configuración**: **Correo electrónico**, para configurarlo, y **Estadísticas de correo**, para ver lo enviado. Cada una necesita su propio permiso.

## Configurar el envío

1. Abre **Sistema → Configuración** y elige **Correo electrónico**.
2. En **General**:
   - Deja activado **Envío de correos activado**.
   - Elige el **Driver de correo**: el servicio que envía los correos.
   - Escribe la **Dirección del remitente** y, si quieres, el **Nombre del remitente**. Si lo dejas vacío, se usa «{{site_name}}».
3. Abre la pestaña del servicio que elegiste (**SMTP**, **Amazon SES** o **Resend**) y rellena los datos que te da tu proveedor de correo.
4. Pulsa **Guardar**.

> [!WARNING]
> Si desactivas **Envío de correos activado**, no sale ningún correo: tampoco los de verificación ni los de recuperar la contraseña.

## Comprobar que funciona

Las pruebas usan la configuración guardada, así que guarda antes.

1. Abre la pestaña **Pruebas**.
2. Con SMTP, pulsa **Probar conexión**.
3. Escribe tu correo en **Destinatario de prueba** y pulsa **Enviar email de prueba**.

## Límites

En **Límites y cuotas** puedes fijar un **Límite diario** y un **Límite mensual** (0 es sin límite). El **Umbral de alerta (%)** marca cuándo avisar de que te acercas al límite.

## Ver qué correos han salido

1. Abre **Sistema → Configuración** y elige **Estadísticas de correo**.
2. La tabla muestra cada correo con su **Destinatario**, su **Asunto**, su **Estado** y su **Fecha**.
3. Filtra por **Estado** para ver los **Fallido** o los **Rebotado**. La columna **Error** dice qué pasó.

El escritorio también puede mostrar el estado del correo y sus estadísticas.

[[action:filament.admin.configuracion.pages.mail-settings|Configurar el correo|if=mail.configure]]
[[action:filament.admin.configuracion.pages.mail-statistics-page|Ver las estadísticas|if=mail.view_stats]]
