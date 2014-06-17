<?php
namespace Entities;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo; // this will be like an alias for Gedmo extensions annotations

/**
* The Place on the Person
*
* @ORM\Table(name="Place", options={"engine":"MyISAM"})
* @ORM\Entity
*/
class Place extends Base
{
    /**
    * @ORM\Column(type="integer", nullable=false)
    * @ORM\Id
    * @ORM\GeneratedValue(strategy="IDENTITY")
    */
    protected $id;

    /**
     * @var integer
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    protected $status = 0;

    /**
    * @ORM\Column(type="string", nullable=true)
    */
    protected $name;


    /**
    * @ORM\Column(type="float", nullable=true)
    */
    protected $latitude;

    /**
    * @ORM\Column(type="float", nullable=true)
    */
    protected $longitude;

    /**
    * @ORM\Column(type="string", nullable=true)
    */
    protected $gnd;

    /**
    * @ORM\Column(type="string", nullable=true)
    */
    protected $geonames;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="created_at", type="datetime")
     */
    protected $createdAt;

    /**
     * @Gedmo\Timestampable(on="change", field={"title", "place", "gnd"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="changed_at", type="datetime")
     */
    protected $changedAt;

}
