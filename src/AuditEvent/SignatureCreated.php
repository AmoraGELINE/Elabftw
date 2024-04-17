<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2024 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\AuditEvent;

use Elabftw\Enums\AuditCategory;
use Elabftw\Enums\EntityType;

class SignatureCreated extends AbstractAuditEvent
{
    public function __construct(int $requesterUserid, private int $entityId, private EntityType $entityType)
    {
        parent::__construct($requesterUserid, 0);
    }

    public function getBody(): string
    {
        return 'An entry has been signed.';
    }

    public function getJsonBody(): string
    {
        $info = array(
            'category' => $this->getCategory(),
            'entity_id' => $this->entityId,
            'entity_type' => $this->entityType->value,
            'message' => $this->getBody(),
            'requester_userid' => $this->getRequesterUserid(),
            'target_userid' => $this->getTargetUserid(),
        );
        return json_encode($info, JSON_THROW_ON_ERROR);
    }

    public function getCategory(): int
    {
        return AuditCategory::SignatureCreated->value;
    }
}
