<?php

// web/index.php
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../config/config.php';

use Symfony\Component\HttpFoundation\Request;

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
$app->register(new Silex\Provider\VarDumperServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());
$app['session.storage.options'] = array(
    'lifetime' => 90000
);

$app->get('/', function() use ($app) {
    return $app['twig']->render('homepage.twig');
});

$app->post('/getfile/','Meow\\FileLoader::AddNewFile');

//serve direct link to images
$app->get('/i/{customUrl}.png', 'Meow\\FileLoader::ServeFileDirect');

//serve generic file page
$app->get('/i/{customUrl}/', 'Meow\\FileLoader::ServeFile')
    ->assert('serviceUrl', '\w{10}\b');;

//serve service page
$app->get('/i/{serviceUrl}/', 'Meow\\FileLoader::ServeFileService')
    ->assert('serviceUrl', '\w{32}\b');

$app->get('/signup/', function () use ($app) {
    return $app['twig']->render('signup.twig');
});

$app->get('/login/', function () use ($app) {
    return $app['twig']->render('loginpage.twig');
});

$app->post('/test/', function (Request $request) {
    dump($request->request->get('private_key'));
    return "Key: ".$request->request->get('private_key');
});

/*
 * Run first time to deploy the database
 * */
$app->get('/deploydb/', function () use ($app, $miu_config) {
    $db = $app['db'];
    $tableName = 'filestorage';
    $result = $db->query('SHOW TABLES LIKE "'.$tableName.'"');
    if($result->rowCount() == 0)
    {
        $query = 'CREATE TABLE '.$tableName.'(
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            original_name varchar(255) NOT NULL,
            internal_name varchar(255) NOT NULL,
            custom_url varchar(255) NULL,
            service_url varchar(255) NULL,
            original_extension varchar(255) NULL,
            internal_mimetype varchar(255) NULL,
            internal_size INT UNSIGNED NOT NULL,
            date INT UNSIGNED NOT NULL,
            visibility_status INT NOT NULL DEFAULT 1,
            PRIMARY KEY(id));';
        $result = $db->query($query);
    }

    $tableName = 'users';
    $result = $db->query('SHOW TABLES LIKE "'.$tableName.'"');
    if($result->rowCount() == 0)
    {
        $query = 'CREATE TABLE '.$tableName.'(
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            login TEXT NOT NULL,
            email varchar(255) NOT NULL,
            password TEXT NOT NULL,
            remote_token varchar(255) NULL,
            role INT NOT NULL DEFAULT 1,
            active INT NOT NULL DEFAULT 1,
            PRIMARY KEY(id));';
        $result = $db->query($query);
    }

    return "Script finished";
});

$app->run();