<?php

declare(strict_types=1);

return [
    'mail' => [
        'enabled' => [
            'subject' => 'Doble factor activado en :site',
            'body' => 'Se ha activado el doble factor de autenticación en tu cuenta de :site el :when. A partir de ahora, cada inicio de sesión te pedirá un código de tu app de autenticación. Hemos cerrado las demás sesiones abiertas de tu cuenta.',
        ],
        'disabled' => [
            'subject' => 'Doble factor desactivado en :site',
            'body' => 'Se ha desactivado el doble factor de autenticación en tu cuenta de :site el :when. Para iniciar sesión vuelve a bastar con tu contraseña.',
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
];
