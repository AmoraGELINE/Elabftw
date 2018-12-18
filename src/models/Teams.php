<?php
/**
 * \Elabftw\Elabftw\Teams
 *
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
declare(strict_types=1);

namespace Elabftw\Elabftw;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Elabftw\Exceptions\DatabaseErrorException;
use Elabftw\Exceptions\ImproperActionException;
use PDO;

/**
 * All about the teams
 */
class Teams implements CrudInterface
{
    /** @var Db $Db SQL Database */
    protected $Db;

    /** @var Users $Users instance of Users */
    public $Users;

    /**
     * Constructor
     *
     * @param Users $users
     */
    public function __construct(Users $users)
    {
        $this->Db = Db::getConnection();
        $this->Users = $users;
    }

    /**
     * Check if the team exists from the id
     *
     * @param int $id team id
     * @return bool
     */
    public function isExisting(int $id): bool
    {
        $sql = 'SELECT team_id FROM teams WHERE team_id = :id';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':id', $id, PDO::PARAM_INT);
        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
        return (bool) $req->fetch();
    }

    /**
     * Check if the team exists already and create one if not
     *
     * @param string $name Name of the team (case sensitive)
     * @return int|false The team ID
     */
    public function initializeIfNeeded(string $name)
    {
        $sql = 'SELECT team_id, team_name, team_orgid FROM teams';
        $req = $this->Db->prepare($sql);
        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
        $teamsArr = $req->fetchAll();
        foreach ($teamsArr as $team) {
            if (($team['team_name'] === $name) || ($team['team_orgid'] === $name)) {
                return $team['team_id'];
            }
        }
        return $this->create($name);
    }

    /**
     * Add a new team
     *
     * @param string $name The new name of the team
     * @return int the new team id
     */
    public function create(string $name)
    {
        $name = filter_var($name, FILTER_SANITIZE_STRING);

        // add to the teams table
        $sql = 'INSERT INTO teams (team_name, link_name, link_href) VALUES (:team_name, :link_name, :link_href)';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':team_name', $name);
        $req->bindValue(':link_name', 'Documentation');
        $req->bindValue(':link_href', 'https://doc.elabftw.net');
        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
        // grab the team ID
        $newId = $this->Db->lastInsertId();

        // create default status
        $Status = new Status($this->Users);
        $Status->createDefault($newId);

        // create default item type
        $ItemsTypes = new ItemsTypes($this->Users);
        $ItemsTypes->create(
            'Edit me',
            '32a100',
            0,
            '<p>Go to the admin panel to edit/add more items types!</p>',
            $newId
        );

        // create default experiment template
        $Templates = new Templates($this->Users);
        $Templates->createDefault($newId);

        return $newId;
    }

    /**
     * Read from the current team
     *
     * @return array
     */
    public function read(): array
    {
        $sql = "SELECT * FROM `teams` WHERE team_id = :team_id";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':team_id', $this->Users->userData['team'], PDO::PARAM_INT);
        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }

        $res = $req->fetch();
        if ($res === false) {
            return array();
        }

        return $res;
    }

    /**
     * Get all the teams
     *
     * @return array
     */
    public function readAll(): array
    {
        $sql = "SELECT * FROM teams ORDER BY team_name ASC";
        $req = $this->Db->prepare($sql);
        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }

        return $req->fetchAll();
    }

    /**
     * Update team
     *
     * @param array $post POST
     * @return void
     */
    public function update(array $post): void
    {
        // CHECKS
        /* TODO provide an upload button
        if (isset($post['stampcert'])) {
            $cert_chain = filter_var($post['stampcert'], FILTER_SANITIZE_STRING);
            $elabRoot = \dirname(__DIR__, 2);
            if (!is_readable(realpath($elabRoot . '/web/' . $cert_chain))) {
                throw new Exception('Cannot read provided certificate file.');
            }
        }
         */

        if (isset($post['stamppass']) && !empty($post['stamppass'])) {
            $stamppass = Crypto::encrypt($post['stamppass'], Key::loadFromAsciiSafeString(\SECRET_KEY));
        } else {
            $teamConfigArr = $this->read();
            $stamppass = $teamConfigArr['stamppass'];
        }

        $deletableXp = 0;
        if ($post['deletable_xp'] == 1) {
            $deletableXp = 1;
        }

        $publicDb = 0;
        if ($post['public_db'] == 1) {
            $publicDb = 1;
        }

        $linkName = 'Documentation';
        if (isset($post['link_name'])) {
            $linkName = filter_var($post['link_name'], FILTER_SANITIZE_STRING);
        }

        $linkHref = 'https://doc.elabftw.net';
        if (isset($post['link_href'])) {
            $linkHref = filter_var($post['link_href'], FILTER_SANITIZE_STRING);
        }

        $sql = "UPDATE teams SET
            deletable_xp = :deletable_xp,
            public_db = :public_db,
            link_name = :link_name,
            link_href = :link_href,
            stamplogin = :stamplogin,
            stamppass = :stamppass,
            stampprovider = :stampprovider,
            stampcert = :stampcert
            WHERE team_id = :team_id";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':deletable_xp', $deletableXp, PDO::PARAM_INT);
        $req->bindParam(':public_db', $publicDb, PDO::PARAM_INT);
        $req->bindParam(':link_name', $linkName);
        $req->bindParam(':link_href', $linkHref);
        $req->bindParam(':stamplogin', $post['stamplogin']);
        $req->bindParam(':stamppass', $stamppass);
        $req->bindParam(':stampprovider', $post['stampprovider']);
        $req->bindParam(':stampcert', $post['stampcert']);
        $req->bindParam(':team_id', $this->Users->userData['team'], PDO::PARAM_INT);

        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
    }

    /**
     * Edit the name of a team, called by ajax
     *
     * @param int $id The id of the team
     * @param string $name The new name we want
     * @param string $orgid The id of the team in the organisation (from IDP for instance)
     * @return void
     */
    public function updateName(int $id, string $name, string $orgid = ""): void
    {
        $name = filter_var($name, FILTER_SANITIZE_STRING);
        $orgid = filter_var($orgid, FILTER_SANITIZE_STRING);

        $sql = "UPDATE teams
            SET team_name = :name,
                team_orgid = :orgid
            WHERE team_id = :id";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':name', $name);
        $req->bindParam(':orgid', $orgid);
        $req->bindParam(':id', $id, PDO::PARAM_INT);

        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
    }

    /**
     * Delete a team on if all the stats are at zero
     *
     * @param int $id ID of the team
     * @return void
     */
    public function destroy(int $id): void
    {
        // check for stats, should be 0
        $count = $this->getStats($id);

        if ($count['totxp'] !== '0' || $count['totdb'] !== '0' || $count['totusers'] !== '0') {
            throw new ImproperActionException('The team is not empty! Aborting deletion!');
        }

        $sql = "DELETE FROM teams WHERE team_id = :team_id";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':team_id', $id, PDO::PARAM_INT);
        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }

        $sql = "DELETE FROM status WHERE team = :team_id";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':team_id', $id, PDO::PARAM_INT);
        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }

        $sql = "DELETE FROM items_types WHERE team = :team_id";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':team_id', $id, PDO::PARAM_INT);
        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }

        $sql = "DELETE FROM experiments_templates WHERE team = :team_id";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':team_id', $id, PDO::PARAM_INT);
        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
    }

    /**
     * Not implemented
     *
     * @return void
     */
    public function destroyAll(): void
    {
        return;
    }

    /**
     * Clear the timestamp password
     *
     * @return bool
     */
    public function destroyStamppass(): bool
    {
        $sql = "UPDATE teams SET stamppass = NULL WHERE team_id = :team_id";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':team_id', $this->Users->userData['team'], PDO::PARAM_INT);

        return $req->execute();
    }

    /**
     * Get statistics for the whole install
     *
     * @return array
     */
    public function getAllStats(): array
    {
        $sql = "SELECT
        (SELECT COUNT(users.userid) FROM users) AS totusers,
        (SELECT COUNT(items.id) FROM items) AS totdb,
        (SELECT COUNT(teams.team_id) FROM teams) AS totteams,
        (SELECT COUNT(experiments.id) FROM experiments) AS totxp,
        (SELECT COUNT(experiments.id) FROM experiments WHERE experiments.timestamped = 1) AS totxpts";
        $req = $this->Db->prepare($sql);
        $req->execute();

        return $req->fetch(PDO::FETCH_NAMED);
    }

    /**
     * Get statistics for a team
     *
     * @param int $team
     * @return array
     */
    public function getStats(int $team): array
    {
        $sql = "SELECT
        (SELECT COUNT(users.userid) FROM users WHERE users.team = :team) AS totusers,
        (SELECT COUNT(items.id) FROM items WHERE items.team = :team) AS totdb,
        (SELECT COUNT(experiments.id) FROM experiments WHERE experiments.team = :team) AS totxp,
        (SELECT COUNT(experiments.id) FROM experiments
            WHERE experiments.team = :team AND experiments.timestamped = 1) AS totxpts";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':team', $team, PDO::PARAM_INT);
        $req->execute();

        return $req->fetch(PDO::FETCH_NAMED);
    }
}
