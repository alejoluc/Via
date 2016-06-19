<?php

class ViaTest extends PHPUnit_Framework_TestCase
{

    /** @var $router \Via\Via */
    private $router;

    public function setUp()
    {
        $this->router = new Via\Via;
    }

    /**
     * @expectedException \Via\NoSuchRouteException
     */
    public function testNoRouteThrowsException()
    {
        $this->router->setRequestString('home');
        $this->router->dispatch();
    }

    /**
     * @expectedException \Via\NoRequestStringSpecifiedException
     */
    public function testNoRequestStringThrowsException()
    {
        $this->router->add('home', 'HomePage');
        $this->router->setRequestMethod('GET');
        $this->router->dispatch();
    }

    /**
     * @expectedException \Via\NoSuchRouteException
     */
    public function testRouteExistsButNotForThatRequestMethod()
    {
        $this->router->setRequestString('home');
        $this->router->setRequestMethod('GET');

        $this->router->add('home', 'HomePage', 'POST');
        $this->router->dispatch();
    }

    public function testDispatchesToCorrectRouteByMethod()
    {
        $this->router->setRequestString('home');
        $this->router->setRequestMethod('POST');

        $this->router->add('home', 'HomePage', 'GET');
        $this->router->add('home', 'HomePagePost', 'POST');
        $this->assertEquals('HomePagePost', $this->router->dispatch()->destination);

        $this->router->setRequestMethod('GET');
        $this->assertEquals('HomePage', $this->router->dispatch()->destination);
    }

    public function testCorrectRouteIsDispatched()
    {
        $this->router->setRequestString('/users/list');
        $this->router->setRequestMethod('GET');

        $this->router->add('users/{:user}/', 'UserMainPage', 'GET');
        $this->router->add('users/{:user}/posts', 'UserPostsPage', 'GET');
        $this->router->add('users/list/', 'UsersListPage', 'GET');
        $this->assertEquals('UsersListPage', $this->router->dispatch()->destination);

        $this->router->setRequestString('users/alejo/');
        $this->assertEquals('UserMainPage', $this->router->dispatch()->destination);
    }

    public function testCustomFiltersSatisfied()
    {
        $this->router->setRequestString('/users/alejo/posts/12');
        $this->router->setRequestMethod('GET');

        $withFilters = $this->router->add('users/{:user}/posts/{:month}', 'UserPostsPageByMonth', 'GET');
        $withFilters->filter('month', '[0-9]{1,2}');

        $this->assertEquals('UserPostsPageByMonth', $this->router->dispatch()->destination);
    }

    /**
     * @expectedException \Via\NoSuchRouteException
     */
    public function testCustomFiltersNotSatisfied()
    {
        $this->router->setRequestString('/users/alejo/posts/123');
        $this->router->setRequestMethod('GET');

        $withFilters = $this->router->add('users/{:user}/posts/{:month}', 'UserPostsPageByMonth', 'GET');
        $withFilters->filter('month', '[0-9]{1,2}');

        $this->router->dispatch();
    }

    public function testDispatcherReturnsArrayIfSpecified()
    {
        $this->router->setRequestString('/users/alejo');
        $this->router->setRequestMethod('GET');

        $this->router->add('users/{:user}', ['UsersController', 'showUserPage']);
        
        $this->assertEquals(['UsersController', 'showUserPage'], $this->router->dispatch()->destination);
    }

    public function testFluentInterface()
    {
        $this->router->setRequestString('/users/alejo/posts/9650');
        $this->router->setRequestMethod('GET');

        $destination = ['UserController', 'UserPostView'];
        $this->router->add('users/{:user}/posts/{:post_id}', $destination)
                     ->filter('user', '\w+')
                     ->filter('post_id', '\d+');
        
        $this->assertEquals($destination, $this->router->dispatch()->destination);
    }

    public function testReturnsParameters() {
        $this->router->setRequestString('/users/alejo/posts');
        $this->router->setRequestMethod('GET');

        $this->router->add('/users/{:username}/posts', ['UserController', 'listPosts'], 'GET');

        $this->assertEquals('alejo', $this->router->dispatch()->parameters->username);
    }

}