<?php
// load required files
require 'Slim/Slim.php';
require 'RedBean/rb.php';

// register Slim auto-loader
\Slim\Slim::registerAutoloader();

// set up database connection
R::setup('mysql:host=localhost;dbname=slim', 'root', 'root');
R::freeze(true);
// initialize app
$app = new \Slim\Slim();

class ResourceNotFoundException extends Exception
{
}

// handle GET requests for /articles/:id
$app->get('/articles/:id', function ($id) use ($app) {
  try {
    // query database for single article
    $article = R::findOne('articles', 'id=?', array($id));

    if ($article) {
      $mediaType = $app->request()->getMediaType();
      if ($mediaType == 'application/xml') {
        $app->response()->header('Content-Type', 'application/xml');
        $xml = new SimpleXMLElement('<articles/>');
        $result = R::exportAll($article);
        foreach ($result as $r) {
          $item = $xml->addChild('item');
          $item->addChild('id', $r['id']);
          $item->addChild('title', $r['title']);
          $item->addChild('url', $r['url']);
          $item->addChild('date', $r['date']);
        }
        echo $xml->asXml();
      } else {
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(R::exportAll($article));
      }
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

// handle GET requests for /articles
$app->get('/articles', function () use ($app) {
  // query database for all articles
  $articles = R::find('articles');
  $mediaType = $app->request()->getMediaType();
  if ($mediaType == 'application/xml') {
    $app->response()->header('Content-Type', 'application/xml');
    $xml = new SimpleXMLElement('<articles/>');
    $result = R::exportAll($articles);
    foreach ($result as $r) {
      $item = $xml->addChild('item');
      $item->addChild('id', $r['id']);
      $item->addChild('title', $r['title']);
      $item->addChild('url', $r['url']);
      $item->addChild('date', $r['date']);
    }
    echo $xml->asXml();
  } else {
    $app->response()->header('Content-Type', 'application/json');
    echo json_encode(R::exportAll($articles));
  }
});

// handle POST requests to /articles
$app->post('articles', function () use ($app) {
  try {
    // check request content type
    // decode request body in JSON or XML format
    $request = $app->request();
    $mediaType = $request->getMediaType();
    $body = $request->getBody();
    if ($mediaType == 'application/xml') {
      $input = simplexml_load_string($body);
    } elseif ($mediaType == 'application/json') {
      $input = json_decode($body);
    }

    // create and store article record
    $article = R::dispense('articles');
    $article->title = (string)$input->title;
    $article->url = (string)$input->url;
    $article->date = (string)$input->date;
    $id = R::store($article);

    // return JSON/XML response
    if ($mediaType == 'application/xml') {
      $app->response()->header('Content-Type', 'application/xml');
      $xml = new SimpleXMLElement('<root/>');
      $result = R::exportAll($article);
      foreach ($result as $r) {
        $item = $xml->addChild('item');
        $item->addChild('id', $r['id']);
        $item->addChild('title', $r['title']);
        $item->addChild('url', $r['url']);
        $item->addChild('date', $r['date']);
      }
      echo $xml->asXml();
    } elseif ($mediaType == 'application/json') {
      $app->response()->header('Content-Type', 'application/json');
      echo json_encode(R::exportAll($article));
    }
  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

$app->put('/articles/:id', function ($id) use ($app) {
  try {
    // get and decode JSON request body
    $request = $app->request();
    $body = $request->getBody();
    $input = json_decode($body);

    // query database for single article
    $article = R::findOne('articles', 'id=?', array($id));

    // store modified article
    // return JSON-encoded response body
    if ($article) {
      $article->title = (string)$input->title;
      $article->url = (string)$input->url;
      $article->date = (string)$input->date;
      R::store($article);
      $app->response()->header('Content-Type', 'application/json');
      echo json_encode(R::exportAll($article));
    } else {
      throw new ResourceNotFoundException();
    }
  } catch (ResourceNotFoundException $e) {
    $app->response()->status(404);
  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

// handle DELETE requests to /articles/:id
$app->delete('/articles/:id', function ($id) use ($app) {
  try {
    // query database for article
    $request = $app->request();
    $article = R::findOne('articles', 'id=?', array($id));

    // delete article
    if ($article) {
      R::trash($article);
      $app->response()->status(204);
    } else {
      throw new ResourceNotFoundException();
    }
  } catch (ResourceNotFoundException $e) {
    $app->response()->status(404);
  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});


// run
$app->run();