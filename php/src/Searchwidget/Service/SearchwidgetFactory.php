<?php

class SearchwidgetFactory
{
    public function get()
    {
        return $this->createSearchwidget();
    }


    protected function createSearchwidget()
    {
        return new Searchwidget();
    }
}