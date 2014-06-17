<?php

namespace App;

use Silex\Application as BaseApplication;

class Application extends BaseApplication
{

    function setup($env) {
        // read config
        define('ROOT_PATH', __DIR__ . '/../..');

        $this->register(new \Igorw\Silex\ConfigServiceProvider(ROOT_PATH . "/resources/config/$env.yaml"));

        $this->register(new \Silex\Provider\DoctrineServiceProvider(), array(
            'db.options' => $this['database'],
        ));

        $this->register(new \Dflydev\Silex\Provider\DoctrineOrm\DoctrineOrmServiceProvider, array(
           // "orm.proxies_dir" => "/path/to/proxies",
            "orm.em.options" => array(
                "mappings" => array(
                    // Using actual filesystem paths
                    array(
                        "type" => "annotation",
                        "namespace" => "App\Entities",
                        "path" => ROOT_PATH ."/src/App/Entities",
                    ),
                ),
            ),
        ));

        //load services
        $servicesLoader = new ServicesLoader($this);
        $servicesLoader->bindServicesIntoContainer();
    }

}
