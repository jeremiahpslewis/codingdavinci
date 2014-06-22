<?php

namespace WebApplication\Controller;

use Silex\Application as BaseApplication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StatisticsController
{

    public function introAction(Request $request, BaseApplication $app)
    {
        // display the intro
        return $app['twig']->render('statistics.intro.twig');
    }

    public function birthYearAction(Request $request, BaseApplication $app)
    {
        // display the authors by birth-year
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

        $querystr = 'SELECT YEAR(date_of_birth) AS year'
                  // . ', sex'
                  . ', COUNT(*) AS how_many'
                  . ' FROM Person WHERE status >= 0 AND date_of_birth IS NOT NULL'
                  // . ' AND sex IS NOT NULL'
                  . ' GROUP BY YEAR(date_of_birth)'
                  // . ', sex'
                  . ' ORDER BY YEAR(date_of_birth)'
                  //. ', sex'
                  ;
        $stmt = $dbconn->query($querystr);

        $max_year = $min_year = 0;
        $data = array();

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
            $sex = 'A'; // $row['sex']
            $data[$row['year']][$sex] = $row['how_many'];
        }
        if ($min_year < 1800) {
            $min_year = 1800;
        }
        if ($max_year > 1945) {
            $max_year = 1945;
        }

        $querystr = 'SELECT YEAR(date_of_birth) AS year, COUNT(DISTINCT Publication.id) AS how_many FROM Person LEFT OUTER JOIN PublicationPerson ON PublicationPerson.person_id=Person.id LEFT OUTER JOIN Publication ON Publication.id=PublicationPerson.publication_id AND Publication.status >= 0 WHERE Person.status >= 0 AND date_of_birth IS NOT NULL'
                  // . ' AND sex IS NOT NULL'
                  . ' GROUP BY YEAR(date_of_birth) ORDER BY YEAR(date_of_birth)';
        $stmt = $dbconn->query($querystr);

        $total_works = 0;
        while ($row = $stmt->fetch()) {
            $total_works += $row['how_many'];
            $data[$row['year']]['works'] = $row['how_many'];
        }
        // var_dump(1.0 * $total_works / $total_active);

        $querystr = 'SELECT PublicationPerson.publication_ord AS year, COUNT(DISTINCT Publication.id) AS how_many FROM Person LEFT OUTER JOIN PublicationPerson ON PublicationPerson.person_id=Person.id LEFT OUTER JOIN Publication ON Publication.id=PublicationPerson.publication_id AND Publication.status >= 0 WHERE Person.status >= 0 AND PublicationPerson.publication_ord IS NOT NULL'
                  // . ' AND sex IS NOT NULL'
                  . ' GROUP BY PublicationPerson.publication_ord ORDER BY PublicationPerson.publication_ord';
        $stmt = $dbconn->query($querystr);
        while ($row = $stmt->fetch()) {
            $total_works += $row['how_many'];
            $data[$row['year']]['works_issued'] = $row['how_many'];
        }

        $categories = array();
        for ($year = $min_year; $year <= $max_year; $year++) {
            $categories[] = 0 == $year % 5 ? $year : '';
            foreach (array('A', /* 'M', 'F', */ 'works', 'works_issued') as $key) {
                $total[$key][$year] = isset($data[$year][$key]) ? intval($data[$year][$key]) : 0;
            }
        }

        return $app['twig']->render('statistics.birth.twig',
                                    array('subtitle' => json_encode($subtitle),
                                          'categories' => json_encode($categories),
                                          'Person_A' => json_encode(array_values($total['A'])),
                                          'Person_M' => json_encode(array()),
                                          'Person_F' => json_encode(array()),
                                          'works' => json_encode(array_values($total['works_issued'])),
                                          )
                                    );
    }
}
