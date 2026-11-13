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
            'proposals' => 'Propuestas',
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
            'proposals_review' => [
                'label' => 'Propuestas para revisar',
                'description' => 'Aviso cuando alguien envía a revisión un evento o un artículo que puedes revisar.',
            ],
            'proposals_status' => [
                'label' => 'Estado de mis propuestas',
                'description' => 'Aviso cuando se aprueba, se devuelve o se rechaza un evento o un artículo que has propuesto.',
            ],
        ],
    ],
    'footer' => [
        'optional' => '¿No quieres recibir más correos como este?',
        'unsubscribe' => 'Darte de baja de «:category»',
        'choose' => 'Elegir qué correos recibes',
        'mandatory' => 'Este correo siempre se envía. Puedes elegir qué otros recibes :profile.',
        'without_category' => 'Puedes elegir qué correos recibes :profile.',
        'profile_link' => 'en tu perfil',
        'mute_subject' => 'No recibir más avisos de «:subject»',
        'digest' => 'Recibes este resumen porque elegiste recibir una vez al día los avisos de estas categorías.',
    ],
    'unsubscribe' => [
        'done' => 'Ya no recibirás correos de «:category».',
        'resubscribed' => 'Vuelves a recibir correos de «:category».',
        'mandatory' => 'Los correos de «:category» siempre se envían.',
        'one_click_not_found' => 'Este enlace ya no sirve para darse de baja.',
    ],
    'mute_subject' => [
        'unmuted' => 'Vuelves a recibir avisos de «:subject».',
    ],
    'digest' => [
        'subject' => '{1} Tu resumen diario: 1 aviso|[2,*] Tu resumen diario: :count avisos',
        'intro' => 'Estos son los avisos que elegiste recibir juntos, una vez al día.',
        'open' => 'Ver',
        'more' => '{1} Y 1 aviso más, el más antiguo, que no cabe en este resumen.|[2,*] Y :count avisos más, los más antiguos, que no caben en este resumen.',
    ],
];
