<?php
/**
 * app/controllers/ExperimentsController.php
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
 * Experiments
 *
 */
require_once '../../app/init.inc.php';

try {

    // CREATE
    if (isset($_GET['create'])) {
        $Experiments = new Experiments($_SESSION['userid']);
        if (isset($_GET['tpl']) && !empty($_GET['tpl'])) {
            $id = $Experiments->create($_GET['tpl']);
        } else {
            $id = $Experiments->create();
        }
        header("location: ../../experiments.php?mode=edit&id=" . $id);
    }

    // UPDATE
    if (isset($_POST['update'])) {
        $Experiments = new Experiments($_SESSION['userid'], $_POST['id']);
        if ($Experiments->update(
            $_POST['title'],
            $_POST['date'],
            $_POST['body']
        )) {
            header("location: ../../experiments.php?mode=view&id=" . $_POST['id']);
        } else {
            throw new Exception('Error updating experiment');
        }
    }

    // DUPLICATE
    if (isset($_GET['duplicateId'])) {
        $Experiments = new Experiments($_SESSION['userid'], $_GET['duplicateId']);
        $id = $Experiments->duplicate();
        $mode = 'edit';
        header("location: ../../experiments.php?mode=" . $mode . "&id=" . $id);
    }

    // UPDATE STATUS
    if (isset($_POST['updateStatus'])) {
        $Experiments = new Experiments($_SESSION['userid'], $_POST['id']);
        if ($Experiments->updateStatus($_POST['status'])) {
            // get the color of the status for updating the css
            $Status = new Status($_SESSION['team_id']);
            echo json_encode(array(
                'res' => true,
                'msg' => _('Saved'),
                'color' => $Status->readColor($_POST['status'])
            ));
        } else {
            echo json_encode(array(
                'res' => false,
                'msg' => Tools::error()
            ));
        }
    }

    // ADD MOL FILE
    if (isset($_POST['addMol'])) {
        $Uploads = new Uploads('experiments', $_POST['item']);
        echo $Uploads->createFromMol($_POST['mol']);
    }

    // UPDATE VISIBILITY
    if (isset($_POST['updateVisibility'])) {
        $Experiments = new Experiments($_SESSION['userid'], $_POST['id']);
        if ($Experiments->updateVisibility($_POST['visibility'])) {
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

    // CREATE LINK
    if (isset($_POST['createLink'])) {
        $Experiments = new Experiments($_SESSION['userid'], $_POST['id']);
        if ($Experiments->Links->create($_POST['linkId'])) {
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

    // DESTROY LINK
    if (isset($_POST['destroyLink'])) {
        $Experiments = new Experiments($_SESSION['userid'], $_POST['id']);
        if ($Experiments->Links->destroy($_POST['linkId'])) {
            echo json_encode(array(
                'res' => true,
                'msg' => _('Link deleted successfully')
            ));
        } else {
            echo json_encode(array(
                'res' => false,
                'msg' => Tools::error()
            ));
        }
    }

    // TIMESTAMP
    if (isset($_POST['timestamp'])) {
        try {
            $ts = new TrustedTimestamps(new Config(), new Teams($_SESSION['team_id']), $_POST['id']);
            if ($ts->timeStamp()) {
                echo json_encode(array(
                    'res' => true
                ));
            }
        } catch (Exception $e) {
            echo json_encode(array(
                'res' => false,
                'msg' => $e->getMessage()
            ));
        }

    }

    // DESTROY
    if (isset($_POST['destroy'])) {
        $Experiments = new Experiments($_SESSION['userid'], $_POST['id']);
        $Teams = new Teams($_SESSION['team_id']);
        if ((($Teams->read('deletable_xp') == '0') &&
            !$_SESSION['is_admin']) ||
            !$Experiments->isOwnedByUser($Experiments->id, 'experiments', $_SESSION['userid'])) {
            throw new Exception(_("You don't have the rights to delete this experiment."));
        }
        if ($Experiments->destroy()) {
            echo json_encode(array(
                'res' => true,
                'msg' => _('Experiment successfully deleted')
            ));
        } else {
            echo json_encode(array(
                'res' => false,
                'msg' => Tools::error()
            ));
        }
    }

} catch (Exception $e) {
    $Logs = new Logs();
    $Logs->create('Error', $_SESSION['userid'], $e->getMessage());
    $_SESSION['ko'][] = Tools::error();
    header('Location: ../../experiments.php');
}
