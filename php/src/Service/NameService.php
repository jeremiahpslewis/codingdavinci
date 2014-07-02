<?php

namespace Service;

require_once __DIR__ . '/../Helper/fullnameparser.php';

class NameService
{
    protected static $GENDER_MAP = array('female' => 'female',
                                         'male' => 'male');

    protected static $NAME_CACHE = array();

    /**
     * @var \Helper\Service\Gender\GenderProvider $gender_provider
     */
    protected $gender_provider;

    protected $fullNameParser;

    protected $cache_lookup = true; // TODO: set from options

    protected $confidence_level = 0.75;

    /**
     * Constructor
     *
     * @param \Helper\Service\Gender\GenderProviderInterface $gender_provider
     *
     * TODO: maybe add options
     *
     */
    public function __construct($gender_provider = null)
    {
        $this->gender_provider = $gender_provider;
        $this->fullNameParser = new \FullNameParser();
        // TODO: $this->humanNameParser = new \HumanNameParser_Parser();
    }

    public function splitFullname($fullname) {
        if (empty($fullname)) {
            return;
        }
        $parts = $this->fullNameParser->split_full_name($fullname);

        $given_parts = array($parts['fname']);
        if (!empty($parts['initials'])) {
            $given_parts[] = $parts['initials'];
        }

        $family_parts = array($parts['lname']);
        if (false !== $parts['suffix'] && !empty($parts['suffix'])) {
            $family_parts[] = $parts['suffix'];
        }

        return array(
                     'given' => implode(' ', $given_parts),
                     'family' => implode(' ', $family_parts),
                     );

    }

    public function genderize($given, $family = null) {
        // for messages
        $fullname = $given;
        if (!empty($family)) {
            $fullname = $given . ' ' . $family;
        }

        $cache_key = null;
        if ($this->cache_lookup) {
            $cache_key = $given;
            if (isset($family)) {
                $cache_key .= ' ' . $family;
            }
            if (array_key_exists($cache_key, self::$NAME_CACHE)) {
                return self::$NAME_CACHE[$cache_key];
            }
        }

        $names = preg_split('/[\-\s]+/', $given);
        $how_many = count($names);
        if ($how_many > 1) {
            if ($names[$how_many - 1] == '') {
                // can happen if $given ends with ' -' - should be stripped in import
                $names = array_slice($names, 0, $how_many - 1);
            }
        }
        if ($how_many > 2) {
            if ($names[$how_many - 2] == 'Le' && $names[$how_many - 1] == 'Roy') {
                $names = array_slice($names, 0, $how_many - 2);
            }
        }
        // var_dump($names);
        $results = $this->gender_provider->guess(count($names) > 1 ? $names : $names[0]);
        if (!isset($results)) {
            return false;
        }

        $gender = null;

        if (count($names) == 1) {
            $name = $names[0];
            // simple case
            // TODO: proper logging
            if (array_key_exists($results->gender, self::$GENDER_MAP)) {
                if ($results->probability >= $this->confidence_level)
                {
                    $gender = $results->gender;
                }
                else {
                    // echo $name . ' was ambiguous: ' . $results->probability . ' (' . $fullname . ')' . "\n";
                }
            }
            else {
                // echo $given . ' could  not be genderized' . ' (' . $fullname . ')' . "\n";
            }
        }
        else {
            for ($i = 0; $i < count($results); $i++) {
                $result = $results[$i];

                $name = $result->name;
                if (array_key_exists($result->gender, self::$GENDER_MAP)
                    && $result->probability >= $this->confidence_level
                    && (!isset($gender) || $gender == $result->gender))
                {
                    $gender = $result->gender;
                }
                else if (isset($gender) && isset($result->gender)
                         && $gender != $result->gender && $result->probability > 0.5)
                {
                    if ($i > 0 && $name = 'Maria') {
                        ; // Eva Maria oder Jose Maria -> ignore Maria
                    }
                    else if ($i == count($results) - 1 && in_array($name, array('Le', 'Des', 'El'))) {
                        ;  // Le / Des / El should be part of lastname
                    }
                    else {
                        // TODO: conflicting
                        var_dump($results);
                        exit;

                    }
                }
                else {
                    // TODO: logging
                    // var_dump($result);
                }
            }

            if (!isset($gender)) {
                // var_dump($results);
                // die('TODO: handle');
            }

        }

        if (isset($gender) && array_key_exists($gender, self::$GENDER_MAP)) {
            if (isset($cache_key)) {
                self::$NAME_CACHE[$cache_key] = self::$GENDER_MAP[$gender];
            }
            return self::$GENDER_MAP[$gender];
        }
    }

}
