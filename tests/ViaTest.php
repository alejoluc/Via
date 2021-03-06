<?php

use PHPUnit\Framework\TestCase;

class ViaTest extends TestCase
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
        $result = $match->getDestination();
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
        $this->assertEquals('HomePagePost', $this->router->dispatch()->getDestination());

        $this->router->setRequestMethod('GET');
        $this->assertEquals('HomePage', $this->router->dispatch()->getDestination());
    }

    public function testCorrectRouteIsDispatched()
    {
        $this->router->setRequestString('/users/list');
        $this->router->setRequestMethod('GET');

        $this->router->add('users/{user}/', 'UserMainPage', 'GET');
        $this->router->add('users/{user}/posts', 'UserPostsPage', 'GET');
        $this->router->add('users/list/', 'UsersListPage', 'GET');
        $this->assertEquals('UsersListPage', $this->router->dispatch()->getDestination());

        $this->router->setRequestString('users/alejo/');
        $this->assertEquals('UserMainPage', $this->router->dispatch()->getDestination());
    }

    public function testCustomConstraintsSatisfied()
    {
        $this->router->setRequestString('/users/alejo/posts/12');
        $this->router->setRequestMethod('GET');

        $withConstraints = $this->router->add('users/{user}/posts/{month}', 'UserPostsPageByMonth', 'GET');
        $withConstraints->where('month', '[0-9]{1,2}');

        $this->assertEquals('UserPostsPageByMonth', $this->router->dispatch()->getDestination());
    }

    public function testCustomConstraintsNotSatisfiedReturnFalseOnFound()
    {
        $this->router->setRequestString('/users/alejo/posts/123');
        $this->router->setRequestMethod('GET');

        $withConstraints = $this->router->add('users/{user}/posts/{month}', 'UserPostsPageByMonth', 'GET');
        $withConstraints->where('month', '[0-9]{1,2}');

        $result = $this->router->dispatch();
        $this->assertEquals(false, $result->isMatch());
    }

    public function testDispatcherReturnsArrayIfSpecified()
    {
        $this->router->setRequestString('/users/alejo');
        $this->router->setRequestMethod('GET');

        $this->router->add('users/{user}', ['UsersController', 'showUserPage']);
        
        $this->assertEquals(['UsersController', 'showUserPage'], $this->router->dispatch()->getDestination());
    }

    public function testSlug() {
        $this->router->setRequestString('/posts/20594-this-is-a-slug');
        $this->router->setRequestMethod('GET');

        $this->router->add('/posts/{number}-{slug}', 'ShowPost');
        $request = $this->router->dispatch()->getRequest();
        $parameters = $request->getParameters();
        
        $this->assertEquals(['number' => '20594', 'slug' => 'this-is-a-slug'], $parameters);
    }

    public function testSlug2() {
        $this->router->setRequestString('/posts/20594-this-is-a-slug');
        $this->router->setRequestMethod('GET');

        $this->router->add('/posts/{number}-{prefix}-{slug}', 'ShowPost');
        $request = $this->router->dispatch()->getRequest();
        $parameters = $request->getParameters();
        $this->assertEquals(['number' => '20594', 'prefix' => 'this', 'slug' => 'is-a-slug'], $parameters);
    }

    public function testFluentInterface()
    {
        $this->router->setRequestString('/users/alejo/posts/9650');
        $this->router->setRequestMethod('GET');

        $destination = ['UserController', 'UserPostView'];
        $this->router->add('users/{user}/posts/{post_id}', $destination)
                     ->where('user', '\w+')
                     ->where('post_id', '\d+');
        
        $this->assertEquals($destination, $this->router->dispatch()->getDestination());
    }

    public function testReturnsParameters() {
        $this->router->setRequestString('/users/alejo/posts');
        $this->router->setRequestMethod('GET');

        $this->router->add('/users/{username}/posts', ['UserController', 'listPosts'], 'GET');
        $this->assertEquals('alejo', $this->router->dispatch()->getRequest()->getParameter('username'));
    }

    public function testFilterRegisteredInRouter() {
        $this->router->setRequestString('/users/alejo/posts');
        $this->router->setRequestMethod('GET');

        $this->router->get('/users/{user}/posts', 'Generic route endpoint')->filter('testFilter');

        $this->router->registerFilter('testFilter', function(){
            return 'Filter fail message';
        });

        $match = $this->router->dispatch();

        $this->assertFalse($match->filtersPass());
        $this->assertEquals('Filter fail message', $match->getFilterErrors()[0]->getErrorMessage());
    }

    public function testFilterCreatedInRoute() {
        $this->router->setRequestString('/users/alejo/posts');
        $this->router->setRequestMethod('GET');

        $this->router->get('/users/{user}/posts', 'Generic route endpoint')->filter(function(){
            return 'Filter fail message';
        });

        $match = $this->router->dispatch();

        $this->assertFalse($match->filtersPass());
        $this->assertEquals('Filter fail message', $match->getFilterErrors()[0]->getErrorMessage());
    }

    /**
     * @expectedException alejoluc\Via\ViaException
     */
    public function testExceptionWhenAccessMatchResultIfFiltersFail() {
        $this->router->setRequestString('/users/alejo/posts');
        $this->router->setRequestMethod('GET');

        $this->router->get('/users/{user}/posts', 'Generic route endpoint')->filter(function(){
            return 'Filter fail message';
        });

        $match = $this->router->dispatch();
        $result = $match->getDestination();
    }

    /**
     * @expectedException  \InvalidArgumentException
     */
    public function testNamedRouteNotFoundThrowsException() {
        $this->router->getPath('no.such.route');
    }


    public function testNamedRouteBuildsCorrectLink() {
        $this->router->get('/users/list', 'List Users', 'users.list');
        $link = $this->router->getPath('users.list');
        $this->assertEquals('/users/list/', $link);
    }

    public function testNamedRouteBuildsCorrectLinkWithParameters() {
        $this->router->get('/users/{name}', 'List Users', 'users.list');
        $link = $this->router->getPath('users.list', ['name' => 'alejo']);
        $this->assertEquals('/users/alejo/', $link);
    }

    public function testNamedRouteBuildsCorrectLinkInsideGroup() {
        $this->router->group('some/', function(){
            $this->router->group('prefix/', function(){
                $this->router->get('endpoint/{action}', 'Endpoint', 'app.endpoint');
            });
        });
        $link = $this->router->getPath('app.endpoint', ['action' => 'check']);
        $this->assertEquals('/some/prefix/endpoint/check/', $link);
    }

    public function testParametersCanBeDefinedInGroups() {
        $this->router->setRequestString('/users/alejo/update/persist');
        $this->router->group('/users/{user}/', function() {
            $this->router->group('/{action}/', function(){
                $this->router->get('persist', function($request){
                    $params = $request->getParameters();
                    return "Persisting action $params[action] for user $params[user]";
                });
            });
        });
        $dispatch = $this->router->dispatch();
        $callback = $dispatch->getDestination();
        $request  = $dispatch->getRequest();
        $result   =  $callback($request);
        $this->assertEquals($result, 'Persisting action update for user alejo');
    }
}