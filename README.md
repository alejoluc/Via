# alejoluc\Via

Simple PHP router with prefix grouping and filters

## Using the "Facade"

A "Facade" is provided for those that want clearer code and don't mind using static classes here and there. You can use any method of the Router class when calling the Facade.

```php
<?php
use alejoluc\Via\RouterFacade as Router;

Router::get('login', ['LoginController', 'showForm']);
Router::group('admin/', function(){
    Router::get('createUser', ['UsersController', 'createForm']);
}, ['isLoggedIn']);
```

## Letting the router build the request string on it's own or using setRequestString()

If no request string is manually specified, the router will try to get it automatically from `$_SERVER['REQUEST_URI']` or `$_SERVER['PATH_INFO']`. Aditionally, it will truncate the script file name from the request string, if it is there, and it will also truncate the query string (anything after `?` or `&`), if it exists. This will not happen if you manually set the request string via `Router::setRequestString()`

As a general rule, you should manually call `Router::setRequestString()` only when you know what you are doing and why, and let the router do the dirty work the rest of the time.

For example, if you have no url rewriting in place in your server, you might still want to use the router and you can do it by placing the routes in a query variable, like `q`. An example for that approach, assuming the file name is index.php, would be:

```php
<?php
use alejoluc\Router\Router;
$router = new Router();
$q = isset($_GET['q']) ? $_GET['q'] : '/';
$router->setRequestString($q);

$router->get('/about', 'About Page'); // Will respond to /index.php?q=/about 
```