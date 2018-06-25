<?php

require dirname(__DIR__) . '/vendor/autoload.php';

session_start();

$router = new \alejoluc\Via\Router();

$query = isset($_GET['query']) ? $_GET['query'] : '/';

// Without this call the Router will default to $_SERVER['REQUEST_URI'] or $_SERVER['PATH_INFO']
$router->setRequestString($query);

$router->setMatchHandler([new \alejoluc\Via\SampleHandlers\SampleFullMatchHandler, 'handle']);
$router->setOptions([
    'filters.stopOnFirstFail' => false
]);

// Registering filters in the router itself
$router->registerFilter('isLoggedIn', function(){
    if (!isset($_SESSION['logged-in'])) {
        //header('Location: index.php?query=/login');
        return 'You must be logged in to view this section';
    }
    return true;
});

$router->registerFilter('isAdmin', function(){
    if (!isset($_SESSION['user-level']) || $_SESSION['user-level'] < 2) {
        return 'You must be an administrator to view this section';
    }
    return true;
});

$router->registerFilter('canHaveMessages', function(){
    return true;
});

$router->get('/', 'HomePage');


$router->group('/user/', function($router){
    // Assign a filter to a specific route, by giving a string the filter will be searched in the filters
    // registered in the router
    $router->get('/cp/', 'Control Panel')->filter('isLoggedIn');

    $router->group('/messages/', function($router){
        $router->get('/', 'Messages Main Page');
        $router->get('/create/', 'Create Message');

        // Assign a custom filter to a specific route
        $router->get('/view/', 'View Message')->filter(function(){
            $time = time();
            if ($time % 2 !== 0) {
                return 'You must visit this page on even seconds';
            }
            return true;
        })->removeFilter('isLoggedIn'); // You can remove filters from specific routes
    }, ['canHaveMessages']);

    $router->get('profile', 'UserProfile');
}, ['isLoggedIn']);

$router->get('about', 'About Us');

$router->group('admin', function($router){
    $router->get('users/add', function(){});
    $router->get('users/edit', function(){});
    $router->get('users/delete', function(){});
    $router->get('users/list', function(){});

    $router->get('topics/add', function(){});
    $router->get('topics/edit', function(){});
    $router->get('topics/delete', function(){});
    $router->get('topics/list', function(){});
}, ['isLoggedIn', 'isAdmin']);




$result = $router->dispatch();

echo $result;