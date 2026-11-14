---
id: core.panel.impersonation
title: Impersonar a otra persona
summary: Mira el sitio y el panel como los ve otra cuenta para reproducir un fallo, sin que se guarde nada.
category: users-and-roles
audience: panel
order: 15
keywords: [impersonar, impersonación, ver como, ver como otra persona, depurar, fallo, no veo, permisos, solo mirar, ejecutar y deshacer, volver a mi cuenta, historial, privacidad]
related: [core.panel.manage-users, core.panel.roles-and-permissions]
conditions:
  permissions:
    all: [users.impersonate]
---
Impersonar sirve para ver {{site_name}} exactamente como lo ve otra cuenta: con sus roles, sus permisos y sus datos. Es útil cuando alguien dice «no veo la pestaña de inscripciones» o «me sale un evento que no debería» y el fallo se reproduce mirando lo que ve esa persona.

> [!IMPORTANT]
> Mientras impersonas, **no se guarda nada**, en ningún modo. Tampoco sale ningún correo, ni se envía ningún aviso ni se borra ningún archivo.

## Quién puede y a quién

- Solo la **administración** puede impersonar. El permiso «Impersonar» no se puede dar a ningún otro rol.
- Puedes impersonar a cualquier cuenta, incluidas las que tienen acceso al panel, salvo:
  - cuentas con el rol de administración;
  - tu propia cuenta;
  - cuentas desactivadas o anonimizadas.
- No se encadenan: si ya estás impersonando, primero vuelve a tu cuenta.
- La persona impersonada no recibe ningún aviso. Sí queda un registro (ver más abajo).

## Empezar

1. Ve a **Personas → Usuarios**.
2. Abre el menú **⋯** de la cuenta, o su ficha, y pulsa **Impersonar**.
3. Elige el modo y confirma.

Verás la portada del sitio con una banda fija: «Estás viendo como…». Navega por el sitio y por el panel como si fueras esa persona. Si esa cuenta no tiene acceso al panel, al entrar en el panel te devolvemos a la portada con un aviso.

## Los dos modos

| Modo | Qué pasa |
|------|----------|
| **Solo mirar** (por defecto) | Los envíos del sitio y las acciones del panel se rechazan con un aviso: «no se guardan cambios». Puedes navegar, filtrar, ordenar, paginar y abrir formularios y ventanas. |
| **Ejecutar y deshacer** | Marca «Permitir ejecutar acciones». Los envíos y las acciones se ejecutan y dan su respuesta normal, para reproducir un «al guardar me da error», pero todo se deshace al terminar cada petición. |

> [!IMPORTANT]
> En **Ejecutar y deshacer**, los avisos de «Guardado» no son reales: no queda nada guardado. La banda cambia de color para que no lo olvides.

El modo no se cambia a mitad: vuelve a tu cuenta y empieza otra impersonación.

## Ver como roles

A veces no hay una cuenta concreta que mirar: quieres saber qué ve «cualquier socio» o «cualquier persona con el rol de moderación». Para eso, en **Personas → Usuarios** y en **Personas → Roles** tienes el botón **Ver como roles**.

1. Pulsa **Ver como roles**.
2. Marca los roles que quieras. Aparecen todos menos el de administración, que nunca se puede ver así.
3. El rol base de toda cuenta va siempre marcado y no se puede quitar.
4. Elige el modo, igual que al impersonar una cuenta, y confirma.

Qué hay que saber:

- En cada página se crea una cuenta nueva, recién registrada y con el correo verificado, que solo tiene esos roles. No existe de verdad: no se guarda y cada petición usa una distinta.
- **No simula lo que una persona ha hecho**: altas, cuotas, mesas, mensajes… Verás lo que ve alguien que acaba de llegar con esos roles, no lo que vería una persona concreta con su historia.
- Un rol que exige ser socio no cuenta mientras no marques también el rol de socio, igual que en una cuenta real.
- Si un rol da acceso al panel, podrás entrar en él.
- En cada petición se vuelve a comprobar que puedes impersonar, que los roles siguen existiendo y que ninguno es el de administración. Si algo falla, vuelves a tu cuenta.
- La banda dice «Estás viendo con los roles …» y en el historial aparece como «Roles: …».

## Volver a tu cuenta

Pulsa **Volver a mi cuenta** en la banda, en el sitio o en el panel: vuelves a la ficha de la persona que impersonabas. Después de **Ver como roles** vuelves a la página de inicio, porque no hay ninguna ficha a la que volver. Si cierras sesión, la impersonación termina y tu sesión se cierra. No hay caducidad: dura hasta que vuelves o cierras sesión.

## Historial

Cada impersonación queda registrada: quién, a quién, en qué modo, cuándo empezó y cuándo terminó. Lo ves en la ficha de la cuenta, en **Impersonaciones de esta cuenta** (las que ha recibido) y en **Impersonaciones hechas por esta cuenta**. Solo lo ve quien puede impersonar. Una fila «Sin cierre registrado» es una sesión que caducó sin volver.

## Aviso para la administración

> [!IMPORTANT]
> Conviene mencionar en la **política de privacidad** que la administración puede ver la web como otra cuenta para atender incidencias, sin modificar nada, y que cada uso queda registrado. Los textos legales de {{site_name}} se editan en **Sitio web → Páginas legales**.
