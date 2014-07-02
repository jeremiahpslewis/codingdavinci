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

    /*
     * see http://stackoverflow.com/a/13522452
     *
     */
    public function toArray($em)
    {
        $className = get_class($this);

        $uow = $em->getUnitOfWork();
        $entityPersister = $uow->getEntityPersister($className);
        $classMetadata = $entityPersister->getClassMetadata();

        $result = array();
        foreach ($uow->getOriginalEntityData($this) as $field => $value) {
            if (isset($classMetadata->associationMappings[$field])) {
                $assoc = $classMetadata->associationMappings[$field];

                // Only owning side of x-1 associations can have a FK column.
                if ( ! $assoc['isOwningSide'] || ! ($assoc['type'] & \Doctrine\ORM\Mapping\ClassMetadata::TO_ONE)) {
                    continue;
                }

                if ($value !== null) {
                    $newValId = $uow->getEntityIdentifier($value);
                }

                $targetClass = $em->getClassMetadata($assoc['targetEntity']);
                $owningTable = $entityPersister->getOwningTable($field);

                foreach ($assoc['joinColumns'] as $joinColumn) {
                    $sourceColumn = $joinColumn['name'];
                    $targetColumn = $joinColumn['referencedColumnName'];

                    if ($value === null) {
                        $result[$sourceColumn] = null;
                    } else if ($targetClass->containsForeignIdentifier) {
                        $result[$sourceColumn] = $newValId[$targetClass->getFieldForColumn($targetColumn)];
                    } else {
                        $result[$sourceColumn] = $newValId[$targetClass->fieldNames[$targetColumn]];
                    }
                }
            } elseif (isset($classMetadata->columnNames[$field])) {
                $columnName = $classMetadata->columnNames[$field];
                $result[$columnName] = $value;
            }
        }

        return $result;
    }


}