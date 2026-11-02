---
id: core.panel.site-settings
title: Configuración del sitio
summary: Cambia el nombre de la web, los logotipos, los colores, la tipografía y las opciones de registro e inicio de sesión.
category: site-settings
audience: panel
order: 10
keywords: [configuración, ajustes, nombre del sitio, nombre de la asociación, logo, logotipo, favicon, colores, tema, tipografía, fuente, modo oscuro, registro, inicio de sesión, verificación de email, apariencia]
related: [core.panel.maintenance, core.panel.association-role, core.panel.mail]
conditions:
  permissions:
    all: [settings.manage]
---
En el menú lateral, abre **Administración** y elige **Configuración del sitio**. Los ajustes están repartidos en pestañas.

> [!IMPORTANT]
> **Guardar** guarda todas las pestañas a la vez. Revisa lo que hayas tocado antes de pulsarlo.

## General

- **Nombre de la asociación**: es el nombre de la web. Sale en la cabecera, el pie, los títulos de las páginas, los correos y el panel. Ahora es «{{site_name}}».
- **Descripción de la asociación**: un texto breve sobre la asociación.

## Logotipos

Sube el logo para el tema claro, el del tema oscuro y el de los correos. Si no subes el de los correos, se usa el del tema claro. En **Favicons** pones el icono de la pestaña del navegador; si no subes ninguno, se usan los de por defecto.

## Colores, tipografía y apariencia

- **Colores**: elige un **Tema predefinido** o tu **Color primario** y tu **Color de acento**. La vista previa te enseña las paletas que salen.
- **Tipografía**: las fuentes de títulos y de texto, y el tamaño base.
- **Apariencia**: bordes, sombras, estilo de botones, **Modo oscuro por defecto** y **Mostrar selector de tema**.

> [!TIP]
> Los temas predefinidos ya tienen colores con buen contraste. Si eliges colores propios, comprueba que el texto se lee bien.

## Autenticación

- **Permitir registro**: si lo desactivas, nadie puede crearse una cuenta.
- **Permitir inicio de sesión**: si lo desactivas, nadie puede entrar en la web. El panel sigue funcionando.
- **Requerir verificación de email**: las cuentas nuevas tienen que confirmar su correo antes de usar las páginas que piden iniciar sesión. Las cuentas antiguas no se ven afectadas.
- **Rol de socio**: tiene su propia guía.
- **Nombre de usuario anonimizado**: el nombre que sale en lugar de las cuentas anonimizadas.

## Mantenimiento

Esta pestaña solo sale si tienes permiso para el modo mantenimiento. Tiene su propia guía.

[[action:filament.admin.pages.site-settings|Abrir la configuración del sitio]]
