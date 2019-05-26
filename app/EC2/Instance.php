<?php

namespace App\EC2;

class Instance extends Entity
{
    public function getName(): string
    {
        return $this->get('KeyName');
    }

    public function getId(): string
    {
        return $this->get('InstanceId');
    }
}
