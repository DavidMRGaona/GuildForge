<?php

declare(strict_types=1);

return [
    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña no es correcta.',
    'throttle' => 'Demasiados intentos de inicio de sesión. Vuelve a intentarlo en :seconds segundos.',
    'registered' => 'Te has registrado correctamente.',
    'verification_failed' => 'El enlace de verificación no es válido.',
    'email_verified' => 'Tu correo electrónico ha sido verificado.',
    'verification_link_sent' => 'Se ha enviado un nuevo enlace de verificación.',
    'email_verification_required' => 'Verifica tu correo electrónico para continuar.',
    'public_name' => [
        'required' => 'Elige tu nombre público para continuar.',
        'saved' => 'Nombre público guardado.',
    ],
    'profile_updated' => 'Ajustes actualizados correctamente.',
    'password_incorrect' => 'La contraseña actual no es correcta.',
    'password_changed' => 'Contraseña actualizada correctamente.',
    'mail_preferences_saved' => 'Preferencias de correo guardadas.',
    'mail_pause_started' => 'Correos optativos en pausa.',
    'mail_pause_ended' => 'Has reanudado los correos optativos.',
    'mail_preferences_not_editable' => 'Esa categoría de correo no se puede cambiar.',
    'email_change_verification_failed' => 'El enlace de verificación no es válido o ha expirado.',
    'email_changed' => 'Tu correo electrónico ha sido actualizado correctamente.',
    'email_change_pending' => 'Se ha enviado un correo de verificación a tu nueva dirección.',

    // Email notification for pending email change
    'verify_email_change_subject' => 'Verifica tu nuevo correo electrónico',
    'verify_email_change_line1' => 'Has solicitado cambiar tu correo electrónico. Haz clic en el botón de abajo para verificar tu nueva dirección.',
    'verify_email_change_action' => 'Verificar correo electrónico',
    'verify_email_change_line2' => 'Si no has solicitado este cambio, puedes ignorar este correo.',

    // Welcome email notification
    'welcome_subject' => '¡Bienvenido a :app!',
    'welcome_greeting' => '¡Hola, :name!',
    'welcome_line1' => 'Gracias por registrarte en :app. Estamos encantados de tenerte con nosotros.',
    'welcome_line2' => 'Ya puedes acceder a tu cuenta y disfrutar de todas las funcionalidades de nuestra comunidad.',
    'welcome_action' => 'Iniciar sesión',
    'welcome_preferences' => 'En los ajustes de tu cuenta puedes elegir qué correos quieres recibir.',
    'welcome_line3' => '¡Esperamos verte pronto en nuestros eventos!',

    // Sign-in challenge (core#770)
    'two_factor' => [
        'invalid_code' => 'El código no es válido o ya se ha usado.',
        'unavailable' => 'Ahora no podemos comprobar el código. Inténtalo de nuevo en unos minutos.',
        'too_many_attempts' => 'Demasiados códigos seguidos. Vuelve a intentarlo en :seconds segundos.',
        'sign_in_again' => 'Vuelve a iniciar sesión.',
        'too_many_failures' => 'Demasiados códigos incorrectos. Vuelve a iniciar sesión.',
        'recovery_codes_left' => '{0} Has usado tu último código de recuperación: genera códigos nuevos en los ajustes de la cuenta.|{1} Te queda :count código de recuperación.|[2,*] Te quedan :count códigos de recuperación.',
    ],
];
