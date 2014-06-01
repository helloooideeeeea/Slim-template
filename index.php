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
R::setup('mysql:host=localhost;dbname=comic_service', 'root', 'root');
R::freeze(true);

class ResourceNotFoundException extends Exception
{
}

const SESSION_KEY = 'comic-service-auth';
const USERS_COL_MAIL_ADDRESS = 'mail_address';
const USERS_COL_PASSWORD = 'password';
const USERS_COL_NICK_NAME = 'nick_name';
const USERS_COL_USER_THUMBNAIL = 'user_thumbnail';
const COMICS_COL_MAIL_ADDRESS = 'mail_address';
const COMICS_COL_TITLE = 'title';
const COMICS_COL_COMIC_IMAGE = 'comic_image';
const COMICS_COL_COMIC_THUMBNAIL = 'comic_thumbnail';
const COMICS_COL_COMIC_RECOMMEND_COUNTS = 'recommend_counts';


/**
 *
 * @param \Slim\Route $route
 */
function authenticate(\Slim\Route $route)
{
  $app = \Slim\Slim::getInstance();
  if (!isset($_SESSION[SESSION_KEY])) {
    /* DB認証が必要か */
    $app->halt(404);
  }
}

$app = new \Slim\Slim(array(
  'debug' => true,
  'view' => new \Slim\Extras\Views\Twig(),
));
session_start();

/**
 * ユーザを作成する
 */
$app->post('/create-user', function () use ($app) {
  try {
    $request = $app->request();
    $mailAddress = $request->post(USERS_COL_MAIL_ADDRESS);
    $password = $request->post(USERS_COL_PASSWORD);
    $nickName = $request->post(USERS_COL_NICK_NAME);
    if (!isset($mailAddress) || !isset($password) || !isset($nickName)) {
      throw new ResourceNotFoundException;
    }
    $userThumbnail = $request->post(USERS_COL_USER_THUMBNAIL);
    $users = R::dispense('users');
    $users->mail_address = (string)$mailAddress;
    $users->password = (string)$password;
    $users->nick_name = (string)$nickName;
    $users->created_at = null;
    $users->last_login_at = null;
    if (isset($userThumbnail)) {
      $users->user_thumbnail = $userThumbnail;
    }
    $id = R::store($users);
    $app->redirect('');
  } catch (ResourceNotFoundException $e) {

  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

$app->get('/register-comic', function () use ($app) {
   $app->render('comic-register.html.twig');
});

$app->post('/register-comic', function () use ($app) {
  try {
    $request = $app->request();
    $title = $request->post(COMICS_COL_TITLE);
    $comicImage = $request->post(COMICS_COL_COMIC_IMAGE);
    $comicThumbnail = $request->post(COMICS_COL_COMIC_THUMBNAIL);

    if (!isset($title) || !isset($comicImage) || !isset($comicThumbnail)) {
      throw new ResourceNotFoundException;
    }

    $comics = R::dispense('comics');
    $comics->mail_address = 'hoge@gmail.com';
    $comics->title = (string)$title;
    $comics->comic_image = (string)$comicImage;
    $comics->comic_thumbnail = (string)$comicThumbnail;
    $comics->created_at = null;
    $id = R::store($comics);
    $app->redirect('/');
  } catch (ResourceNotFoundException $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  } catch(Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

/**
 * login認証
 * loginに成功した場合、SessionIDを設定し /へ
 * loginに失敗した場合、エラーページ
 */
$app->post('/login', function () use ($app) {
  try {
    $request = $app->request();
    $mailAddress = $request->post(USERS_COL_MAIL_ADDRESS);
    $password = $request->post(USERS_COL_PASSWORD);
    /* SQLインジェクションの可能性あり */
    $user = R::findOne('users', 'mail_address=? && password=?', array($mailAddress, $password));
    if ($user) {
      session_regenerate_id(true);
      $_SESSION[SESSION_KEY] = $mailAddress;
      /* ログイン認証成功ページ */
      $app->redirect('/my-page');
    } else {
      /* ログイン認証失敗ページ */
      $app->redirect('/login');
    }
  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

$app->get('/logout', function () use ($app) {
 /* セッション変数のクリア */
  $_SESSION = array();
 /* クッキーの破棄 */
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params["path"], $params["domain"],
      $params["secure"], $params["httponly"]
    );
  }
 /* セッションクリア */
  session_destroy();
});

$app->get('/login', function () use ($app) {
  $app->render('_login.html.twig');
});

/**
 * マイページ
 */
$app->get('/my-page', function () use ($app) {
  try {
    if (isset($_SESSION[SESSION_KEY])) {
      $app->render('my-page.html.twig');
    } else {
      $app->render('_login.html.twig', array('message' => 'セッションが切れています。ログインし直してください。'));
    }
  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

/**
 * トップページ
 */
$app->get('/', function () use ($app) {
  $comics = R::findAll('comics');
  $app->render('index.html.twig', array('comics' => $comics));
});

/**
 * コミックス詳細
 */
$app->get('/comics/:id', function ($id) use ($app) {
  $comic = R::findOne('comics', 'id=?', array((int)$id));
  $comments = R::findAll('comments', 'comics_id=?', array((int)$id));
  $app->render('comic-details.html.twig', array('comic' => $comic, 'comments' => $comments));
});

/**
 * コミックスAPI(Json)
 */
$app->get('/api/comics', function () use ($app) {
  try {
    // query database for single article
    $comics = R::find('comics');
    if ($comics) {
      // if found, return JSON response
      $app->response()->header('Content-Type', 'application/json');
      echo json_encode(R::exportAll($comics));
    } else {
      // else throw exception
      throw new ResourceNotFoundException();
    }
  } catch (ResourceNotFoundException $e) {
    // return 404 server error
    $app->response()->status(404);
  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

/**
 * コミックス詳細API(Json)
 */
$app->get('/api/comics/:id', function ($id) use ($app) {
  try {
    // query database for single article
    $comics = R::findOne('comics', 'id=?', array($id));
    if ($comics) {
      // if found, return JSON response
      $app->response()->header('Content-Type', 'application/json');
      echo json_encode(R::exportAll($comics));
    } else {
      // else throw exception
      throw new ResourceNotFoundException();
    }
  } catch (ResourceNotFoundException $e) {
    // return 404 server error
    $app->response()->status(404);
  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

/**
 * ニックネーム検索API
 */
$app->get('/api/search-nickname/:nickName', function ($nickName) use ($app) {
  try {
    $users = R::findOne('users', 'nick_name=?', array($nickName));
    if ($users) {
      echo $nickName;
    } else {
      echo 'NG';
    }
  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

/**
 * メールアドレス検索API
 */
$app->get('/api/search-email/:e-mail', function ($mailAddress) use ($app) {
  try {
    $users = R::findOne('users', 'mail_address=?', array($mailAddress));
    if ($users) {
      echo $mailAddress;
    } else {
      echo 'NG';
    }
  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

/**
 * パスワードチェックAPI
 */
$app->get('/api/check-password/:password', function ($password) use ($app) {
  try {
    /* 6文字以上 */
    if (strlen($password) == 6) {
      echo 'OK';
    } else {
      echo 'NG';
    }
  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

/* Run the application */
$app->run();
