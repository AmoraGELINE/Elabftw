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

class Export extends AbstractAuditEvent
{
    public function __construct(int $requesterUserid, private int $count)
    {
        parent::__construct($requesterUserid, 0);
    }

    public function getBody(): string
    {
        return sprintf('User exported %d entries', $this->count);
    }

    public function getCategory(): AuditCategory
    {
        return AuditCategory::Export;
    }
}
