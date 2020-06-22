<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) use ($router) {
    $response->getBody()->write('Welcome to Slim!');

    $router->urlFor('users'); // /users
    $router->urlFor('user', ['id' => 4]); // /users/4

    return $response;
    // Благодаря пакету slim/http этот же код можно записать короче
    // return $response->write('Welcome to Slim!');
});

$file = __DIR__ . '/../data/users.json';
$users = json_decode(file_get_contents($file), true);

$app->get('/users', function ($request, $response) use ($users){
    $term = $request->getQueryParam('term');
    $params = ['users' => $users, 'term' => $term];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$app->post('/users', function ($request, $response) use ($file, $users){
    $user = $request->getParsedBodyParam('user');
    $params = [
            'id' => $user['id'], 'nickname' => $user['nickname'], 'email' => $user['email']
    ];
    $users[] = $params;
    $data = json_encode($users);
    $currentFileData = "{$data}";
    file_put_contents($file, $currentFileData);
    return $response->withRedirect('/users');
})->setName('users');

$app->get('/users/new', function ($request, $response) {
    $params = [
        'user' => ['nickname' => '', 'email' => ''],
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

$app->get('/users/{id}', function ($request, $response, $args) use ($users) {
    $params = ['id' => $args['id'], 'users' => $users];

    foreach ($users as $user) {
        if (in_array($params['id'], $user)) {
            $params['nickname'] = $user['nickname'];
            return $this->get('renderer')->render($response, 'users/show.phtml', $params);
        }
    }

    return $response->write("Такого пользователя не существует.")->withStatus(404);
})->setName('user');

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
})->setName('course');

$app->run();
