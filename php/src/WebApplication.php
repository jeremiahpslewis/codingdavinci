<?php


class WebApplication extends BaseApplication
{
    protected $application;

    public function __construct() {
        $container = $this->setupContainer();

        $this->application = $app = new \Silex\Application();
        $app['debug'] = true;

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
            $app['twig']->addGlobal('current_route', $request->get("_route"));
        });

        // pagerfanta
        $app->register(new FranMoreno\Silex\Provider\PagerfantaServiceProvider());

        // searchwidget
        $app->register(new Searchwidget\Provider\SearchwidgetServiceProvider());

        // session
        $app->register(new Silex\Provider\SessionServiceProvider());

        $app['doctrine'] = $container->get('doctrine');

        //load routes into $app
        $routeLoader = new WebApplication\RouteLoader($app);
        $routeLoader->bindRoutesToControllers();

        $app->get('/list', array($this, 'listIndexAction'));
        $app->get('/', array($this, 'listIndexAction'));
    }

    public function listIndexAction () {
        $output = 'TODO: build the list';

        return $output;
    }

    public function run() {
        $this->application->run();
    }
}
