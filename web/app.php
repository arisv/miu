<?php

// web/index.php
require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views'
));
$app['debug'] = true;


$app->get('/', function() use ($app) {
    return $app['twig']->render('homepage.twig');
});

$app->post('/getfile/','Meow\\FileLoader::AddNewFile');

$app->get('/hello/{name}', function ($name) use ($app) {
    return 'Hello '.$app->escape($name);
});

$app->run();