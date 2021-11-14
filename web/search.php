<?php
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
declare(strict_types=1);

namespace Elabftw\Elabftw;

use function count;
use Elabftw\Controllers\SearchController;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Models\Experiments;
use Elabftw\Models\Items;
use Elabftw\Models\ItemsTypes;
use Elabftw\Models\Status;
use Elabftw\Models\Tags;
use Elabftw\Models\TeamGroups;
use Elabftw\Services\AdvancedSearchQuery;
use Elabftw\Services\Check;
use Elabftw\Services\Filter;

/**
 * The search page
 * Here be dragons!
 *
 */
require_once 'app/init.inc.php';
$App->pageTitle = _('Search');

$Experiments = new Experiments($App->Users);
$Database = new Items($App->Users);
$Tags = new Tags($Experiments);
$tagsArr = $Tags->readAll();

$itemsTypesArr = (new ItemsTypes($App->Users))->read(new ContentParams('', 'all'));
$categoryArr = $statusArr = (new Status($App->Users->team))->readAll();
if ($Request->query->get('type') !== 'experiments') {
    $categoryArr = $itemsTypesArr;
}

// TEAM GROUPS
$TeamGroups = new TeamGroups($App->Users);
$teamGroupsArr = $TeamGroups->read(new ContentParams());
$visibilityArr = $TeamGroups->getVisibilityList();

$usersArr = $App->Users->readAllFromTeam();

// WHERE do we search?
if ($Request->query->get('type') === 'experiments') {
    $Entity = $Experiments;
} else {
    $Entity = $Database;
}

// TITLE
$title = '';
$titleError = false;
if ($Request->query->has('title') && !empty($Request->query->get('title'))) {
    $title = $Request->query->get('title');

    $advancedQuery = new AdvancedSearchQuery($title, 'title');
    $whereClause = $advancedQuery->getWhereClause();
    if ($whereClause) {
        $Entity->titleFilter = $whereClause['where'];
        $Entity->titleFilterBindValues = $whereClause['bindValues'];
    }

    $exception = $advancedQuery->getException();
    if ($exception) {
        $titleError = $exception;
    }
}

// BODY
$body = '';
$bodyError = false;
if ($Request->query->has('body') && !empty($Request->query->get('body'))) {
    $body = $Request->query->get('body');

    $advancedQuery = new AdvancedSearchQuery($body, 'body');
    $whereClause = $advancedQuery->getWhereClause();
    if ($whereClause) {
        $Entity->bodyFilter = $whereClause['where'];
        $Entity->bodyFilterBindValues = $whereClause['bindValues'];
    }

    $exception = $advancedQuery->getException();
    if ($exception) {
        $bodyError = $exception;
    }
}

// VISIBILITY
$vis = '';
if ($Request->query->has('vis') && !empty($Request->query->get('vis'))) {
    $vis = Check::visibility($Request->query->get('vis'));
}

// FROM
$from = '';
if ($Request->query->has('from') && !empty($Request->query->get('from'))) {
    $from = Filter::kdate($Request->query->get('from'));
}

// TO
$to = '';
if ($Request->query->has('to') && !empty($Request->query->get('to'))) {
    $to = Filter::kdate($Request->query->get('to'));
}

// RENDER THE FIRST PART OF THE PAGE (search form)
$renderArr = array(
    'Request' => $Request,
    'Experiments' => $Experiments,
    'Database' => $Database,
    'body' => $body,
    'bodyError' => $bodyError,
    'categoryArr' => $categoryArr,
    'itemsTypesArr' => $itemsTypesArr,
    'tagsArr' => $tagsArr,
    'teamGroupsArr' => $teamGroupsArr,
    'title' => $title,
    'titleError' => $titleError,
    'statusArr' => $statusArr,
    'usersArr' => $usersArr,
    'visibilityArr' => $visibilityArr,
);
echo $App->render('search.html', $renderArr);

/**
 * Here the search begins
 * If there is a search, there will be get parameters, so this is our main switch
 */
if ($Request->query->count() > 0 && !$bodyError && !$titleError) {

    // STATUS
    $status = '';
    if (Check::id((int) $Request->query->get('status')) !== false) {
        $status = $Request->query->get('status');
    }

    // RATING
    $rating = null;
    $allowedRatings = array('null', '1', '2', '3', '4', '5');
    if (in_array($Request->query->get('rating'), $allowedRatings, true)) {
        $rating = $Request->query->get('rating');
    }

    // PREPARE SQL query

    /////////////////////////////////////////////////////////////////
    if ($Request->query->has('type')) {
        // Tag search
        if (!empty($Request->query->get('tags'))) {
            // get all the ids with that tag
            $ids = $Entity->Tags->getIdFromTags($Request->query->get('tags'), (int) $App->Users->userData['team']);
            if (count($ids) > 0) {
                $Entity->idFilter = Tools::getIdFilterSql($ids);
            }
        }

        // Visibility search
        if (!empty($vis)) {
            $Entity->addFilter('entity.canread', $vis);
        }

        // Date search
        if (!empty($from) && !empty($to)) {
            $Entity->dateFilter = " AND entity.date BETWEEN '$from' AND '$to'";
        } elseif (!empty($from) && empty($to)) {
            $Entity->dateFilter = " AND entity.date BETWEEN '$from' AND '99991212'";
        } elseif (empty($from) && !empty($to)) {
            $Entity->dateFilter = " AND entity.date BETWEEN '00000101' AND '$to'";
        }

        // Rating search
        if (!empty($rating)) {
            // rating is whitelisted here
            $Entity->addFilter('entity.rating', $rating);
        }

        // Metadata search
        if ($Request->query->get('metakey')) {
            $Entity->addMetadataFilter($Request->query->get('metakey'), $Request->query->get('metavalue'));
        }

        if ($Request->query->get('type') === 'experiments') {

            // USERID FILTER
            if ($Request->query->has('owner')) {
                $owner = $App->Users->userData['userid'];
                if (Check::id((int) $Request->query->get('owner')) !== false) {
                    $owner = $Request->query->get('owner');
                }
                // all the team is 0 as userid
                if ($Request->query->get('owner') !== '0') {
                    $Entity->addFilter('entity.userid', $owner);
                }
            }

            // Status search
            if (!empty($status)) {
                $Entity->addFilter('entity.category', $status);
            }
        } else {
            // FILTER ON DATABASE ITEMS TYPES
            if (Check::id((int) $Request->query->get('type')) !== false) {
                $Entity->addFilter('categoryt.id', $Request->query->get('type'));
            }
        }


        try {
            $Controller = new SearchController($App, $Entity);
            echo $Controller->show(true)->getContent();
        } catch (ImproperActionException $e) {
            echo Tools::displayMessage($e->getMessage(), 'ko', false);
        }
    }
} else {
    // no search
    echo $App->render('todolist-panel.html', array());
    echo $App->render('footer.html', array());
}
