<?php
/**
 * \Elabftw\Elabftw\Scheduler
 *
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
declare(strict_types=1);

namespace Elabftw\Elabftw;

use PDO;

/**
 * All about the team's scheduler
 */
class Scheduler
{
    use EntityTrait;

    /** @var Database $Database instance of Database */
    public $Database;

    /** @var array $itemData data array for item if it's selected */
    public $itemData;

    /**
     * Constructor
     *
     * @param Database $database
     */
    public function __construct(Database $database)
    {
        $this->Db = Db::getConnection();
        $this->Database = $database;
    }

    /**
     * Read the db items and store it in itemData
     *
     */
    public function populate(): void
    {
        $this->itemData = $this->Database->read();
    }

    /**
     * Add an event for an item in the team
     *
     * @param string $start 2016-07-22T13:37:00
     * @param string $end 2016-07-22T19:42:00
     * @param string $title the comment entered by user
     * @return bool
     */
    public function create(string $start, string $end, string $title): bool
    {
        $title = filter_var($title, FILTER_SANITIZE_STRING);

        $sql = "INSERT INTO team_events(team, item, start, end, userid, title)
            VALUES(:team, :item, :start, :end, :userid, :title)";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':team', $this->Database->Users->userData['team'], PDO::PARAM_INT);
        $req->bindParam(':item', $this->Database->id, PDO::PARAM_INT);
        $req->bindParam(':start', $start);
        $req->bindParam(':end', $end);
        $req->bindParam(':title', $title);
        $req->bindParam(':userid', $this->Database->Users->userData['userid'], PDO::PARAM_INT);

        return $req->execute();
    }

    /**
     * Return an array with events for this item
     *
     * @return array
     */
    public function read(): array
    {
        // the title of the event is Firstname + Lastname of the user who booked it
        $sql = "SELECT team_events.*,
            CONCAT(team_events.title, ' (', u.firstname, ' ', u.lastname, ')') AS title
            FROM team_events
            LEFT JOIN users AS u ON team_events.userid = u.userid
            WHERE team_events.team = :team AND team_events.item = :item";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':team', $this->Database->Users->userData['team'], PDO::PARAM_INT);
        $req->bindParam(':item', $this->Database->id, PDO::PARAM_INT);
        $req->execute();

        return $req->fetchAll();
    }

    /**
     * Read info from an event id
     *
     * @return array
     */
    public function readFromId(): array
    {
        $sql = "SELECT * from team_events WHERE id = :id";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':id', $this->id, PDO::PARAM_INT);
        $req->execute();

        return $req->fetch();
    }

    /**
     * Update the start (and end) of an event (when you drag and drop it)
     *
     * @param string $start 2016-07-22T13:37:00
     * @param string $end 2016-07-22T13:37:00
     * @return bool
     */
    public function updateStart(string $start, string $end): bool
    {
        $sql = "UPDATE team_events SET start = :start, end = :end WHERE team = :team AND id = :id";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':start', $start);
        $req->bindParam(':end', $end);
        $req->bindParam(':team', $this->Database->Users->userData['team'], PDO::PARAM_INT);
        $req->bindParam(':id', $this->id, PDO::PARAM_INT);

        return $req->execute();
    }

    /**
     * Update the end of an event (when you resize it)
     *
     * @param string $end 2016-07-22T13:37:00
     * @return bool
     */
    public function updateEnd(string $end): bool
    {
        $sql = "UPDATE team_events SET end = :end WHERE team = :team AND id = :id";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':end', $end);
        $req->bindParam(':team', $this->Database->Users->userData['team'], PDO::PARAM_INT);
        $req->bindParam(':id', $this->id, PDO::PARAM_INT);

        return $req->execute();
    }

    /**
     * Remove an event
     *
     * @return bool
     */
    public function destroy(): bool
    {
        $sql = "DELETE FROM team_events WHERE id = :id AND userid = :userid";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':id', $this->id, PDO::PARAM_INT);
        $req->bindParam(':userid', $this->Database->Users->userData['userid'], PDO::PARAM_INT);

        return $req->execute();
    }
}
