<?php


namespace Meow;
use \Doctrine\DBAL\Connection;

class UserManager
{
    private $db;
    private $adminMail;

    public function __construct(Connection $db, $miu_config)
    {
        $this->db = $db;
        if(isset($miu_config['admin_email']))
            $this->adminMail = $miu_config['admin_email'];
        else
            $this->adminMail = null;
    }

    public function CreateUser($login, $email, $password)
    {
        $result = array(
            'success' => true,
            'errors'  => array()
        );

        if(!Util::AssertUniqueness('users', $email, 'email', $this->db))
        {
            $result['success'] = false;
            $result['errors'][] = "This email is already in use";
        }

        if(!Util::AssertUniqueness('users', $login, 'login', $this->db))
        {
            $result['success'] = false;
            $result['errors'][] = "This username is already in use";
        }

        if($result['success'] === true)
        {
            do{
                $token = bin2hex(openssl_random_pseudo_bytes(16)); //Token for remote clients
            }while(!Util::AssertUniqueness('users', $token, 'remote_token', $this->db));

            $pwhash = password_hash($password, PASSWORD_DEFAULT);

            $role = 1;//user
            if($this->adminMail && $email === $this->adminMail)
                $role = 2; //admin

            $qb = $this->db->createQueryBuilder();
            $qb->insert('users')
                ->setValue('login', '?')
                ->setValue('email', '?')
                ->setValue('password', '?')
                ->setValue('remote_token', '?')
                ->setValue('role', '?')
                ->setValue('active', '?')
                ->setParameter(0, $login)
                ->setParameter(1, $email)
                ->setParameter(2, $pwhash)
                ->setParameter(3, $token)
                ->setParameter(4, $role)
                ->setParameter(5, 1);

            $qbresult = $qb->execute();
        }
        return $result;

    }

    public function Login($email, $password)
    {
        $qb = $this->db->createQueryBuilder();
        $qb->select('*')
            ->from('users')
            ->where('email = :email')
            ->setParameter('email', $email);
        $result = $qb->execute();
        $accdata = $result->fetchAll();
        if($result->rowCount() === 1)
        {
            if(password_verify($password, $accdata[0]['password']))
            {
                return true;
            }
        }
        else
            return false;
    }


}