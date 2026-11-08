---
id: core.panel.roles-and-permissions
title: Roles, permisos y cargos
summary: Crea roles con los permisos justos, limita cuántas personas pueden tener un cargo y entiende qué roles están protegidos.
category: users-and-roles
audience: panel
order: 20
keywords: [roles, rol, permisos, permiso, cargos, cargo, presidencia, junta, editor, acceso al panel, crear rol, máximo de personas, requiere ser socio, protegido, member]
related: [core.panel.manage-users, core.panel.association-role, core.panel.modules]
conditions:
  permissions:
    all: [roles.view_any]
---
Un rol es un conjunto de permisos. Cada persona tiene los permisos de todos sus roles activos.

## Crear un rol

1. En el menú lateral, abre **Personas** y elige **Roles**.
2. Pulsa **Crear rol**.
3. En **Información general**:
   - **Nombre**: identificador interno. Solo minúsculas, números y guiones, sin espacios.
   - **Nombre para mostrar**: el nombre que ve la gente.
   - **Descripción**: para qué sirve el rol.
   - **Puede entrar en el panel**: actívalo si quien tenga el rol debe usar el panel.
4. En **Permisos**, marca lo que podrá hacer. Hay una pestaña por zona (**Contenido**, **Usuarios y roles**, **Sitio**) y una por cada módulo habilitado. Usa **Buscar permisos** para encontrar uno.
5. Pulsa **Crear**.

> [!IMPORTANT]
> Solo das lo que tienes. Si no tienes un permiso, no puedes añadirlo a un rol ni quitárselo. El rol de administración solo lo edita una persona administradora.

«Sugerido para este rol» indica lo que el núcleo o un módulo proponen para ese rol. Nunca se concede solo: tienes que marcarlo.

## Cargos: roles con requisitos

Para cargos como la presidencia o la tesorería, el rol tiene dos opciones más:

- **Requiere ser socio**: quien tenga el rol sin ser {{association_role}} lo tiene inactivo hasta que lo sea. Al activarlo, el formulario te dice a cuántas personas afecta.
- **Máximo de personas**: cuántas personas pueden tenerlo a la vez. Vacío es sin límite. Cuando está lleno, no se puede dar a nadie más. Si bajas el máximo, nadie lo pierde.

## Roles protegidos

Los roles con **Protegido** activado, como el de administración, no se pueden renombrar ni borrar.

Hay dos roles más con reglas propias:

- **member**: el rol base de todas las cuentas.
- El rol de {{association_role}}: el que se elige en la configuración del sitio.

Estos dos tampoco se pueden renombrar ni borrar, ni pueden entrar en el panel, ni admiten requisitos ni máximo de personas. Sus permisos sí se pueden cambiar.

## Borrar un rol

Abre el rol, pulsa **Borrar** y confirma. Las personas que lo tenían pierden sus permisos. Los roles protegidos no tienen este botón.

## Historial de roles

Al final de cada rol, **Historial de roles** muestra los cambios de permisos y a quién se dio o quitó.

[[action:filament.admin.resources.roles.create|Crear un rol|if=roles.create]]
[[action:filament.admin.resources.roles.index|Ver los roles]]
