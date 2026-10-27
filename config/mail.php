<?php

$encryption = env('MAIL_ENCRYPTION', 'tls');

return [
  'default' => env('MAIL_MAILER', env('MAIL_DRIVER', 'smtp')),

  'mailers' => [
    'smtp' => [
      'transport' => 'smtp',
      'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
      'port' => env('MAIL_PORT', 587),
      'scheme' => env('MAIL_SCHEME', $encryption === 'ssl' ? 'smtps' : 'smtp'),
      'auto_tls' => $encryption === 'tls',
      'require_tls' => $encryption === 'tls',
      'username' => env('MAIL_USERNAME'),
      'password' => env('MAIL_PASSWORD'),
      'timeout' => 30,
    ],
    'sendmail' => [
      'transport' => 'sendmail',
      'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs'),
    ],
    'log' => [
      'transport' => 'log',
      'channel' => env('MAIL_LOG_CHANNEL'),
    ],
    'array' => [
      'transport' => 'array',
    ],
  ],

  'from' => [
    'address' => env('MAIL_FROM_ADDRESS', 'datepoll@example.com'),
    'name' => env('MAIL_FROM_NAME', 'DatePoll'),
  ],
  'reply_to' => [
    'address' => env('MAIL_REPLY_ADDRESS', 'reply@example.com'),
    'name' => 'DatePoll',
  ],
  'markdown' => [
    'theme' => 'default',
    'paths' => [resource_path('views/vendor/mail')],
  ],
];
