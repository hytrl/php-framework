<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Example_hook
{
    public static function add_security_headers(): void
    {
        $out = get_instance()->output;
        $out->set_header('X-Content-Type-Options', 'nosniff');
        $out->set_header('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
