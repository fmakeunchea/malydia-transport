<?php
// Copy to config.php inside malydia-private, OUTSIDE public_html. Never commit credentials.
return [
    'origins' => ['https://YOUR-WEBSITE-DOMAIN'], // Add the www origin too if used.
    // Copy the exact SMTP host from Zoho Mail > Settings > Mail Accounts > Server Configuration.
    'smtp_host' => '',
    'smtp_port' => 465, // 465 = implicit TLS; 587 = STARTTLS. Both require certificate verification.
    'smtp_username' => 'info@malydiahealth.com',
    'smtp_password' => '', // Enter a Zoho application-specific password here privately.
];
