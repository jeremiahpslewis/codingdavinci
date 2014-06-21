<?php

/*
 * This file is part of the Searchwidget package.
 *
 * (c) Daniel Burckhardt <daniel.burckhardt@sur-gmbh.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Searchwidget\View\Template;

/**
 * @author Daniel Burckhardt <daniel.burckhardt@sur-gmbh.ch>
 */
class DefaultTemplate extends Template
{
    static protected $defaultOptions = array(
        'css_disabled_class' => 'disabled',
        'css_active_class'  => 'active',
        'input_text_template' => '<input id="%id%" name="%name%" value="%value%" placeholder="%placeholder%" /><input type="submit" value="Los" />',
        'container_template' => '<div>%form%</div>',
        'form_template'      => '<form action="%action%" method="POST">%form_content%</form>',
        'sort_by_template'   => '<a class="btn sort %direction%" data-sort="%name%" href="%href%">Sortiere nach %label%</a>',
        'filter_by_template'   => '<a class="btn %active%" data-filter="%name%" href="%href%">%label%</a>',
        // 'span_template'      => '<span class="%class%">%text%</span>' */
    );

    public function container()
    {
        return $this->option('container_template');
    }

    public function form()
    {
        $search = array('%action%', '%method%');
        $replace = array($this->generateRoute(), 'POST');

        return str_replace($search, $replace, $this->option('form_template'));
    }

    public function search($name = 'search', $options = array())
    {
        // default
        $search_replace = array('%id%' => $name, '%name%' => $name,
                                '%placeholder%' => '',
                                '%value%' => '',
                                );

        foreach ($options as $key => $value) {
            $search_replace['%' . $key . '%'] = $this->escape($value);
        }

        return str_replace(array_keys($search_replace),
                           array_values($search_replace),
                           $this->option('input_text_template'));
    }

    protected function escape ($str) {
        return htmlspecialchars($str, ENT_COMPAT, 'utf-8');
    }

    protected function renderList($elements) {
        return implode(' ', $elements);
    }

    public function sortBy ($name, $options, $current_sort, $request) {
        $label = array_key_exists('label', $options)
            ? $options['label'] : ucfirst($name);

        $routeParams = array_merge($request->query->all(),
                                   $request->attributes->get('_route_params'));
        $routeParams['page'] = 1;

        $direction = 'asc';
        if ($current_sort[0] == $name && $current_sort[1] == 'asc') {
            $direction = 'desc';
        }

        $routeParams['sort'] = $name . ':' . $direction;
        // var_dump($routeParams);

        $search_replace = array(
            '%label%' => $this->escape($label),
            '%href%' => $this->escape($this->generateRoute($routeParams)),
            '%direction%' => $current_sort[0] == $name ? $current_sort[1] : '',
        );

        return str_replace(array_keys($search_replace),
                           array_values($search_replace),
                           $this->option('sort_by_template'));
    }

    public function sortByList ($sort_by_elements) {
        return '<div>' . $this->renderList($sort_by_elements) . '</div>';
    }

    public function filter ($name, $options, $active, $request) {
        $routeParams = array_merge($request->query->all(),
                                   $request->attributes->get('_route_params'));
        $routeParams['page'] = 1;

        $filter_options = array();
        foreach ($options as $option_name => $option_descr) {
            $routeParams[$name] = $option_name;

            if (is_array($option_descr) && array_key_exists('label', $option_descr)) {
                $label = $option_descr['label'];
            }
            else if (is_string($option_descr)) {
                $label = $option_descr;
            }

            $search_replace = array(
                '%label%' => $this->escape($label),
                '%href%' => $this->escape($this->generateRoute($routeParams)),
                '%active%' => isset($active) && $active == $option_name ? 'active' : '',
            );

            $filter_options[] = str_replace(array_keys($search_replace),
                                            array_values($search_replace),
                                            $this->option('filter_by_template'));

        }
        return join(' ', $filter_options);
    }

    public function filterList ($filter_elements) {
        return '<div>' . $this->renderList($filter_elements) . '</div>';
    }
}
