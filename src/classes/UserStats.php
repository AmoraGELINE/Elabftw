<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Elabftw;

use Elabftw\Enums\State;
use Elabftw\Models\ExperimentsStatus;
use Elabftw\Models\Teams;
use Elabftw\Models\Users;
use PDO;

/**
 * Generate experiments statistics for a user (shown on profile page)
 */
class UserStats
{
    private Db $Db;

    private array $pieData = array();

    /**
     * @param $count is the number of all experiments of the user
     */
    public function __construct(private Users $Users, private int $count)
    {
        $this->Db = Db::getConnection();
        $this->readPieDataFromDB();
    }

    public function getPieData(): array
    {
        return $this->pieData;
    }

    /**
     * Take the raw data and make a string that can be injected into conic-gradient css value
     * example: #29AEB9 18%,#54AA08 0 43%,#C0C0C0 0 74%,#C24F3D 0
     */
    public function getFormattedPieData(): string
    {
        $res = array();
        $percentSum = 0;
        foreach ($this->pieData as $key => $value) {
            if ($key === array_key_first($this->pieData)) {
                $res[] = sprintf('%s %s%%,', $value['color'], $value['percent']);
                $percentSum = $value['percent'];
                continue;
            }

            // last one is just 0
            if ($key === array_key_last($this->pieData)) {
                $res[] = $value['color'] . ' 0';
                continue;
            }
            // the percent value needs to be added to the previous sum of percents
            $percentSum += $value['percent'];
            $res[] = $value['color'] . ' 0 ' . $percentSum . '%,';
        }
        return implode($res);
    }

    /**
     * Generate data for pie chart of status
     * We want an array with each value corresponding to a status with: name, percent and color
     */
    private function readPieDataFromDB(): void
    {
        // prevent division by zero error if user has no experiments
        if ($this->count === 0) {
            return;
        }
        $percentFactor = 100.0 / (float) $this->count;

        // get all status name and id with State::Normal
        $statusArr = (new ExperimentsStatus(new Teams($this->Users, $this->Users->team)))->readAllPlus();
        // add "status" for experiments without status
        $statusArr[] = array(
            'title' => _('Not set'),
            'id' => -1,
            'color' => 'bdbdbd',
        );

        // get number of experiments without status
        $sql = 'SELECT COUNT(id)
            FROM experiments
            WHERE userid = :userid
                AND state = :state
                AND status IS NULL';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':userid', $this->Users->userData['userid'], PDO::PARAM_INT);
        $req->bindValue(':state', State::Normal->value, PDO::PARAM_INT);
        $req->execute();
        $countExpWithoutStatus = $req->fetchColumn();

        // prepare sql query for experiments with status
        $sql = 'SELECT COUNT(id)
            FROM experiments
            WHERE userid = :userid
                AND state = :state
                AND status = :status';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':userid', $this->Users->userData['userid'], PDO::PARAM_INT);
        $req->bindValue(':state', State::Normal->value, PDO::PARAM_INT);

        // populate arrays
        foreach ($statusArr as $status) {
            $this->pieData[] = array();
            $lastKey = array_key_last($this->pieData);
            $this->pieData[$lastKey]['name'] = $status['title'];
            $this->pieData[$lastKey]['id'] = $status['id'];
            $this->pieData[$lastKey]['color'] = '#' . $status['color'];

            if ($status['id'] === -1) {
                $this->pieData[$lastKey]['count'] = $countExpWithoutStatus;
            } else {
                // now get the count
                $req->bindParam(':status', $status['id'], PDO::PARAM_INT);
                $req->execute();
                $this->pieData[$lastKey]['count'] = $req->fetchColumn();
            }

            // calculate the percent
            $this->pieData[$lastKey]['percent'] = round($percentFactor * (float) $this->pieData[$lastKey]['count']);
        }
    }
}
