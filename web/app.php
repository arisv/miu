<?php

// web/index.php
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../config/config.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Validator\Constraints as Assert;

$app = new Silex\Application();
$app['debug'] = true;

$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver' => 'pdo_mysql',
        'host' => $miu_config['db_host'],
        'dbname' => $miu_config['db_dbase'],
        'user' => $miu_config['db_username'],
        'password' => $miu_config['db_password'],
        'charset' => 'utf8mb4'
    )
));
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views'
));

$app->register(new Silex\Provider\FormServiceProvider());
$app->register(new Silex\Provider\LocaleServiceProvider());
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'translator.domains' => array(),
));
$app->register(new Silex\Provider\ValidatorServiceProvider());

$app->register(new Silex\Provider\VarDumperServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());
$app['session.storage.options'] = array(
    'lifetime' => 90000
);

//runs before every request
$app->before(function () use ($app, $miu_config) {
    $ums = new \Meow\UserManager($app, $miu_config);
    $app['usermanager.service'] = $ums;
    $app['usermanager.service.loggedUser'] = null;
    $app['isUserAdmin'] = false;

    if($ums->HasLoggedUser())
    {
        $currentUser = $ums->GetCurrentUserData();
        dump($currentUser);
        $app['usermanager.service.loggedUser'] = $currentUser;
        $app['twig']->addGlobal('userLogged', true);
        $app['twig']->addGlobal('userName', $currentUser->GetName());
        if($currentUser->GetRole() == 2)
            $app['isUserAdmin'] = true;
    }
    else
        $app['twig']->addGlobal('userLogged', false);



});
//serve direct link to images
$app->get('/i/{customUrl}.{fileExtension}', 'Meow\\FileLoader::ServeFileDirect');

//serve generic file page (with links)
$app->get('/i/{customUrl}/', 'Meow\\FileLoader::ServeFile')
    ->assert('serviceUrl', '\w{10}\b');;

//serve service page for a file
$app->get('/i/{serviceUrl}/', 'Meow\\FileLoader::ServeFileService')
    ->assert('serviceUrl', '\w{32}\b');

//homepage
$app->get('/', function() use ($app) {
    return $app['twig']->render('homepage.twig');
});

//upload file requests go here
$app->post('/getfile/','Meow\\FileLoader::AddNewFile');

//create account page
$app->match('/signup/', function (Request $request) use ($app) {
    /** @var \Meow\UserManager $userManager */
    $userManager = $app['usermanager.service'];

    if($userManager->HasLoggedUser())
        return $app->redirect('/');

    $data = array(
        'login' => '',
        'email' => '',
        'password' => '',
        'password_r' => ''
    );

    /** @var Symfony\Component\Form\Form $form */
    $form = $app['form.factory']->createBuilder(FormType::class, $data)
        ->add('login', TextType::class, array(
            'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 4)))
        ))
        ->add('email', TextType::class, array(
            'constraints' => array(new Assert\Email())
        ))
        ->add('password', RepeatedType::class, array(
            'type' => PasswordType::class,
            'invalid_message' => 'The password fields must match.',
            'options' => array(
                'attr' => array('class' => 'password-field'),
                'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 7))),
                ),
            'required' => true,
        ))
        ->getForm();


    $form->handleRequest($request);
    if($form->isValid())
    {
        $data = $form->getData();
        $result = $userManager->CreateUser($data['login'], $data['email'], $data['password']);
        if($result['success'] === true)
            return $app['twig']->render('message.twig', array('text' => 'Your account has been created, you can now log in'));
        else
        {
            dump($result);
            return "Creation error";
        }
    }

    return $app['twig']->render('signup.twig', array('form' => $form->createView()));

});

//log in page
$app->match('/login/', function (Request $request) use ($app) {
    /** @var \Meow\UserManager $userManager */
    $userManager = $app['usermanager.service'];

    if($userManager->HasLoggedUser())
        return $app->redirect('/');

    $data = array(
        'email' => '',
        'password' => ''
    );

    /** @var Symfony\Component\Form\Form $form */
    $form = $app['form.factory']->createBuilder(FormType::class, $data)
        ->add('email', TextType::class, array(
            'constraints' => array(new Assert\Email())
        ))
        ->add('password', PasswordType::class, array(
            'constraints' => array(new Assert\NotBlank())
        ))
        ->getForm();

    $form->handleRequest($request);
    if($form->isValid())
    {
        $data = $form->getData();
        $result = $userManager->Login($data['email'], $data['password']);
        if($result)
        {
            return $app->redirect('/');
        }
        else
        {
            dump($result);
            return "Login error";
        }
    }

    return $app['twig']->render('loginpage.twig', array('form' => $form->createView()));
});

$app->get('/logout/', function () use ($app) {
    /** @var \Meow\UserManager $userManager */
    $userManager = $app['usermanager.service'];

    $userManager->Logout();

    return $app->redirect('/');
});

$app->get('/manage/', function () use ($app){
    /** @var \Meow\UserManager $userManager */
    $userManager = $app['usermanager.service'];

    if(!$userManager->HasLoggedUser())
        return $app->redirect('/login/');

    return $app['twig']->render('manage_layout.twig', array('page' => 'home'));

});

$app->get('/manage/mytoken/', function () use ($app){
    /** @var \Meow\UserManager $userManager */
    $userManager = $app['usermanager.service'];

    if(!$userManager->HasLoggedUser())
        return $app->redirect('/login/');

    $token = $app['usermanager.service.loggedUser']->GetRemoteToken();
    return $app['twig']->render('manage_displaytoken.twig', array(
        'page' => 'token',
        'myToken' => $token));

});

$app->get('/manage/mypics/', function (Request $request) use ($app){
    /** @var \Meow\UserManager $userManager */
    $userManager = $app['usermanager.service'];

    if(!$userManager->HasLoggedUser())
        return $app->redirect('/login/');

    $controlPanel = new \Meow\ControlPanel($app);
    $listOfImages = $controlPanel->GetCurrentUserImages($request, $userManager->GetCurrentUserID());
    dump($listOfImages);

    return $app['twig']->render('manage_mypics.twig', array(
        'page' => 'mypics',
        'data' => $listOfImages,
        ));
})->bind('manage_mypics');

$app->get('/manage/admin/users/', function (Request $request) use ($app){
    /** @var \Meow\UserManager $userManager */
    $userManager = $app['usermanager.service'];

    if(!$userManager->HasLoggedUser())
        return $app->redirect('/login/');

    if($app['usermanager.service.loggedUser']->GetRole() != 2)
        return $app->redirect('/manage/');

    $controlPanel = new \Meow\ControlPanel($app);
    $userData = $controlPanel->GetAllUserIndex($request);

    dump($userData);
    return $app['twig']->render('admin_users.twig', array(
        'page' => 'allpics',
        'userlist' => $userData));

});

$app->get('/endpoint/getstoragestats/', function(Request $request) use ($app){
    /** @var \Meow\UserManager $userManager */
    $userManager = $app['usermanager.service'];

    $response = array(
        'status' => 'ok',
        'message' => ''
        );

    if(!$userManager->HasLoggedUser())
    {
        $response['status'] = 'error';
        $response['message'] = 'Please log in';
    }
    else if($app['usermanager.service.loggedUser']->GetRole() != 2)
    {
        $response['status'] = 'error';
        $response['message'] = 'Insufficient privileges';
    }
    else
    {
        $controlPanel = new \Meow\ControlPanel($app);
        $response['message'] = $controlPanel->GetStorageStats($request);
    }
    return $app->json($response);


});

$app->error(function(\Exception $e) use($app) {
    if($e->getMessage() == '404')
    {
        return $app['twig']->render('error_404.twig');
    }
    else
        return $app['twig']->render('error_general.twig');
});

$app->run();