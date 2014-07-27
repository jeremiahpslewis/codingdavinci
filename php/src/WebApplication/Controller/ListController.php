<?php

namespace WebApplication\Controller;

use Silex\Application as BaseApplication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ListController
{

    public function indexAction(Request $request, BaseApplication $app)
    {
        $em = $app['doctrine'];

        $searchwidget = new \Searchwidget\Searchwidget($request, $app['session'], array('routeName' => 'list'));
        // build where
        $dql_where = array();

        $search = $searchwidget->getCurrentSearch();

        if (!empty($search)) {
          $fulltext_condition = \MysqlFulltextSimpleParser::parseFulltextBoolean($search, TRUE);
          $dql_where[] = "MATCH (BL.authorLastname, BL.authorFirstname, BL.title) AGAINST ('" . $fulltext_condition . "' BOOLEAN) = TRUE";
        }

        // Select your items.
        // $dql = "SELECT BL, (case when BL.authorLastname is null then BL.title else BL.authorLastname end) HIDDEN authorTitleSort, (case when BL.pageNumberInOCRDocument = 0 then 1 else 0 end) HIDDEN ocrSort, (case when BL.additionalInfos LIKE '%Jahresliste 1941%' then 41 when BL.additionalInfos LIKE '%Jahresliste 1940%' then 40 when BL.additionalInfos LIKE '%Jahresliste 1939%' then 39  else 38 end) HIDDEN JL FROM Entities\BannedList BL ORDER BY JL, BL.pageNumberInOCRDocument, authorTitleSort, BL.authorFirstname, BL.firstEditionPublicationYear, BL.row";
        $dql = "SELECT BL, (case when BL.authorLastname is null then BL.title else BL.authorLastname end) HIDDEN authorTitleSort, (case when BL.pageNumberInOCRDocument = 0 then 1 else 0 end) HIDDEN ocrSort, (case when BL.additionalInfos LIKE '%Jahresliste 1941%' then 41 when BL.additionalInfos LIKE '%Jahresliste 1940%' then 40 when BL.additionalInfos LIKE '%Jahresliste 1939%' then 39 else 38 end) HIDDEN JL FROM Entities\BannedList BL";

        if (!empty($dql_where)) {
            $dql .= ' WHERE ' . implode(' AND ', $dql_where);
        }

        $dql .= " ORDER BY authorTitleSort, BL.authorFirstname, BL.firstEditionPublicationYear, BL.row";
        // Limit per page.
        $limit = 50;
        // See what page we're on from the query string.
        $page = $request->query->get("page", 1);
        // Determine our offset.
        $offset = ($page - 1) * $limit;
        // Create the query
        $query = $em->createQuery($dql);
        $query->setFirstResult($offset)->setMaxResults($limit);

        $adapter = new \Pagerfanta\Adapter\DoctrineORMAdapter($query);
        $pagerfanta = new \Pagerfanta\Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage($limit);
        $pagerfanta->setCurrentPage($page);

        // display the list
        return $app['twig']->render('list.index.twig',
                                    array(
                                          'pageTitle' => 'Liste',
                                          'searchwidget' => $searchwidget,
                                          'pager' => $pagerfanta,
                                          'entries' => $pagerfanta->getCurrentPageResults(),
                                          )
                                    );
    }

    public function detailAction(Request $request, BaseApplication $app)
    {
        $em = $app['doctrine'];

        $row = $request->get('row');

        $entity = $em->getRepository('Entities\BannedList')->findOneByRow($row);

        if (!isset($entity)) {
            $app->abort(404, "Row $row does not exist.");
        }

        // find related person
        $persons = $em->getRepository('Entities\Person')->findByListRow($entity->row);

        // find related publications
        $publications = $em->getRepository('Entities\Publication')->findByListRow($entity->row);

        // pageTitle
        $pageTitle_prepend = $entity->title;
        if (!empty($pageTitle_prepend)) {
            $pageTitle_prepend .= ' - ';
        }
        else {
            $pageTitle_prepend = '';
        }

        return $app['twig']->render('list.detail.twig',
                                    array(
                                          'pageTitle' => $pageTitle_prepend . 'Liste',
                                          'entry' => $entity,
                                          'persons' => $persons,
                                          'publications' => $publications,
                                          )
                                    );
    }

    public function editAction(Request $request, BaseApplication $app)
    {
        $edit_fields = array('title', 'authorLastname');

        $em = $app['doctrine'];

        $row = $request->get('row');

        $entity = $em->getRepository('Entities\BannedList')->findOneByRow($row);

        if (!isset($entity)) {
            $app->abort(404, "Row $row does not exist.");
        }

        $preset = array();
        foreach ($edit_fields as $key) {
            $preset[$key] = $entity->$key;
        }

        $form = $app['form.factory']->createBuilder('form', $preset)
            ->add('title', 'text',
                  array('label' => 'Titel',
                        'required' => false,
                        ))
            ->add('authorLastname', 'text',
                  array('label' => 'Nachname',
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
                return $app->redirect($app['url_generator']->generate('list-detail', array('row' => $row)));
            }
        }

        // var_dump($entity);
        return $app['twig']->render('list.edit.twig',
                                    array(
                                          'entry' => $entity,
                                          'form' => $form->createView(),
                                          )
                                    );
    }
}
