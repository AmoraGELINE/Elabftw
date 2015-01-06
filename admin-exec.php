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
/* admin-exec.php - for administration of the elab */
require_once 'inc/common.php';
require_once 'inc/locale.php';
require_once 'vendor/autoload.php';

// only admin can use this
if ($_SESSION['is_admin'] != 1) {
    die(_('This section is out of your reach.'));
}

$msg_arr = array();
$errflag = false;
$email = '';

// FORMKEY
require_once 'inc/classes/formkey.class.php';
$formKey = new formKey();

// VALIDATE USERS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['validate'])) {
    // sql to validate users
    $sql = "UPDATE users SET validated = 1 WHERE userid = :userid";
    $req = $pdo->prepare($sql);
    // check we only have int in validate array
    if (!filter_var_array($_POST['validate'], FILTER_VALIDATE_INT)) {
        die();
    }
    // sql to get email of the user
    $sql_email = "SELECT email FROM users WHERE userid = :userid";
    $req_email = $pdo->prepare($sql_email);
    // we loop the validate array
    foreach ($_POST['validate'] as $user) {
        // bind parameters of the user
        $req_email->bindParam(':userid', $user, PDO::PARAM_INT);
        $req->bindParam(':userid', $user, PDO::PARAM_INT);

        // validate the user
        $req->execute();
        $msg_arr[] = _('Validated user with ID :').' '.$user;

        // get email
        $req_email->execute();
        $user = $req_email->fetch();
        // now let's get the URL so we can have a nice link in the email
        $url = 'https://'.$_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'].$_SERVER['PHP_SELF'];
        $url = str_replace('admin-exec.php', 'login.php', $url);
        // we send an email to each validated new user
        $footer = "\n\n~~~\nSent from eLabFTW http://www.elabftw.net\n";
        // Create the message
        $message = Swift_Message::newInstance()
        // Give the message a subject
        // no i18n here
        ->setSubject('[eLabFTW] Account validated')
        // Set the From address with an associative array
        ->setFrom(array(get_config('smtp_username') => get_config('smtp_username')))
        // Set the To addresses with an associative array
        ->setTo(array($user['email'] => 'eLabFTW'))
        // Give it a body
        ->setBody('Hello. Your account on eLabFTW was validated by an admin. Follow this link to login : '.$url.$footer);

        $transport = Swift_SmtpTransport::newInstance(
            get_config('smtp_address'),
            get_config('smtp_port'),
            get_config('smtp_encryption')
        )
            ->setUsername(get_config('smtp_username'))
            ->setPassword(get_config('smtp_password'));
        $mailer = Swift_Mailer::newInstance($transport);
        // now we try to send the email
        try {
            $mailer->send($message);
        } catch (Exception $e) {
            // log the error
            dblog('Error', $_SESSION['userid'], $e->getMessage());
            $errflag = true;
        }
        if ($errflag) {
            $msg_arr[] = _('There was a problem sending the email! Error was logged.');
            $_SESSION['errors'] = $msg_arr;
            header('location: admin.php');
            exit;
        }
    }
    $_SESSION['infos'] = $msg_arr;
    header('Location: admin.php');
    exit;
}

// TEAM CONFIGURATION FORM COMING FROM SYSCONFIG.PHP
// ADD A NEW TEAM
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_team']) && $_POST['new_team'] != '' && $_POST['new_team'] != ' ') {
    $new_team_name = filter_var($_POST['new_team'], FILTER_SANITIZE_STRING);
    $sql = 'INSERT INTO teams (team_name, deletable_xp, link_name, link_href) VALUES (:team_name, :deletable_xp, :link_name, :link_href)';
    $req = $pdo->prepare($sql);
    $result1 = $req->execute(array(
        'team_name' => $new_team_name,
        'deletable_xp' => 1,
        'link_name' => 'Wiki',
        'link_href' => 'https://github.com/NicolasCARPi/elabftw/wiki'
    ));
    $new_team_id = $pdo->lastInsertId();
    // now we need to insert a new default set of status for the newly created team
    $sql = "INSERT INTO status (team, name, color, is_default) VALUES
    (:team, 'Running', '0096ff', 1),
    (:team, 'Success', '00ac00', 0),
    (:team, 'Need to be redone', 'c0c0c0', 0),
    (:team, 'Fail', 'ff0000', 0);";
    $req = $pdo->prepare($sql);
    $req->bindValue(':team', $new_team_id);
    $result2 = $req->execute();

    // now we need to insert a new default set of items_types for the newly created team
    $sql = "INSERT INTO `items_types` (`team`, `name`, `bgcolor`, `template`) VALUES
(:team, 'Antibody', '31a700', '<p><strong>Host :</strong></p>\r\n<p><strong>Target :</strong></p>\r\n<p><strong>Dilution to use :</strong></p>\r\n<p>Don''t forget to add the datasheet !</p>'),
(:team, 'Plasmid', '29AEB9', '<p><strong>Concentration : </strong></p>\r\n<p><strong>Resistances : </strong></p>\r\n<p><strong>Backbone :</strong></p>\r\n<p><strong><br /></strong></p>'),
(:team, 'siRNA', '0064ff', '<p><strong>Sequence :</strong></p>\r\n<p><strong>Target :</strong></p>\r\n<p><strong>Concentration :</strong></p>\r\n<p><strong>Buffer :</strong></p>'),
(:team, 'Drugs', 'fd00fe', '<p><strong>Action :</strong> &nbsp;<strong> </strong></p>\r\n<p><strong>Concentration :</strong>&nbsp;</p>\r\n<p><strong>Use at :</strong>&nbsp;</p>\r\n<p><strong>Buffer :</strong> </p>'),
(:team, 'Crystal', '84ff00', '<p>Edit me</p>');";
    $req = $pdo->prepare($sql);
    $req->bindValue(':team', $new_team_id);
    $result3 = $req->execute();

    // now we need to insert a new default experiment template for the newly created team
    $sql = "INSERT INTO `experiments_templates` (`team`, `body`, `name`, `userid`) VALUES
    ('".$new_team_id."', '<p><span style=\"font-size: 14pt;\"><strong>Goal :</strong></span></p>
    <p>&nbsp;</p>
    <p><span style=\"font-size: 14pt;\"><strong>Procedure :</strong></span></p>
    <p>&nbsp;</p>
    <p><span style=\"font-size: 14pt;\"><strong>Results :</strong></span></p><p>&nbsp;</p>', 'default', 0);";
    $req = $pdo->prepare($sql);
    // FIXME
    // ok, so you will tell me, why the hell would I put the new_team_id here like that ?
    // well, for some unknown and weird reason, it kept returning 0 :
    // even if the value was still the new team id, and there was no error, it went in the DB as 0, not the id of the team
    // it's weird I know, but at least like that it works.
    //$req->bindValue(':team', $new_team_id);
    $result4 = $req->execute();

    if ($result1 && $result2 && $result3 && $result4) {
        $msg_arr[] = _('Team added successfully.');
        $_SESSION['infos'] = $msg_arr;
        header('Location: sysconfig.php');
        exit;
    } else {
        $msg_arr[] = sprintf(_("There was an unexpected problem! Please %sopen an issue on GitHub%s if you think this is a bug."), "<a href='https://github.com/NicolasCARPi/elabftw/issues/'>", "</a>");
        $_SESSION['errors'] = $msg_arr;
        header('Location: sysconfig.php');
        exit;
    }
}



// SERVER SETTINGS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['debug'])) {

    if (isset($_POST['lang']) && (strlen($_POST['lang']) === 5)) {
        $lang = $_POST['lang'];
    } else {
        $lang = 'en_GB';
    }

    if ($_POST['debug'] == 1) {
        $debug = 1;
    } else {
        $debug = 0;
    }

    if (isset($_POST['proxy'])) {
        $proxy = filter_var($_POST['proxy'], FILTER_SANITIZE_STRING);
    } else {
        $proxy = '';
    }

    // SQL
    $updates = array(
        'lang' => $lang,
        'debug' => $debug,
        'proxy' => $proxy
    );

    if (update_config($updates)) {
        $msg_arr[] = _('Configuration updated successfully.');
        $_SESSION['infos'] = $msg_arr;
        header('Location: sysconfig.php?tab=2');
        exit;
    } else {
        $msg_arr[] = sprintf(_("There was an unexpected problem! Please %sopen an issue on GitHub%s if you think this is a bug."), "<a href='https://github.com/NicolasCARPi/elabftw/issues/'>", "</a>");
        $_SESSION['errors'] = $msg_arr;
        header('Location: sysconfig.php?tab=2');
        exit;
    }
} // END SERVER SETTINGS

// TIMESTAMP CONFIG
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['stampshare'])) {

    if ($_POST['stampshare'] == 1) {
        $stampshare = 1;
    } else {
        $stampshare = 0;
    }
    if (isset($_POST['stamplogin'])) {
        $stamplogin = filter_var($_POST['stamplogin'], FILTER_VALIDATE_EMAIL);
    } else {
        $stamplogin = '';
    }
    if (isset($_POST['stamppass'])) {
        $stamppass = filter_var($_POST['stamppass'], FILTER_SANITIZE_STRING);
    } else {
        $stamppass = '';
    }

    // SQL
    $updates = array(
        'stampshare' => $stampshare,
        'stamplogin' => $stamplogin,
        'stamppass' => $stamppass
    );

    if (update_config($updates)) {
        $msg_arr[] = _('Configuration updated successfully.');
        $_SESSION['infos'] = $msg_arr;
        header('Location: sysconfig.php?tab=3');
        exit;
    } else {
        $msg_arr[] = sprintf(_("There was an unexpected problem! Please %sopen an issue on GitHub%s if you think this is a bug."), "<a href='https://github.com/NicolasCARPi/elabftw/issues/'>", "</a>");
        $_SESSION['errors'] = $msg_arr;
        header('Location: sysconfig.php?tab=3');
        exit;
    }
} // END TIMESTAMP CONFIG

// SECURITY
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['admin_validate'])) {

    if ($_POST['admin_validate'] == 1) {
        $admin_validate = 1;
    } else {
        $admin_validate = 0;
    }
    if (isset($_POST['login_tries'])) {
        $login_tries = filter_var($_POST['login_tries'], FILTER_SANITIZE_STRING);
    } else {
        $login_tries = '3';
    }
    if (isset($_POST['ban_time'])) {
        $ban_time = filter_var($_POST['ban_time'], FILTER_SANITIZE_STRING);
    } else {
        $ban_time = '30';
    }

    // SQL
    $updates = array(
        'admin_validate' => $admin_validate,
        'login_tries' => $login_tries,
        'ban_time' => $ban_time
    );

    if (update_config($updates)) {
        $msg_arr[] = _('Configuration updated successfully.');
        $_SESSION['infos'] = $msg_arr;
        header('Location: sysconfig.php?tab=4');
        exit;
    } else {
        $msg_arr[] = sprintf(_("There was an unexpected problem! Please %sopen an issue on GitHub%s if you think this is a bug."), "<a href='https://github.com/NicolasCARPi/elabftw/issues/'>", "</a>");
        $_SESSION['errors'] = $msg_arr;
        header('Location: sysconfig.php?tab=4');
        exit;
    }
} // END SECURITY

// EMAIL
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['smtp_address'])) {

    if (isset($_POST['smtp_address'])) {
        $smtp_address = filter_var($_POST['smtp_address'], FILTER_SANITIZE_STRING);
    } else {
        $smtp_address = '';
    }
    if (isset($_POST['smtp_encryption'])) {
        $smtp_encryption = filter_var($_POST['smtp_encryption'], FILTER_SANITIZE_STRING);
    } else {
        $smtp_encryption = '';
    }
    if (isset($_POST['smtp_port']) && is_pos_int($_POST['smtp_port'])) {
        $smtp_port = $_POST['smtp_port'];
    } else {
        $smtp_port = '';
    }
    if (isset($_POST['smtp_username'])) {
        $smtp_username = filter_var($_POST['smtp_username'], FILTER_SANITIZE_STRING);
    } else {
        $smtp_username = '';
    }
    if (isset($_POST['smtp_password'])) {
        $smtp_password = filter_var($_POST['smtp_password'], FILTER_SANITIZE_STRING);
    } else {
        $smtp_password = '';
    }

    // SQL
    $updates = array(
        'smtp_address' => $smtp_address,
        'smtp_encryption' => $smtp_encryption,
        'smtp_port' => $smtp_port,
        'smtp_username' => $smtp_username,
        'smtp_password' => $smtp_password
    );

    if (update_config($updates)) {
        $msg_arr[] = _('Configuration updated successfully.');
        $_SESSION['infos'] = $msg_arr;
        header('Location: sysconfig.php?tab=5');
        exit;
    } else {
        $msg_arr[] = sprintf(_("There was an unexpected problem! Please %sopen an issue on GitHub%s if you think this is a bug."), "<a href='https://github.com/NicolasCARPi/elabftw/issues/'>", "</a>");
        $_SESSION['errors'] = $msg_arr;
        header('Location: sysconfig.php?tab=5');
        exit;
    }

} // END EMAIL

// TEAM CONFIGURATION COMING FROM ADMIN.PHP
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deletable_xp'])) {

    // CHECKS
    if ($_POST['deletable_xp'] == 1) {
        $deletable_xp = true;
    } else {
        $deletable_xp = false;
    }
    if (isset($_POST['link_name'])) {
        $link_name = filter_var($_POST['link_name'], FILTER_SANITIZE_STRING);
    } else {
        $link_name = 'Wiki';
    }
    if (isset($_POST['link_href'])) {
        $link_href = filter_var($_POST['link_href'], FILTER_SANITIZE_STRING);
    } else {
        $link_href = 'https://github.com/NicolasCARPi/elabftw/wiki';
    }
    if (isset($_POST['stamplogin'])) {
        $stamplogin = filter_var($_POST['stamplogin'], FILTER_VALIDATE_EMAIL);
    } else {
        $stamplogin = '';
    }
    if (isset($_POST['stamppass'])) {
        $stamppass = filter_var($_POST['stamppass'], FILTER_SANITIZE_STRING);
    } else {
        $stamppass = '';
    }

    // SQL
    $sql = "UPDATE teams SET deletable_xp = :deletable_xp, link_name = :link_name, link_href = :link_href, stamplogin = :stamplogin, stamppass = :stamppass WHERE team_id = :team_id";
    $req = $pdo->prepare($sql);
    try {
        $req->execute(array(
        'deletable_xp' => $deletable_xp,
        'link_name' => $link_name,
        'link_href' => $link_href,
        'stamplogin' => $stamplogin,
        'stamppass' => $stamppass,
        'team_id' => $_SESSION['team_id']
        ));
    }
    catch (PDOException $e)
    {
        $msg_arr[] = sprintf(_("There was an unexpected problem! Please %sopen an issue on GitHub%s if you think this is a bug."), "<a href='https://github.com/NicolasCARPi/elabftw/issues/'>", "</a>");
        $_SESSION['errors'] = $msg_arr;
        header('Location: admin.php');
        exit;
    }

    $msg_arr[] = _('Configuration updated successfully.');
    $_SESSION['infos'] = $msg_arr;
    header('Location: admin.php');
    exit;
}

// EDIT USER
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['userid'])) {
    if (!is_pos_int($_POST['userid'])) {
        $msg_arr[] = _("Userid is not valid.");
        $errflag = true;
    }
    if ($errflag) {
        $_SESSION['errors'] = $msg_arr;
        header("location: admin.php?tab=2");
        exit;
    }

    $userid = $_POST['userid'];
    // Put everything lowercase and first letter uppercase
    $firstname = ucwords(strtolower(filter_var($_POST['firstname'], FILTER_SANITIZE_STRING)));
    // Lastname in uppercase
    $lastname = strtoupper(filter_var($_POST['lastname'], FILTER_SANITIZE_STRING));
    $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    if ($_POST['validated'] == 1) {
        $validated = 1;
    } else {
        $validated = 0;
    }
    if (is_pos_int($_POST['usergroup'])) {
        // a non sysadmin cannot put someone sysadmin
        $usergroup = $_POST['usergroup'];
        if ($usergroup == 1 && $_SESSION['is_sysadmin'] != 1) {
            die(_('Only a sysadmin can put someone sysadmin.'));
        }

    } else {
        $usergroup = '4';
    }
    // reset password
    if (isset($_POST['new_password']) && !empty($_POST['new_password']) && isset($_POST['confirm_new_password'])) {
        // check if passwords match
        if ($_POST['new_password'] == $_POST['confirm_new_password']) {
            // Good to go
            // Create salt
            $salt = hash("sha512", uniqid(rand(), true));
            // Create hash
            $passwordHash = hash("sha512", $salt.$_POST['new_password']);

            $sql = "UPDATE users SET password = :password, salt = :salt WHERE userid = :userid";
            $req = $pdo->prepare($sql);
            $result = $req->execute(array(
                'userid' => $userid,
                'password' => $passwordHash,
                'salt' => $salt
            ));
            if ($result) {
                $msg_arr[] = _('Configuration updated successfully.');
                $_SESSION['infos'] = $msg_arr;
            } else {
                $msg_arr[] = sprintf(_("There was an unexpected problem! Please %sopen an issue on GitHub%s if you think this is a bug."), "<a href='https://github.com/NicolasCARPi/elabftw/issues/'>", "</a>");
                $_SESSION['errors'] = $msg_arr;
            }
        } else { // passwords do not match
            $msg_arr[] = _('The passwords do not match!');
            $_SESSION['errors'] = $msg_arr;
        }
    }

    $sql = "UPDATE users SET
        firstname = :firstname,
        lastname = :lastname,
        username = :username,
        email = :email,
        usergroup = :usergroup,
        validated = :validated
        WHERE userid = :userid";
    $req = $pdo->prepare($sql);
    $result = $req->execute(array(
        'firstname' => $firstname,
        'lastname' => $lastname,
        'username' => $username,
        'email' => $email,
        'usergroup' => $usergroup,
        'validated' => $validated,
        'userid' => $userid
    ));
    if ($result) {
        if (empty($msg_arr)) {
            $msg_arr[] = _('Configuration updated successfully.');
            $_SESSION['infos'] = $msg_arr;
            header('Location: admin.php?tab=2');
            exit;
        } else {
            header('Location: admin.php');
            exit;
        }
    } else { //sql fail
        $msg_arr[] = sprintf(_("There was an unexpected problem! Please %sopen an issue on GitHub%s if you think this is a bug."), "<a href='https://github.com/NicolasCARPi/elabftw/issues/'>", "</a>");
        $_SESSION['errors'] = $msg_arr;
        header('Location: admin.php?tab=2');
        exit;
    }
}

// STATUS
if (isset($_POST['status_name']) && is_pos_int($_POST['status_id']) && !empty($_POST['status_name'])) {
    $status_id = $_POST['status_id'];
    $status_name = filter_var($_POST['status_name'], FILTER_SANITIZE_STRING);
    // we remove the # of the hexacode and sanitize string
    $status_color = filter_var(substr($_POST['status_color'], 1, 6), FILTER_SANITIZE_STRING);
    if (isset($_POST['status_is_default']) && $_POST['status_is_default'] === 'on') {
        $status_is_default = true;
        // if we set true to status_is_default somewhere, it's best to remove all other default
        // in the team so we won't have two default status
        $sql = "UPDATE status
                SET is_default = false
                WHERE team = :team_id";
        $req = $pdo->prepare($sql);
        $req->bindParam(':team_id', $_SESSION['team_id'], PDO::PARAM_INT);
        $res = $req->execute();
    } else {
        $status_is_default = false;
    }


    // now we update the status
    $sql = "UPDATE status SET
        name = :name,
        color = :color,
        is_default = :is_default
        WHERE id = :id";
    $req = $pdo->prepare($sql);
    $result = $req->execute(array(
        'name' => $status_name,
        'color' => $status_color,
        'is_default' => $status_is_default,
        'id' => $status_id
    ));
    if ($result) {
        $msg_arr[] = _('Configuration updated successfully.');
        $_SESSION['infos'] = $msg_arr;
        header('Location: admin.php?tab=3');
        exit;
    } else { //sql fail
        $msg_arr[] = sprintf(_("There was an unexpected problem! Please %sopen an issue on GitHub%s if you think this is a bug."), "<a href='https://github.com/NicolasCARPi/elabftw/issues/'>", "</a>");
        $_SESSION['errors'] = $msg_arr;
        header('Location: admin.php?tab=3');
        exit;
    }
}
// add new status
if (isset($_POST['new_status_name'])) {
    if (!empty($_POST['new_status_name'])) {
        $status_name = filter_var($_POST['new_status_name'], FILTER_SANITIZE_STRING);
        // we remove the # of the hexacode and sanitize string
        $status_color = filter_var(substr($_POST['new_status_color'], 1, 6), FILTER_SANITIZE_STRING);
        $sql = "INSERT INTO status(name, color, team, is_default) VALUES(:name, :color, :team, :is_default)";
        $req = $pdo->prepare($sql);
        $result = $req->execute(array(
            'name' => $status_name,
            'color' => $status_color,
            'team' => $_SESSION['team_id'],
            'is_default' => 0
        ));
        if ($result) {
            $msg_arr[] = _('Configuration updated successfully.');
            $_SESSION['infos'] = $msg_arr;
            header('Location: admin.php?tab=3');
            exit;
        } else { //sql fail
            $msg_arr[] = sprintf(_("There was an unexpected problem! Please %sopen an issue on GitHub%s if you think this is a bug."), "<a href='https://github.com/NicolasCARPi/elabftw/issues/'>", "</a>");
            $_SESSION['errors'] = $msg_arr;
            header('Location: admin.php?tab=3');
            exit;
        }
    } else {
        $msg_arr[] = _('A mandatory field is missing!');
        $_SESSION['errors'] = $msg_arr;
        header('Location: admin.php?tab=3');
        exit;
    }
}

// ITEMS TYPES
if (isset($_POST['item_type_name']) && is_pos_int($_POST['item_type_id'])) {
    $item_type_id = $_POST['item_type_id'];
    $item_type_name = filter_var($_POST['item_type_name'], FILTER_SANITIZE_STRING);
    // we remove the # of the hexacode and sanitize string
    $item_type_bgcolor = filter_var(substr($_POST['item_type_bgcolor'], 1, 6), FILTER_SANITIZE_STRING);
    $item_type_template = check_body($_POST['item_type_template']);
    $sql = "UPDATE items_types SET
        name = :name,
        team = :team,
        bgcolor = :bgcolor,
        template = :template
        WHERE id = :id";
    $req = $pdo->prepare($sql);
    $result = $req->execute(array(
        'name' => $item_type_name,
        'team' => $_SESSION['team_id'],
        'bgcolor' => $item_type_bgcolor,
        'template' => $item_type_template,
        'id' => $item_type_id
    ));
    if ($result) {
        $msg_arr[] = _('Configuration updated successfully.');
        $_SESSION['infos'] = $msg_arr;
        header('Location: admin.php?tab=4');
        exit;
    } else { //sql fail
        $msg_arr[] = sprintf(_("There was an unexpected problem! Please %sopen an issue on GitHub%s if you think this is a bug."), "<a href='https://github.com/NicolasCARPi/elabftw/issues/'>", "</a>");
        $_SESSION['errors'] = $msg_arr;
        header('Location: admin.php?tab=4');
        exit;
    }
}
// ADD NEW ITEM TYPE
if (isset($_POST['new_item_type']) && is_pos_int($_POST['new_item_type'])) {
    $item_type_name = filter_var($_POST['new_item_type_name'], FILTER_SANITIZE_STRING);
    if (strlen($item_type_name) < 1) {
        $msg_arr[] = _('You need to put a title!');
        $_SESSION['errors'] = $msg_arr;
        header('Location: admin.php?tab=4');
        exit;
    }

    // we remove the # of the hexacode and sanitize string
    $item_type_bgcolor = filter_var(substr($_POST['new_item_type_bgcolor'], 1, 6), FILTER_SANITIZE_STRING);
    $item_type_template = check_body($_POST['new_item_type_template']);
    $sql = "INSERT INTO items_types(name, team, bgcolor, template) VALUES(:name, :team, :bgcolor, :template)";
    $req = $pdo->prepare($sql);
    $result = $req->execute(array(
        'name' => $item_type_name,
        'team' => $_SESSION['team_id'],
        'bgcolor' => $item_type_bgcolor,
        'template' => $item_type_template
    ));
    if ($result) {
        $msg_arr[] = _('Configuration updated successfully.');
        $_SESSION['infos'] = $msg_arr;
        header('Location: admin.php?tab=4');
        exit;
    } else { //sql fail
        $msg_arr[] = sprintf(_("There was an unexpected problem! Please %sopen an issue on GitHub%s if you think this is a bug."), "<a href='https://github.com/NicolasCARPi/elabftw/issues/'>", "</a>");

        $_SESSION['errors'] = $msg_arr;
        header('Location: admin.php?tab=4');
        exit;
    }
}

// DELETE USER (we receive a formkey from this form)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    // Check the form_key
    if (!isset($_POST['form_key']) || !$formKey->validate()) {
        // form key is invalid
        die(INVALID_FORMKEY);
    }
    if (filter_var($_POST['delete_user'], FILTER_VALIDATE_EMAIL)) {
        $email = $_POST['delete_user'];
    } else {
        $msg_arr[] = _("The email is not valid.");
        $errflag = true;
    }
    if (isset($_POST['delete_user_confpass']) && !empty($_POST['delete_user_confpass'])) {
        // get the salt from db
        $sql = "SELECT salt, password FROM users WHERE userid = :userid";
        $req = $pdo->prepare($sql);
        $req->bindParam(':userid', $_SESSION['userid']);
        $req->execute();
        $pass_infos = $req->fetch();

        // check if the given password is good
        $password_hash = hash("sha512", $pass_infos['salt'].$_POST['delete_user_confpass']);
        if ($password_hash != $pass_infos['password']) {
            $msg_arr[] = _("Wrong password!");
            $errflag = true;
        }

    } else {
        $msg_arr[] = _('You need to put a password!');
        $errflag = true;
    }
    // look which user has this email address and make sure it is in the same team as admin
    $sql = "SELECT userid FROM users WHERE email LIKE :email AND team = :team";
    $req = $pdo->prepare($sql);
    $req->execute(array(
        'email' => $email,
        'team' => $_SESSION['team_id']
    ));
    $user = $req->fetch();
    // email doesn't exist
    if ($req->rowCount() === 0) {
        $msg_arr[] = _('No user with this email or user not in your team');
        $errflag = true;
    }


    // Check for errors and redirect if there is one
    if ($errflag) {
        $_SESSION['errors'] = $msg_arr;
        header("location: admin.php");
        exit;
    }

    $userid = $user['userid'];
    // DELETE USER
    $sql = "DELETE FROM users WHERE userid = ".$userid;
    $req = $pdo->prepare($sql);
    $req->execute();
    $sql = "DELETE FROM experiments_tags WHERE userid = ".$userid;
    $req = $pdo->prepare($sql);
    $req->execute();
    $sql = "DELETE FROM experiments WHERE userid = ".$userid;
    $req = $pdo->prepare($sql);
    $req->execute();
    // get all filenames
    $sql = "SELECT long_name FROM uploads WHERE userid = :userid AND type = :type";
    $req = $pdo->prepare($sql);
    $req->execute(array(
        'userid' => $userid,
        'type' => 'exp'
    ));
    while ($uploads = $req->fetch()) {
        // Delete file
        $filepath = 'uploads/'.$uploads['long_name'];
        unlink($filepath);
    }
    $sql = "DELETE FROM uploads WHERE userid = ".$userid;
    $req = $pdo->prepare($sql);
    $req->execute();
    $msg_arr[] = _('Everything was purged successfully.');
    $_SESSION['infos'] = $msg_arr;
    header('Location: admin.php?tab=2');
    exit;
}
// DEFAULT _('Experiment') TEMPLATE
if (isset($_POST['default_exp_tpl'])) {
    $default_exp_tpl = check_body($_POST['default_exp_tpl']);
    $sql = "UPDATE experiments_templates SET
        name = 'default',
        team = :team,
        body = :body
        WHERE userid = 0 AND team = :team";
    $req = $pdo->prepare($sql);
    try {
        $req->execute(array(
        'body' => $default_exp_tpl,
        'team' => $_SESSION['team_id']
        ));
    }
    catch (PDOException $e)
    {
        $msg_arr[] = sprintf(_("There was an unexpected problem! Please %sopen an issue on GitHub%s if you think this is a bug."), "<a href='https://github.com/NicolasCARPi/elabftw/issues/'>", "</a>");
        $_SESSION['errors'] = $msg_arr;
        header('Location: admin.php?tab=5');
        exit;
    }
    $msg_arr[] = _('Configuration updated successfully.');
    $_SESSION['infos'] = $msg_arr;
    header('Location: admin.php?tab=5');
    exit;
}
