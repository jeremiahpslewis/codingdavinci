<?php

namespace WebApplication\Controller;

use Silex\Application as BaseApplication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PlaceController
{

    public function indexAction(Request $request, BaseApplication $app)
    {
        $em = $app['doctrine'];

        // we set filter/order into route-params
        $route_params = $request->attributes->get('_route_params');

        $searchwidget = new \Searchwidget\Searchwidget($request, $app['session'], array('routeName' => 'place'));

        // build where
        $dql_where = array();

        $search = $searchwidget->getCurrentSearch();

        if (!empty($search)) {
            $fulltext_condition = \MysqlFulltextSimpleParser::parseFulltextBoolean($search, TRUE);
            $dql_where[] = "MATCH (P.name) AGAINST ('" . $fulltext_condition . "' BOOLEAN) = TRUE";
        }

        /*
        $searchwidget->addFilter('place', array('all' => 'Alle Orte',
                                                 'missing' => 'Ohne Geonames',
                                                 ));
        */
        $filters = $searchwidget->getActiveFilters();

        // build order
        $searchwidget->addSortBy('name', array('label' => 'Name'));
        $searchwidget->addSortBy('country', array('label' => 'Land'));

        $sort_by = $searchwidget->getCurrentSortBy();
        $orders = array('nameSort' => 'ASC', 'C.germanName' => 'ASC');
        if (false !== $sort_by) {
            $orders = array($sort_by[0] . 'Sort' => $sort_by[1]) + $orders;
        }
        // var_dump($orders);

        // Select your items.
        $dql = "SELECT P, IFNULL(P.name, 'ZZ') HIDDEN nameSort, C.germanName HIDDEN countrySort, C FROM Entities\Place P JOIN P.country C";
        if (array_key_exists('place', $filters)) {
            if ('missing' == $filters['place']) {
                $dql_where[] = "P.geonames IS NULL";
            }
            else {
                $dql_where[] = "P.gnd IS NOT NULL"; // currently only places connected to Person
            }
        }
        else {
            $dql_where[] = "P.gnd IS NOT NULL"; // currently only places connected to Person
        }

        if (!empty($dql_where)) {
            $dql .= ' WHERE ' . implode(' AND ', $dql_where);
        }

        // Create the query
        if (!empty($orders)) {
            $dql .= ' ORDER BY '
                  . implode(', ',
                            array_map(
                                      function($field) use ($orders) {
                                        return $field . ' ' . $orders[$field];
                                      },
                                      array_keys($orders)
                                     ));
        }
        $query = $em->createQuery($dql);

        // Limit per page.
        $limit = 50;
        // See what page we're on from the query string.
        $page = $request->query->get('page', 1);
        // Determine our offset.
        $offset = ($page - 1) * $limit;
        $query->setFirstResult($offset)->setMaxResults($limit);

        // create a pager
        $adapter = new \Pagerfanta\Adapter\DoctrineORMAdapter($query);
        $pagerfanta = new \Pagerfanta\Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage($limit);
        $pagerfanta->setCurrentPage($page);

        // set them for paging
        $request->attributes->set('_route_params', $route_params);

        // display the list
        return $app['twig']->render('place.index.twig',
                                    array(
                                          'pageTitle' => 'Orte',
                                          'searchwidget' => $searchwidget,
                                          'pager' => $pagerfanta,
                                          'entries' => $pagerfanta->getCurrentPageResults(),
                                          )
                                    );
    }

    public function detailAction(Request $request, BaseApplication $app)
    {
        $em = $app['doctrine'];

        $id = $request->get('id');
        if (!isset($id)) {
            // check if we can get by gnd
            $gnd = $request->get('gnd');
            if (!empty($gnd)) {
                $qb = $em->createQueryBuilder();
                $qb->select(array('P.id'))
                    ->from('Entities\Place', 'P')
                    ->where($qb->expr()->like('P.gnd', ':gnd'))
                    ->setParameter('gnd', '%' . $gnd)
                    ->setMaxResults(1);
                $query = $qb->getQuery();
                $results = $query->getResult();
                foreach ($results as $result) {
                    $id = $result['id'];
                }
            }
        }

        $entity = $em->getRepository('Entities\Place')->findOneById($id);

        if (!isset($entity)) {
            $app->abort(404, "Place $id does not exist.");
        }

        $render_params = array('pageTitle' => 'Ort',
                               'entry' => $entity,
                               );

        $placesOfBirth = $placesOfDeath = null;
        $lifePaths = array();
        $placesGnds = array();
        if (preg_match('/d\-nb\.info\/gnd\/([0-9xX\-]+)/', $entity->gnd, $matches)) {
            $placesGnds[$entity->gnd] = true;
            $render_params['gnd'] = $matches[1];

            // find related Person entries
            $placesOfBirth = $em->getRepository('Entities\Person')->findBy(
                array('gndPlaceOfBirth' => $entity->gnd),
                array('surname' => 'ASC', 'forename' => 'ASC')
            );
            $placesOfDeath = $em->getRepository('Entities\Person')->findBy(
                array('gndPlaceOfDeath' => $entity->gnd),
                array('surname' => 'ASC', 'forename' => 'ASC')
            );
            if (isset($placesOfBirth)) {
                foreach ($placesOfBirth as $person) {
                    $gndPlaceOfDeath = $person->gndPlaceOfDeath;
                    if (!empty($gndPlaceOfDeath)) {
                        $placesGnds[$gndPlaceOfDeath] = true;
                        $lifePaths[$person->id] = array($entity->gnd, $gndPlaceOfDeath);
                    }
                }
                foreach ($placesOfDeath as $person) {
                    $gndPlaceOfBirth = $person->gndPlaceOfBirth;
                    if (!empty($gndPlaceOfBirth)) {
                        $placesGnds[$gndPlaceOfBirth] = true;
                        $lifePaths[$person->id] = array($gndPlaceOfBirth, $entity->gnd);
                    }
                }
            }
        }
        $render_params['placesOfBirth'] = $placesOfBirth;
        $render_params['placesOfDeath'] = $placesOfDeath;

        $map = null;
        $places = array();
        $min_latitude = $min_longitude = 1000;
        $max_longitude = $max_latitude = -1000;
        if (count($placesGnds) > 1) {
            $qb = $em->createQueryBuilder();
            $qb->select(array('P.id, P.name, P.gnd, P.latitude, P.longitude'))
               ->from('Entities\Place', 'P')
               ->where('P.longitude IS NOT NULL AND P.latitude IS NOT NULL')
               ->andWhere('P.gnd IN (:gnds)')
               ->setParameter('gnds', array_keys($placesGnds));

            $query = $qb->getQuery();
            $results = $query->getResult();
            $markers = $polylines = '';
            foreach ($results as $place) {
                $places[$place['gnd']] = $place;
                $markers .= 'L.marker(['
                          . $place['latitude']
                          . ', '
                          . $place['longitude'] . ']).addTo(map)'
                          . '.bindPopup('
                          . json_encode('<b><a href="' . $app['url_generator']->generate('place-detail', array('id' => $place['id'])) . '">'
                                        . $place['name'] . '</a></b>')
                          . ');';
                if ($place['latitude'] > $max_latitude) {
                    $max_latitude = $place['latitude'];
                }
                if ($place['latitude'] < $min_latitude) {
                    $min_latitude = $place['latitude'];
                }
                if ($place['longitude'] > $max_longitude) {
                    $max_longitude = $place['longitude'];
                }
                if ($place['longitude'] < $min_longitude) {
                    $min_longitude = $place['longitude'];
                }
            }
            foreach ($lifePaths as $person_id => $entry) {
                if ($entry[0] != $entry[1]
                    && isset($places[$entry[0]]) && isset($places[$entry[1]]))
                {
                    $from_latitude = $places[$entry[0]]['latitude'];
                    $from_longitude = $places[$entry[0]]['longitude'];
                    $to_latitude = $places[$entry[1]]['latitude'];
                    $to_longitude = $places[$entry[1]]['longitude'];

                    $color = $entity->gnd == $entry[0] ? '#d62728' : '#2ca02c';

                    $polylines .= <<<EOT
                    L.geodesicPolyline([[${from_latitude}, ${from_longitude}],
                               [${to_latitude}, ${to_longitude}]],
                               {color: '{$color}'}).addTo(map);
EOT;
                }
            }

            $map = '<h2>Lebenswege</h2>';
            $map .= '<div id="map" style="width:543px; height: 450px;"></div>';
            $map .= <<<EOT
	<script>
		var map = L.map('map');
        map.fitBounds([
            [${min_latitude}, ${min_longitude}],
            [${max_latitude}, ${max_longitude}]
        ]);
		L.tileLayer('https://{s}.tiles.mapbox.com/v3/{id}/{z}/{x}/{y}.png', {
			maxZoom: 18,
			attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, ' +
				'<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
				'Imagery &copy; <a href="http://mapbox.com">Mapbox</a>',
			id: 'examples.map-i86knfo3'
		}).addTo(map);

        ${markers}
        ${polylines}
	</script>
EOT;
        // var_dump($min_latitude . '<->' . $max_latitude . ' | ' . $min_longitude . '<->' . $max_longitude);

        }
        $render_params['map'] = $map;
        return $app['twig']->render('place.detail.twig', $render_params);
    }

    /*
    public function gndBeaconAction(Request $request, BaseApplication $app) {
        $em = $app['doctrine'];

        $ret = '#FORMAT: BEACON' . "\n" . '#PREFIX: http://d-nb.info/gnd/' . "\n";
        $ret .= sprintf('#TARGET: %s/gnd/{ID}',
                        $app['url_generator']->generate('place', array(), true))
              . "\n";
        $ret .= '#NAME: Verbrannte und Verbannte' . "\n";
        $ret .= '#MESSAGE: Eintrag in der Liste der im Nationalsozialismus verbotenen Publikationen und Autoren' . "\n";

        $dql = "SELECT DISTINCT P.id, P.gnd FROM Entities\Place P WHERE P.status >= 0 AND P.gnd IS NOT NULL ORDER BY P.gnd";
        $query = $em->createQuery($dql);
        // $query->setMaxResults(10);
        foreach ($query->getResult() as $result) {
            if (preg_match('/d\-nb\.info\/gnd\/([0-9xX]+)$/', $result['gnd'], $matches)) {
                $gnd_id = $matches[1];
                $ret .=  $gnd_id . "\n";
            }
        }

        return new Response($ret,
                            200,
                            array('Content-Type' => 'text/plain; charset=UTF-8')
                            );
    }
    */

    public function editAction(Request $request, BaseApplication $app)
    {
        $edit_fields = array('gnd', 'geonames', 'name');

        $em = $app['doctrine'];

        $id = $request->get('id');

        $entity = $em->getRepository('Entities\Place')->findOneById($id);

        if (!isset($entity)) {
            $app->abort(404, "Place $id does not exist.");
        }

        $preset = array();
        foreach ($edit_fields as $key) {
            $preset[$key] = $entity->$key;
        }

        $form = $app['form.factory']->createBuilder('form', $preset)
            ->add('name', 'text',
                  array('label' => 'Name',
                        'required' => true,
                        ))
            ->add('geonames', 'text',
                  array('label' => 'Geonames',
                        'required' => false,
                        ))
            ->add('gnd', 'text',
                  array('label' => 'GND',
                        'required' => false,
                        ))
            ->getForm();


        $form->handleRequest($request);
        if ($form->isValid()) {
            $data = $form->getData();
            $persist = false;
            foreach ($edit_fields as $key) {
                $value_before =  $entity->$key;
                if ($value_before !== $data[$key]) {
                    $persist = true;
                    $entity->$key = $data[$key];
                }
            }
            if ($persist) {
                $em->persist($entity);
                $em->flush();
                return $app->redirect($app['url_generator']->generate('place-detail', array('id' => $id)));
            }
        }

        // var_dump($entity);
        return $app['twig']->render('place.edit.twig',
                                    array(
                                          'entry' => $entity,
                                          'form' => $form->createView(),
                                          )
                                    );
    }

}
