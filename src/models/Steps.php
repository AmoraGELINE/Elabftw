<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Models;

use Elabftw\Elabftw\CreateNotificationParams;
use Elabftw\Elabftw\Db;
use Elabftw\Interfaces\ContentParamsInterface;
use Elabftw\Interfaces\CrudInterface;
use Elabftw\Interfaces\StepParamsInterface;
use Elabftw\Traits\SortableTrait;
use PDO;

/**
 * All about the steps
 */
class Steps implements CrudInterface
{
    use SortableTrait;

    protected Db $Db;

    public function __construct(public AbstractEntity $Entity, private ?int $id = null)
    {
        $this->Db = Db::getConnection();
    }

    /**
     * Add a step
     *
     */
    public function create(ContentParamsInterface $params): int
    {
        $this->Entity->canOrExplode('write');
        // make sure the newly added step is at the bottom
        // count the number of steps and add 1 to be sure we're last
        $ordering = count($this->readAll()) + 1;

        $sql = 'INSERT INTO ' . $this->Entity->type . '_steps (item_id, body, ordering) VALUES(:item_id, :content, :ordering)';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':item_id', $this->Entity->id, PDO::PARAM_INT);
        $req->bindValue(':content', $params->getContent());
        $req->bindParam(':ordering', $ordering, PDO::PARAM_INT);
        $this->Db->execute($req);

        return $this->Db->lastInsertId();
    }

    /**
     * Import a step from a complete step array
     * Used when importing from zip archive (json)
     *
     * @param array<string, mixed> $step
     */
    public function import(array $step): void
    {
        $this->Entity->canOrExplode('write');

        $body = str_replace('|', ' ', $step['body']);
        $sql = 'INSERT INTO ' . $this->Entity->type . '_steps (item_id, body, ordering, finished, finished_time)
            VALUES(:item_id, :body, :ordering, :finished, :finished_time)';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':item_id', $this->Entity->id, PDO::PARAM_INT);
        $req->bindParam(':body', $body);
        $req->bindParam(':ordering', $step['ordering']);
        $req->bindParam(':finished', $step['finished']);
        $req->bindParam(':finished_time', $step['finished_time']);
        $this->Db->execute($req);
    }

    public function readAll(): array
    {
        $sql = 'SELECT * FROM ' . $this->Entity->type . '_steps WHERE item_id = :id ORDER BY ordering';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':id', $this->Entity->id, PDO::PARAM_INT);
        $this->Db->execute($req);

        return $req->fetchAll();
    }

    public function readOne(): array
    {
        $sql = 'SELECT * FROM ' . $this->Entity->type . '_steps WHERE id = :id';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':id', $this->id, PDO::PARAM_INT);
        $this->Db->execute($req);

        return $this->Db->fetch($req);
    }

    /**
     * Copy the steps from one entity to an other
     *
     * @param int $id The id of the original entity
     * @param int $newId The id of the new entity that will receive the steps
     * @param bool $fromTpl do we duplicate from template?
     */
    public function duplicate(int $id, int $newId, $fromTpl = false): void
    {
        $table = $this->Entity->type;
        if ($fromTpl) {
            $table = $this->Entity instanceof Templates ? 'experiments_templates' : 'items_types';
        }
        $stepsql = 'SELECT body, ordering FROM ' . $table . '_steps WHERE item_id = :id';
        $stepreq = $this->Db->prepare($stepsql);
        $stepreq->bindParam(':id', $id, PDO::PARAM_INT);
        $this->Db->execute($stepreq);

        while ($steps = $stepreq->fetch()) {
            $sql = 'INSERT INTO ' . $this->Entity->type . '_steps (item_id, body, ordering) VALUES(:item_id, :body, :ordering)';
            $req = $this->Db->prepare($sql);
            $req->bindParam(':item_id', $newId, PDO::PARAM_INT);
            $req->bindParam(':body', $steps['body'], PDO::PARAM_STR);
            $req->bindParam(':ordering', $steps['ordering'], PDO::PARAM_INT);
            $this->Db->execute($req);
        }
    }

    public function update(StepParamsInterface $params): bool
    {
        $this->Entity->canOrExplode('write');
        $target = $params->getTarget();
        if ($target === 'finished') {
            return $this->toggleFinished();
        }
        if ($target === 'deadline_notif') {
            return $this->toggleNotif();
        }
        if ($target === 'body') {
            $content = $params->getContent();
        } else {
            $content = $params->getDatetime();
        }
        $sql = 'UPDATE ' . $this->Entity->type . '_steps SET ' . $params->getTarget() . ' = :content WHERE id = :id AND item_id = :item_id';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':content', $content, PDO::PARAM_STR);
        $req->bindParam(':id', $this->id, PDO::PARAM_INT);
        $req->bindParam(':item_id', $this->Entity->id, PDO::PARAM_INT);
        return $this->Db->execute($req);
    }

    public function destroy(): bool
    {
        $this->Entity->canOrExplode('write');

        $sql = 'DELETE FROM ' . $this->Entity->type . '_steps WHERE id = :id AND item_id = :item_id';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':id', $this->id, PDO::PARAM_INT);
        $req->bindParam(':item_id', $this->Entity->id, PDO::PARAM_INT);
        return $this->Db->execute($req);
    }

    private function toggleFinished(): bool
    {
        $sql = 'UPDATE ' . $this->Entity->type . '_steps SET finished = !finished,
            finished_time = NOW() WHERE id = :id AND item_id = :item_id';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':id', $this->id, PDO::PARAM_INT);
        $req->bindParam(':item_id', $this->Entity->id, PDO::PARAM_INT);
        return $this->Db->execute($req);
    }

    private function toggleNotif(): bool
    {
        // get the current deadline value so we can insert it in the notification
        $sql = 'SELECT deadline FROM ' . $this->Entity->type . '_steps WHERE id = :id';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':id', $this->id, PDO::PARAM_INT);
        $req->execute();
        $step = $req->fetch();

        // now create a notification if none exist for this step id already
        $Notifications = new Notifications($this->Entity->Users);
        $Notifications->createIfNotExists(new CreateNotificationParams(
            Notifications::STEP_DEADLINE,
            array(
                'step_id' => $this->id,
                'entity_id' => $this->Entity->entityData['id'],
                'entity_page' => $this->Entity->page,
                'deadline' => $step['deadline'],
            ),
        ));

        // update the deadline_notif column so we now if this step has a notif set for deadline or not
        $sql = 'UPDATE ' . $this->Entity->type . '_steps SET deadline_notif = !deadline_notif WHERE id = :id AND item_id = :item_id';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':id', $this->id, PDO::PARAM_INT);
        $req->bindParam(':item_id', $this->Entity->id, PDO::PARAM_INT);
        return $this->Db->execute($req);
    }
}
