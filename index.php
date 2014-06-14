<?php
/* Slimフレームワーク */
require 'Slim/Slim.php';
\Slim\Slim::registerAutoloader();
/* Twig連携 */
require 'Twig/lib/Twig/Autoloader.php';
Twig_Autoloader::register();
/* O/Rマッパー */
require 'RedBean/rb.php';
/* Mail */
require_once 'lib/mail.php';

// set up database connection
R::setup('mysql:host=localhost;dbname=comic_service', 'root', 'root');
R::freeze(true);

class ResourceNotFoundException extends Exception
{
}

class ValidationCheckErrorException extends Exception
{
}

class AuthenticationErrorException extends Exception
{
}

const SESSION_KEY = 'comic-service-auth';
const USERS_COL_MAIL_ADDRESS = 'mail_address';
const USERS_COL_PASSWORD = 'password';
const USERS_COL_NICK_NAME = 'nick_name';
const USERS_COL_USER_THUMBNAIL = 'user_thumbnail';
const USERS_COL_ACCOUNT_HASH = 'account_hash';
const COMICS_COL_MAIL_ADDRESS = 'mail_address';
const COMICS_COL_TITLE = 'title';
const COMICS_COL_COMIC_IMAGE = 'comic_image';
const COMICS_COL_COMIC_THUMBNAIL = 'comic_thumbnail';
const COMICS_COL_COMIC_RECOMMEND_COUNTS = 'recommend_counts';
const TOKEN_COL_TOKEN_NAME = 'token_name';
const TOKEN_COL_MAIL_ADDRESS = 'mail_address';
const TOKEN_COL_PASSWORD = 'password';
const TOKEN_COL_NICK_NAME = 'nick_name';
const TOKEN_COL_USER_THUMBNAIL = 'user_thumbnail';
const TOKEN_COL_EXPIRE_AT = 'expire_at';


$app = new \Slim\Slim(array(
  'debug' => true,
  'view' => new \Slim\Extras\Views\Twig(),
));

/* セッション開始 */
session_start();

/**
 * 自動ログイン認証
 * @param \Slim\Route $route
 */
function authenticate(\Slim\Route $route)
{
  $app = \Slim\Slim::getInstance();
  $accountHash = $app->getCookie(session_name());
  if (!isset($_SESSION[SESSION_KEY])
    || $_SESSION[SESSION_KEY] !== $accountHash
  ) {
    $app->redirect("/");
  }
}

/**
 * セッションをセットする
 * @param $accountHash
 */
function setSession($accountHash)
{
  $_SESSION[SESSION_KEY] = $accountHash;
}

/**
 * 2週間有効なCookieをセットする
 * @param $app
 * @param $accountHash
 */
function setAutoLoginCookie(\Slim\Slim $app, $accountHash)
{
  $app->setCookie(session_name(), $accountHash, time() + 3600 * 24 * 14);
}

/**
 * WebAPI認証(ヘッダー認証)
 * @param \Slim\Route $route
 */
function apiAuthenticate(\Slim\Route $route)
{
  $app = Slim\Slim::getInstance();
  $header = getallheaders();
  if (!isset($header['Authorization']) || $header['Authorization'] !== 'hogehoge') {
    $app->halt(401, 'Authorization Error');
  };
}

/**
 * ユーザ登録画面
 */
$app->get('/sign-up', function () use ($app) {
  $app->render('sign-up.html.twig');
});

/**
 * ワンタイムURLでアクセスした際、ユーザを作成する
 */
$app->get('/sign-up/:id', function ($id) use ($app) {
  try {
    $token = R::findOne('tokens', TOKEN_COL_TOKEN_NAME . '=?', array($id));
    if ($token) {
      if (time($token[TOKEN_COL_EXPIRE_AT]) >= time()) {
        $accountHash = md5(uniqid(rand(), TRUE));
        /* Usersテーブルにユーザ追加 */
        $users = R::dispense('users');
        $users->mail_address = (string)$token[TOKEN_COL_MAIL_ADDRESS];
        $users->password = (string)$token[TOKEN_COL_PASSWORD];
        $users->nick_name = (string)$token[TOKEN_COL_NICK_NAME];
        $users->account_hash = $accountHash;
        if (isset($token[TOKEN_COL_USER_THUMBNAIL])) {
          $users->user_thumbnail = (string)$token[TOKEN_COL_USER_THUMBNAIL];
        }
        $users->created_at = null;
        $users->last_login_at = null;
        $id = R::store($users);

        /* Tokenテーブルのレコード削除 */
        R::trash($token);
        setSession($app, $accountHash);
        $app->render('email-sign-in.html.twig');
      } else {
        R::trash($token);
        /* 有効期限が切れている */
        $app->render('email-expired.html.twig');
      }
    } else {
      /* Tokenが存在しない */
      $app->halt(401, 'Error');
    }
  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

/**
 * トークンを作成し、メール送信
 */
$app->post('/sign-up', function () use ($app) {
  try {
    $request = $app->request();
    $mailAddress = $request->post(TOKEN_COL_MAIL_ADDRESS);
    $password = $request->post(TOKEN_COL_PASSWORD);
    $nickName = $request->post(TOKEN_COL_NICK_NAME);
    if (!isset($mailAddress) || !isset($password) || !isset($nickName)) {
      throw new ValidationCheckErrorException;
    }
    $userThumbnail = $request->post(TOKEN_COL_USER_THUMBNAIL);
    $tokenName = md5(uniqid(rand(), TRUE));
    $token = R::dispense('token');
    $token->token_name = (string)$tokenName;
    $token->mail_address = (string)$mailAddress;
    $token->password = (string)$password;
    $token->nick_name = (string)$nickName;
    if (isset($userThumbnail)) {
      $token->user_thumbnail = $userThumbnail;
    }
    /* トークンの有効期限を６時間に設定する */
    $token->expire_at = date("Y-m-d H:i:s", strtotime('+6 hours'));
    $token->created_at = null;
    $id = R::store($token);

    /* Mail送信 */
    send_token_mail($mailAddress, $tokenName);
    $app->render('email-post.html.twig');
  } catch (ValidationCheckErrorException $e) {
    /* エラーメッセージも */
    $app->render('/sign-up');
  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

/**
 * ログイン画面
 */
$app->get('/sign-in', function () use ($app) {
  $app->render('sign-in.html.twig');
});

/**
 * login認証
 * loginに成功した場合、SessionIDを設定し /へ
 * loginに失敗した場合、エラーページ
 */
$app->post('/sign-in', function () use ($app) {
  try {
    $request = $app->request();
    $mailAddress = $request->post(USERS_COL_MAIL_ADDRESS);
    $password = $request->post(USERS_COL_PASSWORD);
    $autoLoginCheckBox = $request->post('memory');
    /* SQLインジェクションの可能性あり */
    $user = R::findOne(
      'users',
      USERS_COL_MAIL_ADDRESS . '=? && ' . USERS_COL_PASSWORD . '=?',
      array($mailAddress, $password)
    );

    if ($user) {
      $accountHash = $user[USERS_COL_ACCOUNT_HASH];
      setSession($accountHash);
      if ($autoLoginCheckBox === true) {
        setAutoLoginCookie($app, $accountHash);
      }
      /* ログイン認証成功ページ */
      $app->redirect('/comics');
    } else {
      throw new AuthenticationErrorException();
    }
  } catch (AuthenticationErrorException $e) {
    /* ログイン認証失敗ページ */
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

/**
 * ログアウト画面
 */
$app->get('/sign-out', function () use ($app) {
  $app->render('sign-out.html.twig');
});

/**
 * ログアウト(Sessionの破棄)
 */
$app->post('/sign-out', function () use ($app) {
  /* セッション変数を全て解除する */
  $_SESSION = array();
  /** セッションを切断するにはセッションクッキーも削除する。 */
  $cookie = $app->getCookie(session_name());
  if ($cookie) {
    $app->setcookie(session_name(), '', time() - 42000, '/');
  }
  /** セッションを破壊する */
  session_destroy();
  $app->redirect('/');
});

/**
 * マイページ
 */
$app->get('/account', 'authenticate', function () use ($app) {
  try {
    $user = R::findOne('users', USERS_COL_ACCOUNT_HASH . '=?', array($_SESSION[SESSION_KEY]));
    if ($user) {
      $app->render('account.html.twig', array("user" => $user));
    } else {
      throw new AuthenticationErrorException;
    }
  } catch (AuthenticationErrorException $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

/**
 * コミック登録画面
 */
$app->get('/register-comic', 'authenticate', function () use ($app) {
  $app->render('comic-register.html.twig');
});

/**
 * コミック登録
 */
$app->post('/register-comic', 'authenticate', function () use ($app) {
  try {
    $request = $app->request();
    $title = $request->post(COMICS_COL_TITLE);
    $comicImage = $request->post(COMICS_COL_COMIC_IMAGE);
    $comicThumbnail = $request->post(COMICS_COL_COMIC_THUMBNAIL);
    if (!isset($title) || !isset($comicImage) || !isset($comicThumbnail)) {
      throw new ValidationCheckErrorException;
    }
    $user = R::findOne('users', USERS_COL_ACCOUNT_HASH . '=?', array($_SESSION[SESSION_KEY]));
    if (!$user) {
      throw new AuthenticationErrorException;
    }
    $comics = R::dispense('comics');
    $comics->mail_address = (string)$user[USERS_COL_MAIL_ADDRESS];
    $comics->title = (string)$title;
    $comics->comic_image = (string)$comicImage;
    $comics->comic_thumbnail = (string)$comicThumbnail;
    $comics->created_at = null;
    $id = R::store($comics);
    $app->redirect('/account');

  } catch (ValidationCheckErrorException $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  } catch (AuthenticationErrorException $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
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
 * リスト画面
 */
$app->get('/comics', function () use ($app) {
  $comics = R::findAll('comics');
  $app->render('comics-list.html.twig', array('comics' => $comics));
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
$app->get('/api/comics', 'apiAuthenticate', function () use ($app) {
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
$app->get('/api/comics/:id', 'apiAuthenticate', function ($id) use ($app) {
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
$app->get('/api/search-nickname/:nickName', 'apiAuthenticate', function ($nickName) use ($app) {
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
$app->get('/api/search-email/:e-mail', 'apiAuthenticate', function ($mailAddress) use ($app) {
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
$app->get('/api/check-password/:password', 'apiAuthenticate', function ($password) use ($app) {
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
