---
id: core.panel.manage-users
title: Gestionar usuarios
summary: Busca cuentas, crea usuarios, dales roles, desactívalas y, si hace falta, anonimízalas.
category: users-and-roles
audience: panel
order: 10
keywords: [usuarios, usuario, cuentas, cuenta, crear usuario, dar rol, asignar rol, quitar rol, desactivar, bloquear, restaurar, anonimizar, borrar cuenta, rgpd, gdpr, contraseña olvidada, verificación]
related: [core.panel.roles-and-permissions, core.panel.association-role]
conditions:
  permissions:
    all: [users.view_any]
---
Todas las cuentas de {{site_name}} están en **Personas → Usuarios**.

## Encontrar una cuenta

Usa el buscador o los filtros de la lista:

- **Roles**: las cuentas con un rol concreto.
- **Con roles inactivos**: las que tienen roles en pausa.
- **Estado**: activas, desactivadas o todas.

## Crear una cuenta

1. Pulsa **Crear usuario**.
2. Rellena **Nombre de usuario**, **Nombre para mostrar**, **Correo electrónico** y **Contraseña** (mínimo 8 caracteres).
3. Si puedes, marca sus roles en **Roles y permisos**.
4. Pulsa **Crear**.

## Cambiar los roles de alguien

1. Abre la cuenta.
2. En **Roles y permisos**, marca o desmarca roles.
3. Pulsa **Guardar cambios**.

Toda cuenta tiene siempre el rol «member», el rol base: sale marcado y no se puede desmarcar.

> [!IMPORTANT]
> Solo das lo que tienes. Para cambiar roles necesitas el permiso «Asignar roles a usuarios», y solo puedes dar o quitar roles cuyos permisos tengas tú. Los demás salen marcados o desmarcados, pero bloqueados.

Tampoco puedes editar una cuenta que tenga permisos que tú no tienes. Las cuentas de administración solo las edita otra persona administradora.

Un rol puede salir como inactivo si requiere ser {{association_role}} y la persona no lo es. Vuelve a funcionar cuando lo sea.

## Ayudar con el acceso

En el menú **⋯** de cada fila tienes:

- **Enviar email de verificación**: solo si aún no ha verificado su correo.
- **Enviar email de recuperación**: le llega un enlace para poner una contraseña nueva.

Para cambiarle la contraseña tú, escríbela en **Contraseña** y guarda. Si dejas el campo vacío, la contraseña no cambia.

## Desactivar, restaurar y anonimizar

Están en el mismo menú **⋯** de la fila del usuario:

- **Desactivar**: la persona ya no puede iniciar sesión. No puedes desactivar tu propia cuenta.
- **Restaurar**: reactiva una cuenta desactivada. Si alguno de sus roles ya está al máximo de personas, la cuenta vuelve sin ese rol y te lo avisa.
- **Anonimizar (GDPR)**: solo en cuentas desactivadas. Borra sus datos personales y conserva su contenido. Si tiene artículos, eliges entre **Transferir a otro usuario** o **Mantener como "Anónimo"**.

> [!WARNING]
> Anonimizar no se puede deshacer. Úsalo cuando alguien pida que borren sus datos.

## Historial de roles

Al final de cada cuenta, **Historial de roles** muestra quién le dio o quitó cada rol y cuándo.

[[action:filament.admin.resources.users.index|Ver los usuarios]]
