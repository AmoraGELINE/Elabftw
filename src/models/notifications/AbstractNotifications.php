<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2021 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Models\Notifications;

use Elabftw\Elabftw\Db;
use Elabftw\Enums\Notifications;
use Elabftw\Models\Users;
use Elabftw\Traits\SetIdTrait;
use PDO;

/**
 * Mother class for notifications that can be sent to a user
 */
abstract class AbstractNotifications
{
    use SetIdTrait;

    protected const PREF = null;

    protected Notifications $category;

    protected Db $Db;

    public function __construct()
    {
        $this->Db = Db::getConnection();
    }

    public function create(int $userid): int
    {
        [$webNotif, $sendEmail] = $this->getPref($userid);

        $isAck = 1;
        if ($webNotif === 1) {
            $isAck = 0;
        }

        $jsonBody = $this->getJsonBody($this->getBody());

        $sql = 'INSERT INTO notifications(userid, category, send_email, body, is_ack) VALUES(:userid, :category, :send_email, :body, :is_ack)';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':userid', $userid, PDO::PARAM_INT);
        $req->bindValue(':category', $this->category->value, PDO::PARAM_INT);
        $req->bindParam(':send_email', $sendEmail, PDO::PARAM_INT);
        $req->bindParam(':body', $jsonBody, PDO::PARAM_STR);
        $req->bindParam(':is_ack', $isAck, PDO::PARAM_INT);
        $this->Db->execute($req);

        return $this->Db->lastInsertId();
    }

    public function createIfNotExists(int $userid, int $stepId): int
    {
        // check if a similar notification is not already there
        $sql = 'SELECT id FROM notifications WHERE category = :category AND JSON_EXTRACT(body, "$.step_id") = :step_id';
        $req = $this->Db->prepare($sql);
        $req->bindValue(':category', $this->category->value, PDO::PARAM_INT);
        $req->bindValue(':step_id', $stepId, PDO::PARAM_INT);
        $this->Db->execute($req);
        // if there is a notification for this step id, delete it
        if ($req->rowCount() > 0) {
            $sql = 'DELETE FROM notifications WHERE id = :id';
            $reqDel = $this->Db->prepare($sql);
            $reqDel->bindValue(':id', $req->fetch()['id'], PDO::PARAM_INT);
            $reqDel->execute();
            return 0;
        }
        // otherwise, create a notification for it
        return $this->create($userid);
    }

    public function createMultiUsers(array $useridArr, int $selfUserid): int
    {
        foreach ($useridArr as $userid) {
            $userid = (int) $userid;
            // don't self notify this action
            if ($userid === $selfUserid) {
                continue;
            }
            $this->create($userid);
        }
        return count($useridArr);
    }

    abstract protected function getBody(): array;

    protected function getJsonBody(?array $body): string
    {
        if ($body === null) {
            return '{}';
        }
        return json_encode($body, JSON_THROW_ON_ERROR, 5);
    }

    /**
     * @return array<int, int>
     */
    protected function getPref(int $userid): array
    {
        // only categories inferior to 20 have a user setting for email/web notif
        if ($this->category->value >= 20) {
            return array(1, 1);
        }

        $userData = (new Users($userid))->userData;
        return array($userData[$this::PREF], $userData[$this::PREF . '_email']);
    }
}
