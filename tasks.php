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
$page_title = _('Tasks');
$selected_menu = 'Tasks';
require_once 'inc/common.php';
require_once 'inc/locale.php';
require_once 'inc/head.php';
require_once 'inc/info_box.php';

// MAIN SWITCH
if (!isset($_GET['mode']) || (empty($_GET['mode'])) || ($_GET['mode'] === 'show')) {
  require_once 'inc/showTask.php';
} elseif ($_GET['mode'] === 'edit') {
  require_once 'inc/editTask.php';
} elseif ($_GET['mode'] === 'view') {
  require_once 'inc/viewTask.php';
} else {
  printf(_("There was an unexpected problem! Please %sopen an issue on GitHub%s if you think this is a bug."), "<a href='https://github.com/NicolasCARPi/elabftw/issues/'>", "</a>");
  }



require_once 'inc/footer.php';
