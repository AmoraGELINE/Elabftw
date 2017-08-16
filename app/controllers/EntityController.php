<?php
/**
 * app/controllers/EntityController.php
 *
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
namespace Elabftw\Elabftw;

use Exception;

/**
 * Deal with things common to experiments and items like tags, uploads, quicksave and lock
 *
 */
try {
    require_once '../../app/init.inc.php';

    // id of the item (experiment or database item)
    $id = 1;

    if (isset($_POST['id'])) {
        $id = $_POST['id'];
    } elseif (isset($GET['id'])) {
        $id = $_GET['id'];
    }

    if ((isset($_POST['type']) && $_POST['type'] === 'experiments') ||
        (isset($_GET['type']) && ($_GET['type'] === 'experiments'))) {
        $Entity = new Experiments($Users, $id);
    } else {
        $Entity = new Database($Users, $id);
    }
    // GET BODY
    if (isset($_POST['getBody'])) {
        $permissions = $Entity->getPermissions();

        if ($permissions['read'] === false) {
            throw new Exception(Tools::error(true));
        }

        echo $Entity->entityData['body'];
    }

    // LOCK
    if (isset($_POST['lock'])) {

        $permissions = $Entity->getPermissions();

        // We don't have can_lock, but maybe it's our XP, so we can lock it
        if (!$Users->userData['can_lock'] && !$permissions['write']) {
            throw new Exception(_("You don't have the rights to lock/unlock this."));
        }

        $errMsg = Tools::error();
        $res = null;
        try {
            $res = $Entity->toggleLock();
        } catch (Exception $e) {
            $errMsg = $e->getMessage();
        }

        if ($res) {
            echo json_encode(array(
                'res' => true,
                'msg' => _('Saved')
            ));
        } else {
            echo json_encode(array(
                'res' => false,
                'msg' => $errMsg
            ));
        }
    }

    // QUICKSAVE
    if (isset($_POST['quickSave'])) {
        $title = Tools::checkTitle($_POST['title']);

        $body = Tools::checkBody($_POST['body']);

        $date = Tools::kdate($_POST['date']);

        $result = $Entity->update($title, $date, $body);

        if ($result) {
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

    // CREATE TAG
    if (isset($_POST['createTag'])) {
        // Sanitize tag, we remove '\' because it fucks up the javascript if you have this in the tags
        $tag = strtr(filter_var($_POST['tag'], FILTER_SANITIZE_STRING), '\\', '');
        // also remove | because we use this as separator for tags in SQL
        $tag = strtr($tag, '|', ' ');
        // check for string length and if user owns the experiment
        if (strlen($tag) < 1) {
            throw new Exception(_('Tag is too short!'));
        }
        $Entity->canOrExplode('write');

        $Tags = new Tags($Entity);
        $Tags->create($tag);
    }

    // DELETE TAG
    if (isset($_POST['destroyTag'])) {
        if (Tools::checkId($_POST['tag_id']) === false) {
            throw new Exception('Bad id value');
        }
        $Entity->canOrExplode('write');
        $Tags = new Tags($Entity);
        $Tags->destroy($_POST['tag_id']);
    }

    // GET TAG LIST
    if (isset($_GET['term']) && isset($_GET['tag'])) {
        $Tags = new Tags($Entity);
        $term = filter_var($_GET['term'], FILTER_SANITIZE_STRING);
        echo json_encode($Tags->getList($term));
    }

    // GET MENTION LIST
    if (isset($_GET['term']) && isset($_GET['mention'])) {
        $userFilter = false;
        $term = filter_var($_GET['term'], FILTER_SANITIZE_STRING);
        if (isset($_GET['userFilter'])) {
            $userFilter = true;
        }
        echo json_encode($Entity->getMentionList($term, $userFilter));
    }

    // UPDATE FILE COMMENT
    if (isset($_POST['updateFileComment'])) {
        try {
            $comment = filter_var($_POST['comment'], FILTER_SANITIZE_STRING);

            if (strlen($comment) === 0 || $comment === ' ') {
                throw new Exception(_('Comment is too short'));
            }

            $id_arr = explode('_', $_POST['comment_id']);
            if (Tools::checkId($id_arr[1]) === false) {
                throw new Exception(_('The id parameter is invalid'));
            }
            $id = $id_arr[1];

            $Upload = new Uploads($Entity);
            if ($Upload->updateComment($id, $comment)) {
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
        } catch (Exception $e) {
            echo json_encode(array(
                'res' => false,
                'msg' => $e->getMessage()
            ));
        }
    }

    // CREATE UPLOAD
    if (isset($_POST['upload'])) {
        try {
            $Uploads = new Uploads($Entity);
            if ($Uploads->create($Request)) {
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
        } catch (Exception $e) {
            echo json_encode(array(
                'res' => false,
                'msg' => $e->getMessage()
            ));
        }
    }

    // ADD MOL FILE OR PNG
    if ($Request->request->has('addFromString')) {
        $Uploads = new Uploads($Entity);
        $Entity->canOrExplode('write');
        if ($Uploads->createFromString($Request->request->get('fileType'), $Request->request->get('string'))) {
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


    // DESTROY UPLOAD
    if ($Request->request->has('uploadsDestroy')) {
        $Uploads = new Uploads($Entity);
        $upload = $Uploads->readFromId($Request->request->get('upload_id'));
        $permissions = $Entity->getPermissions();
        if ($permissions['write']) {
            if ($Uploads->destroy($Request->request->get('upload_id'))) {
                // check that the filename is not in the body. see #432
                $msg = "";
                if (strpos($Entity->entityData['body'], $upload['long_name'])) {
                    $msg = ". ";
                    $msg .= _("Please make sure to remove any reference to this file in the body!");
                }
                echo json_encode(array(
                    'res' => true,
                    'msg' => _('File deleted successfully' . $msg)
                ));
            } else {
                echo json_encode(array(
                    'res' => false,
                    'msg' => Tools::error()
                ));
            }
        } else {
            echo json_encode(array(
                'res' => false,
                'msg' => Tools::error(true)
            ));
        }
    }
} catch (Exception $e) {
    $Logs = new Logs();
    $Logs->create('Error', $_SESSION['userid'], $e->getMessage());
}
