---
id: core.panel.association-role
title: Elegir el rol de las personas asociadas
summary: Qué rol distingue a quien pertenece a la asociación de cualquier cuenta registrada, y qué pasa al cambiarlo.
category: users-and-roles
audience: panel
order: 30
keywords: [socio, socios, socia, miembro, miembros, asociación, rol de socio, membresía, cuota, solo para socios, roles inactivos, roles en pausa]
related: [core.panel.roles-and-permissions, core.panel.manage-users, core.panel.site-settings]
conditions:
  permissions:
    all: [settings.manage]
---
Registrarse en la web no hace a nadie miembro de la asociación. Para eso hay un rol aparte: el que está elegido en el campo **Rol de socio**.

Los módulos lo usan, por ejemplo, para el contenido reservado, el acceso anticipado o las membresías. También decide qué roles con «Requiere ser socio» están activos.

## Cambiar el rol

1. En el menú lateral, abre **Administración** y elige **Configuración del sitio**.
2. Abre la pestaña **Autenticación**.
3. En **Socios de la asociación**, elige el rol en **Rol de socio**.
4. Lee el aviso que sale debajo, si sale.
5. Pulsa **Guardar**.

La lista no ofrece los roles de administración, edición ni «member», ni los roles que pueden entrar en el panel, que tienen activado «Requiere ser socio» o que tienen un máximo de personas.

> [!WARNING]
> Al cambiarlo, quien tenga el rol nuevo activa sus roles con «Requiere ser socio». Quien tenía el anterior y no tiene el nuevo los deja en pausa. El aviso te dice cuántas personas quedarán con roles inactivos.

> [!IMPORTANT]
> Si alguno de esos roles no lo puedes dar tú, no puedes hacer el cambio. El aviso te dice qué roles son.

## Si sale un aviso de rol no disponible

Si el rol guardado ya no existe o no se puede usar, nadie cuenta como miembro de la asociación hasta que elijas otro y guardes.

El rol elegido no se puede renombrar ni borrar mientras lo sea.

[[action:filament.admin.configuracion.pages.site-settings|Abrir la configuración del sitio]]
