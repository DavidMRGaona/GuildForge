---
id: core.panel.maintenance
title: Activar el modo mantenimiento
summary: Cierra la web pública un rato, por ejemplo mientras instalas o actualizas módulos, y vuelve a abrirla al terminar.
category: site-settings
audience: panel
order: 20
keywords: [mantenimiento, modo mantenimiento, cerrar la web, web cerrada, en obras, desactivar mantenimiento, abrir la web, actualizar, aviso a visitantes]
related: [core.panel.updates, core.panel.modules, core.panel.site-settings]
conditions:
  permissions:
    all: [settings.maintenance]
---
El modo mantenimiento cierra la web pública. Los visitantes ven una página de mantenimiento, y quien puede entrar en el panel sigue viendo la web con normalidad.

## Activarlo

1. En el menú lateral, abre **Administración** y elige **Configuración del sitio**.
2. Abre la pestaña **Mantenimiento**.
3. Activa **Activar modo mantenimiento**.
4. Si quieres, escribe un **Mensaje para los visitantes**. Si lo dejas vacío, sale un mensaje genérico.
5. Pulsa **Guardar**.

Mientras está activo, el panel muestra arriba el aviso «La web pública está en modo mantenimiento».

## Desactivarlo

Pulsa **Desactivar** en el aviso del panel. Te lleva a la pestaña **Mantenimiento**: desactiva **Activar modo mantenimiento** y pulsa **Guardar**.

> [!IMPORTANT]
> Instalar un módulo desde un archivo ZIP y aplicar actualizaciones de módulos solo funciona con el modo mantenimiento activo.

> [!WARNING]
> Acuérdate de desactivarlo al terminar. Mientras siga activo, nadie de fuera puede usar la web.

[[action:filament.admin.configuracion.pages.site-settings|Abrir la configuración del sitio]]
