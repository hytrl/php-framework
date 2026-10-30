<?php
declare(strict_types=1);

#[\AllowDynamicProperties]
abstract class BaseModel
{
    public function __get(string $name): mixed
    {
        return get_instance()?->$name;
    }
}
