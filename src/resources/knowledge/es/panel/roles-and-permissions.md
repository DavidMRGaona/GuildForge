---
id: core.panel.roles-and-permissions
title: Roles, permisos y cargos
summary: Crea roles con los permisos justos, limita cuántas personas pueden tener un cargo y entiende qué roles están protegidos.
category: users-and-roles
audience: panel
order: 20
keywords: [roles, rol, permisos, permiso, cargos, cargo, presidencia, junta, editor, acceso al panel, crear rol, máximo de personas, requiere ser socio, exigir doble factor, requiere doble factor, rol en pausa, 2fa, protegido, member, administración, administrador, solo para la administración, instalar módulos, actualizaciones]
related: [core.panel.manage-users, core.site.two-factor, core.panel.association-role, core.panel.modules]
conditions:
  permissions:
    all: [roles.view_any]
---
Un rol es un conjunto de permisos. Cada persona tiene los permisos de todos sus roles activos.

Los roles solo sirven para repartir permisos dentro de la web. No se muestran en ninguna página pública: solo se ven aquí, en la gestión de roles. Dar a alguien el rol «Presidencia» le da sus permisos, pero no le presenta como presidente ante nadie.

## Un rol para cada tarea

Lo más cómodo y seguro es que cada rol tenga los permisos justos para una tarea, y dárselo a quien se encargue de ella. Por ejemplo, un rol para dar de alta a los socios o uno para mantener los eventos y las noticias.

Si alguien necesita hacer algo concreto, aunque sea por poco tiempo, crea o usa un rol para esa tarea en vez de darle un cargo con muchos más permisos. Cuando termine, quítaselo.

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

## Permisos solo para la administración

Instalar y actualizar módulos y aplicar actualizaciones son permisos solo del rol de administración. Un módulo ejecuta código en el servidor: un error al instalarlo puede romper la web o borrar sus datos, y arreglarlo requiere a quien se encarga de la parte técnica.

Por eso, en cualquier otro rol esos permisos aparecen como «Solo para la administración» y no se pueden marcar. Nadie, ni siquiera una persona administradora, puede ponerlos en otro rol. Así, quien puede crear roles nunca puede crear otro rol de administración.

El rol de administración queda para el mantenimiento técnico, así que no conviene dárselo a nadie para el día a día. Para todo lo demás, incluido poner la web en modo mantenimiento, usa roles con los permisos justos.

## Cargos: roles con requisitos

Para cargos como la presidencia o la tesorería, el rol tiene dos opciones más:

- **Requiere ser socio**: quien tenga el rol sin ser {{association_role}} lo tiene inactivo hasta que lo sea. Al activarlo, el formulario te dice a cuántas personas afecta.
- **Máximo de personas**: cuántas personas pueden tenerlo a la vez. Vacío es sin límite. Cuando está lleno, no se puede dar a nadie más. Si bajas el máximo, nadie lo pierde.

## Exigir doble factor a un rol

Además de los requisitos de un cargo, un rol tiene la casilla **Requiere doble factor**, desmarcada en todos los roles. Con ella marcada, quien tenga el rol sin el doble factor activo lo tiene **en pausa**: el rol no da sus permisos ni en la web ni en el panel, y esto ocurre al instante, sin plazo de gracia. En cuanto la persona activa el doble factor en **Ajustes de la cuenta → Seguridad**, el rol vuelve a funcionar al momento.

- **Dónde no se permite.** No se permite en `member` ni en el rol de {{association_role}}. En `admin` sí. El selector del rol de socio tampoco ofrece roles con la casilla marcada.
- **El freno.** No puedes marcar la casilla en un rol que tú mismo tienes si aún no tienes el doble factor activo: te dejarías a ti sin ese rol. Actívalo primero en tus ajustes de la cuenta. Al marcarla, el formulario te dice a cuántas personas dejará en pausa.
- **Avisos.** Cuando un rol pasa a estar en pausa, la persona recibe un correo y un aviso en la campana con un enlace a **Seguridad**. Si entra al panel con un rol en pausa por esto, ve el mensaje «Tu rol exige doble factor. Actívalo en Ajustes de la cuenta» en lugar de un acceso denegado genérico.
- **Dónde lo ves tú.** En el panel, el widget **Roles inactivos** indica el motivo («sin doble factor»), el filtro **Con roles inactivos** del listado de usuarios permite elegir ese motivo, y la ficha de la persona muestra la insignia **Sin doble factor**. El historial de roles guarda cada cambio de la casilla.
- **Con el sitio en mantenimiento.** Quien tiene el rol en pausa solo por el doble factor puede entrar igualmente a **Ajustes de la cuenta → Seguridad** para activarlo. Ninguna otra pestaña ni página de la web.
- **Quien tiene un rol que lo exige no puede desactivarlo.** Su pestaña **Seguridad** no ofrece **Desactivar**; solo **Cambiar de app**.
- **Sitios que ya funcionan.** La casilla empieza desmarcada en todos los roles, así que al activar esta función nadie queda en pausa.

> [!IMPORTANT]
> Te recomendamos marcarla en `admin` y en los roles que ven datos de socios o cuotas, como la tesorería o la secretaría. Antes de hacerlo, avisa a quienes los tienen para que activen el doble factor, o quedarán en pausa de golpe.

Si alguien pierde el móvil y los códigos de recuperación, una persona administradora puede restablecer su doble factor desde su ficha (mira **Gestionar usuarios**).

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
