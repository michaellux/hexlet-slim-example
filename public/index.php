<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use DI\Container;

session_start();

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});
$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);
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
    $messages = $this->get('flash')->getMessages();
    print_r("<h1>{$messages['success'][0]}</h1>");
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$app->post('/users', function ($request, $response) use ($file, $users){
    $user = $request->getParsedBodyParam('user');
    $params = [
            'id' => $user['id'], 'nickname' => $user['nickname'], 'firstName' => $user['firstName'], 'lastName' => $user['lastName'], 'email' => $user['email']
    ];
    $users[] = $params;
    $data = json_encode($users);
    $currentFileData = "{$data}";
    file_put_contents($file, $currentFileData);
    $this->get('flash')->addMessage('success', 'Новый пользователь создан');
    return $response->withRedirect('/users');
})->setName('users');

$app->get('/users/new', function ($request, $response) {
    $params = [
        'user' => ['nickname' => '', 'firstName' => '', 'lastName' => '', 'email' => ''],
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

$app->patch('/users/{id}', function ($request, $response, array $args) use ($users, $file, $router) {
    $params = ['id' => $args['id'], 'users' => $users];
    $dataForm = $request->getParsedBodyParam('user');
    $key = array_search($params['id'], array_column($users, 'id'));
    if ($key !== false) {
        $users[$key]['nickname'] = $dataForm['nickname'];
        $users[$key]['firstName'] = $dataForm['firstName'];
        $users[$key]['lastName'] = $dataForm['lastName'];
        $users[$key]['email'] = $dataForm['email'];
        $data = json_encode($users);
        $currentFileData = "{$data}";
        file_put_contents($file, $currentFileData);
        $this->get('flash')->addMessage('success', 'Профиль пользователя обновлён');
        return $response->withRedirect('/users');
    }

    return $response->write("Такого пользователя не существует.")->withStatus(404);
});

$app->delete('/users/{id}', function ($request, $response, array $args) use ($users, $file, $router) {
    $params = ['id' => $args['id']];
    $key = array_search($params['id'], array_column($users, 'id'));
    if ($key !== false) {
        $updatedListOfusers = array_filter($users, fn ($user) => $user['id'] !== $params['id']);
        $data = json_encode($updatedListOfusers);
        $currentFileData = "{$data}";
        file_put_contents($file, $currentFileData);
        $this->get('flash')->addMessage('success', 'Пользователь удалён');
        return $response->withRedirect($router->urlFor('users'));
    }

    return $response->write("Такого пользователя не существует.")->withStatus(404);
});

$app->get('/users/{id}/edit', function ($request, $response, array $args) use ($users){
    $params = ['id' => $args['id']];

    foreach ($users as $user) {
        if (in_array($params['id'], $user)) {
            $params['user'] = $user;
            return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
        }
    }
    return $response->write("Такого пользователя не существует.")->withStatus(404);
})->setName('editUser');

$app->get('/users/{id}/delete', function ($request, $response, array $args) use ($users, $router) {
    $linkToListOfUsers = $router->urlFor('user',['id' => $args['id']]);
    $params = ['id' => $args['id'], 'link' => $linkToListOfUsers];
    $key = array_search($params['id'], array_column($users, 'id'));
    if ($key !== false) {
        return $this->get('renderer')->render($response, 'users/delete.phtml', $params);
    }
    return $response->write("Такого пользователя не существует.")->withStatus(404);
})->setName('deleteUser');

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
})->setName('course');

$app->run();
