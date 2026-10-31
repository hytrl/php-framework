<?php
declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

#[\AllowDynamicProperties]
abstract class BaseController
{
    private static ?BaseController $instance = null;

    public Loader $load;
    public Input $input;
    public Output $output;
    public Config $config;
    public Security $security;

    public function __construct()
    {
        self::$instance = $this;

        $this->config   = Framework::core('config');
        $this->input    = Framework::core('input');
        $this->output   = Framework::core('output');
        $this->security = Framework::core('security');
        $this->load     = new Loader();

        $this->load->autoload();
    }

    public static function instance(): ?BaseController
    {
        return self::$instance;
    }

    public function __get(string $name): mixed
    {
        if ($name === 'db') {
            $this->load->database();
            return $this->db;
        }

        trigger_error('Undefined property: ' . static::class . '::$' . $name, E_USER_WARNING);
        return null;
    }
}
