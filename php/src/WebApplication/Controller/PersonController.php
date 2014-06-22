<?php

namespace WebApplication\Controller;

use Silex\Application as BaseApplication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PersonController
{

    public function indexAction(Request $request, BaseApplication $app)
    {
        $em = $app['doctrine'];

        // we set filter/order into route-params
        $route_params = $request->attributes->get('_route_params');

        $searchwidget = new \Searchwidget\Searchwidget($request, $app['session'], array('routeName' => 'person'));

        // build where
        $dql_where = array();

        $search = $searchwidget->getCurrentSearch();

        if (!empty($search)) {
          $fulltext_condition = \MysqlFulltextSimpleParser::parseFulltextBoolean($search, TRUE);
          $dql_where[] = "MATCH (P.surname, P.forename) AGAINST ('" . $fulltext_condition . "' BOOLEAN) = TRUE";
        }

        $searchwidget->addFilter('person', array('all' => 'Alle Personen',
                                                 'completeWorks' => 'Gesamtwerk verboten',
                                                 'today' => 'Heute Geburts- oder Todestag',
                                                 ));
        $filters = $searchwidget->getActiveFilters();

        // build order
        $searchwidget->addSortBy('name', array('label' => 'Name'));
        $searchwidget->addSortBy('dateOfBirth', array('label' => 'Geburtsdatum'));

        $sort_by = $searchwidget->getCurrentSortBy();
        $orders = array('nameSort' => 'ASC', 'P.forename' => 'ASC');
        if (false !== $sort_by) {
            $orders = array($sort_by[0] . 'Sort' => $sort_by[1]) + $orders;
        }
        // var_dump($orders);

        // Select your items.
        $dql = "SELECT P, IFNULL(P.dateOfBirth, '9999-99-99') HIDDEN dateOfBirthSort, IFNULL(P.surname, 'ZZ') HIDDEN nameSort FROM Entities\Person P";
        if (array_key_exists('person', $filters)) {

            if ('today' == $filters['person'] || preg_match('/^\d+\-\d+$/', $filters['person'])) {
                if ('today' == $filters['person']) {
                    $month_day = "DATE_FORMAT(CURRENT_DATE(),'%m-%d')";
                }
                else {
                    $month_day = sprintf("'%s'", $filters['person']);
                }
                $dql_where[] = "(DATE_FORMAT(P.dateOfBirth,'%m-%d') = $month_day
                                 OR DATE_FORMAT(P.dateOfDeath,'%m-%d') = $month_day)";
            }
            else if ('completeWorks' == $filters['person']) {
                $dql_where[] = "P.completeWorks <> 0";
            }

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
        return $app['twig']->render('person.index.twig',
                                    array(
                                          'pageTitle' => 'Personen',
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

        $entity = $em->getRepository('Entities\Person')->findOneById($id);

        if (!isset($entity)) {
            $app->abort(404, "Person $id does not exist.");
        }

        // find related list entry
        $list = null;
        $row = $entity->listRow;
        if (!empty($row)) {
            $list = $em->getRepository('Entities\BannedList')->findOneByRow($row);
        }

        $render_params = array('pageTitle' => 'Person',
                               'entry' => $entity,
                               'list' => $list,
                               );
        if (preg_match('/d\-nb\.info\/gnd\/([0-9xX]+)/', $entity->gnd, $matches)) {
            $render_params['gnd'] = $matches[1];
        }

        return $app['twig']->render('person.detail.twig', $render_params);
    }

    public function editAction(Request $request, BaseApplication $app)
    {
        $em = $app['doctrine'];

        $id = $request->get('id');

        $entity = $em->getRepository('Entities\Person')->findOneById($id);

        if (!isset($entity)) {
            $app->abort(404, "Person $id does not exist.");
        }

        return 'TODO: edit' . $id;
    }
}
