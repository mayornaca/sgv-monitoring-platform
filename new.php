<?php
// Entry point for new Symfony 6.4 application
// Access via: https://vs.gvops.cl/new.php/login

use App\Kernel;

require_once dirname(__FILE__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};