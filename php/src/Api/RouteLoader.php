<?php

namespace Api;

use Silex\Application as BaseApplication;

class RouteLoader
{
    public function __construct(BaseApplication $app)
    {
        $this->app = $app;
        $this->instantiateControllers();
    }

    private function instantiateControllers()
    {
        /* $this->app['list.controller'] = $this->app->share(function () {
            return new Controller\ListController();
        }); */

        $this->app['person.controller'] = $this->app->share(function () {
            return new Controller\PersonController();
        });

        $this->app['publication.controller'] = $this->app->share(function () {
            return new Controller\PublicationController();
        });

        $this->app['place.controller'] = $this->app->share(function () {
            return new Controller\PlaceController();
        });
    }

    public function bindRoutesToControllers()
    {


        // http://silex.sensiolabs.org/doc/organizing_controllers.html
        $api = $this->app['controllers_factory'];

        $app = $this->app;
        $api->get('/', function() use ($app) {
            // do whatever you want
            return 'version 0.0';
        });

        $api->get('/persons', array($this->app['person.controller'], 'getAll'));
        // $api->get('/persons/{id}', array($this->app['person.controller'], 'getOne'));

        $api->get('/publications', array($this->app['publication.controller'], 'getAll'));

        $api->get('/places', array($this->app['place.controller'], 'getAll'));

        /*
        $api->post('/notes', "notes.controller:save");
        $api->post('/notes/{id}', "notes.controller:update");
        $api->delete('/notes/{id}', "notes.controller:delete");
        */
        // $this->app['api']['endpoint'] . '/' . $this->app['api']['version']
        $this->app->mount('/v1/', $api);
    }
}
