<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2022 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Controllers;

use Elabftw\Elabftw\UserParams;
use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Models\Config;
use Elabftw\Models\Teams;
use Elabftw\Models\Users;
use Elabftw\Services\Check;

class UsersController
{
    // the user doing the request
    private Users $requester;

    public function __construct(private Users $target, private array $reqBody)
    {
        $this->requester = $target->requester;
        // a normal user can only access their own user
        // you need to be at least admin to access another user
        // TODO when we implement the @firstname autocompletion for notification, normal users will need to access a stripped down version of user list
        // maybe it'll be a custom function instead of normal get filtered
        if ($this->requester->userData['is_admin'] !== 1 && $this->target->userid !== $this->target->userData['userid']) {
            throw new IllegalActionException('This endpoint requires admin privileges to access other users.');
        }
        // check we edit user of our team, unless we are sysadmin and we can access it
        if ($this->target->userid !== null && !$this->requester->isAdminOf($this->target->userid)) {
            throw new IllegalActionException('User tried to access user from other team.');
        }
    }

    /**
     * Create a user from admin/sysadmin panels
     */
    public function create(): int
    {
        // only support creation of user in one team for now
        $team = $this->reqBody['team'];
        $teams = array('id' => $team);

        if ($this->requester->userData['is_sysadmin'] !== 1) {
            $Config = Config::getConfig();
            // check for instance setting allowing/disallowing creation of users by admins
            if ($Config->configArr['admins_create_users'] === '0') {
                throw new IllegalActionException('Admin tried to create user but user creation is disabled for admins.');
            }
            // check if we are admin of the correct team
            $Teams = new Teams($this->requester);
            if ($Teams->hasCommonTeamWithCurrent($this->requester->userid, $team) === false) {
                throw new IllegalActionException('Admin tried to create user in a team where they are not admin.');
            }
        }
        // check if we are admin the team is ours
        // a sysadmin is free to use any team
        if ($this->requester->userData['is_sysadmin'] === 0) {
            // note: from REST API call the team is not set!! TODO FIXME
            // force using our own team
            // make a isAdminOfTeam()
            $teams = array('id' => $this->requester->userData['team']);
        }
        return $this->target->createOne(
            (new UserParams($this->reqBody['email'], 'email'))->getContent(),
            $teams,
            (new UserParams($this->reqBody['firstname'], 'firstname'))->getContent(),
            (new UserParams($this->reqBody['lastname'], 'lastname'))->getContent(),
            // password is never set by admin/sysadmin
            '',
            $this->checkUsergroup(),
            // automatically validate user
            true,
            // don't alert admin
            false,
        );
    }

    private function checkUsergroup(): int
    {
        $usergroup = Check::usergroup((int) $this->reqBody['usergroup']);
        if ($usergroup === 1 && $this->requester->userData['is_sysadmin'] !== 1) {
            throw new ImproperActionException('Only a sysadmin can promote another user to sysadmin.');
        }
        // a non sysadmin cannot demote a sysadmin
        if (isset($this->target->userData['is_sysadmin']) && $this->target->userData['is_sysadmin'] === 1 &&
            $usergroup !== 1 &&
            $this->requester->userData['is_sysadmin'] !== 1) {
            throw new ImproperActionException('Only a sysadmin can demote another sysadmin.');
        }
        return $usergroup;
    }
}
