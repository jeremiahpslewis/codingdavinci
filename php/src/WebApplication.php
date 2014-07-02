<?php


class WebApplication extends BaseApplication
{
    protected $application;

    public function __construct() {
        $this->container = $container = $this->setupContainer();

        $this->application = $app = new \Silex\Application();
        $app['debug'] = true;

        // form
        $app->register(new \Silex\Provider\FormServiceProvider());

        // validation
        $app->register(new \Silex\Provider\ValidatorServiceProvider());
        $app->register(new \Silex\Provider\TranslationServiceProvider(), array(
            'locale' => 'en_US',
            // 'translation.class_path' => __DIR__ . '/../vendor/symfony/src',
            'translator.messages' => array()
        ));

        // twig
        $app->register(new \Silex\Provider\TwigServiceProvider(), array(
            'twig.path' => $container->getParameter('base_path') . '/resources/view',
        ));
        $app->register(new \Silex\Provider\UrlGeneratorServiceProvider());

        // twig custom filter
        $filter = new Twig_SimpleFilter('dateincomplete',
                                        function ($datestr) {
                                            $date_parts = preg_split('/\-/', $datestr);
                                            $date_parts_formatted = array();
                                            for ($i = 0; $i < count($date_parts); $i++) {
                                                if (0 == $date_parts[$i]) {
                                                    break;
                                                }
                                                $date_parts_formatted[] = $date_parts[$i];
                                            }
                                            if (empty($date_parts_formatted)) {
                                                return '';
                                            }
                                            return implode('.', array_reverse($date_parts_formatted));
                                        });
        $app['twig']->addFilter($filter);

        $filter = new Twig_SimpleFilter('removeat',
                                        function ($str) {
                                            return preg_replace('/(\s+)@/', '\1', $str);
                                        });
        $app['twig']->addFilter($filter);

        $app->before(function ($request) use ($app) {
            $app['twig']->addGlobal('current_route', $request->get('_route'));
        });

        // pagerfanta
        $app->register(new FranMoreno\Silex\Provider\PagerfantaServiceProvider());

        // searchwidget
        $app->register(new Searchwidget\Provider\SearchwidgetServiceProvider());

        // session
        $app->register(new Silex\Provider\SessionServiceProvider());

        // secure edit forms
        $users = $container->hasParameter('users')
            ? $container->getParameter('users') : array();

        // must come after twig
        $app['security.firewalls'] = array(
            'admin' => array(
                'pattern' => '/edit',
                'http' => true,
                'users' => $users,
            ),
        );

        $app->register(new Silex\Provider\SecurityServiceProvider(), array(
            'security.firewalls' => $app['security.firewalls']
        ));

        // doctrine
        $app['doctrine'] = $container->get('doctrine');

        $app['base_path'] = $this->getBasePath();

        //load routes into $app
        $routeLoader = new WebApplication\RouteLoader($app);
        $routeLoader->bindRoutesToControllers();
    }

    public function run() {
        $this->application->run();
    }
}
