<?php

namespace Api;


class ServicesLoader
{
    protected $app;

    public function __construct(\Silex\Application $app)
    {
        $this->app = $app;
    }

    public function bindServicesIntoContainer()
    {
        $this->app['person.service'] = $this->app->share(function () {
            return new Services\PersonService($this->app['db'], $this->app['orm.em']);
        });
    }
}
