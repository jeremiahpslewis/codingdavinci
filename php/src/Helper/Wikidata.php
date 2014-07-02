<?php

namespace Helper;


class Wikidata
{
	const BASE_URL = 'http://wdq.wmflabs.org/api';

	static $KEY_MAP = array(19 => 'placeOfBirth',
							20 => 'placeOfDeath',
							21 => 'sex',
							214 => 'viaf',
							244 => 'lc_naf',
							569 => 'dateOfBirth',
							570 => 'dateOfDeath',
						   );

	static $LABELS = array();

	private static function getItemLabel($id, $lang_preferred) {
		// only query once
		if (array_key_exists($lang_preferred, self::$LABELS)
			&& array_key_exists($id, self::$LABELS[$lang_preferred]))
		{
			return self::$LABELS[$lang_preferred][$id];
		}

		// labels not working, so go through mediawiki-api
		$id_full = 'Q' . $id;
		$url = sprintf('https://www.wikidata.org/w/api.php?action=wbgetentities&ids=%s&props=labels&format=json&languages=%s&languagefallback=' ,
					   $id_full, $lang_preferred);

		$data = json_decode(file_get_contents($url), TRUE);
		if (isset($data['entities']) && isset($data['entities'][$id_full])
			&& !empty($data['entities'][$id_full]['labels']))
		{
			foreach ($data['entities'][$id_full]['labels'] as $lang => $label) {
				if ($lang_preferred == $lang) {
					if (!array_key_exists($lang_preferred, self::$LABELS)) {
						self::$LABELS[$lang_preferred] = array();
					}
					return self::$LABELS[$lang_preferred][$id] = $label['value'];
				}
			}
		}
	}

	private static function normalizePropertyValue ($value, $type, $lang) {
		switch ($type) {
			case 'item':
				return self::getItemLabel($value, $lang);
				break;
			case 'time':
				// we currently handle only dates greater than 0
				if (preg_match('/([0-9]+\-[0-9]+\-[0-9]+)T/', $value, $matches)) {
					return preg_replace('/^0+/', '', $matches[1]); // trim leading 0es
				}
				die('TODO: handle time ' . $value);
				break;
			default:
				return $value;
		}
	}

	private static function getPropertyByIdentifier ($data, $identifier, $property, $lang, $unresolved = false) {
		$properties = $data['props'][$property];
		if (empty($properties)) {
			return;
		}
		foreach ($properties as $property_entry) {
			if ($property_entry[0] == $identifier) {
				return $unresolved || in_array($property, array(21)) // leave certain labels unresolved
					? $property_entry[2]
					: self::normalizePropertyValue($property_entry[2], $property_entry[1], $lang);
			}
		}
	}


	private static function getItems ($q, $properties, $lang) {
		// see http://wdq.wmflabs.org/api_documentation.html
		// and https://www.penflip.com/nichtich/normdaten-in-wikidata/blob/master/wikidata-weiternutzen.txt

		// labels are currently not active
		// https://bitbucket.org/magnusmanske/wikidataquery/issue/1/labels-not-working-question
        $url = self::BASE_URL
		     . sprintf('?q=%s&props=%s&labels=%s',
					   $q, implode(',', $properties), $lang);
// var_dump($url);
		$data = json_decode(file_get_contents($url), TRUE);

		if (FALSE === $data || empty($data['items'])) {
			return;
		}

		return $data;
	}

    static function fetchByGnd ($gnd, $properties = array(
														  569, // date of birth
														  19, // place of birth
														  570, // date of death
														  20, // place of death
														  21, // sex or gender
														  214, // Viaf-identifier
														  244, // LCCN identifier
														  646, // Freebase identifier
														  ),
								$lang = 'de', $options = array()
								)
	{
		$q = sprintf('string[227:%%22%s%%22]', $gnd); // gnd is 227
		$data = self::getItems($q, $properties, $lang);
		if (!isset($data)) {
			return;
		}

        foreach ($data['items'] as $identifier) {
	        $entity = new Wikidata();
			$entity->identifier = $identifier;
			$entity->gnd = $gnd;

			foreach ($properties as $property_key) {
				$property = self::getPropertyByIdentifier($data, $identifier, $property_key, $lang);
				if (!empty($property) && array_key_exists($property_key, self::$KEY_MAP)) {
					switch ($property_key) {
						case 21: // sex or gender
							switch ($property) {
								case 6581072:
									$entity->{self::$KEY_MAP[$property_key]} = 'F';
									break;
								case 6581097:
									$entity->{self::$KEY_MAP[$property_key]} = 'M';
									break;
								default:
									die('TODO: handle P21: ' . $property);
							}
							break;
						case 19:
						case 20:
							$item = self::getPropertyByIdentifier($data, $identifier, $property_key, $lang, true);
							$place_query = 'items[' . $item . ']';
							$place_data = self::getItems($place_query, array(227), $lang);
							$gnd = self::getPropertyByIdentifier($place_data, $item, 227, $lang, true);
							if (!empty($gnd)) {
								$entity->{'gnd' . ucfirst(self::$KEY_MAP[$property_key])} = $gnd;
							}
							// fall through
						default:
							$entity->{self::$KEY_MAP[$property_key]} = $property;
							break;
					}
				}
			}
			break; // we currently take only first macht
        }
        return $entity;
    }

	var $identifier = NULL;
    var $gnd;
	var $viaf = NULL;
	var $lc_naf = NULL;
    var $preferredName;
	var $sex;
    var $academicTitle;
    var $dateOfBirth;
    var $placeOfBirth;
    var $placeOfResidence;
    var $dateOfDeath;
    var $placeOfDeath;
}
