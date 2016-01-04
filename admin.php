<?php
/**
 * admin.php
 *
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see http://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

/**
 * Administration of a team
 *
 */
require_once 'inc/common.php';

// only admin can use this
if ($_SESSION['is_admin'] != 1) {
    die(_('This section is out of your reach.'));
}

$formKey = new \Elabftw\Elabftw\FormKey();
$crypto = new \Elabftw\Elabftw\CryptoWrapper();

$page_title = _('Admin panel');
$selected_menu = null;
require_once 'inc/head.php';
?>
<script src="js/tinymce/tinymce.min.js"></script>
<link href="js/colorpicker/jquery.colorpicker.css" rel="stylesheet" type="text/css" />
<?php
// MAIN SQL FOR USERS
$sql = "SELECT * FROM users WHERE validated = :validated AND team = :team";
$user_req = $pdo->prepare($sql);
$user_req->bindValue(':validated', 0);
$user_req->bindValue(':team', $_SESSION['team_id']);
$user_req->execute();
$count = $user_req->rowCount();
// only show the frame if there is some users to validate and there is an email config
if ($count > 0 && strlen(get_config('mail_from')) > 0) {
    $message = _('There are users waiting for validation of their account:');
    $message .= "<form method='post' action='app/admin-exec.php'>";
    $message .= $formKey->getFormkey();
    $message .= "<ul>";
    while ($data = $user_req->fetch()) {
        $message .= "<li><label>
            <input type='checkbox' name='validate[]'
            value='".$data['userid'] . "'> " . $data['firstname'] . " " . $data['lastname'] . " (" . $data['email'] . ")
            </label></li>";
    }
    $message .= "</ul><div class='center'>
    <button class='button' type='submit'>"._('Submit') . "</button></div>";
    display_message('error', $message);
    echo "</form>";
}

// get the team config
$team = get_team_config();

$stamppass = '';
if (!empty($team['stamppass'])) {
    try {
        $stamppass = $crypto->decrypt($team['stamppass']);
    } catch (Exception $e) {
        $stamppass = '';
    }
}
?>


<menu>
    <ul>
    <li class='tabhandle' id='tab1'><?php echo _('Team'); ?></li>
        <li class='tabhandle' id='tab2'><?php echo _('Users'); ?></li>
        <li class='tabhandle' id='tab3'><?php echo ngettext('Status', 'Status', 2); ?></li>
        <li class='tabhandle' id='tab4'><?php echo _('Types of items'); ?></li>
        <li class='tabhandle' id='tab5'><?php echo _('Experiments template'); ?></li>
        <li class='tabhandle' id='tab6'><?php echo _('Import CSV'); ?></li>
        <li class='tabhandle' id='tab7'><?php echo _('Import ZIP'); ?></li>
        <li class='tabhandle' id='tab8'><?php echo _('Groups'); ?></li>
    </ul>
</menu>

<!-- TAB 1 -->
<div class='divhandle' id='tab1div'>

<h3><?php echo _('Configure your team'); ?></h3>
    <form method='post' action='app/admin-exec.php' autocomplete='off'>
        <p>
        <label for='deletable_xp'><?php echo _('Users can delete experiments:'); ?></label>
        <select name='deletable_xp' id='deletable_xp'>
            <option value='1'<?php
                if ($team['deletable_xp'] == 1) { echo " selected='selected'"; } ?>
                    ><?php echo _('Yes'); ?></option>
            <option value='0'<?php
                    if ($team['deletable_xp'] == 0) { echo " selected='selected'"; } ?>
                        ><?php echo _('No'); ?></option>
        </select>
        <span class='smallgray'><?php echo _('An admin account will always be able to delete experiments.'); ?></span>
        </p>
        <p>
        <label for='link_name'><?php echo _('Name of the link in the top menu:'); ?></label>
        <input type='text' value='<?php echo $team['link_name']; ?>' name='link_name' id='link_name' />
        </p>
        <p>
        <label for='link_href'><?php echo _('Address where this link should point:'); ?></label>
        <input type='text' value='<?php echo $team['link_href']; ?>' name='link_href' id='link_href' />
        </p>
        <p>
        <label for='stampprovider'><?php echo _('URL for external timestamping service:'); ?></label>
        <input type='url' value='<?php echo $team['stampprovider']; ?>' name='stampprovider' id='stampprovider' />
        <span class='smallgray'><?php echo _('This should be the URL used for <a href="https://tools.ietf.org/html/rfc3161">RFC 3161</a>-compliant timestamping requests.'); ?></span>
        </p>
        <p>
        <label for='stampcert'><?php echo _('Chain of certificates of the external timestamping service:'); ?></label>
        <input type='text' placeholder='vendor/pki.dfn.pem' value='<?php echo $team['stampcert']; ?>' name='stampcert' id='stampcert' />
        <span class='smallgray'><?php echo _('This should point to the chain of certificates used by your external timestamping provider to sign the timestamps.<br /> Local path relative to eLabFTW installation directory. The file needs to be in <a href="https://en.wikipedia.org/wiki/Privacy-enhanced_Electronic_Mail">PEM-encoded (ASCII)</a> format!'); ?></span>
        </p>
        <label for='stamplogin'><?php echo _('Login for external timestamping service:'); ?></label>
        <input type='text' value='<?php echo $team['stamplogin']; ?>' name='stamplogin' id='stamplogin' />
        <span class='smallgray'><?php echo _('This should be the login associated with your timestamping service provider'); ?></span>
        </p>
        <p>
        <label for='stamppass'><?php echo _('Password for external timestamping service:'); ?></label>
        <input type='password' value='<?php echo $stamppass; ?>' name='stamppass' id='stamppass' />
        <span class='smallgray'><?php echo _('Your timestamping service provider password'); ?></span>
        </p>
        <div class='center'>
            <button type='submit' name='submit_config' class='submit button'>Save</button>
        </div>
    </form>

</div>

<!-- TAB 2 USERS -->
<div class='divhandle' id='tab2div'>

    <h3><?php echo _('Edit users'); ?></h3>
    <ul class='list-group'>
    <?php
    // we show only the validated users here
    $user_req->bindValue(':validated', 1);
    $user_req->execute();
    while ($users = $user_req->fetch()) {
        ?>
            <li class='list-group-item'>
            <a class='trigger_users_<?php echo $users['userid']; ?>'><?php echo $users['firstname'] . " " . $users['lastname']; ?></a>
            <div class='toggle_users_<?php echo $users['userid']; ?>'>
        <br>
                <form method='post' action='app/admin-exec.php' id='admin_user_form'>
                    <input type='hidden' value='<?php echo $users['userid']; ?>' name='userid' />
                    <label class='block' for='edituser_firstname'><?php echo _('Firstname'); ?></label>
                    <input  id='edituser_firstname' type='text' value='<?php echo $users['firstname']; ?>' name='firstname' />
                    <label class='block' for='edituser_lastname'><?php echo _('Lastname'); ?></label>
                    <input  id='edituser_lastname' type='text' value='<?php echo $users['lastname']; ?>' name='lastname' />
                    <label class='block' for='edituser_username'><?php echo _('Username'); ?></label>
                    <input  id='edituser_username' type='text' value='<?php echo $users['username']; ?>' name='username' />
                    <label class='block' for='edituser_email'><?php echo _('Email'); ?></label>
                    <input id='edituser_email' type='email' value='<?php echo $users['email']; ?>' name='email' /><br>
        <br>
        <label for='validated'><?php echo _('Has an active account?'); ?></label>
        <select name='validated' id='validated'>
            <option value='1'<?php
                    if ($users['validated'] == 1) { echo " selected='selected'"; } ?>
                        ><?php echo _('Yes'); ?></option>
            <option value='0'<?php
                if ($users['validated'] == 0) { echo " selected='selected'"; } ?>
                    ><?php echo _('No'); ?></option>
        </select>
        <br>
        <label for='usergroup'><?php echo _('Group:'); ?></label>
        <select name='usergroup' id='usergroup'>
<?php
            if ($_SESSION['is_sysadmin'] == 1) {
?>
                <option value='1'<?php
                        if ($users['usergroup'] == 1) { echo " selected='selected'"; } ?>
                >Sysadmins</option>
<?php
            }
?>
            <option value='2'<?php
                    if ($users['usergroup'] == 2) { echo " selected='selected'"; } ?>
            >Admins</option>
            <option value='3'<?php
                    if ($users['usergroup'] == 3) { echo " selected='selected'"; } ?>
            >Admin + Lock power</option>
            <option value='4'<?php
                    if ($users['usergroup'] == 4) { echo " selected='selected'"; } ?>
            >Users</option>
        </select>
        <br>
        <label for='users_reset_password'><?php echo _('Reset user password:'); ?></label>
        <input id='users_reset_password' type='password' value='' name='new_password' />
        <br>
        <br>
        <div class='center'>
        <button type='submit' class='button'><?php echo _('Edit this user'); ?></button>
        </div>
            </form>
        <script>
                $(".toggle_users_<?php echo $users['userid']; ?>").hide();
                $("a.trigger_users_<?php echo $users['userid']; ?>").click(function(){
                    $('div.toggle_users_<?php echo $users['userid']; ?>').slideToggle(1);
                });
        </script>
        </div>
        </li>
        <?php
    }
    ?>

<!-- DELETE USER -->
<ul class='list-group'>
<li class='list-group-item' style='border-color:red;background-color:#FFC1B7;'>
    <h3><?php echo _('DANGER ZONE'); ?></h3>
    <h4><strong><?php echo _('Delete an account'); ?></strong></h4>
    <form action='app/admin-exec.php' method='post'>
        <!-- form key -->
        <?php echo $formKey->getFormkey(); ?>
        <label for='delete_user'><?php echo _('Type EMAIL ADDRESS of a member to delete this user and all his experiments/files forever:'); ?></label>
        <input type='email' name='delete_user' id='delete_user' required />
        <br>
        <br>
        <label for='delete_user_confpass'><?php echo _('Type your password:'); ?></label>
        <input type='password' name='delete_user_confpass' id='delete_user_confpass' required />
    <div class='center'>
        <button type='submit' class='button submit'><?php echo _('Delete this user!'); ?></button>
    </div>
    </form>
</li>
</ul>

</div>

<!-- TAB 3 STATUS -->
<div class='divhandle' id='tab3div'>
    <h3><?php echo _('Edit an existing status'); ?></h3>
    <ul class='draggable sortable_status list-group'>

    <?php
    // SQL to get all status
    $sql = "SELECT * from status WHERE team = :team ORDER BY ordering ASC";
    $req = $pdo->prepare($sql);
    $req->bindParam(':team', $_SESSION['team_id'], PDO::PARAM_INT);
    $req->execute();

    while ($status = $req->fetch()) {
        // count the experiments with this status
        // don't allow deletion if experiments with this status exist
        // but instead display a message to explain
        $count_exp_sql = "SELECT COUNT(*) FROM experiments WHERE status = :status AND team = :team";
        $count_exp_req = $pdo->prepare($count_exp_sql);
        $count_exp_req->bindParam(':status', $status['id'], PDO::PARAM_INT);
        $count_exp_req->bindParam(':team', $_SESSION['team_id'], PDO::PARAM_INT);
        $count_exp_req->execute();
        $count = $count_exp_req->fetchColumn();
        ?>

        <li id='status_<?php echo $status['id']; ?>' class='list-group-item'>
        <a class='trigger_status_<?php echo $status['id']; ?>'><?php echo $status['name']; ?></a>
        <div class='toggle_container_status_<?php echo $status['id']; ?>'>
        <?php
        if ($count == 0) {
            ?>
            <img class='align_right' src='img/small-trash.png' title='delete' alt='delete' onClick="deleteThis('<?php echo $status['id']; ?>','status', 'admin.php')" />
        <?php
        } else {
            ?>
                <img class='align_right' src='img/small-trash.png' title='delete' alt='delete' onClick="alert('<?php echo _('Remove all experiments with this status before deleting this status.'); ?>')" />
        <?php
        }
        ?>

        <form action='app/admin-exec.php' method='post'>
            <input type='text' name='status_name' value='<?php echo stripslashes($status['name']); ?>' />
            <label for='default_checkbox'><?php echo _('Default status'); ?></label>
            <input type='checkbox' name='status_is_default' id='default_checkbox'
            <?php
            // check the box if the status is already default
            if ($status['is_default'] == 1) {
                echo " checked";
            }
            ?>>
            <div id='colorwheel_div_edit_status_<?php echo $status['id']; ?>'>
            <input class='colorpicker' type='text' name='status_color' value='<?php echo $status['color']; ?>' />
            </div>
            <input type='hidden' name='status_id' value='<?php echo $status['id']; ?>' />
            <br>

            <div class='center'>
                <button type='submit' class='button'><?php echo _('Edit') . ' ' . stripslashes($status['name']); ?></button><br>
            </div>
        </form>
        <script>$(document).ready(function() {
            $(".toggle_container_status_<?php echo $status['id']; ?>").hide();
            $("a.trigger_status_<?php echo $status['id']; ?>").click(function(){
                $('div.toggle_container_status_<?php echo $status['id']; ?>').slideToggle(100);
                // disable sortable behavior
                $('.sortable_status').sortable("disable");
            });
        });</script></div></li>
        <?php
    }
    ?>
</ul>

<!-- ADD NEW STATUS -->
<ul class='list-group'>
<li class='list-group-item'>
    <form action='app/admin-exec.php' method='post'>
        <label for='new_status_name'><?php echo _('Add a new status'); ?></label>
        <input type='text' id='new_status_name' name='new_status_name' required />
        <div id='colorwheel_div_new_status'>
            <input class='colorpicker' type='text' name='new_status_color' value='000000' />
        </div>
        <div class='center'>
            <button type='submit' class='submit button'><?php echo _('Save'); ?></button>
        </div>
        <br>
    </form>
</li>
</ul>

</div>

<!-- TAB 4 ITEMS TYPES-->
<div class='divhandle' id='tab4div'>
    <h3><?php echo _('Database items types'); ?></h3>
    <ul class='draggable sortable_itemstypes list-group'>

    <?php
    // SQL to get all items type
    $sql = "SELECT * from items_types WHERE team = :team ORDER BY ordering ASC";
    $req = $pdo->prepare($sql);
    $req->execute(array(
        'team' => $_SESSION['team_id']
    ));

    while ($items_types = $req->fetch()) {
        ?>
        <li id='itemstypes_<?php echo $items_types['id']; ?>' class='list-group-item'>
            <a class='trigger_<?php echo $items_types['id']; ?>'><?php echo _('Edit') . ' ' . $items_types['name']; ?></a>
            <div class='toggle_container_<?php echo $items_types['id']; ?>'>
            <?php
            // count the items with this type
            // don't allow deletion if items with this type exist
            // but instead display a message to explain
            $count_db_sql = "SELECT COUNT(*) FROM items WHERE type = :type";
            $count_db_req = $pdo->prepare($count_db_sql);
            $count_db_req->bindParam(':type', $items_types['id'], PDO::PARAM_INT);
            $count_db_req->execute();
            $count = $count_db_req->fetchColumn();
            if ($count == 0) {
                ?>
                <img class='align_right' src='img/small-trash.png' title='delete' alt='delete' onClick="deleteThis('<?php echo $items_types['id']; ?>','item_type', 'admin.php')" />
            <?php
            } else {
                ?>
                <img class='align_right' src='img/small-trash.png' title='delete' alt='delete' onClick="alert('<?php echo _('Remove all database items with this type before deleting this type.'); ?>')" />
            <?php
            }
            ?>

            <form action='app/admin-exec.php' method='post'>
            <label><?php echo _('Edit name'); ?></label>
                <input required type='text' name='item_type_name' value='<?php echo stripslashes($items_types['name']); ?>' />
                <input type='hidden' name='item_type_id' value='<?php echo $items_types['id']; ?>' />

                <div id='colorwheel_div_<?php echo $items_types['id']; ?>'>
            <label><?php echo _('Edit color'); ?></label>
                    <input class='colorpicker' type='text' style='display:inline' name='item_type_bgcolor' value='<?php echo $items_types['bgcolor']; ?>'/>
                </div>
                <textarea class='mceditable' name='item_type_template' /><?php echo stripslashes($items_types['template']); ?></textarea><br>
                <div class='center'>
                    <button type='submit' class='button'><?php echo _('Edit') . ' ' . stripslashes($items_types['name']); ?></button><br>
                </div>
            </form>

        <script>$(document).ready(function() {
            $(".toggle_container_<?php echo $items_types['id']; ?>").hide();
            $("a.trigger_<?php echo $items_types['id']; ?>").click(function(){
                $('div.toggle_container_<?php echo $items_types['id']; ?>').slideToggle(100);
                // disable sortable behavior
                $('.sortable_itemstypes').sortable("disable");
            });
        });</script>
        </div>
        </li>
        <?php
    } // end generation of items_types
    ?>

</ul>

<!-- ADD NEW TYPE OF ITEM -->
<ul class='list-group'>
<li class='list-group-item'>
    <form action='app/admin-exec.php' method='post'>
        <label for='new_item_type_name'><?php echo _('Add a new type of item:'); ?></label>
        <input required type='text' id='new_item_type_name' name='new_item_type_name' />
        <input type='hidden' name='new_item_type' value='1' />
        <div id='colorwheel_div_new'>
            <label><?php echo _('Edit color'); ?></label>
            <input class='colorpicker' type='text' name='new_item_type_bgcolor' value='000000' />
        </div>
        <textarea class='mceditable' name='new_item_type_template' /></textarea>
        <div class='center submitButtonDiv'>
        <button type='submit' class='button'><?php echo _('Save'); ?></button>
        </div>
    </form>
</li>
</ul>

</div>

<!-- TAB 5 -->
<div class='divhandle' id='tab5div'>

    <h3><?php echo _('Common experiment template'); ?></h3>
    <?php
    // get what is the default experiment template
    $sql = "SELECT body FROM experiments_templates WHERE userid = 0 AND team = :team LIMIT 1";
    $req = $pdo->prepare($sql);
    $req->bindParam(':team', $_SESSION['team_id'], PDO::PARAM_INT);
    $req->execute();
    $exp_tpl = $req->fetch();
    ?>
    <p><?php echo _('This is the default text when someone creates an experiment.'); ?></p>
    <form action='app/admin-exec.php' method='post'>
        <input type='hidden' name='default_exp_tpl' value='1' />
        <textarea class='mceditable' name='default_exp_tpl' />
        <?php
        echo $exp_tpl['body'];
        ?></textarea>
        <div class='center submitButtonDiv'>
        <button type='submit' class='button'><?php echo _('Edit'); ?></button>
        </div>
    </form>
</div>

<!-- TAB 6 -->
<div class='divhandle' id='tab6div'>

    <h3><?php echo _('Import a CSV file'); ?></h3>
    <?php

    // file upload block
    // show select of type
    // SQL to get items names
    $sql = "SELECT * FROM items_types WHERE team = :team";
    $req = $pdo->prepare($sql);
    $req->bindParam(':team', $_SESSION['team_id'], PDO::PARAM_INT);
    $req->execute();
    ?>
        <p style='text-align:justify'><?php echo _("This page will allow you to import a .csv (Excel spreadsheet) file into the database.<br>First you need to open your .xls/.xlsx file in Excel or Libreoffice and save it as .csv.<br>In order to have a good import, the first row should be the column's field names. You can make a tiny import of 3 lines to see if everything works before you import a big file."); ?>
<span class='strong'><?php echo _('You should make a backup of your database before importing thousands of items!'); ?></span></p>

        <label for='item_selector'><?php echo _('1. Select a type of item to import to:'); ?></label>
        <select id='item_selector' onchange='goNext(this.value)'><option value=''>--------</option>
        <?php
        while ($items_types = $req->fetch()) {
            echo "<option value='" . $items_types['id'] . "' name='type' ";
            echo ">" . $items_types['name'] . "</option>";
        }
        ?>
        </select><br>
        <div class='import_block'>
        <form enctype="multipart/form-data" action="app/import.php" method="POST">
        <label for='uploader'><?php echo _('2. Select a CSV file to import:'); ?></label>
            <input id='uploader' name="file" type="file" accept='.csv' />
            <input name='type' type='hidden' value='csv' />
            <div class='center'>
            <button type="submit" class='button' value="Upload"><?php echo _('Import CSV'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- TAB 7 -->
<div class='divhandle' id='tab7div'>

    <h3><?php echo _('Import a ZIP file'); ?></h3>
    <?php

    // file upload block
    // show select of type
    // SQL to get items names
    $sql = "SELECT * FROM items_types WHERE team = :team";
    $req = $pdo->prepare($sql);
    $req->bindParam(':team', $_SESSION['team_id'], PDO::PARAM_INT);
    $req->execute();

    // sql to get team members names
    $sql = "SELECT firstname, lastname, userid FROM users WHERE team = :team";
    $reqTeam = $pdo->prepare($sql);
    $reqTeam->bindParam(':team', $_SESSION['team_id'], PDO::PARAM_INT);
    $reqTeam->execute();

    ?>
        <p style='text-align:justify'><?php echo _("This page will allow you to import a .elabftw.zip archive."); ?>
<br><span class='strong'><?php echo _('You should make a backup of your database before importing thousands of items!'); ?></span></p>

        <label for='item_selector'><?php echo _('1. Select where to import:'); ?></label>
        <select id='item_selector' onchange='goNext(this.value)'><option value='' disabled>Import items</option>
        <?php
        while ($items_types = $req->fetch()) {
            echo "<option value='" . $items_types['id'] . "' name='type' ";
            echo ">" . $items_types['name'] . "</option>";
        }
        echo "<option value='' disabled>Import experiments</option>";

        while ($users = $reqTeam->fetch()) {
            echo "<option value='" . $users['userid'] . "' name='type' ";
            echo ">" . $users['firstname'] . " " . $users['lastname'] . "</option>";
        }
        ?>
        </select><br>
        <div class='import_block'>
        <form enctype="multipart/form-data" action="app/import.php" method="POST">
        <label for='uploader'><?php echo _('2. Select a ZIP file to import:'); ?></label>
            <input id='uploader' name="file" type="file" accept='.elabftw.zip' />
            <input name='type' type='hidden' value='zip' />
            <div class='center'>
            <button type="submit" class='button' value="Upload"><?php echo _('Import ZIP'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- TAB 8 -->
<div class='divhandle' id='tab8div'>
    <h3><?php echo _('Manage groups of users'); ?></h3>
<!-- CREATE A GROUP -->
<label for='create_teamgroup'><?php echo _('Create a group'); ?></label>
    <input id='create_teamgroup' name="create_teamgroup" type="text" />
    <button type='submit' onclick='createTeamgroup()' class='button'><?php echo _('Create'); ?></button>
<!-- END CREATE GROUP -->

<?php
// get a list of team_groups for this team
$sql = "SELECT * FROM team_groups WHERE team = :team";
$team_groups_req = $pdo->prepare($sql);
$team_groups_req->bindParam(':team', $_SESSION['team_id']);
$team_groups_req->execute();
// get all users again

echo "<div id='team_groups_div'>";
if ($team_groups_req->rowCount() > 0) {
    $user_req->execute();
    ?>
    <div class='well'>
    <section>
    <!-- ADD USER TO GROUP -->
        <label for='add_teamgroup_user'><?php echo _('Add this user'); ?></label>
        <select name='add_teamgroup_user' id='add_teamgroup_user'>
        <?php
        while ($users = $user_req->fetch()) {
            echo "<option value='" . $users['userid'] . "'>";
            echo $users['firstname'] . " " . $users['lastname'] . "</option>";
        }
        ?>
        </select>

        <label for='add_teamgroup_group'><?php echo _('to this group'); ?></label>
        <select name='add_teamgroup_group' id='add_teamgroup_group'>
        <?php
        while ($team_groups = $team_groups_req->fetch()) {
            echo "<option value='" . $team_groups['id'] . "'>";
            echo $team_groups['name'] . "</option>";
        }
        ?>
        </select>
        <button type="submit" onclick="manageTeamgroup('add')" class='button'><?php echo _('Go'); ?></button>

    </section>
    <section>
    <!-- RM USER FROM GROUP -->
        <label for='rm_teamgroup_user'><?php echo _('Remove this user'); ?></label>
        <select name='rm_teamgroup_user' id='rm_teamgroup_user'>
        <?php
        $user_req->execute();
        while ($users = $user_req->fetch()) {
            echo "<option value='" . $users['userid'] . "'>";
            echo $users['firstname'] . " " . $users['lastname'] . "</option>";
        }
        ?>
        </select>

        <label for='rm_teamgroup_group'><?php echo _('from this group'); ?></label>
        <select name='rm_teamgroup_group' id='rm_teamgroup_group'>
        <?php
        $team_groups_req->execute();
        while ($team_groups = $team_groups_req->fetch()) {
            echo "<option value='" . $team_groups['id'] . "'>";
            echo $team_groups['name'] . "</option>";
        }
        ?>
        </select>
        <button type="submit" onclick="manageTeamgroup('rm')" class='button'><?php echo _('Go'); ?></button>
    </section>
    </div>

    <?php
    // show available team groups
    echo "<h3>" . _('Existing groups') . "</h3>";
    $sql = "SELECT DISTINCT users.firstname, users.lastname FROM users CROSS JOIN users2team_groups ON (users2team_groups.userid = users.userid AND users2team_groups.groupid = :groupid)";
    $team_groups_req->execute();
    while ($res = $team_groups_req->fetch()) {
        echo "<div class='well'><img onclick='deleteTeamgroup(" . $res['id'] . ")' src='img/small-trash.png' style='float:right' alt='trash' title='Remove this group' /><h3 class='inline editable teamgroup_name' id='teamgroup_" . $res['id'] . "'>" . $res['name'] . "</h3><ul>";
        $req2 = $pdo->prepare($sql);
        $req2->bindParam(':groupid', $res['id']);
        $req2->execute();
        while ($res2 = $req2->fetch()) {
            echo "<li>" . $res2['firstname'] . " " . $res2['lastname'] . "</li>";
        }
        echo "</ul></div>";
    }
}
?>


    </div>
</div>

<script>
// TEAM GROUP
function manageTeamgroup(action) {
    if (action === 'add') {
        var userid = $('#add_teamgroup_user').val();
        var groupid = $('#add_teamgroup_group').val();
    } else {
        var userid = $('#rm_teamgroup_user').val();
        var groupid = $('#rm_teamgroup_group').val();
    }
    $.post('app/admin-ajax.php', {
        action: action,
        teamgroup_user: userid,
        teamgroup_group: groupid
    }).success(function () {
        $('#team_groups_div').load('admin.php #team_groups_div');
    })
}

function createTeamgroup() {
    var name = $('#create_teamgroup').val();
    if (name.length > 0) {
        $.post('app/admin-ajax.php', {
            create_teamgroup: name
        }).success(function () {
            $('#team_groups_div').load('admin.php #team_groups_div');
            $('#create_teamgroup').val('');
        })
    }
}
function deleteTeamgroup(id) {
    var you_sure = confirm('<?php echo _('Delete this?'); ?>');
    if (you_sure == true) {
        $.post('app/delete.php', {
            type: 'teamgroup',
            id: id
        }).success(function () {
            $("#team_groups_div").load("admin.php #team_groups_div");
        })
    }
    return false;
}
// END TEAM GROUP

// used on import csv/zip to go to next step
function goNext(x) {
    if(x == '') {
        return;
    }
    document.cookie = 'itemType='+x;
    $('.import_block').show();
}

$(document).ready(function() {
    // validate on enter
    $('#create_teamgroup').keypress(function (e) {
        var keynum;
        if (e.which) {
            keynum = e.which;
        }
        if (keynum == 13) { // if the key that was pressed was Enter (ascii code 13)
            createTeamgroup();
        }
    });
    // edit the team group name
    $('h3.teamgroup_name').editable('app/admin-ajax.php', {
     tooltip : 'Click to edit',
     indicator : 'Saving...',
     name : 'teamgroup',
     submit : 'Save',
     cancel : 'Cancel',
     style : 'display:inline'

    });
    // SORTABLE for STATUS
    $('.sortable_status').sortable({
        // limit to horizontal dragging
        axis : 'y',
        helper : 'clone',
        // do ajax request to update db with new order
        update: function(event, ui) {
            // send the orders as an array
            var ordering = $(".sortable_status").sortable("toArray");

            $.post("app/order.php", {
                'ordering_status' : ordering
            }).done(showSaved());
        }
    });

    // SORTABLE for ITEMS TYPES
    $('.sortable_itemstypes').sortable({
        // limit to horizontal dragging
        axis : 'y',
        helper : 'clone',
        // do ajax request to update db with new order
        update: function(event, ui) {
            // send the orders as an array
            var ordering = $(".sortable_itemstypes").sortable("toArray");

            $.post("app/order.php", {
                'ordering_itemstypes' : ordering
            }).done(showSaved());
        }
    });
    // IMPORT
    $('.import_block').hide();

    // TABS
    // get the tab=X parameter in the url
    var params = getGetParameters();
    var tab = parseInt(params['tab']);
    if (!isInt(tab)) {
        var tab = 1;
    }
    var initdiv = '#tab' + tab + 'div';
    var inittab = '#tab' + tab;
    // init
    $(".divhandle").hide();
    $(initdiv).show();
    $(inittab).addClass('selected');

    $(".tabhandle" ).click(function(event) {
        var tabhandle = '#' + event.target.id;
        var divhandle = '#' + event.target.id + 'div';
        $(".divhandle").hide();
        $(divhandle).show();
        $(".tabhandle").removeClass('selected');
        $(tabhandle).addClass('selected');
    });
    // END TABS
    // TOGGLE
    $(".toggle_users_<?php echo $users['userid']; ?>").hide();
    $("a.trigger_users_<?php echo $users['userid']; ?>").click(function(){
        $('div.toggle_users_<?php echo $users['userid']; ?>').slideToggle(1);
    });
    // COLORPICKER
    $('.colorpicker').colorpicker({
        hsv: false,
        okOnEnter: true,
        rgb: false
    });
    // EDITOR
    tinymce.init({
        mode : "specific_textareas",
        editor_selector : "mceditable",
        content_css : "css/tinymce.css",
        plugins : "table textcolor searchreplace code fullscreen insertdatetime paste charmap save image link",
        toolbar1: "undo redo | bold italic underline | fontsizeselect | alignleft aligncenter alignright alignjustify | superscript subscript | bullist numlist outdent indent | forecolor backcolor | charmap | link",
        removed_menuitems : "newdocument",
        language : '<?php echo $_SESSION['prefs']['lang']; ?>'
    });
});
</script>
<?php require_once 'inc/footer.php';
