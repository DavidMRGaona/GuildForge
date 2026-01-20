<?php

declare(strict_types=1);

return [
    'settings' => [
        'title' => 'Configuración del sitio',
        'location' => [
            'title' => 'Ubicación de la sede',
            'description' => 'Configura las coordenadas y datos de la ubicación que se mostrará en el mapa de la página "Nosotros".',
            'name' => 'Nombre del lugar',
            'address' => 'Dirección',
            'lat' => 'Latitud',
            'lng' => 'Longitud',
            'zoom' => 'Nivel de zoom',
            'preview' => 'Vista previa del mapa',
            'saved' => 'Configuración guardada correctamente',
        ],
        'about' => [
            'title' => 'Sobre nosotros',
            'association_name' => 'Nombre de la asociación',
            'association_name_help' => 'Se mostrará en el título de la página "Sobre nosotros"',
            'history' => 'Nuestra historia',
            'history_help' => 'Contenido de la sección "Nuestra historia"',
        ],
        'contact' => [
            'title' => 'Contacto',
            'email' => 'Correo electrónico',
            'phone' => 'Teléfono',
            'address' => 'Dirección postal',
        ],
    ],
];
