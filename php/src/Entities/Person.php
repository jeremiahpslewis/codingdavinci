<?php
namespace Entities;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo; // for Gedmo extensions annotations

/**
* The Person on the NS-Verbotsliste
*
* @ORM\Table(name="Person", options={"engine":"MyISAM"})
* @ORM\Entity
*/
class Person extends Base
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
    protected $surname;

    /**
    * @ORM\Column(type="string", nullable=true)
    */
    protected $forename;

    /**
    * @ORM\Column(name="preferred_name", type="string", nullable=true)
    */
    protected $preferredName;

    /**
    * @ORM\Column(name="variant_names", type="string", nullable=true)
    */
    protected $variantNames;

    /**
    * @ORM\Column(type="string", nullable=true)
    */
    protected $gender;

    /**
    * @ORM\Column(name="academic_degree", type="string", nullable=true)
    */
    protected $academicDegree;


    /**
    * @ORM\Column(name="biographical_or_historical_information", type="string", nullable=true)
    */
    protected $biographicalOrHistoricalInformation;

    /**
    * @ORM\Column(name="date_of_birth", type="string", nullable=true)
    */
    protected $dateOfBirth;

    /**
    * @ORM\Column(name="date_of_death", type="string", nullable=true)
    */
    protected $dateOfDeath;

    /**
    * @ORM\Column(name="place_of_birth", type="string", nullable=true)
    */
    protected $placeOfBirth;

    /**
    * @ORM\Column(name="gnd_place_of_birth", type="string", nullable=true)
    */
    protected $gndPlaceOfBirth;

    /**
    * @ORM\Column(name="place_of_death", type="string", nullable=true)
    */
    protected $placeOfDeath;

    /**
    * @ORM\Column(name="gnd_place_of_death", type="string", nullable=true)
    */
    protected $gndPlaceOfDeath;

    /**
     * @ORM\Column(name="list_row", type="integer", nullable=true)
     */
    protected $listRow;

    /**
    * @ORM\Column(name="complete_works", type="integer")
    */
    protected $completeWorks;

    /**
    * @ORM\Column(type="string", nullable=true)
    */
    protected $gnd;

    /**
    * @ORM\Column(type="string", nullable=true)
    */
    protected $viaf;

    /**
    * @ORM\Column(name="lc_naf", type="string", nullable=true)
    */
    protected $lcNaf;

    /**
    * @ORM\Column(type="json_array", nullable=true)
    */
    protected $entityfacts;

    /**
     * @ORM\OneToMany(targetEntity="PublicationPerson", mappedBy="person")
     * @ORM\OrderBy({"publicationOrd" = "ASC"})
     */
    private $publicationRefs;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="created_at", type="datetime")
     */
    protected $createdAt;

    /**
     * @Gedmo\Timestampable(on="change", field={"surname", "forename", "gnd"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="changed_at", type="datetime")
     */
    protected $changedAt;


    public function getFullname($forename_first = false) {
        $parts = array();
        foreach (array('surname', 'forename') as $key) {
            if (!empty($this->$key)) {
                $parts[] = $this->$key;
            }
        }
        if (empty($parts)) {
            return '';
        }
        return $forename_first
            ? implode(' ', array_reverse($parts))
            : implode(', ', $parts);
    }
}
