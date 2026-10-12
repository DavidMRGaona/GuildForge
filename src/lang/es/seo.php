<?php

declare(strict_types=1);

return [
    'formats' => [
        'date' => 'j \d\e F \d\e Y',
        'day_month' => 'j \d\e F',
    ],
    'schedule' => [
        'single_day' => ':date, :time',
        'same_month' => ':start_day–:end_date',
        'same_year' => ':start_day_month – :end_date',
        'different_year' => ':start_date – :end_date',
    ],
    'titles' => [
        'events' => 'Eventos',
        'articles' => 'Artículos',
        'gallery' => 'Galería',
        'calendar' => 'Calendario de eventos',
        'search' => 'Buscar',
        'about' => 'Sobre :name',
        'login' => 'Iniciar sesión',
        'register' => 'Crear cuenta',
        'forgot_password' => 'Recuperar contraseña',
        'reset_password' => 'Restablecer contraseña',
        'verify_email' => 'Verificar correo',
        'profile' => 'Mi perfil',
        'maintenance' => 'Estamos en mantenimiento',
    ],
    'descriptions' => [
        'articles' => 'Noticias y artículos de la asociación',
        'gallery' => 'Galería de fotos de la asociación',
    ],
];
