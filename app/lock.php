<?php
/********************************************************************************
*                                                                               *
*   Copyright 2012 Nicolas CARPi (nicolas.carpi@gmail.com)                      *
*   http://www.elabftw.net/                                                     *
*                                                                               *
********************************************************************************/

/********************************************************************************
*  This file is part of eLabFTW.                                                *
*                                                                               *
*    eLabFTW is free software: you can redistribute it and/or modify            *
*    it under the terms of the GNU Affero General Public License as             *
*    published by the Free Software Foundation, either version 3 of             *
*    the License, or (at your option) any later version.                        *
*                                                                               *
*    eLabFTW is distributed in the hope that it will be useful,                 *
*    but WITHOUT ANY WARRANTY; without even the implied                         *
*    warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR                    *
*    PURPOSE.  See the GNU Affero General Public License for more details.      *
*                                                                               *
*    You should have received a copy of the GNU Affero General Public           *
*    License along with eLabFTW.  If not, see <http://www.gnu.org/licenses/>.   *
*                                                                               *
********************************************************************************/
// lock.php
require_once '../inc/common.php';
require_once ELAB_ROOT . 'inc/locale.php';
// Check id is valid and assign it to $id
if (isset($_GET['id']) && is_pos_int($_GET['id'])) {
    $id = $_GET['id'];
} else {
    die(_("The id parameter is not valid!"));
}

// what do we do ? lock or unlock ?
if (isset($_GET['action']) && !empty($_GET['action'])) {
    if ($_GET['action'] === 'lock') {
        $action = 1; // lock
    } else {
        $action = 0; // unlock
    }
} else {
    exit;
}

switch ($_GET['type']) {

    // Locking experiment
        case 'experiments':
        // Is the user in a group with can_lock set to 1 ?
        // 1. get what is the group of the user
        $sql = "SELECT * FROM users WHERE userid = :userid LIMIT 1";
        $req = $pdo->prepare($sql);
        $req->bindParam(':userid', $_SESSION['userid']);
        $req->execute();
        $user = $req->fetch();

        // 2. check if this group has locking rights
        $sql = "SELECT can_lock FROM groups WHERE group_id = :usergroup";
        $req = $pdo->prepare($sql);
        $req->bindParam(':usergroup', $user['usergroup']);
        $req->execute();
        $can_lock = $req->fetchColumn(); // can be 0 or 1

        // We don't have can_lock, but maybe it's our XP, so we can lock it
        if ($can_lock == 0 && $action === 1) {
            // Is it his own XP ?
            $sql = "SELECT userid FROM experiments WHERE id = :id";
            $req = $pdo->prepare($sql);
            $req->execute(array(
                'id' => $id
            ));
            $userid = $req->fetchColumn();
            // we are trying to lock an XP which is not ours, and we don't have can_lock, show error
            if ($userid != $_SESSION['userid']) {
                $err_arr[] = _("You don't have the rights to lock/unlock this.");
                $_SESSION['errors'] = $err_arr;
                header("Location: ../experiments.php?mode=view&id=" . $id);
                exit();
            }
        }


        // check who locked it for unlock purpose
        if ($action === 0) {
            $sql = "SELECT lockedby FROM experiments WHERE id = :id";
            $req = $pdo->prepare($sql);
            $req->execute(array(
                'id' => $id
            ));
            $lockedby = $req->fetchColumn();
            if ($lockedby != $_SESSION['userid']) {
                // Get the first name of the locker to show in error message
                $sql = "SELECT firstname FROM users WHERE userid = :userid";
                $req = $pdo->prepare($sql);
                $req->execute(array(
                    'userid' => $lockedby
                ));
                $locker_name = $req->fetchColumn();
                $err_arr[] = _('This experiment was locked by') . ' ' . $locker_name . '. ' . _("You don't have the rights to lock/unlock this.");
                $_SESSION['errors'] = $err_arr;
                header("Location: ../experiments.php?mode=view&id=" . $id);
                exit();
            }
        }

        // check if the experiment is timestamped. Disallow unlock in this case.
        $sql = "SELECT timestamped FROM experiments WHERE id = :id";
        $req = $pdo->prepare($sql);
        $req->bindParam(':id', $id, PDO::PARAM_INT);
        $req->execute();
        $timestamped = $req->fetchColumn();
        if ($action === 0 && $timestamped) {
                $err_arr[] = _('You cannot unlock or edit in any way a timestamped experiment.');
                $_SESSION['errors'] = $err_arr;
                header("Location: ../experiments.php?mode=view&id=" . $id);
                exit;
        }
        

        // The actual locking action (and we add a timestamp in the lockedwhen column)
            $sql = "UPDATE experiments SET locked = :action, lockedby = :lockedby, lockedwhen = CURRENT_TIMESTAMP WHERE id = :id";
            $req = $pdo->prepare($sql);
            $result = $req->execute(array(
                'action' => $action,
                'lockedby' => $_SESSION['userid'],
                'id' => $id
            ));
            if ($result) {
                header("Location: ../experiments.php?mode=view&id=" . $id);
                exit;
            } else {
                die(sprintf(_("There was an unexpected problem! Please %sopen an issue on GitHub%s if you think this is a bug."), "<a href='https://github.com/elabftw/elabftw/issues/'>", "</a>"));
            }
            break;

        // Locking item
        case 'items':

            $sql = "UPDATE items SET locked = :action WHERE id = :id";
            $req = $pdo->prepare($sql);
            $result = $req->execute(array(
                'action' => $action,
                'id' => $id
            ));
            if ($result) {
                header("Location: ../database.php?mode=view&id=" . $id);
                exit;
            } else {
                die(sprintf(_("There was an unexpected problem! Please %sopen an issue on GitHub%s if you think this is a bug."), "<a href='https://github.com/elabftw/elabftw/issues/'>", "</a>"));
            }
            break;
        default:
            die(sprintf(_("There was an unexpected problem! Please %sopen an issue on GitHub%s if you think this is a bug."), "<a href='https://github.com/elabftw/elabftw/issues/'>", "</a>"));
}
