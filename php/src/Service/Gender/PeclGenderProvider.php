<?php

namespace Service\Gender;

class PeclGenderProvider implements GenderProviderInterface
{
    protected function getSingle($name, $country = null) {
        $gender = new \Gender\Gender();
        $gender_result = $gender->get(utf8_decode($name), $country);

        $result = null;
        switch ($gender_result) {
            case \Gender\Gender::IS_FEMALE:
                $result = array('name' => $name, 'gender' => 'female', 'probability' => 1.0);
                break;

            case \Gender\Gender::IS_MOSTLY_FEMALE:
                $result = array('name' => $name, 'gender' => 'female', 'probability' => 0.75);
                break;

            case \Gender\Gender::IS_MALE:
                $result = array('name' => $name, 'gender' => 'male', 'probability' => 1.0);
                break;

            case \Gender\Gender::IS_MOSTLY_MALE:
                $result = array('name' => $name, 'gender' => 'male', 'probability' => 0.75);
                break;

            case \Gender\Gender::IS_UNISEX_NAME:
                $result = array('name' => $name, 'gender' => 'unisex', 'probability' => 0.5);
                break;

            case \Gender\Gender::IS_A_COUPLE:
                $result = array('name' => $name, 'gender' => 'both', 'probability' => 0.5);
                break;

            case \Gender\Gender::NAME_NOT_FOUND:
                $result = array('name' => $name, 'gender' => null);
                break;
        }

        if (isset($result)) {
            $result = json_decode(json_encode($result), FALSE); // quick & dirty conversion from array to object
        }

        return $result;
    }

    public function guess($name, $country_id = null, $language_id = null) {
        if (empty($name)) {
            return;
        }

        $country = null;

        if (isset($country_id)) {
            switch ($country_id) {
                case 'FR':
                    $country = \Gender\Gender::FRANCE;
                    break;
                default:
                    throw new \Exception('TODO: map iso-country-code ' . $country_id . ' to Gender-constants');
            }
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

        return $this->getSingle($name, $country);
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'pecl-gender';
    }

}
