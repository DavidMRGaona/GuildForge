<?php

declare(strict_types=1);

return [
    'unavailable' => 'El doble factor no está disponible ahora mismo. Inténtalo de nuevo en unos segundos.',
    'mail' => [
        'enabled' => [
            'subject' => 'Doble factor activado en :site',
            'body' => 'Se ha activado el doble factor de autenticación en tu cuenta de :site el :when. A partir de ahora, cada inicio de sesión te pedirá un código de tu app de autenticación. Hemos cerrado las demás sesiones abiertas de tu cuenta.',
        ],
        'disabled' => [
            'subject' => 'Doble factor desactivado en :site',
            'body' => 'Se ha desactivado el doble factor de autenticación en tu cuenta de :site el :when. Para iniciar sesión vuelve a bastar con tu contraseña.',
        ],
        'reset' => [
            'subject' => 'La administración ha quitado el doble factor de tu cuenta de :site',
            'body' => 'La administración de :site ha quitado el doble factor de autenticación de tu cuenta el :when. Hemos cerrado todas las sesiones abiertas de tu cuenta. Para iniciar sesión basta con tu contraseña; puedes volver a activar el doble factor desde los ajustes de la cuenta.',
            'not_you' => 'Si no lo has pedido tú, cambia tu contraseña cuanto antes y avisa a la administración del sitio.',
        ],
        'repeated_code_failures' => [
            'subject' => 'Intentos de entrar en tu cuenta de :site',
            'body' => 'Alguien ha intentado entrar en tu cuenta de :site con tu contraseña y un código incorrecto varias veces seguidas (la última, el :when). El doble factor lo ha impedido.',
            'not_you' => 'Si no has sido tú, alguien conoce tu contraseña: cámbiala cuanto antes.',
        ],
        'recovery_code_used' => [
            'subject' => 'Se ha usado un código de recuperación en :site',
            'body' => 'Se ha usado uno de tus códigos de recuperación para iniciar sesión en tu cuenta de :site el :when.',
            'remaining' => '{0} No te queda ningún código de recuperación.|{1} Te queda :count código de recuperación.|[2,*] Te quedan :count códigos de recuperación.',
            'few_left' => 'Te quedan pocos: genera códigos nuevos desde los ajustes de tu cuenta y guárdalos en un lugar seguro.',
        ],
        'recovery_codes_regenerated' => [
            'subject' => 'Códigos de recuperación nuevos en :site',
            'body' => 'Se han generado códigos de recuperación nuevos para tu cuenta de :site el :when. Los anteriores ya no sirven.',
        ],
        'not_you' => 'Si no has sido tú, cambia tu contraseña cuanto antes y avisa a la administración del sitio.',
        'action' => 'Ir a los ajustes de la cuenta',
    ],

    'command' => [
        'not_found' => 'No hay ninguna cuenta con ese correo.',
        'confirm' => '¿Quitar el doble factor de la cuenta :email (:name, id :id)?',
        'ambiguous' => 'Hay :count cuentas cuyo correo solo se distingue en las mayúsculas: escribe el correo exacto de la que quieres.',
        'cancelled' => 'Cancelado: no se ha cambiado nada.',
        'refused' => 'No se ha podido restablecer el doble factor.',
        'nothing' => 'La cuenta :email no tiene el doble factor activado: no hay nada que quitar.',
        'done' => 'Doble factor quitado de la cuenta :email. Se han cerrado sus sesiones y se le ha avisado por correo.',
    ],

    // The panel's sign-in challenge (core#770)
    'panel' => [
        'label' => 'App de autenticación',
        'app_code' => 'Código de la app',
        'recovery_code' => 'Código de recuperación',
        'use_recovery_code' => 'Usar un código de recuperación',
    ],
];
