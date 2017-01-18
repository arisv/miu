<?php


namespace Meow;
use \Doctrine\DBAL\Connection;

class UserManager
{
    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function CreateUser()
    {

    }

    public function Login()
    {

    }


}