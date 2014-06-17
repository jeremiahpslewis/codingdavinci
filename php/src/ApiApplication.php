<?php

use Silex\Application;
use Silex\Provider\HttpCacheServiceProvider;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Api\ServicesLoader;
use Api\RoutesLoader;

class ApiApplication extends BaseApplication
{
    protected $application;

    public function __construct() {
        $container = $this->setupContainer();

        $this->application = $app = new \Silex\Application();
        $app['debug'] = true;

        //handling CORS preflight request
        $app->before(function (Request $request) {
           if ($request->getMethod() === "OPTIONS") {
               $response = new Response();
               $response->headers->set("Access-Control-Allow-Origin","*");
               $response->headers->set("Access-Control-Allow-Methods","GET,POST,PUT,DELETE,OPTIONS");
               $response->headers->set("Access-Control-Allow-Headers","Content-Type");
               $response->setStatusCode(200);
               $response->send();
           }
        }, Application::EARLY_EVENT);

        //handling CORS respons with right headers
        $app->after(function (Request $request, Response $response) {
           $response->headers->set("Access-Control-Allow-Origin","*");
           $response->headers->set("Access-Control-Allow-Methods","GET,POST,PUT,DELETE,OPTIONS");
        });

        //accepting JSON
        $app->before(function (Request $request) {
            if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
                $data = json_decode($request->getContent(), true);
                $request->request->replace(is_array($data) ? $data : array());
            }
        });

        /*
        $app->register(new HttpCacheServiceProvider(), array("http_cache.cache_dir" => ROOT_PATH . "/storage/cache",));

        $app->register(new MonologServiceProvider(), array(
            "monolog.logfile" => ROOT_PATH . "/storage/logs/" . Carbon::now('Europe/London')->format("Y-m-d") . ".log",
            "monolog.level" => $app["log.level"],
            "monolog.name" => "application"
        ));
        */

        //load routes
        $routeLoader = new Api\RouteLoader($app);
        $routeLoader->bindRoutesToControllers();

        $app->error(function (\Exception $e, $code) use ($app) {
            /* $app['monolog']->addError($e->getMessage());
            $app['monolog']->addError($e->getTraceAsString()); */
            return new JsonResponse(array("statusCode" => $code, "message" => $e->getMessage(), "stacktrace" => $e->getTraceAsString()));
        });


        $app['doctrine'] = $container->get('doctrine');

        //load routes into $app
        $routeLoader = new WebApplication\RouteLoader($app);
        $routeLoader->bindRoutesToControllers();

        $app->get('/list', array($this, 'listIndexAction'));
        $app->get('/', array($this, 'listIndexAction'));
    }


    public function run() {
        $this->application->run();
    }
}
