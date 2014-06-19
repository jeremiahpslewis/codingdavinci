<?php

namespace Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * PublicationPerson
 *
 * @ORM\Table(name="PublicationPerson", indexes={
 *  @ORM\Index(name="idxPublicationPersonPublication", columns={"publication_id"}),
 *  @ORM\Index(name="idxPublicationPersonPerson", columns={"person_id"})
 * })
 * @ORM\Entity
 */
class PublicationPerson extends Base
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var Publication
     *
     * @ORM\ManyToOne(targetEntity="Publication", inversedBy="personRefs")
     * @ORM\JoinColumn(name="publication_id", referencedColumnName="id")
     */
    protected $publication;

    /**
     * @var Person
     *
     * @ORM\ManyToOne(targetEntity="Person", inversedBy="publicationRefs")
     * @ORM\JoinColumn(name="person_id", referencedColumnName="id")
     */
    protected $person;

    /**
     * @var integer
     *
     * @ORM\Column(name="person_ord", type="integer", nullable=false)
     */
    protected $personOrd = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="role", type="string", nullable=false)
     */
    protected $role = 'aut';

    /**
     * @var integer
     *
     * @ORM\Column(name="publication_ord", type="integer", nullable=false)
     */
    protected $publicationOrd = 0;

}
