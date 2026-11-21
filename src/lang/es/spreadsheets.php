<?php

declare(strict_types=1);

return [
    'errors' => [
        'unsupported_type' => 'El fichero no es una hoja de cálculo admitida. Sube un .csv o un .xlsx.',
        'unreadable' => 'No se ha podido leer el fichero. Comprueba que no está dañado o guárdalo de nuevo como .csv o .xlsx.',
        'empty' => 'El fichero no tiene ninguna fila con datos debajo de la cabecera.',
        'too_many_rows' => 'El fichero tiene más de :limit filas con datos.',
        'too_many_columns' => 'El fichero tiene más de :limit columnas.',
        'too_large' => 'El fichero ocupa más de :limit.',
        'duplicate_headers' => 'La columna :column se llama igual que otra. Cada columna de la cabecera necesita un nombre distinto.',
    ],
];
