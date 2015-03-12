

# Romma TO-DO

- [ ] Implement OR
- [ ] Map several routes to the same dispatch

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