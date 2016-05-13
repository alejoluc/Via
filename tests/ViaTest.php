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
        $this->assertEquals('HomePagePost', $this->router->dispatch());

        $this->router->setRequestMethod('GET');
        $this->assertEquals('HomePage', $this->router->dispatch());
    }

    public function testCorrectRouteIsDispatched()
    {
        $this->router->setRequestString('/users/list');
        $this->router->setRequestMethod('GET');

        $this->router->add('users/{:user}/', 'UserMainPage', 'GET');
        $this->router->add('users/{:user}/posts', 'UserPostsPage', 'GET');
        $this->router->add('users/list/', 'UsersListPage', 'GET');
        $this->assertEquals('UsersListPage', $this->router->dispatch());
    }

}