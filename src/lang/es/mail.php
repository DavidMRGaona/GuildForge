<?php

declare(strict_types=1);

return [
    'test' => [
        'subject' => ':app: email de prueba',
        'heading' => 'Email de prueba',
        'intro' => 'Este es un correo de prueba enviado desde :app para verificar la configuración del correo electrónico.',
        'driver' => 'Driver',
        'date' => 'Fecha',
        'outro' => 'Si has recibido este correo, la configuración de correo funciona correctamente.',
    ],
    'preferences' => [
        'sections' => [
            'account' => 'Cuenta',
            'site_management' => 'Gestión del sitio',
        ],
        'categories' => [
            'account' => [
                'label' => 'Tu cuenta',
                'description' => 'Verificación del email, cambio de contraseña o de email, bienvenida y avisos sobre tus roles.',
                'reason' => 'Siempre se envía: confirma algo que has hecho tú, te avisa de un cambio en tu cuenta o te pide una acción.',
            ],
            'role_management' => [
                'label' => 'Roles inactivos',
                'description' => 'Aviso cuando una persona a la que puedes conceder roles se queda con alguno inactivo.',
            ],
        ],
    ],
];
