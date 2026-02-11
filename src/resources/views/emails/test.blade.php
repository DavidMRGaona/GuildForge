<x-mail::message>
# Email de prueba

Este es un correo de prueba enviado desde GuildForge para verificar la configuración del correo electrónico.

**Driver**: {{ $driver }}
**Fecha**: {{ $timestamp }}

Si has recibido este correo, la configuración de correo funciona correctamente.

Saludos,<br>
{{ config('app.name') }}
</x-mail::message>
