<?php

declare(strict_types=1);

return [
    'errors' => [
        'quota_exceeded' => 'Límite mensual de :limit candidaturas alcanzado. Hazte Pro para candidaturas ilimitadas.',
        'already_subscribed' => 'Ya tienes una suscripción Pro activa.',
        'not_subscribed' => 'No tienes una suscripción activa.',
        'gateway_failure' => 'No pudimos contactar al proveedor de pago. Inténtalo de nuevo en unos segundos.',
        'cpf_required' => 'Informa tu CPF o CNPJ.',
        'cpf_invalid' => 'CPF o CNPJ inválido. Usa 11 dígitos para CPF o 14 para CNPJ.',
    ],

    'mail' => [
        'confirmed' => [
            'subject' => '¡Bienvenido a Open to Work Pro 🎉',
            'greeting' => '¡Hola, :name!',
            'intro' => 'Tu pago fue confirmado y tu suscripción Pro ya está activa. Ahora tienes candidaturas ilimitadas y todas las funciones premium desbloqueadas.',
            'renews' => 'Tu suscripción se renueva automáticamente el :date.',
            'button' => 'Ir a Open to Work',
            'thanks' => '¡Mucha suerte con tus candidaturas!',
        ],
        'canceled' => [
            'subject' => 'Tu suscripción Pro fue cancelada',
            'greeting' => 'Hola, :name,',
            'intro' => 'Confirmamos la cancelación de tu suscripción Pro. No se te cobrará de nuevo.',
            'access_until' => 'Mantienes el acceso Pro hasta el :date; después tu cuenta vuelve al plan gratuito.',
            'access_ended' => 'Tu acceso Pro ha finalizado y tu cuenta está en el plan gratuito.',
            'button' => 'Reactivar cuando quieras',
            'thanks' => '¡Esperamos verte de vuelta pronto!',
        ],
    ],
];
