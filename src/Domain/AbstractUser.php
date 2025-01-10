<?php

namespace Linkedcode\Doctrine\Domain;

class AbstractUser
{
    protected int $id;
    protected string $name;

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public static function createFromId($id): static
    {
        $lenght = 16;
        $bytes = openssl_random_pseudo_bytes(ceil($lenght / 2));

        $user = new static;
        $user->id = $id;
        $user->name = substr(bin2hex($bytes), 0, $lenght);

        return $user;
    }
}