<?php
defined('BASEPATH') or exit('No direct script access allowed');

$config['base_url']   = '';

// İzin verilen host listesi (Host Header Injection koruması)
$config['allowed_hosts'] = ['localhost', '127.0.0.1'];

$config['index_page'] = '';

$config['timezone']   = 'Europe/Istanbul';
$config['charset']    = 'UTF-8';

$config['log_threshold'] = 1;

$config['proxy_ips'] = [];

// Session ayarları
$config['session_name']           = 'fw_session';
$config['session_expiration']     = 7200;
$config['session_time_to_update'] = 300;

// CSRF (Cross-Site Request Forgery) koruması
$config['csrf_protection']   = false;
$config['csrf_token_name']   = 'csrf_token';
$config['csrf_cookie_name']  = 'csrf_cookie';
$config['csrf_expire']       = 7200;
$config['csrf_regenerate']   = false;
$config['csrf_exclude_uris'] = [];

// Temel güvenlik başlıkları
$config['security_headers'] = [
    'X-Content-Type-Options'            => 'nosniff',
    'X-Frame-Options'                   => 'SAMEORIGIN',
    'Referrer-Policy'                   => 'strict-origin-when-cross-origin',
    'X-Permitted-Cross-Domain-Policies' => 'none',
];
