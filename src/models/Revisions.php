<?php
/**
 * \Elabftw\Elabftw\Revisions
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
 * All about the revisions
 */
class Revisions implements CrudInterface
{
    /** @var Db $Db SQL Database */
    private $Db;

    /** @var AbstractEntity $Entity an instance of Experiments or Database */
    private $Entity;

    /** @var int MIN_DELTA the min number of characters different between two versions to trigger save */
    private const MIN_DELTA = 20;

    /**
     * Constructor
     *
     * @param AbstractEntity $entity
     */
    public function __construct(AbstractEntity $entity)
    {
        $this->Entity = $entity;
        $this->Db = Db::getConnection();
    }

    /**
     * Add a revision if the changeset is big enough
     *
     * @param string $body
     * @return bool
     */
    public function create($body): bool
    {
        // only save a revision if there is at least MIN_DELTA characters difference between the old version and the new one
        if (abs(strlen($this->Entity->entityData['body']) - strlen($body)) > self::MIN_DELTA) {
            $sql = "INSERT INTO " . $this->Entity->type . "_revisions (item_id, body, userid)
                VALUES(:item_id, :body, :userid)";

            $req = $this->Db->prepare($sql);
            $req->bindParam(':item_id', $this->Entity->id);
            $req->bindParam(':body', $body);
            $req->bindParam(':userid', $this->Entity->Users->userid);

            return $req->execute();
        }
        return true;
    }

    /**
     * Get how many revisions we have
     *
     * @return int number of revisions existing
     */
    public function readCount(): int
    {
        $sql = "SELECT COUNT(*) FROM " . $this->Entity->type . "_revisions
             WHERE item_id = :item_id";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':item_id', $this->Entity->id);
        $req->execute();

        return (int) $req->fetchColumn();
    }


    /**
     * Output HTML for displaying revisions
     *
     * @return string html with the count
     */
    public function showCount(): string
    {
        $html = '';
        $count = $this->readCount();

        if ($count > 0) {
            $html .= "<span class='align_right'><i class='fas fa-history mr-1'></i>";
            $html .= "<a href='revisions.php?type=" . $this->Entity->type . "&item_id=" .
                $this->Entity->id . "'>" . $count . " " .
                ngettext('revision available', 'revisions available', $count) . "</a>";
            $html .= "</span>";
        }

        return $html;
    }

    /**
     * Read all revisions for an item
     *
     * @return array
     */
    public function readAll(): array
    {
        $sql = "SELECT * FROM " . $this->Entity->type . "_revisions
            WHERE item_id = :item_id AND userid = :userid ORDER BY savedate DESC";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':item_id', $this->Entity->id);
        $req->bindParam(':userid', $this->Entity->Users->userid);
        $req->execute();

        return $req->fetchAll();
    }

    /**
     * Get the body of a revision
     *
     * @param int $revId The id of the revision
     * @return string
     */
    private function readRev($revId): string
    {
        $sql = "SELECT body FROM " . $this->Entity->type . "_revisions WHERE id = :rev_id AND userid = :userid";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':rev_id', $revId);
        $req->bindParam(':userid', $this->Entity->Users->userid);
        $req->execute();

        return $req->fetchColumn();
    }

    /**
     * Check if item is locked before restoring it
     *
     * @throws Exception
     * @return bool
     */
    private function isLocked(): bool
    {
        $sql = "SELECT locked FROM " . $this->Entity->type . " WHERE id = :id";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':id', $this->Entity->id, PDO::PARAM_INT);
        $req->execute();
        $locked = $req->fetch();

        return $locked['locked'] == 1;
    }

    /**
     * Restore a revision
     *
     * @param int $revId The id of the revision we want to restore
     * @throws Exception
     * @return bool
     */
    public function restore($revId): bool
    {
        // check for lock
        if ($this->isLocked()) {
            throw new Exception(_('You cannot restore a revision of a locked item!'));
        }

        $body = $this->readRev($revId);

        $sql = "UPDATE " . $this->Entity->type . " SET body = :body WHERE id = :id";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':body', $body);
        $req->bindParam(':id', $this->Entity->id, PDO::PARAM_INT);

        return $req->execute();
    }

    /**
     * Not implemented
     *
     * @param int $id
     * @return bool
     */
    public function destroy(int $id): bool
    {
        return false;
    }

    /**
     * Not implemented
     *
     */
    public function destroyAll(): bool
    {
        return false;
    }
}
