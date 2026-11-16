---
id: core.panel.manage-users
title: Gestionar usuarios
summary: Busca cuentas, crea usuarios, dales roles, desactívalas y, si hace falta, anonimízalas.
category: users-and-roles
audience: panel
order: 10
keywords: [usuarios, usuario, cuentas, cuenta, crear usuario, dar rol, asignar rol, quitar rol, desactivar, bloquear, restaurar, anonimizar, borrar cuenta, rgpd, gdpr, contraseña olvidada, verificación, cambiar correo, cambiar email, correo verificado, perfil público, restablecer perfil, sobre mí, banner, moderar perfil]
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

La cuenta nace con el correo sin verificar: a la persona le llegan la bienvenida y un enlace para verificarlo. Marca **Marcar el correo como verificado** solo si ya has comprobado que la dirección es suya.

> [!WARNING]
> Un correo verificado permite actuar sobre esa dirección, por ejemplo darla de baja de un boletín. Por eso queda registrado quién marca la casilla.

## Cambiar el correo de alguien

1. Abre la cuenta.
2. Escribe la nueva dirección en **Correo electrónico**.
3. Pulsa **Guardar cambios**.

El correo no cambia en ese momento. A la nueva dirección le llega un enlace, y el cambio se aplica cuando la persona lo pulsa con su sesión iniciada. Hasta entonces la cuenta conserva el correo actual, y bajo el campo verás el cambio pendiente. Queda registrado quién lo pidió.

Si la dirección pendiente es un error, pulsa **Anular el cambio de correo** arriba en la cuenta. La cuenta conserva su correo y el enlace enviado deja de servir. Volver a guardar el correo actual no anula el cambio, para no deshacer uno que haya pedido la propia persona.

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

## Restablecer un perfil público

Si el «Sobre mí» o el banner del perfil público de alguien no son adecuados, abre la cuenta y pulsa **Restablecer perfil público** arriba. Confirma en el diálogo y marca la casilla.

Se borran el «Sobre mí» y el banner. La dirección del perfil, quién puede verlo y el orden de sus secciones no cambian, y la persona puede volver a escribirlos. Queda registrado quién lo hizo.

> [!IMPORTANT]
> Necesitas el permiso «Restablecer perfiles públicos», que no tiene ningún rol de entrada: se concede en el editor de roles.

## Historial de roles

Al final de cada cuenta, **Historial de roles** muestra quién le dio o quitó cada rol y cuándo.

[[action:filament.admin.resources.users.index|Ver los usuarios]]
