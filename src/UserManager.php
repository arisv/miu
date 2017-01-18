<?php


namespace Meow;
use \Doctrine\DBAL\Connection;
use Silex\Application;
use Symfony\Component\HttpFoundation\Session\Session;

class UserManager
{
    /** @var Connection $db */
    private $db;
    /** @var Session $session */
    private $session;
    private $adminMail;

    public function __construct(Application $app, $miu_config)
    {
        $this->db = $app['db'];
        $this->session = $app['session'];
        if (isset($miu_config['admin_email']))
            $this->adminMail = $miu_config['admin_email'];
        else
            $this->adminMail = null;
    }

    public function CreateUser($login, $email, $password)
    {
        $result = array(
            'success' => true,
            'errors' => array()
        );

        if (!Util::AssertUniqueness('users', $email, 'email', $this->db)) {
            $result['success'] = false;
            $result['errors'][] = "This email is already in use";
        }

        if (!Util::AssertUniqueness('users', $login, 'login', $this->db)) {
            $result['success'] = false;
            $result['errors'][] = "This username is already in use";
        }

        if ($result['success'] === true) {
            do {
                $token = bin2hex(openssl_random_pseudo_bytes(16)); //Token for remote clients
            } while (!Util::AssertUniqueness('users', $token, 'remote_token', $this->db));

            $pwhash = password_hash($password, PASSWORD_DEFAULT);

            $role = 1;//user
            if ($this->adminMail && $email === $this->adminMail)
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
            ->where('email = :email AND active = 1')
            ->setParameter('email', $email);
        $result = $qb->execute();
        $accdata = $result->fetchAll();
        if ($result->rowCount() === 1) {
            if (password_verify($password, $accdata[0]['password'])) {
                $this->SetCurrentUserID($accdata[0]['id']);
                return true;
            }

        }
        return null;
    }

    public function HasLoggedUser()
    {
        if(intval($this->session->get('currentUser')) > 0)
            return true;
        return false;
    }

    public function GetCurrentUserID()
    {
        return $this->session->get('currentUser');
    }

    public function SetCurrentUserID($id)
    {
        $this->session->set('currentUser', $id);
    }

    public function Logout()
    {
        $this->session->set('currentUser', null);
    }

    public function GetCurrentUserData()
    {
        return $this->GetUserByID($this->GetCurrentUserID());
    }

    public function GetUserByID($id)
    {
        if($id == null)
            return null;
        $qb = $this->db->createQueryBuilder();
        $qb->select('*')
            ->from('users')
            ->where('id = :id AND active = 1')
            ->setParameter('id', $id);
        $result = $qb->execute();
        $accdata = $result->fetchAll();
        if(!empty($accdata))
        {
            return new User($accdata[0]['id'],
                $accdata[0]['email'],
                $accdata[0]['login'],
                $accdata[0]['remote_token'],
                $accdata[0]['role']);
        }
        return null;
    }


}