<?php
// load required files
require 'Slim/Slim.php';
\Slim\Slim::registerAutoloader();
require 'Twig/lib/Twig/Autoloader.php';
Twig_Autoloader::register();

$app = new \Slim\Slim(array(
  'debug' => true,
  'view' => new \Slim\Extras\Views\Twig(),
));

$app->get('/', function() use ($app) {
  $app->render('index.html.twig', array('name' => 'batman'));
});

/* Run the application */
$app->run();
