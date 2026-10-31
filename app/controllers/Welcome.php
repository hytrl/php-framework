<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Welcome extends BaseController {

    public function __construct() {

        parent::__construct();

    }

    public function index() {

        $this->load->view('welcome_message', [
            'title'   => 'The framework is running!',
            'version' => FRAMEWORK_VERSION,
            'php'     => PHP_VERSION,
        ]);

    }

}
