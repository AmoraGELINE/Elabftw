<?php

/**
 * @package   Elabftw\Elabftw
 * @author    Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2023 Nicolas CARPi
 * @license   https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @see       https://www.elabftw.net Official website
 */

declare(strict_types=1);

namespace Elabftw\Interfaces;

use Elabftw\Enums\AuditCategory;

interface AuditEventInterface
{
    public function getRequesterUserid(): int;

    public function getTargetUserid(): int;

    public function getBody(): string;

    public function getJsonBody(): string;

    public function getCategory(): AuditCategory;
}
