<?php

/*
 * In this example, the following routes will be generated, by using prefixes for some of them:
 *
 * /
 * /api/
 * /api/users/
 * /api/users/{:user}/
 * /api/listall/
 * /api/v0.1/createuser/
 * /about/
 * /api/v0.2/actions/create/user/{:user}/
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$router = new alejoluc\Via\Router();

$query = isset($_GET['query']) ? $_GET['query'] : '/';

$router->setRequestMethod('GET');
$router->setRequestString($query);


$router->get('/', 'Hello World');

$router->group('/api/', function($router){
    $router->get('/', 'Api Default Page');  // The slash in the pattern is optional, can be blank string
    $router->group('/users/', function($router){
        $router->get('', 'Users List');
        $router->get('{:user}', function($user){
            echo 'Viewing ' . $user;
        });
    });

    $router->get('listAll', 'Viewing all users');
});

$router->group('/api/v0.1/', function($router){
    $router->get('createUser', 'Creating user');
});

$router->group('/api/v0.2/', function($router){
    $router->group('/actions/create', function($router){
        $router->get('/user/{:user}/', function($user){
            return 'Creating user ' . $user . ' with api v0.2';
        });
    });
});

$router->get('/about', 'We are such and such');

$match = $router->dispatch();

if (!$match->isMatch()) {
    echo '<h1>404 NOT FOUND</h1>';
} elseif (!$match->filtersPass()) {
    echo 'Filter error: ' . $match->getFilterError() . '<br />';
} else {
    $result = $match->getResult();

    if (is_string($result)) {
        echo $result;
    } elseif (is_callable($result)) {
        echo call_user_func_array($result, $match->getParameters());
    } else {
        throw new Exception('Unhandled match type: ' . gettype($result));
    }
}