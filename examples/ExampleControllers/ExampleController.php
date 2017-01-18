<?php

class ExampleController {

    public function home() {
        return '<h1>Welcome to our website</h1>';
    }

    public function usersList() {
        return '<h1>This is the list of our users</h1>';
    }

    public function addUserForm() {
        return '<form method="POST" action="?query=api/users/add">
                    Username: <input type="text" name="username" /><br />
                    Password: <input type="password" name="password" /><br />
                    <input type="submit" value="Crear" />
                </form>';
    }

    public function addUser() {
        $out  = '<h1>User created</h1>';
        $out .= '<p>Username: ' . $_POST['username'] . '</p>';
        $out .= '<p>Password: ' . $_POST['password'] . '</p>';
        return $out;
    }

}