<?php

namespace Meow;


class User
{
    private $id;
    private $email;
    private $login;
    private $role;
    private $remoteToken;

    public function __construct($id, $email, $login, $remoteToken, $role)
    {
        $this->id = $id;
        $this->email = $email;
        $this->login = $login;
        $this->remoteToken = $remoteToken;
        $this->role = $role; //1 - user, 2 - admin
    }

    public function GetID()
    {
        return $this->id;
    }

    public function GetName()
    {
        return $this->login;
    }

    public function GetRemoteToken()
    {
        return $this->remoteToken;
    }
}