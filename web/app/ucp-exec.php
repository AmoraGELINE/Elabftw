<?php
/**
 * ucp-exec.php
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
 * Deal with post requests coming from ucp.php
 *
 */
require_once '../app/init.inc.php';

$tab = '1';

$Auth = new Auth();
$Users = new Users();

try {
    // TAB 1 : PREFERENCES
    if (isset($_POST['display'])) {
        if ($_POST['display'] === 'default') {
            $new_display = 'default';
        } elseif ($_POST['display'] === 'compact') {
            $new_display = 'compact';
        } else {
            $new_display = 'default';
        }

        // LIMIT
        $filter_options = array(
            'options' => array(
                'default' => 15,
                'min_range' => 1,
                'max_range' => 500
            ));
        $new_limit = filter_var($_POST['limit'], FILTER_VALIDATE_INT, $filter_options);

        // KEYBOARD SHORTCUTS
        $new_sc_create = 'c';
        $new_sc_edit = 'e';
        $new_sc_submit = 's';
        $new_sc_todo = 't';

        // only take it if it's lowercase letter, and only take first char
        if (preg_match('[a-z]', $_POST['key_create'])) {
            $new_sc_create = substr($_POST['key_create'], 0, 1);
        }
        if (preg_match('[a-z]', $_POST['key_edit'])) {
            $new_sc_edit = substr($_POST['key_edit'], 0, 1);
        }
        if (preg_match('[a-z]', $_POST['key_submit'])) {
            $new_sc_submit = substr($_POST['key_submit'], 0, 1);
        }
        if (preg_match('[a-z]', $_POST['key_todo'])) {
            $new_sc_todo = substr($_POST['key_todo'], 0, 1);
        }

        // SHOW TEAM
        if (isset($_POST['show_team']) && $_POST['show_team'] === 'on') {
            $new_show_team = 1;
        } else {
            $new_show_team = 0;
        }

        // CLOSE WARNING
        if (isset($_POST['close_warning']) && $_POST['close_warning'] === 'on') {
            $new_close_warning = 1;
        } else {
            $new_close_warning = 0;
        }
        // CHEM EDITOR
        if (isset($_POST['chem_editor']) && $_POST['chem_editor'] === 'on') {
            $new_chem_editor = 1;
        } else {
            $new_chem_editor = 0;
        }

        // LANG
        $lang_array = array('en_GB', 'ca_ES', 'de_DE', 'es_ES', 'fr_FR', 'it_IT', 'pl_PL', 'pt_BR', 'pt_PT', 'ru_RU', 'sl_SI', 'zh_CN');
        if (isset($_POST['lang']) && in_array($_POST['lang'], $lang_array)) {
            $new_lang = $_POST['lang'];
        } else {
            $new_lang = 'en_GB';
        }


        // SQL
        $sql = "UPDATE users SET
            display = :new_display,
            limit_nb = :new_limit,
            sc_create = :new_sc_create,
            sc_edit = :new_sc_edit,
            sc_submit = :new_sc_submit,
            sc_todo = :new_sc_todo,
            show_team = :new_show_team,
            close_warning = :new_close_warning,
            chem_editor = :new_chem_editor,
            lang = :new_lang
            WHERE userid = :userid;";
        $req = $pdo->prepare($sql);
        $req->execute(array(
            'new_display' => $new_display,
            'new_limit' => $new_limit,
            'new_sc_create' => $new_sc_create,
            'new_sc_edit' => $new_sc_edit,
            'new_sc_submit' => $new_sc_submit,
            'new_sc_todo' => $new_sc_todo,
            'new_show_team' => $new_show_team,
            'new_close_warning' => $new_close_warning,
            'new_chem_editor' => $new_chem_editor,
            'new_lang' => $new_lang,
            'userid' => $_SESSION['userid']
        ));
        // put it in session
        $_SESSION['prefs']['display'] = $new_display;
        $_SESSION['prefs']['limit'] = $new_limit;
        $_SESSION['prefs']['shortcuts']['create'] = $new_sc_create;
        $_SESSION['prefs']['shortcuts']['edit'] = $new_sc_edit;
        $_SESSION['prefs']['shortcuts']['submit'] = $new_sc_submit;
        $_SESSION['prefs']['shortcuts']['todo'] = $new_sc_todo;
        $_SESSION['prefs']['show_team'] = $new_show_team;
        $_SESSION['prefs']['close_warning'] = $new_close_warning;
        $_SESSION['prefs']['chem_editor'] = $new_chem_editor;
        $_SESSION['prefs']['lang'] = $new_lang;
        $_SESSION['ok'][] = _('Preferences updated.');
    }
    // END TAB 1

    // TAB 2 : ACCOUNT
    if (isset($_POST['currpass'])) {
        $tab = '2';

        // check that we got the good password
        $me = $Users->read($_SESSION['userid']);
        if (!$Auth->checkCredentials($me['email'], $_POST['currpass'])) {
            throw new Exception(_("Please input your current password!"));
        }

        if (!isset($_POST['firstname']) ||
            empty($_POST['firstname']) ||
            !isset($_POST['lastname']) ||
            empty($_POST['lastname']) ||
            !isset($_POST['email']) ||
            empty($_POST['email']) ||
            !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {

            throw new Exception(_('A mandatory field is missing!'));
        }

        $firstname = $Users->purifyFirstname($_POST['firstname']);
        $lastname = $Users->purifyLastname($_POST['lastname']);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        if ($Users->isDuplicateEmail($email) && ($email != $me['email'])) {
            throw new Exception(_('Someone is already using that email address!'));
        }

        // Check phone
        if (isset($_POST['phone']) && !empty($_POST['phone'])) {
            $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);
        } else {
            $phone = null;
        }
        // Check cellphone
        if (isset($_POST['cellphone']) && !empty($_POST['cellphone'])) {
            $cellphone = filter_var($_POST['cellphone'], FILTER_SANITIZE_STRING);
        } else {
            $cellphone = null;
        }
        // Check skype
        if (isset($_POST['skype']) && !empty($_POST['skype'])) {
            $skype = filter_var($_POST['skype'], FILTER_SANITIZE_STRING);
        } else {
            $skype = null;
        }
        // Check website
        if (isset($_POST['website']) && !empty($_POST['website'])) {
            if (filter_var($_POST['website'], FILTER_VALIDATE_URL)) {
                $website = $_POST['website'];
            } else { // does not validate as url
                throw new Exception(_('A mandatory field is missing!'));
            }
        } else {
            $website = null;
        }

        // PASSWORD CHANGE
        if (isset($_POST['cnewpass']) &&
            !empty($_POST['cnewpass']) &&
            isset($_POST['newpass']) &&
            !empty($_POST['newpass'])) {

            $password = $_POST['newpass'];
            $cpassword = $_POST['cnewpass'];

            // check confirmation password
            if (strcmp($password, $cpassword) != 0) {
                throw new Exception(_('The passwords do not match!'));
            }

            // DEMO BLOCK
            $password = 'demodemo';
            // END DEMO BLOCK

            // UPDATE PASSWORD
            $Users->updatePassword($password);
        }

        // SQL for update preferences
        $sql = "UPDATE users SET
            email = :email,
            firstname = :firstname,
            lastname = :lastname,
            phone = :phone,
            cellphone = :cellphone,
            skype = :skype,
            website = :website
            WHERE userid = :userid";
        $req = $pdo->prepare($sql);
        $result = $req->execute(array(
            'email' => $email,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'phone' => $phone,
            'cellphone' => $cellphone,
            'skype' => $skype,
            'website' => $website,
            'userid' => $_SESSION['userid']));
        if (!$result) {
            throw new Exception(Tools::error());
        }

        $_SESSION['ok'][] = _('Profile updated.');

    }// END TAB 2

    // TAB 3 : EXPERIMENTS TEMPLATES
    // add new tpl
    if (isset($_POST['new_tpl_form'])) {
        $tab = '3';

        // do nothing if the template name is empty
        if (empty($_POST['new_tpl_name'])) {
            throw new Exception(_('You must specify a name for the template!'));
        }
        // template name must be 3 chars at least
        if (strlen($_POST['new_tpl_name']) < 3) {
            throw new Exception(_('The template name must be 3 characters long.'));
        }

        $tpl_name = filter_var($_POST['new_tpl_name'], FILTER_SANITIZE_STRING);
        $tpl_body = Tools::checkBody($_POST['new_tpl_body']);

        $Templates = new Templates($_SESSION['team_id']);
        if (!$Templates->create($tpl_name, $tpl_body, $_SESSION['userid'])) {
            throw new Exception(Tools::error());
        }
        $_SESSION['ok'][] = _('Experiment template successfully added.');
    }

    // edit templates
    if (isset($_POST['tpl_form'])) {
        $tab = '3';

        $tpl_id = array();
        foreach ($_POST['tpl_id'] as $id) {
            $tpl_id[] = $id;
        }
        $new_tpl_body = array();
        foreach ($_POST['tpl_body'] as $body) {
            $new_tpl_body[] = $body;
        }
        $new_tpl_name = array();
        foreach ($_POST['tpl_name'] as $name) {
            $new_tpl_name[] = $name;
        }
        $new_tpl_body[] = filter_var($_POST['tpl_body'], FILTER_SANITIZE_STRING);
        $new_tpl_name[] = filter_var($_POST['tpl_name'], FILTER_SANITIZE_STRING);
        $sql = "UPDATE experiments_templates SET
            body = :body,
            name = :name
            WHERE userid = :userid AND id = :id";
        $req = $pdo->prepare($sql);
        $cnt = count($_POST['tpl_body']);
        for ($i = 0; $i < $cnt; $i++) {
            $req->execute(array(
                'id' => $tpl_id[$i],
                'body' => $new_tpl_body[$i],
                'name' => $new_tpl_name[$i],
                'userid' => $_SESSION['userid']
            ));
        }
        $_SESSION['ok'][] = _('Template successfully edited.');
    }

} catch (Exception $e) {
    $_SESSION['ko'][] = $e->getMessage();
} finally {
    header("location: ../ucp.php?tab=" . $tab);
}
