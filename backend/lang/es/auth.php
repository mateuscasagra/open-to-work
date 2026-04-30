<?php

declare(strict_types=1);

return [
    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña proporcionada es incorrecta.',
    'throttle' => 'Demasiados intentos de inicio de sesión. Inténtalo de nuevo en :seconds segundos.',

    'verify' => [
        'email_subject' => 'Confirma tu correo',
        'email_greeting' => '¡Hola, :name!',
        'email_intro' => 'Usa el código a continuación para confirmar tu correo y activar tu cuenta:',
        'email_expires' => 'Este código expira en :minutes minutos.',
        'email_ignore' => 'Si no creaste una cuenta, ignora este mensaje.',
        'email_thanks' => 'Gracias,',
        'must_verify' => 'Necesitas confirmar tu correo antes de iniciar sesión. Te enviamos un nuevo código.',
        'invalid_email' => 'No encontramos una cuenta con este correo.',
        'invalid_code' => 'Código inválido. Verifica e inténtalo de nuevo.',
        'code_expired' => 'El código expiró. Solicita uno nuevo.',
        'too_many_attempts' => 'Demasiados intentos inválidos. Solicita un nuevo código.',
        'already_verified' => 'Este correo ya fue confirmado.',
    ],

    'reset' => [
        'email_subject' => 'Restablecer tu contraseña',
        'email_greeting' => '¡Hola, :name!',
        'email_intro' => 'Recibimos una solicitud para restablecer la contraseña de tu cuenta. Haz clic en el botón a continuación para elegir una nueva contraseña.',
        'email_button' => 'Restablecer contraseña',
        'email_expires' => 'Este enlace expira en :minutes minutos.',
        'email_ignore' => 'Si no solicitaste el restablecimiento, ignora este correo — tu contraseña no cambiará.',
        'email_thanks' => 'Gracias,',
        'invalid_token' => 'Enlace de restablecimiento inválido o expirado. Solicita uno nuevo.',
    ],
];
