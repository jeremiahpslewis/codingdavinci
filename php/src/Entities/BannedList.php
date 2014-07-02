<?php
namespace Entities;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo; // this will be like an alias for Gedmo extensions annotations

/**
* The NS-Verbotsliste
*
* @ORM\Table(name="List")
* @ORM\Entity
*/
class BannedList extends Base
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
     * @var integer
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    protected $row;

    /**
    * @ORM\Column(type="json_array")
    */
    protected $entry;

    /**
    * @ORM\Column(type="string")
    */
    protected $title;

    /**
    * @ORM\Column(name="ss_flag", type="boolean")
    */
    protected $ssFlag = false;

    /**
    * @ORM\Column(name="author_firstname", type="string")
    */
    protected $authorFirstname;

    /**
    * @ORM\Column(name="author_lastname", type="string")
    */
    protected $authorLastname;

    /**
    * @ORM\Column(name="first_edition_publisher", type="string")
    */
    protected $firstEditionPublisher;

    /**
    * @ORM\Column(name="first_edition_publication_year", type="string")
    */
    protected $firstEditionPublicationYear;

    /**
    * @ORM\Column(name="first_edition_publication_place", type="string")
    */
    protected $firstEditionPublicationPlace;

    /**
    * @ORM\Column(name="second_edition_publisher", type="string")
    */
    protected $secondEditionPublisher;

    /**
    * @ORM\Column(name="second_edition_publication_year", type="string")
    */
    protected $secondEditionPublicationYear;

    /**
    * @ORM\Column(name="second_edition_publication_place", type="string")
    */
    protected $secondEditionPublicationPlace;

    /**
    * @ORM\Column(name="additional_infos", type="string")
    */
    protected $additionalInfos;

    /**
    * @ORM\Column(name="page_number_in_ocr_document", type="integer")
    */
    protected $pageNumberInOCRDocument;

    /**
    * @ORM\Column(name="ocr_result", type="string")
    */
    protected $ocrResult;

    /**
    * @ORM\Column(name="corrections_after_1938", type="string")
    */
    protected $correctionsAfter1938;

    /**
    * @ORM\Column(name="title_gnd", type="string")
    */
    protected $titleGND;

    /**
    * @ORM\Column(name="author_gnd", type="string")
    */
    protected $authorGND;

    /**
    * @ORM\Column(name="complete_works", type="integer")
    */
    protected $completeWorks;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="created_at", type="datetime")
     */
    protected $createdAt;

    /**
     * @Gedmo\Timestampable(on="change", field={"title", "authorLastname", "authorFirstname", "ssFlag", "entity"})
     * @ORM\Column(name="changed_at", type="datetime")
     */
    protected $changedAt;

}
