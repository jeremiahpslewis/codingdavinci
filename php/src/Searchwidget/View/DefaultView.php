<?php

/*
 * This file is part of the Searchwidget package.
 *
 * (c) Daniel Burckhardt
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Searchwidget\View;

use Searchwidget\View\Template\DefaultTemplate;

/*
use Pagerfanta\SearchwidgetInterface;
use Pagerfanta\View\Template\TemplateInterface;
*/

/**
 * @author Pablo Díez <pablodip@gmail.com>
 */
class DefaultView /* implements ViewInterface */
{
    private $template;

    private $pagerfanta;
    private $proximity;

    public function __construct(TemplateInterface $template = null)
    {
        $this->template = $template ?: $this->createDefaultTemplate();
    }

    protected function createDefaultTemplate()
    {
        return new DefaultTemplate();
    }

    /**
     * {@inheritdoc}
     */
    public function render(\Searchwidget\Searchwidget $searchwidget, $routeGenerator, array $options = array())
    {
        $this->initializeSearchwidget($searchwidget);
        $this->initializeOptions($options);

        $this->configureTemplate($routeGenerator, $options);

        return $this->generate();
    }

    private function initializeSearchwidget(\Searchwidget\Searchwidget $searchwidget)
    {
        $this->searchwidget = $searchwidget;
    }

    private function initializeOptions($options)
    {
        /*
        $this->proximity = isset($options['proximity']) ?
                           (int) $options['proximity'] :
                           $this->getDefaultProximity();
        */
    }

    private function configureTemplate($routeGenerator, $options)
    {
        $this->template->setRouteGenerator($routeGenerator);
        $this->template->setOptions($options);
    }

    private function generate()
    {
        $form = $this->generateForm();

        return $this->generateContainer($form);
    }

    private function generateContainer($form)
    {
        return str_replace('%form%', $form, $this->template->container());
    }

    private function generateForm()
    {
        $form_content = $this->generateSearch('search')
                      . $this->generateFilters()
                      . $this->generateSortBy()
                      ;

        return str_replace('%form_content%', $form_content, $this->template->form());
    }

    private function generateSearch($name = 'search')
    {
        return $this->template->search($name, array('value' => $this->searchwidget->getCurrentSearch($name)));
    }

    private function generateSortBy()
    {

        $current_sort = $this->searchwidget->getCurrentSortBy();
        $sort_by_elements = array();
        foreach ($this->searchwidget->getSortBy() as $name => $options) {
            $sort_by_elements[] = $this->template->sortBy($name, $options, $current_sort, $this->searchwidget->getRequest());
        }
        if (empty($sort_by_elements)) {
            return '';
        }
        return $this->template->sortByList($sort_by_elements);
    }

    private function generateFilters()
    {
        $active = $this->searchwidget->getActiveFilters();
        $filter_elements = array();
        foreach ($this->searchwidget->getFilters() as $name => $options) {
            $filter_elements[] = $this->template->filter($name, $options,
                                                         array_key_exists($name, $active) ? $active[$name] : null,
                                                         $this->searchwidget->getRequest());
        }
        if (empty($filter_elements)) {
            return '';
        }
        return $this->template->filterList($filter_elements);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'default';
    }
}
