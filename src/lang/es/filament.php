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
            'hero_image' => 'Imagen del encabezado',
            'hero_image_help' => 'Imagen de fondo para el hero. Si no se configura, se muestra un degradado.',
            'tagline' => 'Eslogan',
            'tagline_help' => 'Subtítulo opcional bajo el título del hero',
            'history' => 'Nuestra historia',
            'history_help' => 'Contenido de la sección "Nuestra historia"',
            'activities' => 'Actividades',
            'activities_help' => 'Actividades que se muestran en "¿Qué hacemos?"',
            'activity_icon' => 'Icono',
            'activity_title' => 'Título',
            'activity_description' => 'Descripción',
            'icons' => [
                'dice' => 'Dado (Juegos de rol)',
                'sword' => 'Espada (Wargames)',
                'book' => 'Libro (Lectura/Lore)',
                'users' => 'Usuarios (Comunidad)',
                'calendar' => 'Calendario (Eventos)',
                'map' => 'Mapa (Campañas)',
                'trophy' => 'Trofeo (Torneos)',
                'puzzle' => 'Puzzle (Juegos de mesa)',
                'sparkles' => 'Estrellas (General)',
                'heart' => 'Corazón (Voluntariado)',
            ],
            'join_steps' => 'Pasos para unirse',
            'join_steps_help' => 'Pasos que se muestran en la sección "Cómo unirte" de la página Nosotros',
            'join_step_title' => 'Título del paso',
            'join_step_description' => 'Descripción (opcional)',
        ],
        'contact' => [
            'title' => 'Contacto',
            'email' => 'Correo electrónico',
            'phone' => 'Teléfono',
            'address' => 'Dirección postal',
        ],
    ],
];
