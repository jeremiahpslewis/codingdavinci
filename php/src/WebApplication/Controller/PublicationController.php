<?php

namespace WebApplication\Controller;

use Silex\Application as BaseApplication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicationController
{

    public function indexAction(Request $request, BaseApplication $app)
    {
        $em = $app['doctrine'];

        // we set filter/order into route-params
        $route_params = $request->attributes->get('_route_params');

        $searchwidget = new \Searchwidget\Searchwidget($request, $app['session'], array('routeName' => 'publication'));

        // build where
        $dql_where = array();

        $search = $searchwidget->getCurrentSearch();

        if (!empty($search)) {
          $fulltext_condition = \MysqlFulltextSimpleParser::parseFulltextBoolean($search, TRUE);
          $dql_where[] = "MATCH (P.author, P.editor, P.title, P.publicationStatement) AGAINST ('" . $fulltext_condition . "' BOOLEAN) = TRUE";
        }

        $searchwidget->addFilter('completeWorks', array('all' => 'Alle Titel',
                                                        'list' => 'Auf der Liste stehende Titel',
                                               ));
        $filters = $searchwidget->getActiveFilters();

        // build order
        $searchwidget->addSortBy('authorEditor', array('label' => 'Verfasser / Herausgeber'));
        $searchwidget->addSortBy('title', array('label' => 'Titel'));
        $searchwidget->addSortBy('issued', array('label' => 'Publikationsjahr'));
        $sort_by = $searchwidget->getCurrentSortBy();
        $orders = array('authorEditorSort' => 'ASC', 'titleSort' => 'ASC', 'P.issued' => 'ASC');
        if (false !== $sort_by) {
            $orders = array($sort_by[0] . 'Sort' => $sort_by[1]) + $orders;
        }
        // var_dump($orders);

        // Select your items.
        $dql = "SELECT P, IFNULL(P.author, IFNULL(P.editor, 'ZZ')) HIDDEN authorEditorSort"
             . ", IFNULL(P.issued, '9999') HIDDEN issuedSort, IFNULL(P.title, 'ZZ') HIDDEN titleSort"
             . " FROM Entities\Publication P";

        if (array_key_exists('completeWorks', $filters)
            && ('list' == $filters['completeWorks'])) {
            $dql_where[] = "0 = P.completeWorks";
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
        return $app['twig']->render('publication.index.twig',
                                    array(
                                          'pageTitle' => 'Publikationen',
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

        $entity = $em->getRepository('Entities\Publication')->findOneById($id);

        if (!isset($entity)) {
            $app->abort(404, "Publication $id does not exist.");
        }

        // find related list entry
        $list = null;
        $row = $entity->listRow;
        if (!empty($row)) {
            $list = $em->getRepository('Entities\BannedList')->findOneByRow($row);
        }

        return $app['twig']->render('publication.detail.twig',
                                    array(
                                          'entry' => $entity,
                                          'list' => $list,
                                          )
                                    );
    }

}
