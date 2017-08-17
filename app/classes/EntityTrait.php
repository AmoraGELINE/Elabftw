<?php
/**
 * \Elabftw\Elabftw\EntityTrait
 *
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
namespace Elabftw\Elabftw;

use PDO;
use Exception;

/**
 * For things that are used by experiments, database, status, item types, templates, …
 *
 */
trait EntityTrait {

    /** @var Db $pdo SQL Database */
    protected $pdo;

    /** @var Users $Users our user */
    public $Users;

    /** @var int $id Id of the entity */
    public $id;

    /**
     * Check and set id
     *
     * @param int $id
     */
    public function setId($id)
    {
        if (Tools::checkId($id) === false) {
            throw new Exception(_('The id parameter is not valid!'));
        }
        $this->id = $id;
        // prevent reusing of old data from previous id
        unset($this->entityData);
    }

    /**
     * Update ordering for status, experiment templates or items types
     *
     * @param array $post POST
     * @return bool
     */
    public function updateOrdering($post)
    {
        $success = array();

        // whitelist the tables
        $whitelist = array(
            'status',
            'experiments_templates',
            'items_types'
        );

        if (!in_array($post['table'], $whitelist)) {
            throw new Exception('Wrong table.');
        }

        foreach ($post['ordering'] as $ordering => $id) {
            $id = explode('_', $id);
            $id = $id[1];
            // the table param is whitelisted here
            $sql = "UPDATE " . $post['table'] . " SET ordering = :ordering WHERE id = :id AND team = :team";
            $req = $this->pdo->prepare($sql);
            $req->bindParam(':ordering', $ordering, PDO::PARAM_INT);
            $req->bindParam(':team', $this->Users->userData['team']);
            $req->bindParam(':id', $id, PDO::PARAM_INT);
            $success[] = $req->execute();
        }

        return !in_array(false, $success);
    }
}
