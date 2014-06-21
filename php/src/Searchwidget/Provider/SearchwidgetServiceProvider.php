<?php
namespace Searchwidget\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;

use Searchwidget\View\DefaultView;
use Searchwidget\View\ViewFactory;

/*
use Pagerfanta\View\DefaultView;
use Pagerfanta\View\TwitterBootstrapView;
use Pagerfanta\View\TwitterBootstrap3View;
use Pagerfanta\View\ViewFactory;
use FranMoreno\Silex\Service\PagerfantaFactory;
use FranMoreno\Silex\Twig\PagerfantaExtension;
*/

class SearchwidgetServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['searchwidget.searchwidget_factory'] = $app->share(function ($app) {
            return new SearchwidgetFactory();
        });

        $app['searchwidget.view.default_options'] = array(
            'routeName'        => null,
            'routeParams'      => array(),
            'pageParameter'    => '[page]',
            'proximity'        => 3,
            'next_message'     => '&raquo;',
            'previous_message' => '&laquo;',
            'default_view'     => 'default'
        );

        $app['searchwidget.view_factory'] = $app->share(function ($app) {
            $defaultView = new DefaultView();
            // $twitterBoostrapView = new TwitterBootstrapView();
            // $twitterBoostrap3View = new TwitterBootstrap3View();

            $factoryView = new ViewFactory();
            $factoryView->add(array(
                $defaultView->getName() => $defaultView,
                // $twitterBoostrapView->getName() => $twitterBoostrapView,
                // $twitterBoostrap3View->getName() => $twitterBoostrap3View,
            ));

            return $factoryView;
        });


        if (isset($app['twig'])) {
            $app->extend('twig', function($twig, $app) {
                $twig->addExtension(new \Searchwidget\Twig\SearchwidgetExtension($app));

                return $twig;
            });
        }
    }

    public function boot(Application $app)
    {
        $options = isset($app['searchwidget.view.options']) ? $app['searchwidget.view.options'] : array();
        $app['searchwidget.view.options'] = array_replace($app['searchwidget.view.default_options'], $options);
    }
}
