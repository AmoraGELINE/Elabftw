<?php
/**
 * app/controllers/AdminController.php
 *
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see http://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
namespace Elabftw\Elabftw;

use Exception;

/**
 * Deal with ajax requests sent from the admin page
 *
 */
try {
    require_once '../../app/init.inc.php';

    if (!$_SESSION['is_admin']) {
        throw new Exception('Non admin user tried to access admin panel.');
    }

    $redirect = false;

    // UPDATE ORDERING
    if (isset($_POST['updateOrdering'])) {
        if ($_POST['table'] === 'status') {
            $Entity = new Status($_SESSION['team_id']);
        } elseif ($_POST['table'] === 'items_types') {
            $Entity = new ItemsTypes($_SESSION['team_id']);
        } elseif ($_POST['table'] === 'experiments_templates') {
            // remove the create new entry
            unset($_POST['ordering'][0]);
            $Entity = new Templates($_SESSION['team_id']);
        }

        if ($Entity->updateOrdering($_POST)) {
            echo json_encode(array(
                'res' => true,
                'msg' => _('Saved')
            ));
        } else {
            echo json_encode(array(
                'res' => false,
                'msg' => Tools::error()
            ));
        }
    }

    // UPDATE TEAM SETTINGS
    if (isset($_POST['teamsUpdateFull'])) {
        $redirect = true;
        $Teams = new Teams($_SESSION['team_id']);
        if ($Teams->update($_POST)) {
            $_SESSION['ok'][] = _('Configuration updated successfully.');
        } else {
            $_SESSION['ko'][] = _('An error occurred!');
        }
    }

    // UPDATE COMMON TEMPLATE
    if (isset($_POST['commonTplUpdate'])) {
        $Templates = new Templates($_SESSION['team_id']);
        $Templates->update($_POST['commonTplUpdate']);
    }

} catch (Exception $e) {
    $Logs = new Logs();
    $Logs->create('Error', $_SESSION['userid'], $e->getMessage());
} finally {
    if ($redirect) {
        header('Location: ../../admin.php?tab=1');
    }
}
