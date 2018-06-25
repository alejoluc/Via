<?php

require __DIR__ . '/../vendor/autoload.php';

use alejoluc\Via\RouterFacade as Router;

$query = isset($_GET['query']) ? $_GET['query'] : '/';
Router::setRequestString($query); // Without this call the Router will default to $_SERVER['REQUEST_URI'] or $_SERVER['PATH_INFO']
Router::setMatchHandler([new \alejoluc\Via\SampleHandlers\SampleFullMatchHandler, 'handle']);

Router::registerFilter('alwaysFails', function(){
    return 'There was a filter failure';
});

Router::get('/', 'Main Page');

Router::group('/users', function(){
    Router::get('/', 'Manage users');
}, ['alwaysFails']);

echo Router::dispatch();