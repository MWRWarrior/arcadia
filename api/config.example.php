<?php
/**
 * Copy this file to config.php and fill in your database details.
 * config.php is gitignored — never commit real credentials.
 *
 * In cPanel: MySQL Databases → create a database and a user, add the user to
 * the database with ALL PRIVILEGES, then run schema.sql in phpMyAdmin.
 */

return [
    // cPanel usually prefixes both with your account name, e.g. "carlpre_arcadia"
    'db_host' => 'localhost',
    'db_name' => 'YOURACCT_arcadia',
    'db_user' => 'YOURACCT_arcadia',
    'db_pass' => 'YOUR_PASSWORD_HERE',

    // Any long random string. Used only to hash IP addresses for rate limiting,
    // so a raw IP is never written to disk. Change it once and leave it alone —
    // changing it later just resets rate-limit counters.
    'ip_salt' => 'CHANGE_ME_to_a_long_random_string',

    // How many builds one address may create per hour.
    'rate_limit_per_hour' => 30,

    // Reject anything larger than this (bytes). A full nine-slot build is ~500.
    'max_payload_bytes' => 8000,
];
