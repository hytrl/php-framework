<?php
declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

#[\AllowDynamicProperties]
abstract class BaseModel
{
    public function __get(string $name): mixed
    {
        return get_instance()?->$name;
    }
}
