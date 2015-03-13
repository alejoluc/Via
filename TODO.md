

# Romma TO-DO

- [ ] Make special characters work as parameter identifiers
- [ ] Allow the passing of a Dependency Injection Container
- [ ] Implement OR
- [ ] Map several routes to the same dispatch

### Make special characters work as parameter identifiers

Currently, special characters like slash, underscore and dot work only for
retreiving the request data, but they don't work as the field identifiers.
In simpler words, I can use a dot in the URL, but I can't use a dot in the
parameter name when I define the route.

### Allow the passing of a DIC

```php
<?php
$romma->setDIC($app); // Set is as a member?

$romma->add('/api/users/git/{user}', function($request, $app){ // Pass it?
    $validator = $this->getDIC('app'); // Get it from member?
    $validator = $app('validator'); // Get it from function call?

    $user = $request->param('user');
    return $validator->validate('validUsername', $user);
});
?>
```

### Implement OR

```php
<?php
$romma->add('/api/users/get/{user}{.json|.xml:format}', function($request){
    $format = $request->param('format', 'json'); // json is the default
    $user = $request->param('user');
    $response = $DIContainer('response');
    $response->setOutputFormat($format);
    $response->setBody($DIContainer('userApi')->getById($user)->format($format));
    return true;
});
?>
```

### Map several routes to the same dispatch

```php
<?php
$routes = [
    '/api/users/get/{user}',
    '/api/users/getById/{user}'
];
$romma->add($routes, function($request){
    $user = $request->param('user');
});
?>
```