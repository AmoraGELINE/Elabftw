<?php
/**
 * experiments.php
 *
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Elabftw;

use Exception;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entry point for all experiment stuff
 *
 */
require_once 'app/init.inc.php';
$App->pageTitle = ngettext('Experiment', 'Experiments', 2);
$Response = new Response();
$Response->prepare($Request);

try {
    $Entity = new Experiments($App->Users);
    $EntityView = new ExperimentsView($Entity);

    $Status = new Status($App->Users);
    $categoryArr = $Status->readAll();

    // VIEW
    if ($Request->query->get('mode') === 'view') {
        $Entity->setId((int) $Request->query->get('id'));
        $Entity->canOrExplode('read');

        // LINKS
        $linksArr = $Entity->Links->readAll();

        // STEPS
        $stepsArr = $Entity->Steps->readAll();

        // COMMENTS
        $commentsArr = $Entity->Comments->readAll();

        // UPLOADS
        $UploadsView = new UploadsView($Entity->Uploads);

        // REVISIONS
        $Revisions = new Revisions($Entity);
        $revNum = $Revisions->readCount();

        $template = 'view.html';

        $renderArr = array(
            'Ev' => $EntityView,
            'Entity' => $Entity,
            'Uv' => $UploadsView,
            'linksArr' => $linksArr,
            'revNum' => $revNum,
            'stepsArr' => $stepsArr,
            'commentsArr' => $commentsArr,
            'mode' => 'view'
        );

    // EDIT
    } elseif ($Request->query->get('mode') === 'edit') {
        $Entity->setId((int) $Request->query->get('id'));
        // check permissions
        $Entity->canOrExplode('write');
        // a locked experiment cannot be edited
        if ($Entity->entityData['locked']) {
            throw new Exception(_('<strong>This item is locked.</strong> You cannot edit it.'));
        }

        // REVISIONS
        $Revisions = new Revisions($Entity);
        $revNum = $Revisions->readCount();

        // UPLOADS
        $UploadsView = new UploadsView($Entity->Uploads);

        // TEAM GROUPS
        $TeamGroups = new TeamGroups($Entity->Users);
        $visibilityArr = $TeamGroups->getVisibilityList();

        // LINKS
        $linksArr = $Entity->Links->readAll();

        // STEPS
        $stepsArr = $Entity->Steps->readAll();

        $template = 'edit.html';

        $renderArr = array(
            'Entity' => $Entity,
            'Uv' => $UploadsView,
            'categoryArr' => $categoryArr,
            'lang' => Tools::getCalendarLang($App->Users->userData['lang']),
            'linksArr' => $linksArr,
            'maxUploadSize' => Tools::getMaxUploadSize(),
            'mode' => 'edit',
            'revNum' => $revNum,
            'stepsArr' => $stepsArr,
            'visibilityArr' => $visibilityArr
        );

    // DEFAULT MODE IS SHOW
    } else {
        $searchType = '';
        $tag = '';
        $query = '';
        $getTags = false;

        // CATEGORY FILTER
        if (Tools::checkId((int) $Request->query->get('cat')) !== false) {
            $Entity->categoryFilter = " AND status.id = " . $Request->query->get('cat');
            $searchType = 'filter';
        }
        // TAG FILTER
        if ($Request->query->get('tag') != '') {
            $tag = filter_var($Request->query->get('tag'), FILTER_SANITIZE_STRING);
            $Entity->tagFilter = " AND tags.tag LIKE '" . $tag . "'";
            $searchType = 'tag';
            $getTags = true;
        }
        // QUERY FILTER
        if ($Request->query->get('q') != '') {
            $query = filter_var($Request->query->get('q'), FILTER_SANITIZE_STRING);
            $Entity->queryFilter = " AND (
                title LIKE '%$query%' OR
                date LIKE '%$query%' OR
                body LIKE '%$query%' OR
                elabid LIKE '%$query%'
            )";
            $searchType = 'query';
        }
        // ORDER
        $order = '';

        // load the pref from the user
        if (isset($Entity->Users->userData['orderby'])) {
            $order = $Entity->Users->userData['orderby'];
        }

        // now get pref from the filter-order-sort menu
        if ($Request->query->has('order') && !empty($Request->query->get('order'))) {
            $order = $Request->query->get('order');
        }

        if ($order === 'cat') {
            $Entity->order = 'status.id';
        } elseif ($order === 'date' || $order === 'rating' || $order === 'title') {
            $Entity->order = 'experiments.' . $order;
        } elseif ($order === 'comment') {
            $Entity->order = 'experiments_comments.recent_comment';
        }

        // SORT
        $sort = '';

        // load the pref from the user
        if (isset($Entity->Users->userData['sort'])) {
            $sort = $Entity->Users->userData['sort'];
        }

        // now get pref from the filter-order-sort menu
        if ($Request->query->has('sort') && !empty($Request->query->get('sort'))) {
            $sort = $Request->query->get('sort');
        }

        if ($sort === 'asc' || $sort === 'desc') {
            $Entity->sort = $sort;
        }

        // PAGINATION
        $limit = $App->Users->userData['limit_nb'] ?? 15;
        if ($Request->query->has('limit') && Tools::checkId((int) $Request->query->get('limit')) !== false) {
            $limit = $Request->query->get('limit');
        }

        $offset = 0;
        if ($Request->query->has('offset') && Tools::checkId((int) $Request->query->get('offset')) !== false) {
            $offset = $Request->query->get('offset');
        }

        $Entity->setOffset($offset);
        $Entity->setLimit($limit);
        // END PAGINATION

        $TeamGroups = new TeamGroups($Entity->Users);
        $visibilityArr = $TeamGroups->getVisibilityList();

        $Templates = new Templates($Entity->Users);
        $templatesArr = $Templates->readAll();

        // READ ALL ITEMS
        if ($App->Session->get('anon')) {
            $Entity->visibilityFilter = "AND experiments.visibility = 'public'";
            $itemsArr = $Entity->read();

        // related filter
        } elseif (Tools::checkId((int) $Request->query->get('related')) !== false) {
            $searchType = 'related';
            $itemsArr = $Entity->readRelated($Request->query->get('related'));

        } else {
            // filter by user only if we are not making a search
            if (!$Entity->Users->userData['show_team'] && ($searchType === '' || $searchType === 'filter')) {
                $Entity->setUseridFilter();
            }

            $itemsArr = $Entity->read($getTags);
        }

        $template = 'show.html';

        $renderArr = array(
            'Entity' => $Entity,
            'categoryArr' => $categoryArr,
            'itemsArr' => $itemsArr,
            'offset' => $offset,
            'query' => $query,
            'searchType' => $searchType,
            'tag' => $tag,
            'templatesArr' => $templatesArr,
            'visibilityArr' => $visibilityArr
        );
    }
} catch (InvalidArgumentException $e) {
    $template = 'error.html';
    $renderArr = array('error' => $e->getMessage());
} catch (Exception $e) {
    $message = $e->getMessage();
    if ($App->Config->configArr['debug']) {
        $message .= ' in ' . $e->getFile() . ' (line ' . $e->getLine() . ')';
    }
    $template = 'error.html';
    $renderArr = array('error' => $message);
}

$Response->setContent($App->render($template, $renderArr));
$Response->send();
