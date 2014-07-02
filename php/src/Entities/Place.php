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
    * @ORM\Column(name="country_code", type="string", nullable=true)
    */
    protected $countryCode;

    /**
     * @var Country|null
     *
     * @ORM\ManyToOne(targetEntity="Country", fetch="EAGER")
     * @ORM\JoinColumn(name="country_code", referencedColumnName="iso2", nullable=true)
     */
    protected $country;

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
    * @ORM\Column(name="geonames_parent_adm1", type="string", nullable=true)
    */
    protected $geonamesParentAdm1;

    /**
    * @ORM\Column(name="geonames_parent_adm2", type="string", nullable=true)
    */
    protected $geonamesParentAdm2;

    /**
    * @ORM\Column(name="geonames_parent_adm3", type="string", nullable=true)
    */
    protected $geonamesParentAdm3;

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
