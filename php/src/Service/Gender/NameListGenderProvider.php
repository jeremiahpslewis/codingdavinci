<?php

namespace Service\Gender;

class NameListGenderProvider implements GenderProviderInterface
{
    static $NAME_LIST = array();

    private static function readList($fname) {
        $trimmed = file(realpath($fname), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (false === $trimmed) {
            return $false;
        }
        self::$NAME_LIST[$fname] = array();
        foreach ($trimmed as $line) {
            $parts = preg_split('/\s*\t\s*/', $line);
            if (count($parts) >= 2) {
                $name = $parts[0];
                $key = mb_strtolower($name, 'utf-8');

                $result = array('name' => $name, 'gender' => $parts[1]);
                if (count($parts) > 2 && $parts[2] !== '') {
                    $result['probability'] = $parts[2];
                }
                else {
                    $result['probability'] = 1.0;
                }
                self::$NAME_LIST[$fname][$key] = $result;
            }
        }
        return true;
    }

    protected $fname = null;

    /**
     * Constructor
     *
     * @param $fname
     */
    public function __construct($fname)
    {
        $this->fname = $fname;
        if (!isset(self::$NAME_LIST[$fname])) {
            $success = self::readList($fname);
            if (!$success) {
                throw new \Exception("Could not read " . $fname);
            }
        }
    }

    protected function getSingle($name, $country_id = null) {
        $key = mb_strtolower($name, 'utf-8');
        $result = array_key_exists($key, self::$NAME_LIST[$this->fname])
            ? self::$NAME_LIST[$this->fname][$key]
            : array('gender' => null);

        $result['name'] = $name;

        return json_decode(json_encode($result), FALSE); // quick & dirty conversion from array to object
    }

    public function guess($name, $country_id = null, $language_id = null) {
        if (empty($name)) {
            return;
        }

        if (is_array($name)) {
            $results = array();
            foreach ($name as $single_name) {
                $result = $this->getSingle($single_name);
                if (isset($result)) {
                    $results[] = $result;
                }
            }
            return $results;
        }

        return $this->getSingle($name, $country_id);
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'namelist-gender';
    }

}
