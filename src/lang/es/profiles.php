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
];
