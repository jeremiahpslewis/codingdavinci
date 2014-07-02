<?php
namespace Entities;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo; // this will be like an alias for Gedmo extensions annotations

/**
* The Publication on the NS-Verbotsliste
*
* @ORM\Table(name="Publication", options={"engine":"MyISAM"})
* @ORM\Entity
*/
class Publication extends Base
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
    protected $author;

    /**
    * @ORM\Column(type="string", nullable=true)
    */
    protected $editor;

    /**
    * @ORM\Column(type="string", nullable=true)
    */
    protected $title;

    /**
    * @ORM\Column(name="other_title_information", type="string", nullable=true)
    */
    protected $otherTitleInformation;

    /**
    * @ORM\Column(name="place_of_publication", type="string", nullable=true)
    */
    protected $placeOfPublication;

    /**
    * @ORM\Column(name="geonames_place_of_publication", type="string", nullable=true)
    */
    protected $geonamesPlaceOfPublication;

    /**
    * @ORM\Column(type="string", nullable=true)
    */
    protected $publisher;

    /**
    * @ORM\Column(name="publication_statement", type="string", nullable=true)
    */
    protected $publicationStatement;

    /**
    * @ORM\Column(type="string", nullable=true)
    */
    protected $extent;

    /**
    * @ORM\Column(type="string", nullable=true)
    */
    protected $issued;

    /**
    * @ORM\Column(name="is_part_of", type="string", nullable=true)
    */
    protected $isPartOf;

    /**
    * @ORM\Column(name="bibliographic_citation", type="string", nullable=true)
    */
    protected $bibliographicCitation;

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
    protected $oclc;

    /**
    * @ORM\Column(type="string", nullable=true)
    */
    protected $culturegraph;

    /**
     * @ORM\OneToMany(targetEntity="PublicationPerson", mappedBy="publication")
     * @ORM\OrderBy({"role" = "ASC", "personOrd" = "ASC"})
     */
    private $personRefs;

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

    public function addPersonRef($personRef) {
        $this->personRefs[] = $personRef;
    }

}
