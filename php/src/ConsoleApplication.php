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
            new AnalyticsCommand($container),
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

    protected function normalizeUnicode($value) {
        if (!class_exists('Normalizer')) {
            return $value;
        }
        if (!Normalizer::isNormalized($value)) {
            $normalized = Normalizer::normalize($value);
            if (false !== $normalized) {
                $value = $normalized;
            }
        }
        return $value;
    }

    protected function getBasePath() {
        return $this->container->getParameter('base_path');
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

    protected function getNameService() {
        return $this->container->get('name-service');
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
                'table that you want to import (list / publication / publicationperson / publicationplace / place / country)'
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
        if (array_key_exists('id', $publication_entry)) {
            $entity = $this->em->getRepository('Entities\Publication')->findOneById($publication_entry['id']);
        }
        else {
            if (empty($publication_entry['titleGND'])) {
                return false;
            }
            $entity = $this->em->getRepository('Entities\Publication')->findOneByGnd($publication_entry['titleGND']);
        }

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
                           'culturegraph', 'issued', 'completeWorks',
                           'geonamesPlaceOfPublication',
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

    private function insertUpdateCountry(&$country, $update_existing = true) {
        if (empty($country['iso-2'])) {
            return false;
        }

        $entity = $this->em->getRepository('Entities\Country')->findOneByIso2($country['iso-2']);

        if (isset($entity) && !$update_existing) {
            return true; // already done
        }

        if (!isset($entity)) {
            // preset new entity
            $entity = new Entities\Country();
            $entity->iso2 = $country['iso-2'];
        }

        foreach (array('name' => 'name',
                       'iso-2' => 'iso2', 'iso-3' => 'iso3',
                       'name_de' => 'germanName',
                       ) as $src => $target)
        {
            if (array_key_exists($src, $country)) {
                $value = $country[$src];
                if ('' === $value) {
                    $value = null;
                }
                $entity->{$target} = $value;
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
        else if ($action == 'publicationplace') {
            $places_map = array();
            switch ($type) {
                case 'tab':
                    $file = $this->getBasePath() . '/resources/data/publications_place_geonames.tsv';
                    $places_geonames = $this->readCsv($file, "\t");
                    foreach ($places_geonames as $entry) {
                       $places_map[$entry['place']] = $entry;
                    }
                    $file = $this->getBasePath() . '/resources/data/publications_place_normalized.tsv';
                    $publications = $this->readCsv($file, "\t");
                    break;
                default:
                    die('Currently not handling type ' . $type);
            }
            foreach ($publications as $publication) {
                $place_normalized = $publication['place_normalized'];
                if (!empty($place_normalized) && isset($places_map[$place_normalized])) {
                    $geonames_id = $places_map[$place_normalized]['geonames_id'];
                    $publication_entry = array('id' => $publication['id'],
                                               // 'placeOfPublication' => $publication['place_of_publication'],
                                               'geonamesPlaceOfPublication' => 'http://sws.geonames.org/' . $geonames_id . '/');
                    $this->insertUpdatePublicationEntry($publication_entry, $update_existing);
                }
            }
        }
        else if ($action == 'place') {
            switch ($type) {
                case 'tab':
                    $file = $this->getBasePath() . '/resources/data/places_geonames.tsv';
                    $places_geonames = $this->readCsv($file, "\t");
                    break;
                default:
                    die('Currently not handling type ' . $type);
            }
            foreach ($places_geonames as $place) {
                $dql = 'UPDATE Entities\Place p SET p.geonames = :geonames WHERE p.name = :name AND p.countryCode = :countryCode AND p.geonames IS NULL';
                $parameters = array('name' => $place['place'],
                                    'countryCode' => $place['country_code'],
                                    'geonames' => 'http://sws.geonames.org/' . $place['geonames_id'] . '/');
                $query = $this->em->createQuery($dql)->setParameters($parameters);
                $query->execute();
            }
        }
        else if ($action == 'country') {
            switch ($type) {
                case 'csv':
                    $file = $this->getBasePath() . '/resources/data/2014-06-28.dump.countrylist.net.csv';
                    $countries = $this->readCsv($file, ";");
                    break;
                default:
                    die('Currently not handling type ' . $type);
            }
            foreach ($countries as $country) {
                $this->insertUpdateCountry($country, $update_existing);
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
                'table that you want to fetch (person / person-gender / publication / place)'
            )
            ->addOption(
                'type',
                null,
                InputOption::VALUE_REQUIRED,
                'specify entityfacts, dnb, wikidata, oclc or geonames',
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
        else if ('openlibrary' == $type) {
            $qb->where("P.oclc IS NOT NULL");
        }
        // $qb->setMaxResults(1);

        $query = $qb->getQuery();
        $results = $query->getResult();
        foreach ($results as $publication) {
            $persist = false;
            if ('openlibrary' == $type) {
                /* TODO: Probably needs an editions-crosswalk as follows to find results
                 * http://www.worldcat.org/oclc/719211663
                 * -> http://xisbn.worldcat.org/webservices/xid/oclcnum/719211663?method=getEditions&format=json&fl=lccn,isbn
                 * -> https://openlibrary.org/api/books?bibkeys=LCCN:29022204&jscmd=data&format=json
                 * -> https://openlibrary.org/books/OL6733681M/The_Maurizius_case
                var_dump($publication->oclc);
                $bibkey = 'OCLC:' . $publication->oclc;
                $url = sprintf('https://openlibrary.org/api/books?bibkeys=%s&jscmd=data&format=json',
                               $bibkey);
                $result = $this->executeJsonQuery($url,
                                                  array('Accept' => 'application/json'));
                if (false !== $result) {
                    if (!empty($result)) {
                        var_dump($result[$bibkey]);
                        exit;
                    }
                }
                else {
                    echo('Problem calling ' . $url . "\n");
                }
                */
            }
            else if ('oclc' == $type) {
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

    /**
     * easyrdf-Helper Stuff
     */
    protected function setValuesFromResource (&$values, $resource, $propertyMap, $prefix = '') {
        foreach ($propertyMap as $src => $target) {
            if (is_int($src)) {
                // numerical indexed
                $key = $target;
            }
            else {
                $key = $src;
            }

            if (!empty($prefix) && !preg_match('/\:/', $key)) {
                $key = $prefix . ':' . $key;
            }

            $count = $resource->countValues($key);
            if ($count > 1) {
                $collect = array();
                $properties = $resource->all($key);
                foreach ($properties as $property) {
                    $value = $property->getValue();
                    if (!empty($value)) {
                        $collect[] = $value;
                    }
                }
                $values[$target] = $collect;

            }
            else if ($count == 1) {
                $property = $resource->get($key);

                if (isset($property) && !($property instanceof \EasyRdf_Resource)) {
                    $value = $property->getValue();
                    if (!empty($value)) {
                        $values[$target] = $value;
                    }
                }
            }
        }
    }

    private function fetchPlaceData($type, $update=false) {
        // find missing
        $dql = "SELECT P, C FROM Entities\Place P LEFT JOIN P.country C";

        if ('dnb' == $type) {
            $dql .= ' WHERE P.gnd IS NOT NULL';
            $dql .= ' AND P.country IS NULL AND P.name IS NULL';
        }
        else {
            $dql .= ' WHERE P.geonames IS NOT NULL';
            $dql .= ' AND P.name IS NULL OR P.latitude IS NULL';
        }
        $query = $this->em->createQuery($dql);
        // $query->setMaxResults(1);
        $results = $query->getResult();

        \EasyRdf_Namespace::set('dc', 'http://purl.org/dc/elements/1.1/');
        \EasyRdf_Namespace::set('gnd', 'http://d-nb.info/standards/elementset/gnd#');
        \EasyRdf_Namespace::set('geo', 'http://www.opengis.net/ont/geosparql#');

        \EasyRdf_Namespace::set('gn', 'http://www.geonames.org/ontology#');
        \EasyRdf_Namespace::set('wgs84_pos', 'http://www.w3.org/2003/01/geo/wgs84_pos#');

        foreach ($results as $place) {
            if ('dnb' == $type && preg_match('/d\-nb\.info\/gnd\/([0-9xX]+)/', $place->gnd, $matches)) {
                $gnd_id = $matches[1];
                if ('dnb' == $type) {
                    var_dump($place->gnd);
                    $persist = false;
                    $url = $place->gnd;


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
                            foreach ($resource->allResources('gnd:geographicAreaCode') as  $geographicAreaCode) {
                                $uri = $geographicAreaCode->getUri();
                                if (preg_match('/#X[A-E]\-([A-Z][A-Z])\b/', $uri, $matches)) {
                                    $place->countryCode = $matches[1];
                                    $persist = true;
                                    break;
                                }
                                else if ('http://d-nb.info/standards/vocab/gnd/geographic-area-code#XA-DDDE' == $uri) {
                                    $place->countryCode = 'DE';
                                    $persist = true;
                                    break;
                                }
                                else if (in_array($uri,
                                                  array('http://d-nb.info/standards/vocab/gnd/geographic-area-code#XA-DXDE',
                                                        'http://d-nb.info/standards/vocab/gnd/geographic-area-code#XA-YUCS',
                                                        'http://d-nb.info/standards/vocab/gnd/geographic-area-code#XA-CSXX',
                                                        'http://d-nb.info/standards/vocab/gnd/geographic-area-code#XA-CSHH',
                                                        'http://d-nb.info/standards/vocab/gnd/geographic-area-code#XA-SUHH',
                                                        'http://d-nb.info/standards/vocab/gnd/geographic-area-code#XA-AAAT',
                                                        )))
                                {
                                    // no automatic mapping for no longer existing territories such as Deutsches Reich, Habsburg, Soviet Union or Yugoslavia, Czechoslovakia...

                                }
                                else {
                                    die($uri);
                                }
                            }

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
                }
            }
            else if (preg_match('/geonames\.org/', $place->geonames)) {
                var_dump($place->geonames);
                $persist = false;
                $graph = new EasyRdf_Graph($place->geonames);
                try {
                    $graph->load();
                    $resource = $graph->get('gn:Feature', '^rdf:type');

                    // no result
                    if (empty($resource)) {
                        die('nothing found for ' . $place->geonames);
                    }
                    $values = array();
                    $this->setValuesFromResource($values, $resource,
                                                 array('name' => 'name',
                                                       'countryCode' => 'countryCode',
                                                       ),
                                                 'gn');
                    $this->setValuesFromResource($values, $resource,
                                 array('lat' => 'latitude',
                                       'long' => 'longitude',
                                       ),
                                 'wgs84_pos');

                    foreach (array('parentADM1' => 'geonamesParentAdm1',
                                   'parentADM2' => 'geonamesParentAdm2',
                                   'parentADM3' => 'geonamesParentAdm3',
                                   ) as $key => $target) {
                        $related = $resource->getResource('gn:' . $key);
                        if (isset($related)) {
                            $values[$target] = $related->getUri();
                        }
                    }

                    foreach ($values as $target => $new_value) {
                        $value = $place->$target;
                        if (($update || empty($value))) {
                            $persist = true;
                            $place->$target = $new_value;
                        }
                    }

                }
                catch (Exception $e) {
                }

                if ($persist) {
                    $this->em->persist($place);
                    $this->em->flush();
                }



            }

        }
    }

    protected function fetchPersonGenderData ($update = false) {
        // find missing
        $qb = $this->em->createQueryBuilder();
        $qb->select(array('P'))
            ->from('Entities\Person', 'P')
            ->where('P.forename IS NOT NULL'
                    . (!$update ? ' AND P.gender IS NULL' : ''));
        // $qb->setMaxResults(15);

        $query = $qb->getQuery();
        $results = $query->getResult();
        $name_service = $this->getNameService();
        foreach ($results as $person) {
            if (preg_match('/^[A-Z]\.$/', $person->forename)) {
                // skip initials
                continue;
            }
            $gender = $name_service->genderize($person->forename, $person->surname);
            if (!empty($gender)) {
                $person->gender = $gender;
                $this->em->persist($person);
                $this->em->flush();
            }
        }
    }

    private function fetchPersonData($type, $update = false) {
        // find missing
        $qb = $this->em->createQueryBuilder();
        $qb->select(array('P'))
            ->from('Entities\Person', 'P');
        if ('dnb' == $type) {
            $qb->where('P.forename IS NULL AND P.surname IS NULL AND P.gnd IS NOT NULL');
        }
        else if ('wikidata' == $type) {
            $qb->where("P.gnd IS NOT NULL AND (P.gndPlaceOfBirth IS NULL OR P.gndPlaceOfDeath IS NULL)");
            $qb->orderby('P.gnd');
        }
        else {
            $qb->where('P.entityfacts IS NULL AND P.gnd IS NOT NULL');
        }
        // $qb->setMaxResults(15);

        $query = $qb->getQuery();
        $results = $query->getResult();
        foreach ($results as $person) {
            if (preg_match('/d\-nb\.info\/gnd\/([0-9xX]+)/', $person->gnd, $matches)) {
                $gnd_id = $matches[1];
                if ('wikidata' == $type) {
                    $wikidata = Helper\Wikidata::fetchByGnd($gnd_id);
        			if (!empty($wikidata)) {
                        var_dump($gnd_id);
                        $persist = false;
                        foreach (array('dateOfBirth', 'dateOfDeath',
                                       'placeOfBirth', 'placeOfDeath',
                                       'gndPlaceOfBirth', 'gndPlaceOfDeath') as $key)
                        {
                            $target = $key;
                            $value = $person->$target;
                            if (($update || empty($value) || preg_match('/\-00$/', $value))
                                && (!empty($wikidata->$key)))
                            {
                                $value_new = $wikidata->$key;
                                if (in_array($key, array('gndPlaceOfBirth', 'gndPlaceOfDeath'))) {
                                    if (!$update) {
                                        // make sure place-values match
                                        $place_key = lcfirst(preg_replace('/^gnd/', '', $key));
                                        if ($person->{$place_key} != $wikidata->{$place_key}) {
                                            var_dump($person->{$place_key} . ' != ' . $wikidata->{$place_key});
                                            continue;
                                        }
                                    }
                                    $value_new = 'http://d-nb.info/gnd/' . $value_new;
                                }
                                $persist = true;
                                $person->$target = $value_new;
                                // var_dump($key . '->'.  $value_new . ' (' . $value . ')');
                            }
                        }
                        if ($persist) {
                            $this->em->persist($person);
                            $this->em->flush();
                        }
                    }

                }
                else if ('dnb' == $type) {
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
        if ($action == 'test') {
            $wikidata = Helper\Wikidata::fetchByGnd('139134980');
            var_dump($wikidata);
        }
        else if ($action == 'publication') {
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
        else if ('person-gender' == $action) {
            $this->fetchPersonGenderData();
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
                'table that you want to populate (publication / publication-person / person / person-detail / place)'
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
        if (!empty($place['geonames'])) {
            $entity = $this->em->getRepository('Entities\Place')->findOneByGeonames($place['geonames']);
        }
        else {
            $entity = $this->em->getRepository('Entities\Place')->findOneByGnd($place['gnd']);
        }

        if (isset($entity) && !$update_existing) {
            return true; // already done
        }

        if (!isset($entity)) {
            // preset new entity
            $entity = new Entities\Place();
            if (!empty($place['geonames'])) {
                $entity->geonames = $place['geonames'];
            }
            else {
                $entity->gnd = $place['gnd'];
            }
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
                ->where('P.entityfacts IS NOT NULL'
                        . (!$update ? ' AND (P.preferredName IS NULL OR P.gndPlaceOfBirth IS NULL)' : ''))
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

                        $person->$target = $this->normalizeUnicode($value); // entityfacts uses combining characters
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
        else if ('place-from-publication' == $action) {
            $update_existing = false;

            // find missing Place
            $qb = $entityManager->createQueryBuilder();
            $qb->select(array('Publication', 'P1'))
                ->from('Entities\Publication', 'Publication')
                ->leftJoin('Entities\Place', 'P1',
                           \Doctrine\ORM\Query\Expr\Join::WITH, 'P1.geonames=Publication.geonamesPlaceOfPublication')
                ->where('Publication.geonamesPlaceOfPublication IS NOT NULL')
                // ->setMaxResults(5)
                ;
            if (!$update_existing) {
                $qb->having('P1.id IS NULL');
            }

            $query = $qb->getQuery();
            $results = $query->getResult();
            foreach ($results as $result) {
                if (!isset($result)) {
                    continue;
                }
                foreach (array('placeOfPublication') as $key) {
                    $geonames = $result->__get('geonames' . ucfirst($key));
                    if (!is_null($geonames)) {
                        $place_record = array('geonames' => $geonames);
                        var_dump($place_record);
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

class AnalyticsCommand extends BaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->setName('analytics')
            ->setDescription('Create json-files for analytics')
            ->addArgument(
                'action',
                InputArgument::OPTIONAL,
                'report that you want to generate (flare)'
            );
    }

    private function stripGnd ($gnd) {
        $gnd = preg_replace('/http\:\/\/d\-nb\.info\/gnd\//', '', $gnd);
        return preg_replace('/\-/', '_', $gnd);
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->getLogger($output);

        $this->em = $entityManager = $this->getEntityManager();

        $action = $input->getArgument('action');
        if ($action == 'flare') {
            $dbconn = $this->em->getConnection();

            $querystr = 'SELECT Place.gnd AS gnd, Place.name AS name, Country.iso2 AS country_code'
                      . ' FROM Person JOIN Place ON Person.gnd_place_of_death=Place.gnd JOIN Country ON Place.country_code=Country.iso2'
                      . ' WHERE Person.status >= 0'
                      . " AND Person.complete_works <> 0"
                      // . " AND Country.iso2 IN ('AR', 'AT')"
                      . ' GROUP BY Country.iso3, Place.name'
                      . ' ORDER BY country_code, Place.name'
                      ;
            $stmt = $dbconn->query($querystr);
            $death_by_country = array();
            while ($row = $stmt->fetch()) {
                $deathplaces_by_country[$row['country_code']][$row['gnd']] = $row['name'];
            }
            $missingplaces_by_country = array();

            $dependencies = array();
            foreach ($deathplaces_by_country as $country_code => $places) {
                foreach ($places as $gnd => $place) {
                    // find all birth-places as dependencies
                    $querystr = 'SELECT Place.gnd AS gnd, Place.name AS name, Country.iso2 AS country_code, COUNT(*) AS how_many'
                              . ' FROM Person JOIN Place ON Person.gnd_place_of_birth=Place.gnd JOIN Country ON Place.country_code=Country.iso2'
                              . " WHERE Person.gnd_place_of_death='" . $gnd. "' AND Person.status >= 0"
                              . " AND Person.complete_works <> 0"
                              . ' GROUP BY Country.iso3, Place.name';
                    $stmt = $dbconn->query($querystr);
                    $dependencies_by_place = array();
                    while ($row = $stmt->fetch()) {
                        // add to $missingplaces_by_country if not already in $death_by_country
                        if (!isset($deathplaces_by_country[$row['country_code']])
                            || !isset($deathplaces_by_country[$row['country_code']][$row['gnd']]))
                        {
                            $missingplaces_by_country[$row['country_code']][$row['gnd']] = $row['name'];
                        }
                        $place_key = 'place.' . $row['country_code'] . '.' . $this->stripGnd($row['gnd']);
                        $dependencies_by_place[] = $place_key;
                    }
                    $place_key = 'place.' . $country_code . '.' . $this->stripGnd($gnd);
                    $entry = array("name" => $place_key,
                                   "label" => $place,
                                   "size" => 1,
                                   "imports" => array(),
                                   );
                    if (!empty($dependencies_by_place)) {
                        $entry["imports"] = $dependencies_by_place;
                    }

                    $dependencies[] = $entry;

                }
            }

            foreach ($missingplaces_by_country as $country_code => $places) {
                foreach ($places as $gnd => $place) {
                    $place_key = $country_code . '.' . $this->stripGnd($gnd);
                    $entry = array("name" => 'place.' . $place_key,
                                   "label" => $place,
                                   "size" => 1,
                                   "imports" => array(),
                                   );
                    $dependencies[] = $entry;
                }
            }
            echo json_encode($dependencies);
        }

    }
}
