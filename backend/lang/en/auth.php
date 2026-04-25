<?php

declare(strict_types=1);

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    'verify' => [
        'email_subject' => 'Confirm your email',
        'email_greeting' => 'Hi, :name!',
        'email_intro' => 'Use the code below to confirm your email and activate your account:',
        'email_expires' => 'This code expires in :minutes minutes.',
        'email_ignore' => "If you didn't create an account, just ignore this message.",
        'email_thanks' => 'Thanks,',
        'must_verify' => 'You need to confirm your email before signing in. We just sent you a new code.',
        'invalid_email' => 'We could not find an account with this email.',
        'invalid_code' => 'Invalid code. Please double-check and try again.',
        'code_expired' => 'The code has expired. Please request a new one.',
        'too_many_attempts' => 'Too many invalid attempts. Please request a new code.',
        'already_verified' => 'This email has already been verified.',
    ],
];
