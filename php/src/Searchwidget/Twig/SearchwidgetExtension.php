<?php
namespace Searchwidget\Twig;

use Silex\Application;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Searchwidget\SearchwidgetInterface;

class SearchwidgetExtension extends \Twig_Extension
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function getFunctions()
    {
        return array(
            'searchwidget' => new \Twig_Function_Method($this, 'renderSearchwidget', array('is_safe' => array('html'))),
        );
    }

    /**
     * Renders a searchwidget.
     *
     * @param SearchwidgetInterface $searchwidget The searchwidget.
     * @param string              $viewName   The view name.
     * @param array               $options    An array of options (optional).
     *
     * @return string The searchwidget rendered.
     */
    public function renderSearchwidget(\Searchwidget\Searchwidget $searchwidget, $viewName = null, array $options = array())
    {
        $options = array_replace($this->app['searchwidget.view.options'], $options);

        if (null === $viewName) {
            $viewName = $options['default_view'];
        }

        $router = $this->app['url_generator'];

        //Custom router and router params
        if (isset($this->app['searchwidget.view.router.name'])) {
            $options['routeName'] = $this->app['searchwidget.view.router.name'];
        }

        if (isset($this->app['searchwidget.view.router.params'])) {
            $options['routeParams'] = $this->app['searchwidget.view.router.params'];
        }

        if (null === $options['routeName']) {
            $request = $this->app['request'];

            $options['routeName'] = $request->attributes->get('_route');
            $options['routeParams'] = array_merge($request->query->all(), $request->attributes->get('_route_params'));
        }

        $routeName = $options['routeName'];
        $routeParams = $options['routeParams'];
        // $pageParameter = $options['pageParameter'];
        $propertyAccessor = PropertyAccess::getPropertyAccessor();

        $routeGenerator = function($routeParamsOverride) use($router, $routeName, $routeParams, $propertyAccessor) {
            // var_dump($routeParamsOverride);
            if (empty($routeParamsOverride)) {
                $routeParamsOverride = $routeParams;
            }
            unset($routeParamsOverride['page']); // form / sort start at 0
            return $router->generate($routeName, $routeParamsOverride);
        };

        return $this->app['searchwidget.view_factory']->get($viewName)->render($searchwidget, $routeGenerator, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'searchwidget';
    }
}
