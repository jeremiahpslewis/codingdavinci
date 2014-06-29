<?php
namespace Entities;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo; // this will be like an alias for Gedmo extensions annotations

/**
* The Country
*
* @ORM\Table(name="Country", options={"engine":"MyISAM"})
* @ORM\Entity
*/
class Country extends Base
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
    * @ORM\Column(name="name_de", type="string", nullable=true)
    */
    protected $germanName;

    /**
    * @ORM\Column(name="iso2", type="string", nullable=true)
    */
    protected $iso2;

    /**
    * @ORM\Column(name="iso3", type="string", nullable=true)
    */
    protected $iso3;

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
