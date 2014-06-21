<?php
namespace Entities;

abstract class Base
{

    public function __construct($properties = array())
    {
        if (isset($properties) && is_array($properties)) {
            foreach ($properties as $key => $val) {
                $this->__set($key, $val);
            }
        }
    }

    /* add generic setters and getters */
    /* once we are on PHP 5.4, implement as a Trait
     * now a bit of a hack to get access to private properties in inherited class
    */

    public function __get($name)
    {
        if (property_exists($this, $name)){
            // return $this->$name; // this doesn't work if private

            $reflected = new \ReflectionClass(get_class($this));
            $property = $reflected->getProperty($name);
            $property->setAccessible(true);
            return $property->getValue($this);

        }
    }

    public function __set($name, $value)
    {
        // check if there is a setter
        $set_method_name = 'set' . ucfirst($name);
        if (method_exists($this, $set_method_name)) {
            return $this->$set_method_name($value);
        }

        if (property_exists($this, $name)){
            // return $this->$name = $value;  // this doesn't work if private
            $reflected = new \ReflectionClass(get_class($this));
            $property = $reflected->getProperty($name);
            $property->setAccessible(true);
            return $property->setValue($this, $value);
        }
    }

}