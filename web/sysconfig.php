<?php
/**
 * sysconfig.php
 *
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
declare(strict_types=1);

namespace Elabftw\Elabftw;

use function dirname;
use Elabftw\Enums\EnforceMfa;
use Elabftw\Enums\Language;
use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Models\AuthFail;
use Elabftw\Models\Experiments;
use Elabftw\Models\Idps;
use Elabftw\Models\Teams;
use Elabftw\Services\UsersHelper;
use Exception;
use function file_get_contents;
use PDO;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administrate elabftw install
 *
 */
require_once 'app/init.inc.php';
$App->pageTitle = _('eLabFTW Configuration');
/** @psalm-suppress UncaughtThrowInGlobalScope */
$Response = new Response();
$Response->prepare($App->Request);

$template = 'error.html';
$renderArr = array();

try {
    if (!$App->Session->get('is_sysadmin')) {
        throw new IllegalActionException('Non sysadmin user tried to access sysconfig panel.');
    }

    $AuthFail = new AuthFail();
    $Idps = new Idps();
    $idpsArr = $Idps->readAll();
    $Teams = new Teams($App->Users);
    $teamsArr = $Teams->readAll();
    $teamsStats = $Teams->getAllStats();
    $Experiments = new Experiments($App->Users);

    // Users search
    $isSearching = false;
    $usersArr = array();
    if ($App->Request->query->has('q')) {
        $isSearching = true;
        $usersArr = $App->Users->readFromQuery(
            filter_var($App->Request->query->get('q'), FILTER_SANITIZE_STRING),
            (int) filter_var($App->Request->query->get('teamFilter'), FILTER_SANITIZE_NUMBER_INT)
        );
        foreach ($usersArr as &$user) {
            $UsersHelper = new UsersHelper((int) $user['userid']);
            $user['teams'] = $UsersHelper->getTeamsFromUserid();
        }
    }


    $phpInfos = array(
        PHP_OS,
        PHP_VERSION,
        PHP_INT_MAX,
        PHP_SYSCONFDIR,
        ini_get('upload_max_filesize'),
        ini_get('date.timezone'),
        Db::getConnection()->getAttribute(PDO::ATTR_SERVER_VERSION),
    );

    $elabimgVersion = getenv('ELABIMG_VERSION') ?: 'Not in Docker';

    $privacyPolicyTemplate = file_get_contents(dirname(__DIR__) . '/src/templates/privacy-policy.html');

    $template = 'sysconfig.html';
    $renderArr = array(
        'Request' => $App->Request,
        'nologinUsersCount' => $AuthFail->getLockedUsersCount(),
        'lockoutDevicesCount' => $AuthFail->getLockoutDevicesCount(),
        'elabimgVersion' => $elabimgVersion,
        'idpsArr' => $idpsArr,
        'isSearching' => $isSearching,
        'langsArr' => Language::getAllHuman(),
        'phpInfos' => $phpInfos,
        'privacyPolicyTemplate' => $privacyPolicyTemplate,
        'Teams' => $Teams,
        'teamsArr' => $teamsArr,
        'teamsStats' => $teamsStats,
        'timestampLastMonth' => $Experiments->getTimestampLastMonth(),
        'usersArr' => $usersArr,
        'enforceMfaArr' => EnforceMfa::getArray(),
    );
} catch (IllegalActionException $e) {
    $renderArr['error'] = Tools::error(true);
} catch (Exception $e) {
    $App->Log->error('', array(array('userid' => $App->Session->get('userid')), array('Exception' => $e)));
    $renderArr['error'] = $e->getMessage();
} finally {
    $Response->setContent($App->render($template, $renderArr));
    $Response->send();
}
