<?php

// web/index.php
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../config/config.php';

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



$app->get('/', function() use ($app) {
    return $app['twig']->render('homepage.twig');
});

$app->post('/getfile/','Meow\\FileLoader::AddNewFile');

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
            original_extension varchar(255) NULL,
            internal_filecategory INT UNSIGNED NOT NULL,
            date INT UNSIGNED NOT NULL,
            visibility_status INT NOT NULL DEFAULT 1,
            PRIMARY KEY(id));';
        $result = $db->query($query);
    }
    return "Script finished";
});

$app->run();