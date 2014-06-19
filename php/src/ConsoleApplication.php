<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ConsoleApplication extends BaseApplication
{
    protected $application;

    public function __construct() {
        $container = $this->setupContainer();

        $this->application = new \Symfony\Component\Console\Application();

        $commands = array(
            new CreateCommand($container),
            new ImportCommand($container),
            new FetchCommand($container),
            new PopulateCommand($container),
        );

        $this->application->addCommands($commands);
    }

    public function run() {
        $this->application->run();
    }
}

abstract class BaseCommand extends Command
{
    protected $container;

    public function __construct($container) {
        parent::__construct();
        $this->container = $container;
    }

    protected function readCsv($fname, $separator = ',') {
        $entries = array();

        $headers = false;

        if (($handle = fopen($fname, 'r')) !== false) {
            while (($data = fgetcsv($handle, 1000, $separator, '"')) !== false) {
                if (empty($data)) {
                    continue;
                }
                if (false === $headers) {
                    $headers = $data;
                    continue;
                }
                $record = array();
                for ($i = 0; $i < count($headers); $i++) {
                    $record[$headers[$i]] = isset($data[$i]) ? trim($data[$i]) : null;
                }
                $entries[] = $record;
            }
            fclose($handle);
        }
        return $entries;
    }

    protected function getEntityManager() {
        return $this->container->get('doctrine');
    }

    protected function getBasePath() {
        return $this->container->getParameter('base_path');
    }

    protected function getLogger($output) {
        try {
            return $this->container->get('logger');
        }
        catch (\Exception $e) {
            $verbosityLevelMap = array(
                \Psr\Log\LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
                \Psr\Log\LogLevel::DEBUG   => OutputInterface::VERBOSITY_VERBOSE,
            );
            return new Symfony\Component\Console\Logger\ConsoleLogger($output, $verbosityLevelMap);
        }
    }

    /**
     * Executes a query
     *
     * @param string $query
     * @param array|null $headers
     * @param bool|null $assoc
     *
     * @throws NoResultException
     *
     * @return json object representing the query result
     */
    protected function executeJsonQuery($url, $headers = array(), $assoc = false)
    {
        if (!isset($this->client)) {
            $this->client = new \EasyRdf_Http_Client();
        }
        $this->client->setUri($url);
        $this->client->resetParameters(true); // clear headers
        foreach ($headers as $name => $val) {
            $this->client->setHeaders($name, $val);
        }
        try {
            $response = $this->client->request();
            if ($response->getStatus() < 400) {
                $content = $response->getBody();
            }
        } catch (\Exception $e) {
            $content = null;
        }
        if (!isset($content)) {
            return false;
        }
        $json = json_decode($content, true);

        // API error
        if (!isset($json)) {
            return false;
        }

        return $json;
    }


}


class CreateCommand extends BaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->setName('create')
            ->setDescription('Create database tables');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->getLogger($output);

        $entityManager = $this->getEntityManager();

        $table_classes = array(
            'List' => $entityManager->getClassMetadata('Entities\BannedList'),
            'Publication' => $entityManager->getClassMetadata('Entities\Publication'),
            'Person' => $entityManager->getClassMetadata('Entities\Person'),
            'PublicationPerson' => $entityManager->getClassMetadata('Entities\PublicationPerson'),
        );

        $schemaManager = $entityManager->getConnection()->getSchemaManager();
        $schemaTool = new Doctrine\ORM\Tools\SchemaTool($entityManager);

        try {
            foreach ($table_classes as $table => $class) {
                if (!$schemaManager->tablesExist(array($table))) {
                    $res = $schemaTool->createSchema(array($class));
                    $logger->info("Table " . $table . " created");
                }
                else {
                    $logger->debug("Table " . $table . " already exists");
                }
            }
        }
        catch (Exception $e) {
           $logger->error("Error createSchema " . $table . ': ' . $e->getMessage());
        }

    }
}

class ImportCommand extends BaseCommand
{
    /*
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->setName('import')
            ->setDescription('Import lists into database')
            ->addArgument(
                'action',
                InputArgument::OPTIONAL,
                'table that you want to import (list / publication / publicationperson)'
            )
            ->addOption(
                'type',
                null,
                InputOption::VALUE_REQUIRED,
                'specify json or csv',
                null
                )
            ->addOption(
                'update',
                null,
                InputOption::VALUE_NONE,
                'If set, existing entries will be updates'
            );
    }

    private function insertUpdatePersonRefs($entity, $person_gnds, $role = 'aut')
    {
        $max_ord = 0;
        foreach ($person_gnds as $person_gnd) {
            $person = $this->em->getRepository('Entities\Person')->findOneByGnd($person_gnd);

            if (!isset($person)) {
                // create new person
                $person = new Entities\Person();
                $person->gnd = $person_gnd;
                $this->em->persist($person);
            }

            // check if we need to add a ref
            $add_ref = true;
            foreach ($entity->personRefs as $personRef) {
                if ($personRef->role != $role) {
                    continue;
                }
                $otherPerson = $personRef->person;
                if ($otherPerson->gnd == $person_gnd) {
                    $add_ref = false;
                    break;
                }
                if ($personRef->publicationOrd > $max_ord) {
                    $max_ord = $personRef->publicationOrd;
                }
            }

            if ($add_ref) {
                $personRef = new Entities\PublicationPerson();
                $personRef->publication = $entity;
                $personRef->person = $person;
                $personRef->role = $role;
                $personRef->publicationOrd = $max_ord++;
                $entity->addPersonRef($personRef);
                $this->em->persist($entity);
                $this->em->persist($personRef); // currently no cascade
            }
        }
    }


    private function insertUpdatePublicationEntry(&$publication_entry,
                                                  $update_existing = true, $create_missing = false) {
        if (empty($publication_entry['titleGND'])) {
            return false;
        }
        $entity = $this->em->getRepository('Entities\Publication')->findOneByGnd($publication_entry['titleGND']);

        $created = false;
        if (!isset($entity)) {
            if (!$create_missing) {
                return false;
            }

            if (!isset($entity)) {
                // preset new entity
                $created = true;
                $entity = new Entities\Publication();
                $entity->gnd = $publication_entry['titleGND'];
                $entity->personRefs = array();
            }

        }

        if ($update_existing || $created) {
            foreach (array('title',
                           'culturegraph', 'issued', 'completeWorks'
                           ) as $key)
            {
                if (array_key_exists($key, $publication_entry)) {
                    $entity->{$key} = $publication_entry[$key];
                }
            }
        }

        if (!empty($publication_entry['authorGND'])) {
            $this->insertUpdatePersonRefs($entity, preg_split('/\s*\;\s*/', $publication_entry['authorGND']), 'aut');
        }

        if (!empty($publication_entry['contributorGND'])) {
            $this->insertUpdatePersonRefs($entity, preg_split('/\s*\;\s*/', $publication_entry['contributorGND']), 'edt');
        }

        $this->em->persist($entity);
        $this->em->flush();

        return true;
    }

    private function insertUpdatePerson(&$person, $update_existing = true) {
        if (empty($person['gnd'])) {
            return false;
        }
        $entity = $this->em->getRepository('Entities\Person')->findOneByGnd($person['gnd']);

        if (!isset($entity)) {
            // we currently don't create missing
            return false;
        }

        if (isset($entity) && !$update_existing) {
            return true; // already done
        }

        if ($update_existing) {
            foreach (array('forename', 'surname',
                           ) as $key)
            {
                if (array_key_exists($key, $person)) {
                    $entity->{$key} = $person[$key];
                }
            }
        }

        if (!empty($person['person'])) {
            $entityfacts = json_decode($person['person'], true);
            if (isset($entityfacts) && false !== $entityfacts) {
                $entity->entityfacts = $entityfacts;
            }
        }

        $this->em->persist($entity);
        $this->em->flush();

        return true;
    }

    private function insertUpdateListEntry(&$list_entry, $row, $update_existing = true) {
        $entity = $this->em->getRepository('Entities\BannedList')->findOneByRow($row);

        if (isset($entity) && !$update_existing) {
            return true; // already done
        }

        if (!isset($entity)) {
            // preset new entity
            $entity = new Entities\BannedList();
            $entity->row = $row;
            $entity->entry = $list_entry;
        }

        foreach (array('title', 'ssFlag',
                       'authorFirstname', 'authorLastname',
                       'firstEditionPublisher', 'firstEditionPublicationYear', 'firstEditionPublicationPlace',
                       'secondEditionPublisher', 'secondEditionPublicationYear', 'secondEditionPublicationPlace',
                       'additionalInfos', 'pageNumberInOCRDocument', 'ocrResult',
                       'correctionsAfter1938',

                       'titleGND', 'authorGND', 'completeWorks'
                       ) as $key)
        {
            if (array_key_exists($key, $list_entry)) {
                $value = $list_entry[$key];
                if ('' === $value) {
                    $value = null;
                }
                if ('ssFlag' == $key) {
                    $value = 'true' == $value;
                }
                if ('completeWorks' == $key) {
                    if (!isset($value)) {
                        $value = 0;
                    }
                }
                $entity->{$key} = $value;
            }
        }


        $this->em->persist($entity);
        $this->em->flush();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->em = $this->getEntityManager(); // save some typing

        // check if overwrite
        $update_existing = $input->getOption('update');

        $type = $input->getOption('type');
        if (empty($type)) {
            $type = 'json';
        }

        $action = $input->getArgument('action');
        if ($action == 'publication') {
            // list
            switch ($type) {
                case 'tab':
                    $file = $this->getBasePath() . '/resources/data/publicationCompleteWorks.tab';
                    $publications = $this->readCsv($file, "\t");
                    break;
                default:
                    die('Currently not handling type ' . $type);
            }
            foreach ($publications as $publication_entry) {
                if ('O' == $publication_entry['type']) {
                    continue; // currently skip online publications
                }
                $publication_entry['completeWorks'] = 1; // part of "alle schriften"
                $this->insertUpdatePublicationEntry($publication_entry, $update_existing, true);
            }
        }
        else if ($action == 'publicationperson') {
            // list
            switch ($type) {
                case 'tab':
                    // $file = $this->getBasePath() . '/resources/data/additionalPublicationIdAndTitle.tab';
                    $file = $this->getBasePath() . '/resources/data/publicationIdTitleAuthorsContributors.tab';
                    $publication_persons = $this->readCsv($file, "\t");
                    break;
                default:
                    die('Currently not handling type ' . $type);
            }
            foreach ($publication_persons as $publication_entry) {
                $this->insertUpdatePublicationEntry($publication_entry, $update_existing);
            }
        }
        else if ($action == 'person') {
            switch ($type) {
                case 'csv':
                    // $file = $this->getBasePath() . '/resources/data/culturehub_clean.csv';
                    $file = $this->getBasePath() . '/resources/data/culturehub_clean-test.csv';
                    $persons = $this->readCsv($file);
                    break;
                default:
                    die('Currently not handling type ' . $type);
            }
            foreach ($persons as $person) {
                $this->insertUpdatePerson($person, $update_existing);
            }

        }
        else {
            // list
            switch ($type) {
                case 'csv':
                    $file = $this->getBasePath() . '/resources/data/verbannte-buecher.csv';
                    $list_entries = $this->readCsv($file);
                    break;
                case 'json':
                    $file = $this->getBasePath() . '/resources/data/verbannte-buecher.json';
                    $list_entries = json_decode(file_get_contents($file), true);
                    break;
                default:
                    die('Currently not handling type ' . $type);
            }


            $count = 0;
            foreach ($list_entries as $list_entry) {
                ++$count;
                $row = !empty($list_entry['row']) ? $list_entry['row'] : $count;
                $this->insertUpdateListEntry($list_entry, $row, $update_existing);
            }
        }
    }

}

class FetchCommand extends BaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->setName('fetch')
            ->addArgument(
                'action',
                InputArgument::OPTIONAL,
                'table that you want to fetch (person / publication)'
            )
            ->addOption(
                'type',
                null,
                InputOption::VALUE_REQUIRED,
                'specify entityfacts, dnb or oclc',
                null
                )
            ->setDescription('Fetch external data');
    }

    private function fetchPublicationData($type) {
        if ('dnb' == $type) {
            \EasyRdf_Namespace::set('dc', 'http://purl.org/dc/elements/1.1/');
            \EasyRdf_Namespace::set('gnd', 'http://d-nb.info/standards/elementset/gnd#');
            \EasyRdf_Namespace::set('isbd', 'http://iflastandards.info/ns/isbd/elements/');
            \EasyRdf_Namespace::set('rda', 'http://rdvocab.info/');
            \EasyRdf_Namespace::set('marcRole', 'http://id.loc.gov/vocabulary/relators/');
            \EasyRdf_Namespace::set('geo', 'http://www.opengis.net/ont/geosparql#');
        }

        // find missing
        $qb = $this->em->createQueryBuilder();
        $qb->select(array('P'))
            ->from('Entities\Publication', 'P');
        if ('dnb' == $type) {
            $qb->where('P.gnd IS NOT NULL AND (P.title IS NULL OR P.oclc IS NULL)');
        }
        else if ('oclc' == $type) {
            $qb->where("P.oclc IS NOT NULL AND (P.title IS NULL OR P.title='')");
        }
        // $qb->setMaxResults(1);

        $query = $qb->getQuery();
        $results = $query->getResult();
        foreach ($results as $publication) {
            $persist = false;
            if ('oclc' == $type) {
                var_dump($publication->id);
                $url = sprintf('http://www.worldcat.org/oclc/%s',
                               $publication->oclc);
                $graph = new EasyRdf_Graph($url);
                try {
                    $graph->load();
                    $uri = $graph->getUri();

                    if (!empty($uri)) {
                        $resource = $graph->resource($uri);
                        $rdf_type = $resource->get('rdf:type');
                        if (empty($rdf_type)) {
                            unset($resource);
                        }
                    }

                    if (empty($resource)) {
                        die('TODO: handle rdf:type for ' . $url);
                    }
                    if (isset($resource)) {
                        foreach (array(
                                       'schema:name' => 'title',
                                       'schema:datePublished' => 'issued',
                                       ) as $src => $target) {
                            $property = $resource->get($src);
                            if (isset($property) && !($property instanceof \EasyRdf_Resource)) {
                                $value = $property->getValue();
                                $current_value = $publication->$target;
                                if (empty($current_value) && !empty($value)) {
                                    var_dump($target . ': ' . $value);
                                    $persist = true;
                                    $publication->$target = $value;
                                }
                            }
                        }
                    }
                }
                catch (Exception $e) {
                    var_dump($e);
                }
            }
            else if ('dnb' == $type) {
                if (preg_match('/d\-nb\.info.*?\/([0-9xX]+)/', $publication->gnd, $matches)) {
                    $gnd_id = $matches[1];
                    var_dump($publication->gnd);
                    $url = sprintf('http://d-nb.info/%s',
                                   $gnd_id);

                    $graph = new EasyRdf_Graph($url);
                    try {
                        $graph->load();
                        foreach (array('http://purl.org/ontology/bibo/Document',
                                       'http://purl.org/ontology/bibo/Periodical',
                                       'http://purl.org/ontology/bibo/Collection',
                                       'http://purl.org/ontology/bibo/Series',
                                       ) as $rdf_type)
                        {
                            $resource = $graph->get($rdf_type, '^rdf:type');
                            if (isset($resource)) {
                                break;
                            }
                        }

                        if (!isset($resource) && !preg_match('/d\-nb\.info\/gnd\//', $publication->gnd)) {
                            echo('WARN: handle type for ' . $publication->gnd);
                        }

                        if (isset($resource)) {
                            foreach (array(
                                           'rda:otherTitleInformation' => 'otherTitleInformation',
                                           'rda:placeOfPublication' => 'placeOfPublication',
                                           'rda:publicationStatement' => 'publicationStatement',
                                           'isbd:P1053' => 'extent',
                                           'dc:identifier' => 'oclc',
                                           'dc:publisher' => 'publisher',
                                           'dcterms:issued' => 'issued',
                                           'dcterms:bibliographicCitation' => 'bibliographicCitation',
                                           ) as $src => $target) {
                                $property = $resource->get($src);
                                if (isset($property) && !($property instanceof \EasyRdf_Resource)) {
                                    $value = $property->getValue();
                                    if ('oclc' == $target) {
                                        if (!preg_match('/^\(OColc\)(\S+)$/', $value, $matches)) {
                                            unset($value);
                                        }
                                        else {
                                            $value = $matches[1];
                                        }
                                    }
                                    $current_value = $publication->$target;
                                    if (empty($current_value) && !empty($value)) {
                                        $persist = true;
                                        $publication->$target = $value;
                                    }
                                }
                            }
                        }

                    }
                    catch (Exception $e) {
                        var_dump($e);
                    }
                }
            }
            if ($persist) {
                $this->em->persist($publication);
                $this->em->flush();
            }
        }
    }

    private function fetchPlaceData($type) {
        // find missing
        $qb = $this->em->createQueryBuilder();
        $qb->select(array('P'))
            ->from('Entities\Place', 'P');
        if ('dnb' == $type) {
            $qb->where('P.gnd IS NOT NULL AND (P.geonames IS NULL OR P.latitude IS NULL)');
        }
        else {
            $qb->where('P.geonames IS NOT NULL');
        }
        // $qb->setMaxResults(1);

        $query = $qb->getQuery();
        $results = $query->getResult();
        foreach ($results as $place) {
            if (preg_match('/d\-nb\.info\/gnd\/([0-9xX]+)/', $place->gnd, $matches)) {
                $gnd_id = $matches[1];
                if ('dnb' == $type) {
                    var_dump($place->gnd);
                    $persist = false;
                    $url = $place->gnd;

                    \EasyRdf_Namespace::set('dc', 'http://purl.org/dc/elements/1.1/');
                    \EasyRdf_Namespace::set('gnd', 'http://d-nb.info/standards/elementset/gnd#');
                    \EasyRdf_Namespace::set('geo', 'http://www.opengis.net/ont/geosparql#');
                    $graph = new EasyRdf_Graph($url);
                    try {
                        $graph->load();
                        $resource = $graph->get('gnd:TerritorialCorporateBodyOrAdministrativeUnit', '^rdf:type');
                        /* if (!isset($resource)) {
                            $resource = $graph->get('gnd:UndifferentiatedPerson', '^rdf:type');
                        } */
                        if (!isset($resource)) {
                            echo('WARN: handle type for ' . $url);
                        }
                        if (isset($resource)) {
                            $hasGeometry = $resource->getResource('geo:hasGeometry');
                            if (isset($hasGeometry)) {
                                $asWKT = $hasGeometry->get('geo:asWKT');
                                if (isset($asWKT)) {
                                    $coordinates = $asWKT->getValue();
                                    if (preg_match('/Point\s*\(\s*(.+?)\s+(.+?)\s*\)/', $coordinates, $matches)) {
                                        $place->longitude = $matches[1];
                                        $place->latitude = $matches[2];
                                        $persist = true;
                                    }
                                }
                            }

                            foreach ($resource->allResources('owl:sameAs') as $sameAs) {
                                $uri = $sameAs->getUri();
                                if (preg_match('/geonames\.org/', $uri)) {
                                    $place->geonames = $uri;
                                    $persist = true;
                                    break;
                                }
                            }

                        }

                    }
                    catch (Exception $e) {
                        var_dump($e->getMessage());

                    }
                    if ($persist) {
                        $this->em->persist($place);
                        $this->em->flush();
                    }


                }
                else {
                    die('TODO: handle type' . $type);
                    $url = sprintf('http://hub.culturegraph.org/entityfacts/%s',
                                   $gnd_id);
                    var_dump($url);
                    $result = $this->executeJsonQuery($url,
                                                      array('Accept' => 'application/json',
                                                            'Accept-Language' => 'en-us,en', // date-format!
                                                            ));
                    if (false !== $result) {
                        $person->entityfacts = $result;

                        $this->em->persist($person);
                        $this->em->flush();
                    }

                }
            }

        }
    }

    private function fetchPersonData($type) {
        // find missing
        $qb = $this->em->createQueryBuilder();
        $qb->select(array('P'))
            ->from('Entities\Person', 'P');
        if ('dnb' == $type) {
            $qb->where('P.forename IS NULL AND P.surname IS NULL AND P.gnd IS NOT NULL');
        }
        else {
            $qb->where('P.entityfacts IS NULL AND P.gnd IS NOT NULL');
        }
        // $qb->setMaxResults(1);

        $query = $qb->getQuery();
        $results = $query->getResult();
        foreach ($results as $person) {
            if (preg_match('/d\-nb\.info\/gnd\/([0-9xX]+)/', $person->gnd, $matches)) {
                $gnd_id = $matches[1];
                if ('dnb' == $type) {
                    var_dump($person->gnd);
                    $persist = false;
                    $url = sprintf('http://d-nb.info/%s',
                                   $gnd_id);

                    \EasyRdf_Namespace::set('gnd', 'http://d-nb.info/standards/elementset/gnd#');
                    $graph = new EasyRdf_Graph($url);
                    try {
                        $graph->load();
                        $resource = $graph->get('gnd:DifferentiatedPerson', '^rdf:type');
                        if (!isset($resource)) {
                            $resource = $graph->get('gnd:UndifferentiatedPerson', '^rdf:type');
                        }
                        if (isset($resource)) {
                            $preferredNameEntityForThePerson = $resource->getResource('gnd:preferredNameEntityForThePerson');
                            if (isset($preferredNameEntityForThePerson)) {
                                foreach (array('forename', 'surname') as $key) {
                                    $property = $preferredNameEntityForThePerson->get('gnd:' . $key);
                                    if (isset($property) && !($property instanceof \EasyRdf_Resource)) {
                                        $value = $property->getValue();
                                        if (!empty($value)) {
                                            $persist = true;
                                            $person->$key = $value;
                                        }
                                    }
                                }
                            }

                        }

                    }
                    catch (Exception $e) {

                    }
                    if ($persist) {
                        $this->em->persist($person);
                        $this->em->flush();
                    }


                }
                else {
                    $url = sprintf('http://hub.culturegraph.org/entityfacts/%s',
                                   $gnd_id);
                    var_dump($url);
                    $result = $this->executeJsonQuery($url,
                                                      array('Accept' => 'application/json',
                                                            'Accept-Language' => 'en-us,en', // date-format!
                                                            ));
                    if (false !== $result) {
                        $person->entityfacts = $result;

                        $this->em->persist($person);
                        $this->em->flush();
                    }

                }
            }

        }
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->getLogger($output);

        $this->em = $entityManager = $this->getEntityManager();

        $action = $input->getArgument('action');
        if ($action == 'publication') {
            $type = $input->getOption('type');
            if (empty($type)) {
                $type = 'dnb';
            }
            $this->fetchPublicationData($type);
        }
        else if ($action == 'place') {
            $type = $input->getOption('type');
            if (empty($type)) {
                $type = 'dnb';
            }
            $this->fetchPlaceData($type);
        }
        else {
            $type = $input->getOption('type');
            if (empty($type)) {
                $type = 'entityfacts';
            }
            $this->fetchPersonData($type);
        }

    }
}

class PopulateCommand extends BaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->setName('populate')
            ->addArgument(
                'action',
                InputArgument::OPTIONAL,
                'table that you want to populate (publication / publication-person / person / person-detail)'
            )
            ->setDescription('Populate database tables');
    }

    private function insertUpdatePerson(&$person, $update_existing = true) {
        $entity = $this->em->getRepository('Entities\Person')->findOneByGnd($person['gnd']);

        if (isset($entity) && !$update_existing) {
            return true; // already done
        }

        if (!isset($entity)) {
            // preset new entity
            $entity = new Entities\Person();
            $entity->gnd = $person['gnd'];
        }

        foreach (array('listRow', 'completeWorks',
                       ) as $key)
        {
            if (array_key_exists($key, $person)) {
                $entity->{$key} = $person[$key];
            }
        }

        $this->em->persist($entity);
        $this->em->flush();

        return true;
    }

    private function insertUpdatePlace(&$place, $update_existing = true) {
        $entity = $this->em->getRepository('Entities\Place')->findOneByGnd($place['gnd']);

        if (isset($entity) && !$update_existing) {
            return true; // already done
        }

        if (!isset($entity)) {
            // preset new entity
            $entity = new Entities\Place();
            $entity->gnd = $place['gnd'];
        }

        foreach (array('name',
                       ) as $key)
        {
            if (array_key_exists($key, $place)) {
                $entity->{$key} = $place[$key];
            }
        }

        $this->em->persist($entity);
        $this->em->flush();

        return true;
    }

    private function insertUpdatePublication(&$publication, $update_existing = true) {
        $entity = $this->em->getRepository('Entities\Publication')->findOneByGnd($publication['gnd']);

        if (isset($entity) && !$update_existing) {
            return true; // already done
        }

        if (!isset($entity)) {
            // preset new entity
            $entity = new Entities\Publication();
            $entity->gnd = $publication['gnd'];
        }

        foreach (array('title', 'listRow'
                       ) as $key)
        {
            if (array_key_exists($key, $publication)) {
                $value = $publication[$key];
                if (isset($value) && '' === $value) {
                    $value = null;
                }
                $entity->{$key} = $publication[$key];
            }
        }

        $this->em->persist($entity);
        $this->em->flush();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->getLogger($output);

        $this->em = $entityManager = $this->getEntityManager();

        $action = $input->getArgument('action');
        if ($action == 'person-detail') {
            $update = true; // todo: get from option

            // TODO: move this action to other command
            $qb = $entityManager->createQueryBuilder();
            $qb->select(array('P'))
                ->from('Entities\Person', 'P')
                ->where('P.entityfacts IS NOT NULL AND (P.preferredName IS NULL OR P.gndPlaceOfBirth IS NULL)')
                // ->setMaxResults(4)
                ;

            $query = $qb->getQuery();
            $results = $query->getResult();
            foreach ($results as $person) {
                $persist = false;

                $entityfacts = $person->entityfacts;
                foreach (array('forename' => 'forename', 'surname' => 'surname',
                               'preferredName' => 'preferredName',
                               'variantName' => 'variantNames',
                               'placeOfBirth' => 'placeOfBirth',
                               // 'placeOfActivity' => 'placeOfActivity', // can be multiple
                               'placeOfDeath' => 'placeOfDeath',
                               'biographicalOrHistoricalInformation' => 'biographicalOrHistoricalInformation',
                               ) as $key => $target) {
                    $value = $person->$target;
                    if (($update || empty($value)) && (!empty($entityfacts['person'][$key]))) {
                        $persist = true;
                        if (is_array($entityfacts['person'][$key])) {
                            if (array_key_exists('@value', $entityfacts['person'][$key])) {
                                $value = $entityfacts['person'][$key]['@value'];
                                if (array_key_exists('@id', $entityfacts['person'][$key])) {
                                    $person->{'gnd' . ucfirst($target)} = $entityfacts['person'][$key]['@id'];
                                }
                            }
                            else {
                                $value = join("\n", $entityfacts['person'][$key]);
                            }

                        }
                        else {
                            $value = $entityfacts['person'][$key];
                        }

                        $person->$target = $value;
                    }

                }
                if (!empty($entityfacts['person']['gender'])) {
                    switch ($entityfacts['person']['gender']['@id']) {
                        case 'http://d-nb.info/standards/vocab/gnd/gender#male':
                            $persist = true;
                            $person->gender = 'male';
                            break;
                        case 'http://d-nb.info/standards/vocab/gnd/gender#female':
                            $persist = true;
                            $person->gender = 'female';
                            break;
                    }
                }
                foreach (array('dateOfBirth', 'dateOfDeath') as $key) {
                    $value = $person->{$key};
                    if ((empty($value) || $value === '0000-00-00')
                        && !empty($entityfacts['person'][$key]))
                    {
                        $value = $entityfacts['person'][$key];
                        if (preg_match('/^\d{4}$/', $value)) {
                            $value .= '-00-00';
                        }
                        else {
                            $date = \DateTime::createFromFormat('F d, Y', $value);
                            if (isset($date)) {
                                $res = \DateTime::getLastErrors();
                                if (0 == $res['warning_count'] && 0 == $res['error_count']) {
                                    $date_str = $date->format('Y-m-d');
                                    if ('0000-00-00' !== $date_str) {
                                        $value = $date_str;
                                    }
                                }
                            }
                        }
                        $person->{$key} = $value;
                        $persist = true;
                    }
                }

                if (!empty($entityfacts['sameAs'])) {
                    foreach ($entityfacts['sameAs'] as $sameAs) {
                        switch ($sameAs['publisher']['abbr']) {
                            case 'VIAF':
                                $person->viaf = $sameAs['@id'];
                                $persist = true;
                                break;
                            case 'LC':
                                $person->lcNaf = $sameAs['@id'];
                                $persist = true;
                                break;
                        }
                    }
                }
                if ($persist) {
                    $this->em->persist($person);
                    $this->em->flush();
                }
            }

        }
        else if ('person' == $action) {
            $update_existing = true;

            // find missing person
            $qb = $entityManager->createQueryBuilder();
            $qb->select(array('BL', 'P'))
                ->from('Entities\BannedList', 'BL')
                ->leftJoin('Entities\Person', 'P',
                           \Doctrine\ORM\Query\Expr\Join::WITH, 'BL.authorGND = P.gnd')
                ->where('BL.authorGND IS NOT NULL AND BL.completeWorks <> 0')
                // ->setMaxResults(1)
                ;
            if (!$update_existing) {
                $qb->having('P.id IS NULL');
            }

            $query = $qb->getQuery();
            $results = $query->getResult();
            foreach ($results as $result) {
                if (!isset($result)) {
                    continue;
                }
                $gnd = $result->authorGND;
                $person_record = array('gnd' => $gnd, 'listRow' => $result->row,
                                       'completeWorks' => $result->completeWorks);
                $this->insertUpdatePerson($person_record, $update_existing);
            }
        }
        else if ('place' == $action) {
            $update_existing = false;

            // find missing Place
            $qb = $entityManager->createQueryBuilder();
            $qb->select(array('Person', 'P1', 'P2'))
                ->from('Entities\Person', 'Person')
                ->leftJoin('Entities\Place', 'P1',
                           \Doctrine\ORM\Query\Expr\Join::WITH, 'P1.gnd=Person.gndPlaceOfBirth')
                ->leftJoin('Entities\Place', 'P2',
                           \Doctrine\ORM\Query\Expr\Join::WITH, 'P2.gnd=Person.gndPlaceOfDeath')
                ->where('Person.gndPlaceOfBirth IS NOT NULL OR Person.gndPlaceOfDeath IS NOT NULL')
                // ->setMaxResults(1)
                ;
            if (!$update_existing) {
                $qb->having('P1.id IS NULL OR P2.id IS NULL');
            }

            $query = $qb->getQuery();
            $results = $query->getResult();
            foreach ($results as $result) {
                if (!isset($result)) {
                    continue;
                }
                foreach (array('placeOfBirth', 'placeOfDeath') as $key) {
                    $gnd = $result->__get('gnd' . ucfirst($key));
                    if (!is_null($gnd)) {
                        $place_record = array('gnd' => $gnd,
                                              'name' => $result->$key);
                        var_dump($place_record);
                        // exit;
                        $this->insertUpdatePlace($place_record, $update_existing);

                    }

                }
            }
        }
        else if ('publication-person' == $action) {
            // find missing publications
            $qb = $entityManager->createQueryBuilder();
            $qb->select('PP')
                ->from('Entities\PublicationPerson', 'PP')
                ->where('PP.publicationOrd IS NULL OR PP.publicationOrd=0')
                // ->setMaxResults(10)
                ;
            $batch_size = 100;
            $persist_count = 0;
            $query = $qb->getQuery();
            $results = $query->getResult();
            foreach ($results as $publicationRef) {
                $issued = $publicationRef->publication->issued;
                if (isset($issued) && preg_match('/^(\d{4})/', $issued, $matches)) {
                    $publicationRef->publicationOrd = $matches[1];
                    $this->em->persist($publicationRef);
                    ++$persist_count;
                }
                if ($persist_count >= $batch_size) {
                    $this->em->flush();
                    $persist_count = 0;
                }
            }
            if ($persist_count >= 0) {
                $this->em->flush();
                $persist_count = 0;
            }
        }
        else if ($action == 'publication-detail') {
            $update = true; // todo: get from option
            // find Publication without author / editor
            $qb = $entityManager->createQueryBuilder();
            $qb->select(array('P'))
                ->from('Entities\Publication', 'P')
                ->where('P.author IS NULL AND P.editor IS NULL')
                // ->setMaxResults(100)
                ;

            $query = $qb->getQuery();
            $results = $query->getResult();
            $batch_size = 100;
            $persist_count = 0;
            foreach ($results as $publication) {
                if (!isset($publication) || 0 == count($publication->personRefs)) {
                    continue;
                }
                $authors = $editors = array();
                foreach ($publication->personRefs as $personRef) {
                    $person = $personRef->person;
                    $name_parts = array();
                    foreach (array('surname', 'forename') as $key) {
                        $value = $person->$key;
                        if (!empty($value)) {
                            $name_parts[] = $value;
                        }
                    }
                    if (count($name_parts) > 0) {
                        $fullname = implode(', ', $name_parts);
                        if ('aut' == $personRef->role) {
                            $authors[] = $fullname;
                        }
                        else if ('edt' == $personRef->role) {
                            $editors[] = $fullname;
                        }
                    }
                    $persist = false;
                    if (count($authors) > 0) {
                        $persist = true;
                        $publication->author = implode('; ', $authors);
                    }
                    if (count($editors) > 0) {
                        $persist = true;
                        $publication->editor = implode('; ', $editors);
                    }
                    if ($persist) {
                        $this->em->persist($publication);
                        ++$persist_count;
                    }
                    if ($persist_count >= $batch_size) {
                        $this->em->flush();
                        $persist_count = 0;
                    }
                }
            }
            if ($persist_count >= 0) {
                $this->em->flush();
                $persist_count = 0;
            }
        }
        else {
            // find missing publications
            $qb = $entityManager->createQueryBuilder();
            $qb->select(array('BL', 'P'))
                ->from('Entities\BannedList', 'BL')
                ->leftJoin('Entities\Publication', 'P',
                           \Doctrine\ORM\Query\Expr\Join::WITH, 'BL.titleGND = P.gnd')
                ->where('BL.titleGND IS NOT NULL')
                ->having('P.id IS NULL')
                // ->setMaxResults(5)
                ;

            $query = $qb->getQuery();
            $results = $query->getResult();
            foreach ($results as $result) {
                if (!isset($result)) {
                    continue;
                }
                $gnd = $result->titleGND;
                $publication_record = array('gnd' => $gnd, 'listRow' => $result->row);
                $this->insertUpdatePublication($publication_record);
            }
        }

    }
}
