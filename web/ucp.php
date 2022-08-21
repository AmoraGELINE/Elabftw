<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Elabftw;

use Elabftw\Enums\Language;
use Elabftw\Exceptions\DatabaseErrorException;
use Elabftw\Exceptions\FilesystemErrorException;
use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Models\ApiKeys;
use Elabftw\Models\ItemsTypes;
use Elabftw\Models\Revisions;
use Elabftw\Models\TeamGroups;
use Elabftw\Models\Templates;
use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * User Control Panel
 */
require_once 'app/init.inc.php';
$App->pageTitle = _('User Control Panel');

/** @psalm-suppress UncaughtThrowInGlobalScope */
$Response = new Response();
$Response->prepare($App->Request);

try {
    $ApiKeys = new ApiKeys($App->Users);
    $apiKeysArr = $ApiKeys->readAll();

    $TeamGroups = new TeamGroups($App->Users);
    $teamGroupsArr = $TeamGroups->readAll();

    $Templates = new Templates($App->Users);
    $templatesArr = $Templates->getWriteableTemplatesList();
    $entityData = array();
    if ($App->Request->query->has('templateid')) {
        $Templates->setId((int) $App->Request->query->get('templateid'));
        $entityData = $Templates->readOne();
        $Revisions = new Revisions(
            $Templates,
            (int) $App->Config->configArr['max_revisions'],
            (int) $App->Config->configArr['min_delta_revisions'],
            (int) $App->Config->configArr['min_days_revisions'],
        );
    }

    // TEAM GROUPS
    $TeamGroups = new TeamGroups($App->Users);
    $visibilityArr = $TeamGroups->getVisibilityList();

    // the items categoryArr for add link input
    $ItemsTypes = new ItemsTypes($App->Users);
    $itemsCategoryArr = $ItemsTypes->readAll();

    // Notifications
    $notificationsSettings = array(
        array(
            'designation' => _('New comment notification'),
            'setting' => 'notif_comment_created',
        ),
        array(
            'designation' => _('Step deadline'),
            'setting' => 'notif_step_deadline',
        ),
    );

    if ($App->Users->userData['is_admin']) {
        $notificationsSettings[] =
            array(
                'designation' => _('New user created'),
                'setting' => 'notif_user_created',
            );
        $notificationsSettings[] =
            array(
                'designation' => _('New user need validation'),
                'setting' => 'notif_user_need_validation',
            );
        $notificationsSettings[] =
            array(
                'designation' => _('Booking event cancelled'),
                'setting' => 'notif_event_deleted',
            );
    }

    $template = 'ucp.html';
    $renderArr = array(
        'Entity' => $Templates,
        'apiKeysArr' => $apiKeysArr,
        'langsArr' => Language::getAllHuman(),
        'entityData' => $entityData,
        'itemsCategoryArr' => $itemsCategoryArr,
        'notificationsSettings' => $notificationsSettings,
        'teamGroupsArr' => $teamGroupsArr,
        'templatesArr' => $templatesArr,
        'visibilityArr' => $visibilityArr,
        'revNum' => isset($Revisions) ? $Revisions->readCount() : 0,
    );
} catch (ImproperActionException $e) {
    // show message to user
    $template = 'error.html';
    $renderArr = array('error' => $e->getMessage());
    $Response->setContent($App->render($template, $renderArr));
} catch (IllegalActionException $e) {
    // log notice and show message
    $App->Log->notice('', array(array('userid' => $App->Session->get('userid')), array('IllegalAction', $e)));
    $template = 'error.html';
    $renderArr = array('error' => $e->getMessage());
    $Response->setContent($App->render($template, $renderArr));
} catch (DatabaseErrorException | FilesystemErrorException $e) {
    // log error and show message
    $App->Log->error('', array(array('userid' => $App->Session->get('userid')), array('Error', $e)));
    $template = 'error.html';
    $renderArr = array('error' => $e->getMessage());
    $Response->setContent($App->render($template, $renderArr));
} catch (Exception $e) {
    // log error and show general error message
    $App->Log->error('', array(array('userid' => $App->Session->get('userid')), array('Exception' => $e)));
    $template = 'error.html';
    $renderArr = array('error' => Tools::error());
    $Response->setContent($App->render($template, $renderArr));
}
$Response->setContent($App->render($template, $renderArr));
$Response->send();
