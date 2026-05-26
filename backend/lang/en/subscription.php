<?php

declare(strict_types=1);

return [
    'errors' => [
        'quota_exceeded' => 'Monthly limit of :limit applications reached. Upgrade to Pro for unlimited applications.',
        'already_subscribed' => 'You already have an active Pro subscription.',
        'not_subscribed' => 'You do not have an active subscription.',
        'gateway_failure' => 'Could not reach the payment provider. Please try again in a few seconds.',
        'cpf_required' => 'Please provide your CPF or CNPJ.',
        'cpf_invalid' => 'Invalid CPF or CNPJ. Use 11 digits for CPF or 14 for CNPJ.',
    ],

    'mail' => [
        'confirmed' => [
            'subject' => 'Welcome to Open to Work Pro 🎉',
            'greeting' => 'Hi :name!',
            'intro' => 'Your payment was confirmed and your Pro subscription is now active. You now have unlimited applications and every premium feature unlocked.',
            'renews' => 'Your subscription renews automatically on :date.',
            'button' => 'Open Open to Work',
            'thanks' => 'Good luck with your applications!',
        ],
        'canceled' => [
            'subject' => 'Your Pro subscription was canceled',
            'greeting' => 'Hi :name,',
            'intro' => 'We\'ve confirmed the cancellation of your Pro subscription. You won\'t be charged again.',
            'access_until' => 'You keep Pro access until :date — after that your account returns to the free plan.',
            'access_ended' => 'Your Pro access has ended and your account is on the free plan.',
            'button' => 'Reactivate anytime',
            'thanks' => 'We hope to see you back soon!',
        ],
    ],
];
