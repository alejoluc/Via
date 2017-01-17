<?php

require dirname(__DIR__) . '/vendor/autoload.php';

$router = new \alejoluc\Via\Router();

$query = isset($_GET['query']) ? $_GET['query'] : '/';

$router->setRequestMethod('GET');
$router->setRequestString($query);


// Registering filters in the router itself
$router->registerFilter('isLoggedIn', function(){
    session_start();
    if (!isset($_SESSION['logged-in'])) {
        return 'You must be logged in to view this section';
    }
    return true;
});

$router->registerFilter('isAdmin', function(){
    session_start();
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
}, ['isAdmin']);


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