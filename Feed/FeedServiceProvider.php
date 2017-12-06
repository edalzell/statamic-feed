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
        // Here we get all the current routes, then add our feed routes right before the catch-all segment route
        $old_routes = app('router')->getRoutes();
        $new_routes = new RouteCollection();
        foreach ($old_routes as $i => $route) {
            if ($route->getUri() == '{segments?}') {
                $json_feed_route = new Route(['GET'],
                                             Str::removeLeft($this->getConfig('json_url','feed.json'), '/'),
                                             ['uses' => '\Statamic\Addons\Feed\FeedController@json']);
                $new_routes->add($json_feed_route);
                $atom_feed_route = new Route(['GET'],
                                             Str::removeLeft($this->getConfig('atom_url','feed'), '/'),
                                             ['uses' => '\Statamic\Addons\Feed\FeedController@atom']);
                $new_routes->add($atom_feed_route);
            }
            $new_routes->add($route);
        }

        app('router')->setRoutes($new_routes);
    }
}
