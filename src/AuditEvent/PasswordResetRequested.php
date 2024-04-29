<?php

/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2023 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

declare(strict_types=1);

namespace Elabftw\AuditEvent;

use Elabftw\Enums\AuditCategory;

class PasswordResetRequested extends AbstractAuditEvent
{
    public function __construct(private string $email)
    {
        parent::__construct();
    }

    public function getBody(): string
    {
        return sprintf('Password reset was requested for account associated with: %s', $this->email);
    }

    public function getCategory(): AuditCategory
    {
        return AuditCategory::PasswordResetRequested;
    }
}
