<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2023 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\AuditEvent;

use Elabftw\Enums\Usergroup;

class TeamAddition extends AbstractUsers2TeamsModifiedEvent
{
    public function __construct(private int $teamid, private int $group, int $requester, int $userid)
    {
        parent::__construct($requester, $userid);
    }

    public function getBody(): string
    {
        return sprintf('User was associated with team %d and permission level %s', $this->teamid, Usergroup::from($this->group)->toHuman());
    }
}
