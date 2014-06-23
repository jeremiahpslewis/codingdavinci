<?php

namespace WebApplication\Controller;

use Silex\Application as BaseApplication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StatisticsController
{
    private function addWordCount (&$words, $text, $stemmer = NULL, $split = TRUE) {
      // see http://stackoverflow.com/questions/790596/split-a-text-into-single-words
        $tokens = $split ? preg_split('/((^\p{P}+)|(\p{P}*\s+\p{P}*)|(\p{P}+$))/u', $text, -1, PREG_SPLIT_NO_EMPTY) : array($text);

        foreach ($tokens as $match) {
            $match = mb_strtolower($match, 'UTF-8');
            if (isset($stemmer))
                $match = $stemmer->apply($match);
            if (!isset($words[$match])) {
                $words[$match] = 0;
            }
            $words[$match]++;
        }
    }

    function cmpPercentage ($a, $b) {
        if ($a['percentage'] == $b['percentage']) {
            return 0;
        }
        return ($a['percentage'] > $b['percentage']) ? -1 : 1;
    }

    public function introAction(Request $request, BaseApplication $app)
    {
        // display the intro
        return $app['twig']->render('statistics.intro.twig');
    }

    public function yearAction(Request $request, BaseApplication $app)
    {
        // display the authors by birth-year, the publications by issued-year
        $em = $app['doctrine'];
        $dbconn = $em->getConnection();
        $querystr = "SELECT 'active' AS type, COUNT(*) AS how_many FROM Person"
                  . " WHERE status >= 0 AND date_of_birth IS NOT NULL"
                  // . "  AND sex IS NOT NULL"
                  ;
        $querystr .= " UNION SELECT 'total' AS type, COUNT(*) AS how_many"
                   . " FROM Person WHERE status >= 0";
        $stmt = $dbconn->query($querystr);
        $subtitle_parts = array();
        while ($row = $stmt->fetch()) {
          if ('active' == $row['type']) {
            $total_active = $row['how_many'];
          }
          $subtitle_parts[] = $row['how_many'];
        }
        $subtitle = implode(' von ', $subtitle_parts) . ' Personen';

        $data = array();
        $max_year = $min_year = 0;
        foreach (array('birth', 'death') as $key) {
            $date_field = 'date_of_' . $key;
            $querystr = 'SELECT YEAR(' . $date_field . ') AS year'
                      // . ', sex'
                      . ', COUNT(*) AS how_many'
                      . ' FROM Person WHERE status >= 0 AND ' . $date_field . ' IS NOT NULL'
                      // . ' AND sex IS NOT NULL'
                      . ' GROUP BY YEAR(' . $date_field. ')'
                      // . ', sex'
                      . ' ORDER BY YEAR(' . $date_field . ')'
                      //. ', sex'
                      ;
            $stmt = $dbconn->query($querystr);


            while ($row = $stmt->fetch()) {
                if (0 == $min_year || $row['year'] < $min_year) {
                    $min_year = $row['year'];
                }
                if ($row['year'] > $max_year) {
                    $max_year = $row['year'];
                }
                if (!isset($data[$row['year']])) {
                    $data[$row['year']] = array();
                }
                $data[$row['year']][$key] = $row['how_many'];
            }

        }
        if ($min_year < 1830) {
            $min_year = 1830;
        }
        if ($max_year > 2000) {
            $max_year = 2000;
        }

        $total_works = 0;
        /*
        $querystr = 'SELECT YEAR(date_of_birth) AS year, COUNT(DISTINCT Publication.id) AS how_many FROM Person LEFT OUTER JOIN PublicationPerson ON PublicationPerson.person_id=Person.id LEFT OUTER JOIN Publication ON Publication.id=PublicationPerson.publication_id AND Publication.status >= 0 WHERE Person.status >= 0 AND date_of_birth IS NOT NULL'
                  // . ' AND sex IS NOT NULL'
                  . ' GROUP BY YEAR(date_of_birth) ORDER BY YEAR(date_of_birth)';
        $stmt = $dbconn->query($querystr);

        while ($row = $stmt->fetch()) {
            $total_works += $row['how_many'];
            $data[$row['year']]['works'] = $row['how_many'];
        }
        // var_dump(1.0 * $total_works / $total_active);
        */

        $querystr = 'SELECT PublicationPerson.publication_ord AS year, Publication.complete_works = 0 AS base, COUNT(DISTINCT Publication.id) AS how_many FROM Person LEFT OUTER JOIN PublicationPerson ON PublicationPerson.person_id=Person.id LEFT OUTER JOIN Publication ON Publication.id=PublicationPerson.publication_id AND Publication.status >= 0 WHERE Person.status >= 0 AND PublicationPerson.publication_ord IS NOT NULL'
                  // . ' AND sex IS NOT NULL'
                  . ' GROUP BY PublicationPerson.publication_ord, Publication.complete_works = 0'
                  . ' ORDER BY PublicationPerson.publication_ord, Publication.complete_works = 0';
        $stmt = $dbconn->query($querystr);
        while ($row = $stmt->fetch()) {
            $total_works += $row['how_many'];
            $key = $row['base'] ? 'works_issued_base' : 'works_issued_extended';
            $data[$row['year']][$key] = $row['how_many'];
        }

        $categories = array();
        for ($year = $min_year; $year <= $max_year; $year++) {
            $categories[] = 0 == $year % 5 ? $year : '';
            foreach (array('birth', 'death',
                           /* 'works', */ 'works_issued_base', 'works_issued_extended')
                     as $key) {
                $total[$key][$year] = isset($data[$year][$key])
                    ? intval($data[$year][$key]) : 0;
            }
        }

        return $app['twig']->render('statistics.year.twig',
                                    array('subtitle' => json_encode($subtitle),
                                          'categories' => json_encode($categories),
                                          'person_birth' => json_encode(array_values($total['birth'])),
                                          'person_death' => json_encode(array_values($total['death'])),
                                          'works_base' => json_encode(array_values($total['works_issued_base'])),
                                          'works_extended' => json_encode(array_values($total['works_issued_extended'])),
                                          )
                                    );
    }

    public function wordCountAction(Request $request, BaseApplication $app)
    {
        // display the most often words in title
        $em = $app['doctrine'];
        $dbconn = $em->getConnection();

        // preset custom stopwords
        $stopwords = array('roman' => true,
                           'gesammelte' => true, 'werke' => true,
                           'schriften' => true, 'ausgewählte' => true,
                           'the' => true, 'and' => true,
                           'van' => true,
                           );

        $words = file($app['base_path'] . '/resources/data/stopwords_de.txt',
                      FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($words as $word) {
            $stopwords[$word] = true;
        }

        $fields = array('title', 'other_title_information');
        $querystr = "SELECT issued, " . implode(', ', $fields) . " FROM Publication"
                  . " WHERE Publication.status >= 0"
            ;
        $stmt = $dbconn->query($querystr);

        $stemmer = new TrivialStemmer();

        $group_by_date = true;
        $words = array('total' => array());
        while ($row = $stmt->fetch()) {
            foreach ($fields as $field) {
                if (!empty($row[$field])) {
                    $this->addWordCount($words['total'], $row[$field], $stemmer);
                    if ($group_by_date) {
                        $cat = 'rest';
                        if (isset($row['issued'])) {
                            if ($row['issued'] >= 1900 && $row['issued'] < 1910)
                                $cat = '00er';
                            else if ($row['issued'] >= 1910 && $row['issued'] < 1920)
                                $cat = '10er';
                            else if ($row['issued'] >= 1920 && $row['issued'] < 1930)
                                $cat = '20er';
                            else if ($row['issued'] >= 1930 && $row['issued'] < 1940)
                                $cat = '30er';
                            else if ($row['issued'] >= 1940 && $row['issued'] <= 1945)
                                $cat = '40er';
                        }
                        $this->addWordCount($words[$cat], $row[$field], $stemmer);
                    }
                }
            }
        }

        arsort($words['total']);
        $types = array_keys($words);

        $categories = $total = array();
        foreach ($words['total'] as $word => $count) {
            if (count($categories) > 60) {
                // 60 terms
                break;
            }
            if (isset($stopwords[$word]) && $word) {
                continue;
            }
            if (mb_strlen($word, 'UTF-8') <= 2) {
                // skip short works
                continue;
            }
            $categories[] = $word;
            $total['total'][] = $count;
            if ($group_by_date) {
                foreach ($types as $type) {
                    if ('total' == $type) {
                        continue;
                    }
                    if (!isset($total[$type])) {
                        $total[$type] = array();
                    }
                    $total[$type][] = isset($words[$type][$word]) ? $words[$type][$word] : 0;
                }
            }
        }
        $subtitle = $group_by_date ? 'nach Jahrzehnten' : '';

        $render_values = array('subtitle' => json_encode($subtitle),
                               'categories' => json_encode($categories),
                               );
        if ($group_by_date) {
            foreach ($types as $type) {
                if ('total' == $type) {
                    continue;
                }
                $render_values['word_' . $type] =
                    json_encode(array_values($total[$type]));
            }
        }
        else {
            $render_values['word_total'] = json_encode(array_values($total['total']));
        }


        return $app['twig']->render('statistics.wordcount.twig',
                                    $render_values
                                    );

    }

    public function placeCountAction(Request $request, BaseApplication $app)
    {
        // display the most often death-places
        $em = $app['doctrine'];
        $dbconn = $em->getConnection();

        // preset custom stopwords
        $stopwords = array(
                           );

        $words = array();
        foreach ($words as $word) {
            $stopwords[$word] = true;
        }

        $keys = array('birth', 'death');
        $words = array('total' => array(), 'birth_rest' => array(), 'death_rest' => array());
        foreach ($keys as $key) {
            $fields = array('place_of_' . $key);
            $querystr = "SELECT YEAR(date_of_" . $key . ") AS issued, "
                      . implode(', ', $fields) . " FROM Person"
                      . " WHERE Person.status >= 0"
                ;
            $stmt = $dbconn->query($querystr);

            $stemmer = new TrivialStemmer();

            $group_by_date = true;
            while ($row = $stmt->fetch()) {
                foreach ($fields as $field) {
                    if (!empty($row[$field])) {
                        $this->addWordCount($words['total'], $row[$field], $stemmer, false);
                        if ($group_by_date) {
                            $cat = $key . '_rest';
                            if (isset($row['issued'])) {
                                if ('birth' == $key) {
                                    if ($row['issued'] < 1890)
                                        $cat = $key . '_1890';
                                    else if ($row['issued'] >= 1890)
                                        $cat = $key . '1890_';
                                }
                                else {
                                    if ($row['issued'] < 1933)
                                        $cat = $key . '_33';
                                    else if ($row['issued'] >= 1933 && $row['issued'] <= 1945)
                                        $cat = $key . '33_45';
                                    else if ($row['issued'] > 1945)
                                        $cat = $key . '45_';
                                }
                            }
                            $this->addWordCount($words[$cat], $row[$field], $stemmer, false);
                        }
                    }
                }
            }

        }

        arsort($words['total']);
        $types = array_keys($words);

        $categories = $total = array();
        foreach ($words['total'] as $word => $count) {
            if (count($categories) > 60) {
                // 60 places
                break;
            }
            if (isset($stopwords[$word]) && $word) {
                continue;
            }
            if (mb_strlen($word, 'UTF-8') <= 2) {
                // skip short works
                continue;
            }
            $categories[] = $word;
            $total['total'][] = $count;
            if ($group_by_date) {
                foreach ($types as $type) {
                    if ('total' == $type) {
                        continue;
                    }
                    if (!isset($total[$type])) {
                        $total[$type] = array();
                    }
                    $total[$type][] = isset($words[$type][$word]) ? $words[$type][$word] : 0;
                }
            }
        }
        $subtitle = $group_by_date ? 'nach Epochen' : '';

        $render_values = array('subtitle' => json_encode($subtitle),
                               'categories' => json_encode($categories),
                               );
        if ($group_by_date) {
            foreach ($types as $type) {
                if ('total' == $type) {
                    continue;
                }
                $render_values[$type] =
                    json_encode(array_values($total[$type]));
            }
        }
        else {
            $render_values['word_total'] = json_encode(array_values($total['total']));
        }

        return $app['twig']->render('statistics.placecount.twig',
                                    $render_values
                                    );

    }
}

class TrivialStemmer
{
    static $map = array(
                        //'stillleben' => 'stilleben'
                        );
    function apply ($word) {
        return isset(self::$map[$word]) ? self::$map[$word] : $word;
    }
}
