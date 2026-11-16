---
id: core.site.two-factor
title: Doble factor de autenticación
summary: Qué es el doble factor, cómo activarlo con una app de autenticación, cómo iniciar sesión con el código y qué hacer si pierdes el móvil.
category: account
audience: site
order: 22
keywords: [doble factor, dos pasos, verificación en dos pasos, 2fa, autenticación, app de autenticación, código, códigos de recuperación, recuperación, totp, qr, seguridad, perdí el móvil, he perdido el móvil, cambiar de móvil, cambiar de app, desactivar doble factor, restablecer doble factor]
related: [core.account.change-password, core.account.complete-profile]
---
El doble factor añade un segundo paso al iniciar sesión: además de tu contraseña, escribes un código de seis cifras que genera una app de autenticación en tu móvil. Así, una contraseña robada no basta para entrar en tu cuenta.

Es voluntario para cualquier cuenta. Algunos roles pueden exigirlo (mira **Por qué tu rol lo exige**, más abajo).

## Qué app usar

Cualquier app de autenticación que genere códigos de seis cifras que cambian cada 30 segundos. Por ejemplo, Google Authenticator, Microsoft Authenticator, Aegis, 2FAS o el gestor de contraseñas que ya uses, si incluye esa función.

## Activarlo paso a paso

1. Pulsa tu nombre en la cabecera y elige **Ajustes de la cuenta**.
2. Abre la pestaña **Seguridad**.
3. En la tarjeta **Doble factor de autenticación**, pulsa **Activar doble factor**.
4. Escribe tu contraseña y pulsa **Continuar**.
5. Abre tu app de autenticación y escanea el código QR. Si no puedes escanearlo, escribe a mano la **Clave para escribir a mano**; el botón **Copiar clave** la copia. Pulsa **Ya la he añadido**.
6. Escribe el código de seis cifras que muestra la app y pulsa **Activar**.
7. Se muestran tus **códigos de recuperación**. Guárdalos (siguiente sección), marca **He guardado los códigos** y pulsa **Terminar**.

Verás el aviso «Doble factor activado» y la tarjeta pasará a decir **Activado**, con la fecha desde la que lo está.

> [!IMPORTANT]
> Al activarlo se cierran las demás sesiones abiertas de tu cuenta, por ejemplo en otros navegadores. Tendrás que iniciar sesión de nuevo en ellas, ya con el código. Te llega también un correo de aviso.

> [!IMPORTANT]
> Si la web te pide verificar tu email, no podrás activar ni gestionar el doble factor hasta hacerlo.

## Guardar los códigos de recuperación

Son 8 códigos, con el aspecto `abcde-fghij`. Si pierdes el móvil, cada uno te deja entrar **una sola vez**. Se muestran únicamente cuando los creas: después no se pueden volver a ver.

- **Copiar códigos** los copia al portapapeles.
- **Descargar .txt** guarda un archivo con ellos.

Guárdalos en un lugar seguro y que no dependa de tu móvil: un gestor de contraseñas o una copia en papel. No los guardes en el mismo móvil que tiene la app.

Cuando te quedan pocos (dos o menos), la tarjeta te avisa para que generes códigos nuevos.

## Iniciar sesión con el código

1. Escribe tu correo y tu contraseña en **Iniciar sesión** y pulsa el botón.
2. Llegas a **Verificación en dos pasos**. Escribe el **Código de la app** que muestra ahora tu app de autenticación y pulsa **Verificar**.

Si marcaste **Recordarme**, solo se aplica una vez que el código es correcto.

Un código sirve una sola vez: si acabas de usarlo y te pide otro, espera a que la app muestre el siguiente. Para no quedarte atascado, la verificación tiene límites:

- Tienes unos minutos desde que pones la contraseña. Si se pasa el tiempo, la web te pide volver a iniciar sesión.
- Tras varios intentos seguidos con un código incorrecto, se descarta el inicio de sesión y hay que volver a escribir la contraseña. Además, te llega un aviso por correo.

> [!WARNING]
> Si te llega un aviso de intentos de entrar que no has hecho tú, alguien conoce tu contraseña: cámbiala cuanto antes. El doble factor ha impedido que entrara.

## Usar un código de recuperación

Si no tienes el móvil a mano:

1. En **Verificación en dos pasos**, pulsa **Usar un código de recuperación**.
2. Escribe uno de tus códigos y pulsa **Verificar**.

Ese código ya no vale más. Al entrar verás cuántos te quedan, y te llega un correo que lo avisa. Cuando te queden pocos, genera códigos nuevos.

## Cambiar de móvil o de app

Hazlo **antes** de perder el móvil antiguo, mientras aún funciona.

1. En **Ajustes de la cuenta → Seguridad**, pulsa **Cambiar de app**.
2. Confirma con tu contraseña y un código: el de tu app actual o uno de recuperación.
3. Escanea el nuevo código QR con la app nueva, o escribe la clave a mano.
4. Escribe el código que muestra la app nueva para confirmar.

Hasta que confirmes con el código de la app nueva, la anterior sigue valiendo. Al terminar, la anterior deja de servir. Tus códigos de recuperación siguen siendo los mismos. Se cierran las demás sesiones abiertas y te llega un correo.

## Generar códigos nuevos

1. En **Ajustes de la cuenta → Seguridad**, pulsa **Generar códigos nuevos**.
2. Confirma con tu contraseña y un código de tu app o de recuperación.
3. Guarda los códigos nuevos.

Los códigos anteriores dejan de servir, aunque no los hubieras usado. Te llega un correo de aviso.

## Desactivarlo

1. En **Ajustes de la cuenta → Seguridad**, pulsa **Desactivar**.
2. Confirma con tu contraseña y un código de tu app o de recuperación.

Volverá a bastar con tu contraseña para iniciar sesión. Te llega un correo de aviso.

Si alguno de tus roles exige el doble factor, **Desactivar** no está disponible. Lo que sí puedes es **Cambiar de app**.

## Si lo pierdes todo

Si has perdido el móvil y no tienes ningún código de recuperación, no puedes entrar por tu cuenta, y es a propósito: si fuera fácil saltarse el doble factor, no protegería nada.

Pide a la administración de {{site_name}} que restablezca el doble factor de tu cuenta. Te pedirá comprobar que eres tú por otro medio, como una llamada o verte en persona. Al restablecerlo, se cierran todas tus sesiones y te llega un correo. Después podrás iniciar sesión solo con tu contraseña y volver a activarlo.

> [!IMPORTANT]
> Si alguno de tus roles exige el doble factor, ese rol queda en pausa hasta que lo actives otra vez.

## Por qué tu rol lo exige

<!-- #772: texto pendiente de que la issue esté en main -->

[[action:profile.show?tab=seguridad|Ir a la pestaña Seguridad]]
