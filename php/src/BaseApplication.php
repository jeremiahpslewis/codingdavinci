<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

// helper class
class MysqlFulltextSimpleParser
{
    var $min_length = 0; // you might have to turn this up to ft_min_word_len
    var $add_wildcard = FALSE; // match -> match*
    var $output = '';

    /**
      * Callback function (or mode / state), called by the Lexer. This one
      * deals with text outside of a variable reference.
      * @param string the matched text
      * @param int lexer state (ignored here)
      */
    function accept($match, $state) {
        // echo "$state: -$match-<br />";
        if ($state == LEXER_UNMATCHED && strlen($match) < $this->min_length && strpos($match, '*') === FALSE)
            return TRUE;

        if ($state != LEXER_MATCHED) {
            $this->output .= '+';
            if ($this->add_wildcard) {
                $match .= '*';
            }
        }

        $this->output .= $match;

        return TRUE;
    }

    function writeQuoted($match, $state) {
        static $words;

        switch ($state) {
            // Entering the variable reference
            case LEXER_ENTER:
                $words = array();
                break;

            // Contents of the variable reference
            case LEXER_MATCHED:
                break;

            case LEXER_UNMATCHED:
                if (strlen($match) >= $this->min_length)
                    $words[] = $match;
                break;

            // Exiting the variable reference
            case LEXER_EXIT:
                if (count($words) > 0)
                    $this->output .= '+"' . implode(' ', $words) . '"';
                break;
      }

      return TRUE;
    }

    static function parseFulltextBoolean ($search, $add_wildcard = FALSE) {
        include_once 'simpletest_parser.php';

        if (preg_match("/[\+\-][\b\"]/", $search))
            return $search;

        $parser = new MysqlFulltextSimpleParser();
        if ($add_wildcard) {
            $parser->add_wildcard = TRUE;
        }
        $lexer = new SimpleLexer($parser);
        $lexer->addPattern("\\s+");
        $lexer->addEntryPattern('"', 'accept', 'writeQuoted');
        $lexer->addPattern("\\s+", 'writeQuoted');
        $lexer->addExitPattern('"', 'writeQuoted');

        // do it
        $lexer->parse($search);

        return isset($parser->output) ? $parser->output : '';
    }
}

// setup Doctrine\Search
// use Doctrine\Common\Annotations\AnnotationRegistry;

abstract class BaseApplication
{
    protected $entityManager;

    public static function setupDoctrine($container) {
        // setup Doctrine\ORM
        $isDevMode = true;
        $paths = array('Entity');

        // simple setup
        $config = \Doctrine\ORM\Tools\Setup::createAnnotationMetadataConfiguration($paths, $isDevMode);

        $reader = new \Doctrine\Common\Annotations\CachedReader(
                    new \Doctrine\Common\Annotations\IndexedReader(
                        new \Doctrine\Common\Annotations\AnnotationReader()
                    ),
                    new \Doctrine\Common\Cache\ArrayCache()
                  );

        $annotationDriver = new \Doctrine\ORM\Mapping\Driver\AnnotationDriver(
                                $reader, $paths);


        // create a driver chain for metadata reading
        $driverChain = new \Doctrine\ORM\Mapping\Driver\DriverChain();
        // load superclass metadata mapping only, into driver chain
        // also registers Gedmo annotations.NOTE: you can personalize it
        \Gedmo\DoctrineExtensions::registerAbstractMappingIntoDriverChainORM(
            $driverChain, // our metadata driver chain, to hook into
            $reader // our cached annotation reader
        );
        $driverChain->addDriver($annotationDriver, 'Entity');

        $config->setMetadataDriverImpl($annotationDriver);


        $config->addCustomStringFunction('IFNULL', 'DoctrineExtensions\Query\Mysql\IfNull');
        $config->addCustomNumericFunction('MATCH', 'DoctrineExtensions\Query\Mysql\MatchAgainst');

        $config->addCustomStringFunction('date_format', 'Mapado\MysqlDoctrineFunctions\DQL\MysqlDateFormat');

        $dbParams = $container->hasParameter('dbal')
            ? $container->getParameter('dbal') : array();
        if (isset($dbParams['path'])) {
            $dbParams['path'] = $container->getParameterBag()->resolveValue($dbParams['path']);
        }

        // create event manager and hook preferred extension listeners
        $evm = new \Doctrine\Common\EventManager();

		// timestampable
        $timestampableListener = new \Gedmo\Timestampable\TimestampableListener;
        $timestampableListener->setAnnotationReader($reader);
        $evm->addEventSubscriber($timestampableListener);

        $entityManager = \Doctrine\ORM\EntityManager::create($dbParams, $config, $evm);
        return $entityManager;
    }

    protected function setupContainer() {
        $base_dir = realpath(__DIR__ . '/..'); // assume we are one below the project-base
        $container = new ContainerBuilder();

        // read the service configuration
        $loader = new YamlFileLoader($container, new FileLocator($base_dir . '/resources/config/'));
        $loader->load('services.yml');

        if (!$container->hasParameter('base_path')) {
            // if not set already
            $container->setParameter('base_path', $base_dir); // so we can use relative paths for logging and stuff
        }


        $container->set('doctrine', $this->setupDoctrine($container));

        return $container;
    }

}
