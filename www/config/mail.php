<?php
/**
 * Mail Service Configuration
 * FinalNote Project
 */

return [
    // Driver: 'smtp' or 'log' (saves to storage/logs/mail.log for local testing)
    'driver'     => getenv('MAIL_DRIVER') ?: 'log',
    'host'       => getenv('MAIL_HOST') ?: 'sandbox.smtp.mailtrap.io',
    'port'       => (int)(getenv('MAIL_PORT') ?: 2525),
    'username'   => getenv('MAIL_USERNAME') ?: '',
    'password'   => getenv('MAIL_PASSWORD') ?: '',
    'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
    'from'       => [
        'address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@finalnote.tdtu.edu.vn',
        'name'    => getenv('MAIL_FROM_NAME') ?: 'FinalNote Notifications',
    ],
];
