<?php

declare(strict_types=1);

return [
    'errors' => [
        'quota_exceeded' => 'Limite mensal de :limit candidaturas atingido. Faça upgrade pro Pro pra candidaturas ilimitadas.',
        'already_subscribed' => 'Você já tem uma assinatura Pro ativa.',
        'not_subscribed' => 'Você não tem uma assinatura ativa.',
        'gateway_failure' => 'Não conseguimos falar com o provedor de pagamento. Tente novamente em alguns segundos.',
        'cpf_required' => 'Informe seu CPF ou CNPJ.',
        'cpf_invalid' => 'CPF ou CNPJ inválido. Use 11 dígitos para CPF ou 14 para CNPJ.',
    ],

    'mail' => [
        'confirmed' => [
            'subject' => 'Bem-vindo ao Open to Work Pro 🎉',
            'greeting' => 'Olá, :name!',
            'intro' => 'Seu pagamento foi confirmado e sua assinatura Pro está ativa. Agora você tem candidaturas ilimitadas e todos os recursos premium liberados.',
            'renews' => 'Sua assinatura renova automaticamente em :date.',
            'button' => 'Acessar o Open to Work',
            'thanks' => 'Bom trabalho e boa sorte nas candidaturas!',
        ],
        'canceled' => [
            'subject' => 'Sua assinatura Pro foi cancelada',
            'greeting' => 'Olá, :name,',
            'intro' => 'Confirmamos o cancelamento da sua assinatura Pro. Você não será cobrado novamente.',
            'access_until' => 'Você continua com acesso Pro até :date — depois disso sua conta volta para o plano gratuito.',
            'access_ended' => 'Seu acesso Pro foi encerrado e sua conta está no plano gratuito.',
            'button' => 'Reativar quando quiser',
            'thanks' => 'Esperamos te ver de volta em breve!',
        ],
    ],
];
