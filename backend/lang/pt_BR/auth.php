<?php

declare(strict_types=1);

return [
    'failed' => 'Essas credenciais não correspondem aos nossos registros.',
    'password' => 'A senha fornecida está incorreta.',
    'throttle' => 'Muitas tentativas de login. Tente novamente em :seconds segundos.',

    'verify' => [
        'email_subject' => 'Confirme seu e-mail',
        'email_greeting' => 'Olá, :name!',
        'email_intro' => 'Use o código abaixo para confirmar seu e-mail e ativar sua conta:',
        'email_expires' => 'Este código expira em :minutes minutos.',
        'email_ignore' => 'Se você não criou uma conta, ignore esta mensagem.',
        'email_thanks' => 'Obrigado,',
        'must_verify' => 'Você precisa confirmar seu e-mail antes de entrar. Reenviamos um código para você.',
        'invalid_email' => 'Não encontramos uma conta com este e-mail.',
        'invalid_code' => 'Código inválido. Verifique e tente novamente.',
        'code_expired' => 'O código expirou. Solicite um novo.',
        'too_many_attempts' => 'Muitas tentativas inválidas. Solicite um novo código.',
        'already_verified' => 'Este e-mail já foi confirmado.',
    ],

    'reset' => [
        'email_subject' => 'Redefinir sua senha',
        'email_greeting' => 'Olá, :name!',
        'email_intro' => 'Recebemos um pedido para redefinir a senha da sua conta. Clique no botão abaixo para escolher uma nova senha.',
        'email_button' => 'Redefinir senha',
        'email_expires' => 'Este link expira em :minutes minutos.',
        'email_ignore' => 'Se você não solicitou a redefinição, ignore este e-mail — sua senha não será alterada.',
        'email_thanks' => 'Obrigado,',
        'invalid_token' => 'Link de redefinição inválido ou expirado. Solicite um novo.',
    ],
];
