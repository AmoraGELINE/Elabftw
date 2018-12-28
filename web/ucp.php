<?php
/**
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
declare(strict_types=1);

namespace Elabftw\Elabftw;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * User Control Panel
 *
 */
require_once 'app/init.inc.php';
$App->pageTitle = _('User Control Panel');

$Response = new Response();
$Response->prepare($Request);

try {
    $TeamGroups = new TeamGroups($App->Users);
    $teamGroupsArr = $TeamGroups->readAll();

    $Templates = new Templates($App->Users);
    $templatesArr = $Templates->readAll();

    $template = 'ucp.html';
    $renderArr = array(
        'langsArr' => Tools::getLangsArr(),
        'teamGroupsArr' => $teamGroupsArr,
        'templatesArr' => $templatesArr
    );

} catch (Exception $e) {
    $template = 'error.html';
    $renderArr = array('error' => $e->getMessage());
}
$Response->setContent($App->render($template, $renderArr));
$Response->send();
