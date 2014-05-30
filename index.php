<?php
/* Slimフレームワーク */
require 'Slim/Slim.php';
\Slim\Slim::registerAutoloader();
/* Twig連携 */
require 'Twig/lib/Twig/Autoloader.php';
Twig_Autoloader::register();
/* O/Rマッパー */
require 'RedBean/rb.php';

// set up database connection
R::setup('mysql:host=localhost;dbname=slim', 'root', 'root');
R::freeze(true);

class ResourceNotFoundException extends Exception
{
}

/**
 *
 * @param \Slim\Route $route
 */
function authenticate(\Slim\Route $route)
{
  $app = \Slim\Slim::getInstance();
  $uid = $app->getEncryptedCookie('uid');
  $key = $app->getEncryptedCookie('key');
  if (validateUserKey($uid, $key) === false) {
    $app->halt(401);
  }
}

/**
 *
 * @param $uid
 * @param $key
 * @return bool
 */
function validateUserKey($uid, $key)
{
  if ($uid === 'batman' && $key === 'dark-knight') {
    return true;
  } else {
    return false;
  }
}

$app = new \Slim\Slim(array(
  'debug' => true,
  'view' => new \Slim\Extras\Views\Twig(),
));

$app->get('/cookie', function () use ($app) {
  try {
    $app->setEncryptedCookie('uid', 'batman', '10 minutes');
    $app->setEncryptedCookie('key', 'dark-knight', '10 minutes');
  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

$app->get('/', function () use ($app) {
  $app->render('index.html.twig', array('name' => 'batman'));
});

/* Run the application */
$app->run();
