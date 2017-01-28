<?php

require dirname(__DIR__) . '/vendor/autoload.php';

require __DIR__ . '/ExampleControllers/ExampleController.php';

$router = new alejoluc\Via\Router();

$query = isset($_GET['query']) ? $_GET['query'] : '/';

$router->setRequestString($query);

$router->setMatchHandler([new \alejoluc\Via\SampleHandlers\SampleFullMatchHandler, 'handle']);

// Targeting a class and method by using an array in the destination
$router->get('/', ['ExampleController', 'home']);

// Returning a string
$router->get('/about', 'This is our about page');

// Returning the result of a callback
$router->get('/contact', function(){
   return 'This is our contact page. Current date: ' . date('d/m/Y h:i:s');
});

// Using a prefix to group related url paths

$router->group('/api', function($router){
   $router->group('/users', function($router){
       $router->get('/',     ['ExampleController', 'usersList']);   // route: api/users/
       $router->get('/list', ['ExampleController', 'usersList']);   // route: api/users/list
       $router->get('/add',  ['ExampleController', 'addUserForm']); // route: api/users/add, responds to GET
       $router->post('/add', ['ExampleController', 'addUser']);     // route: api/users/add, responds to POST
   });
});

echo $router->dispatch();