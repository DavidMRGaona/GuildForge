<?php

declare(strict_types=1);

return [
    'create_missing' => [
        'created' => '{1} :count perfil creado|[0,*] :count perfiles creados',
        'would_create' => '{1} :count perfil se crearía|[0,*] :count perfiles se crearían',
        'realigned' => '{1} :count dirección realineada con el nombre público|[0,*] :count direcciones realineadas con el nombre público',
        'would_realign' => '{1} :count dirección se realinearía con el nombre público|[0,*] :count direcciones se realinearían con el nombre público',
        'failed' => '{1} :count cuenta se quedó sin perfil o sin realinear (detalles en el log)|[0,*] :count cuentas se quedaron sin perfil o sin realinear (detalles en el log)',
        'would_fail' => '{1} :count cuenta se quedaría sin dirección libre|[0,*] :count cuentas se quedarían sin dirección libre',
        'dry_run_note' => 'Simulación: no se ha escrito nada. Una carrera con otra escritura solo se ve al ejecutarlo de verdad.',
    ],
    'stamp_completed' => [
        'stamped' => '{1} :count perfil completo marcado|[0,*] :count perfiles completos marcados',
        'would_stamp' => '{1} :count perfil completo se marcaría|[0,*] :count perfiles completos se marcarían',
        'dry_run_note' => 'Simulación: no se ha escrito nada.',
    ],
    'sections' => [
        'about' => [
            'label' => 'Sobre mí',
            'description' => 'Lo que cuentas de ti, con los enlaces que quieras compartir.',
        ],
    ],
    'editor' => [
        'saved' => 'Perfil guardado.',
        'banner_saved' => 'Banner actualizado.',
        'banner_removed' => 'Banner quitado.',
        'attributes' => [
            'handle' => 'dirección',
            'visibility' => 'quién puede verlo',
            'bio' => 'sobre mí',
            'show_membership' => 'mostrar que soy socio',
            'sections' => 'secciones',
            'section' => 'sección',
            'stats' => 'contadores',
            'stat' => 'contador',
            'banner' => 'banner',
        ],
        'errors' => [
            'handle_unavailable' => 'Esta dirección no está disponible.',
            'handle_malformed' => 'La dirección lleva de 3 a 30 letras minúsculas sin tilde, cifras y guiones sueltos entre ellas.',
            'handle_too_soon' => 'Podrás cambiar tu dirección de nuevo a partir del :date.',
            'membership_only' => 'Solo quien es socio puede mostrarlo.',
            'banner_type' => 'El banner tiene que ser una imagen JPEG, PNG o WebP.',
            'banner_size' => 'El banner puede ocupar 5 MB como mucho.',
            'banner_failed' => 'No se ha podido guardar el banner. Inténtalo de nuevo.',
        ],
    ],
];
