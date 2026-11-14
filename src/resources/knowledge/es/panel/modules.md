---
id: core.panel.modules
title: Instalar, habilitar y configurar módulos
summary: Añade funciones a la web con módulos, como torneos o reservas, actívalos y ajusta sus opciones.
category: modules
audience: panel
order: 10
keywords: [módulos, módulo, instalar módulo, habilitar, activar módulo, deshabilitar, desactivar módulo, desinstalar, configurar módulo, ajustes del módulo, zip, extensiones, funciones nuevas]
related: [core.panel.updates, core.panel.maintenance, core.panel.roles-and-permissions]
conditions:
  permissions:
    any: [modules.manage, modules.configure]
---
Los módulos añaden funciones a la web, como torneos, partidas o reservas. Están en **Sistema → Módulos**.

Cada módulo sale en una tarjeta con su versión, su autor y sus dependencias. Usa **Todos**, **Habilitados** y **Deshabilitados** o el buscador para encontrar uno.

## Configurar un módulo

1. En la tarjeta de un módulo habilitado, pulsa **Configuración**.
2. Cambia sus opciones.
3. Guarda los cambios.

Casi todos los módulos tienen sus ajustes en **Sistema → Configuración**, en una página propia, y el botón te lleva allí. Esa página pide el permiso de ajustes del propio módulo: si no lo tienes, el botón no sale. Los módulos sin página propia se configuran en una página de **Módulos**.

Tus ajustes se conservan cuando el módulo se actualiza. Solo los módulos con opciones tienen este botón.

> [!TIP]
> Si solo puedes configurar módulos, **Configuración** es la única acción que verás. Instalar, habilitar y deshabilitar necesita otro permiso.

## Habilitar o deshabilitar

- **Habilitar**: activa el módulo. La primera vez prepara sus datos. Si sale la marca **Incompatible**, no se puede habilitar: deja el ratón sobre el botón para ver por qué.
- **Deshabilitar**: lo apaga sin borrar nada. Sus datos siguen ahí para cuando lo vuelvas a habilitar.

Un módulo que otros necesitan sale con **Requerido por**: deshabilita antes los que dependen de él.

> [!IMPORTANT]
> Habilitar un módulo no da sus permisos a nadie. Después, en **Roles**, marca en la pestaña del módulo lo que debe poder hacer cada rol.

## Instalar o actualizar desde un archivo

1. Activa el modo mantenimiento.
2. Pulsa **Instalar / actualizar**, arriba a la derecha.
3. Sube el **Archivo ZIP** del módulo (hasta 50 MB).
4. Confirma con **Instalar / actualizar**.
5. Habilita el módulo si es nuevo y desactiva el modo mantenimiento.

**Descubrir** busca módulos que ya están en la instalación pero que aún no salen en la lista.

## Desinstalar

Solo se puede desinstalar un módulo deshabilitado.

1. Pulsa **Desinstalar** en su tarjeta.
2. Decide si marcas **Eliminar también los datos del módulo**.
3. Pulsa **Sí, desinstalar**.

> [!WARNING]
> Desinstalar borra el módulo y sus archivos. Si marcas la casilla, también borra sus datos. No se puede deshacer.

[[action:filament.admin.pages.modules-page|Ver los módulos]]
