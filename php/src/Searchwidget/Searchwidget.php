<?php

namespace Searchwidget;

class Searchwidget
{
    protected $request;
    protected $session = null;

    protected $values = array();
    protected $routeName;
    protected $sort_by = array();
    protected $sort_by_key = 'sort_by';
    protected $filters = array();

    /**
    */
    public function __construct($request, $session = null, $options = array())
    {
        $this->request = $request;
        $this->values = $request->request->all();
        $this->routeName = isset($options['routeName'])
            ? $options['routeName'] : $request->attributes->get('_route');

        $this->session = $session;
    }

    public function getRequest() {
        return $this->request;
    }

    public function addSortBy($name, $options = array()) {
        $this->sort_by[$name] = $options;
    }

    public function addFilter($name, $options = array()) {
        $this->filters[$name] = $options;
    }

    public function getCurrentSearch ($name = 'search') {
        return $this->getValueFromRequest($name);
    }

    public function getSortBy() {
        return $this->sort_by;
    }

    public function getCurrentSortBy($name = 'sort') {
        if (empty($this->sort_by)) {
            // no orderings set
            return false;
        }

        reset($this->sort_by);
        $default_sort_by = key($this->sort_by);
        $default_sort_by_dir = 'asc';

        $current_sort = $this->getValueFromRequest($name);
        if (!empty($current_sort)) {
            $current_sort = explode(':', $current_sort, 2);
            if (array_key_exists($current_sort[0], $this->sort_by)) {
                $sort_by = $current_sort[0];
                if (count($current_sort) > 1 && in_array($current_sort[1], array('asc', 'desc'))) {
                    $sort_by_dir = $current_sort[1];
                }
            }
        }

        if (empty($sort_by)) {
            $sort_by = $default_sort_by;
        }
        if (empty($sort_by_dir)) {
            $sort_by_dir = 'asc'; // TODO: check if overriden in options
        }
        return array($sort_by, $sort_by_dir);
    }

    public function getFilters() {
        return $this->filters;
    }

    public function getActiveFilters() {
        $active = array();
        foreach ($this->filters as $name => $filter) {
            $value = $this->getValueFromRequest($name);
            $active[$name] = $value;
        }
        return $active;
    }

    protected function getValueFromRequest($name) {
        $value = null;

        if ('POST' == $this->request->getMethod()) {
            if ($this->request->request->has($name)) {
                $value = $this->request->request->get($name);
            }
        }

        if (!isset($value)) {
            if ($this->request->query->has($name)) {
                $value = $this->request->query->get($name);
            }
        }

        if (isset($this->session)) {
            $key = (!empty($this->routeName) ? $this->routeName . '_' : '') . $name;
            if (!isset($value)) {
                if ($this->session->has($key)) {
                    $value = $this->session->get($key);
                }
            }
            else {
                $this->session->set($key, $value);
            }
        }

        return $value;
    }

}