<?php

namespace Statamic\Addons\Feed;

use Statamic\API\Str;
use Illuminate\Routing\Route;
use Statamic\Extend\ServiceProvider;
use Illuminate\Routing\RouteCollection;

class FeedServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    public $defer = true;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $router = app('router');
        $routes = new RouteCollection();

        // Here we get all the current routes, then add our feed routes right before the catch-all segment route
        foreach ($router->getRoutes() as $i => $route) {
            if ($route->getUri() == '{segments?}') {
                collect($this->getConfig('feeds', []))->each(function ($r, $key) use ($routes) {
                    $feed_route = new Route(['GET'],
                                            $r['route'],
                                            ['uses' => '\Statamic\Addons\Feed\FeedController@' . $r['type']]);
                    //feed_route->middleware('staticcache');
                    $routes->add($feed_route);
                });
            }
            $routes->add($route);
        }

        $router->setRoutes($routes);
    }
}
