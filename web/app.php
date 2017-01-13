<?php

// web/index.php
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'../config/config.php';

$app = new Silex\Application();
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver' => 'pdo_mysql',
        'host' => $miu_config['db_host'],
        'db_name' => $miu_config['db_dbase'],
        'user' => $miu_config['db_username'],
        'password' => $miu_config['db_password'],
        'charset' => 'utf8mb4'
    )
));
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views'
));
$app['debug'] = true;


$app->get('/', function() use ($app) {
    return $app['twig']->render('homepage.twig');
});

$app->post('/getfile/','Meow\\FileLoader::AddNewFile');

/*
 * Run first time to deploy the database
 * */
$app->get('/deploydb/', function ($name) use ($app) {
    return 'Hello '.$app->escape($name);
});

$app->run();