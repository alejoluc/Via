<?php

class ViaTest extends PHPUnit_Framework_TestCase
{

    /** @var $router alejoluc\Via\Router */
    private $router;

    public function setUp()
    {
        $this->router = new alejoluc\Via\Router();
    }

    public function testNoRouteReturnsFalseOnFoundField()
    {
        $this->router->setRequestString('home');
        $result = $this->router->dispatch();
        $this->assertEquals(false, $result->isMatch());
    }

    /**
     * @expectedException alejoluc\Via\NoRequestStringSpecifiedException
     */
    public function testNoRequestStringThrowsException()
    {
        $this->router->add('home', 'HomePage');
        $this->router->setRequestMethod('GET');
        $this->router->dispatch();
    }

    public function testRouteExistsButNotForThatRequestMethodReturnsFalseOnFound()
    {
        $this->router->setRequestString('home');
        $this->router->setRequestMethod('GET');

        $this->router->add('home', 'HomePage', 'POST');
        $result = $this->router->dispatch();
        $this->assertEquals(false, $result->isMatch());
    }

    /**
     * @expectedException alejoluc\Via\ViaException
     */
    public function testExceptionWhenAccessMatchResultIfNoMatchFound() {
        $this->router->setRequestString('/users/alejo/posts');
        $this->router->setRequestMethod('GET');

        $this->router->get('/unused/route', 'Generic endpoint');

        $match = $this->router->dispatch();
        $result = $match->getResult();
    }

    public function testOnRouteFoundReturnsMatchObject() {
        $this->router->setRequestString('home');
        $this->router->setRequestMethod('GET');

        $this->router->add('home', 'HomePage', 'GET');
        $res = $this->router->dispatch();
        $this->assertTrue($res instanceof alejoluc\Via\Match);
    }

    public function testDispatchesToCorrectRouteByMethod()
    {
        $this->router->setRequestString('home');
        $this->router->setRequestMethod('POST');

        $this->router->add('home', 'HomePage', 'GET');
        $this->router->add('home', 'HomePagePost', 'POST');
        $this->assertEquals('HomePagePost', $this->router->dispatch()->getResult());

        $this->router->setRequestMethod('GET');
        $this->assertEquals('HomePage', $this->router->dispatch()->getResult());
    }

    public function testCorrectRouteIsDispatched()
    {
        $this->router->setRequestString('/users/list');
        $this->router->setRequestMethod('GET');

        $this->router->add('users/{:user}/', 'UserMainPage', 'GET');
        $this->router->add('users/{:user}/posts', 'UserPostsPage', 'GET');
        $this->router->add('users/list/', 'UsersListPage', 'GET');
        $this->assertEquals('UsersListPage', $this->router->dispatch()->getResult());

        $this->router->setRequestString('users/alejo/');
        $this->assertEquals('UserMainPage', $this->router->dispatch()->getResult());
    }

    public function testCustomConstraintsSatisfied()
    {
        $this->router->setRequestString('/users/alejo/posts/12');
        $this->router->setRequestMethod('GET');

        $withConstraints = $this->router->add('users/{:user}/posts/{:month}', 'UserPostsPageByMonth', 'GET');
        $withConstraints->where('month', '[0-9]{1,2}');

        $this->assertEquals('UserPostsPageByMonth', $this->router->dispatch()->getResult());
    }

    public function testCustomConstraintsNotSatisfiedReturnFalseOnFound()
    {
        $this->router->setRequestString('/users/alejo/posts/123');
        $this->router->setRequestMethod('GET');

        $withConstraints = $this->router->add('users/{:user}/posts/{:month}', 'UserPostsPageByMonth', 'GET');
        $withConstraints->where('month', '[0-9]{1,2}');

        $result = $this->router->dispatch();
        $this->assertEquals(false, $result->isMatch());
    }

    public function testDispatcherReturnsArrayIfSpecified()
    {
        $this->router->setRequestString('/users/alejo');
        $this->router->setRequestMethod('GET');

        $this->router->add('users/{:user}', ['UsersController', 'showUserPage']);
        
        $this->assertEquals(['UsersController', 'showUserPage'], $this->router->dispatch()->getResult());
    }

    public function testFluentInterface()
    {
        $this->router->setRequestString('/users/alejo/posts/9650');
        $this->router->setRequestMethod('GET');

        $destination = ['UserController', 'UserPostView'];
        $this->router->add('users/{:user}/posts/{:post_id}', $destination)
                     ->where('user', '\w+')
                     ->where('post_id', '\d+');
        
        $this->assertEquals($destination, $this->router->dispatch()->getResult());
    }

    public function testReturnsParameters() {
        $this->router->setRequestString('/users/alejo/posts');
        $this->router->setRequestMethod('GET');

        $this->router->add('/users/{:username}/posts', ['UserController', 'listPosts'], 'GET');
        $this->assertEquals('alejo', $this->router->dispatch()->getParameters()[0]);
    }

    public function testFilterRegisteredInRouter() {
        $this->router->setRequestString('/users/alejo/posts');
        $this->router->setRequestMethod('GET');

        $this->router->get('/users/{:user}/posts', 'Generic route endpoint')->filter('testFilter');

        $this->router->registerFilter('testFilter', function(){
            return 'Filter fail message';
        });

        $match = $this->router->dispatch();

        $this->assertFalse($match->filtersPass());
        $this->assertEquals('Filter fail message', $match->getFilterErrors()[0]);
    }

    public function testFilterCreatedInRoute() {
        $this->router->setRequestString('/users/alejo/posts');
        $this->router->setRequestMethod('GET');

        $this->router->get('/users/{:user}/posts', 'Generic route endpoint')->filter(function(){
            return 'Filter fail message';
        });

        $match = $this->router->dispatch();

        $this->assertFalse($match->filtersPass());
        $this->assertEquals('Filter fail message', $match->getFilterErrors()[0]);
    }

    /**
     * @expectedException alejoluc\Via\ViaException
     */
    public function testExceptionWhenAccessMatchResultIfFiltersFail() {
        $this->router->setRequestString('/users/alejo/posts');
        $this->router->setRequestMethod('GET');

        $this->router->get('/users/{:user}/posts', 'Generic route endpoint')->filter(function(){
            return 'Filter fail message';
        });

        $match = $this->router->dispatch();
        $result = $match->getResult();
    }

}