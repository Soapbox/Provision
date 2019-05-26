<?php

namespace App\EC2;

class Instance
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getName(): string
    {
        return $this->data['KeyName'];
    }

    public function getId(): string
    {
        return $this->data['InstanceId'];
    }
}
