<?php
/**
 * \Elabftw\Elabftw\Experiments
 *
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
namespace Elabftw\Elabftw;

use Exception;
use PDO;

/**
 * All about the experiments
 */
class Experiments extends AbstractEntity
{
    use EntityTrait;

    /** @var Links $Links instance of Links */
    public $Links;

    /** @var Steps $Steps instance of Steps */
    public $Steps;

    /**
     * Constructor
     *
     * @param Users $users
     * @param int|null $id
     */
    public function __construct(Users $users, $id = null)
    {
        parent::__construct($users, $id);
        $this->page = 'experiments';
        //TODO remove type and check with instanceof, rename to table because it's used as table
        $this->type = 'experiments';

        $this->Links = new Links($this);
        $this->Steps = new Steps($this);
    }

    /**
     * Create an experiment
     *
     * @param int|null $tpl the template on which to base the experiment
     * @return int the new id of the experiment
     */
    public function create($tpl = null): int
    {
        $Templates = new Templates($this->Users);

        // do we want template ?
        if ($tpl !== null) {
            $Templates->setId($tpl);
            $templatesArr = $Templates->read();
            $title = $templatesArr['name'];
            $body = $templatesArr['body'];
        } else {
            $title = _('Untitled');
            $body = $Templates->readCommonBody();
        }

        $visibility = 'team';
        if ($this->Users->userData['default_vis'] !== null) {
            $visibility = $this->Users->userData['default_vis'];
        }

        // SQL for create experiments
        $sql = "INSERT INTO experiments(team, title, date, body, status, elabid, visibility, userid)
            VALUES(:team, :title, :date, :body, :status, :elabid, :visibility, :userid)";
        $req = $this->Db->prepare($sql);
        $req->execute(array(
            'team' => $this->Users->userData['team'],
            'title' => $title,
            'date' => Tools::kdate(),
            'body' => $body,
            'status' => $this->getStatus(),
            'elabid' => $this->generateElabid(),
            'visibility' => $visibility,
            'userid' => $this->Users->userid
        ));
        $newId = $this->Db->lastInsertId();

        // insert the tags from the template
        if ($tpl !== null) {
            $Tags = new Tags(new Templates($this->Users, $tpl));
            $Tags->copyTags($newId);
        }

        return (int) $newId;
    }

    /**
     * Read all experiments related to a DB item
     *
     * @param int $itemId the DB item
     * @return array
     */
    public function readRelated($itemId): array
    {
        $itemsArr = array();

        // get the id of related experiments
        $sql = "SELECT item_id FROM experiments_links
            WHERE link_id = :link_id";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':link_id', $itemId);
        $req->execute();
        while ($data = $req->fetch()) {
            $this->setId($data['item_id']);
            $this->canOrExplode('read');
            $itemsArr[] = $this->read();
        }

        return $itemsArr;
    }

    /**
     * Check if we have a correct value
     *
     * @param string $visibility
     * @return bool
     */
    public function checkVisibility(string $visibility): bool
    {
        $validArr = array(
            'public',
            'organization',
            'team',
            'user'
        );

        if (in_array($visibility, $validArr)) {
            return true;
        }

        // or we might have a TeamGroup, so an int
        return (bool) Tools::checkId((int) $visibility);
    }

    /**
     * Update the visibility for an experiment
     *
     * @param string $visibility
     * @return bool
     */
    public function updateVisibility($visibility): bool
    {
        $sql = "UPDATE experiments SET visibility = :visibility WHERE id = :id";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':visibility', $visibility);
        $req->bindParam(':id', $this->id, PDO::PARAM_INT);

        return $req->execute();
    }

    /**
     * Update the status for an experiment
     *
     * @param int $status Id of the status
     * @return bool
     */
    public function updateCategory(int $status): bool
    {
        $sql = "UPDATE experiments SET status = :status WHERE id = :id";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':status', $status);
        $req->bindParam(':id', $this->id, PDO::PARAM_INT);

        return $req->execute();
    }

    /**
     * Can this experiment be timestamped?
     *
     * @return bool
     */
    public function isTimestampable(): bool
    {
        $currentStatus = (int) $this->entityData['category_id'];
        $sql = "SELECT is_timestampable FROM status WHERE id = :status;";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':status', $currentStatus);
        $req->execute();
        return (bool) $req->fetchColumn();
    }

    /**
     * Set the experiment as timestamped with a path to the token
     *
     * @param string $responseTime the date of the timestamp
     * @param string $responsefilePath the file path to the timestamp token
     * @return bool
     */
    public function updateTimestamp($responseTime, $responsefilePath): bool
    {
        $sql = "UPDATE experiments SET
            locked = 1,
            lockedby = :userid,
            lockedwhen = :when,
            timestamped = 1,
            timestampedby = :userid,
            timestampedwhen = :when,
            timestamptoken = :longname
            WHERE id = :id;";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':when', $responseTime);
        // the date recorded in the db has to match the creation time of the timestamp token
        $req->bindParam(':longname', $responsefilePath);
        $req->bindParam(':userid', $this->Users->userid);
        $req->bindParam(':id', $this->id);

        return $req->execute();
    }

    /**
     * Select what will be the status for the experiment
     *
     * @return int The status ID
     */
    private function getStatus(): int
    {
        // what will be the status ?
        // go pick what is the default status upon creating experiment
        // there should be only one because upon making a status default,
        // all the others are made not default
        $sql = 'SELECT id FROM status WHERE is_default = true AND team = :team LIMIT 1';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':team', $this->Users->userData['team']);
        $req->execute();
        $status = $req->fetchColumn();

        // if there is no is_default status
        // we take the first status that come
        if (!$status) {
            $sql = 'SELECT id FROM status WHERE team = :team LIMIT 1';
            $req = $this->Db->prepare($sql);
            $req->bindParam(':team', $this->Users->userData['team']);
            $req->execute();
            $status = $req->fetchColumn();
        }
        return (int) $status;
    }

    /**
     * Generate unique elabID
     * This function is called during the creation of an experiment.
     *
     * @return string unique elabid with date in front of it
     */
    private function generateElabid(): string
    {
        $date = Tools::kdate();
        return $date . "-" . \sha1(\uniqid($date, true));
    }

    /**
     * Duplicate an experiment
     *
     * @return int the ID of the new item
     */
    public function duplicate(): int
    {
        $experiment = $this->read();

        // let's add something at the end of the title to show it's a duplicate
        // capital i looks good enough
        $title = $experiment['title'] . ' I';

        $sql = "INSERT INTO experiments(team, title, date, body, status, elabid, visibility, userid)
            VALUES(:team, :title, :date, :body, :status, :elabid, :visibility, :userid)";
        $req = $this->Db->prepare($sql);
        $req->execute(array(
            'team' => $this->Users->userData['team'],
            'title' => $title,
            'date' => Tools::kdate(),
            'body' => $experiment['body'],
            'status' => $this->getStatus(),
            'elabid' => $this->generateElabid(),
            'visibility' => $experiment['visibility'],
            'userid' => $this->Users->userid));
        $newId = $this->Db->lastInsertId();

        $this->Links->duplicate($this->id, $newId);
        $this->Steps->duplicate($this->id, $newId);
        $this->Tags->copyTags($newId);

        return (int) $newId;
    }

    /**
     * Destroy an experiment and all associated data
     *
     * @return bool
     */
    public function destroy(): bool
    {
        // delete the experiment
        $sql = "DELETE FROM experiments WHERE id = :id";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':id', $this->id);
        $req->execute();

        $this->Comments->destroyAll();
        $this->Links->destroyAll();
        $this->Steps->destroyAll();
        $this->Tags->destroyAll();
        $this->Uploads->destroyAll();

        return true;
    }

    /**
     * Lock/unlock
     *
     * @throws Exception
     * @return bool
     */
    public function toggleLock(): bool
    {
        $locked = (int) $this->entityData['locked'];

        // if we try to unlock something we didn't lock
        if ($locked === 1 && ($this->entityData['lockedby'] != $this->Users->userid)) {
            // Get the first name of the locker to show in error message
            $sql = "SELECT firstname FROM users WHERE userid = :userid";
            $req = $this->Db->prepare($sql);
            $req->bindParam(':userid', $this->entityData['lockedby']);
            $req->execute();
            throw new Exception(
                _('This experiment was locked by') .
                ' ' . $req->fetchColumn() . '. ' .
                _("You don't have the rights to unlock this.")
            );
        }

        // check if the experiment is timestamped. Disallow unlock in this case.
        if ($locked === 1 && $this->entityData['timestamped']) {
            throw new Exception(_('You cannot unlock or edit in any way a timestamped experiment.'));
        }

        // toggle
        if ($locked === 1) {
            $locked = 0;
        } else {
            $locked = 1;
        }
        $sql = "UPDATE experiments
            SET locked = :locked, lockedby = :lockedby, lockedwhen = CURRENT_TIMESTAMP WHERE id = :id";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':locked', $locked);
        $req->bindParam(':lockedby', $this->Users->userid);
        $req->bindParam(':id', $this->id);

        return $req->execute();
    }
}
