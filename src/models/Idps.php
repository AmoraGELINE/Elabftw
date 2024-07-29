<?php

/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

declare(strict_types=1);

namespace Elabftw\Models;

use Elabftw\Elabftw\Db;
use Elabftw\Enums\Action;
use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Interfaces\RestInterface;
use Elabftw\Traits\SetIdTrait;
use PDO;

/**
 * An IDP is an Identity Provider. Used in SAML2 authentication context.
 */
class Idps implements RestInterface
{
    use SetIdTrait;

    public const string SSO_BINDING = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST';

    public const string SLO_BINDING = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect';

    private const string EMAIL_ATTR = 'urn:oid:0.9.2342.19200300.100.1.3';

    private const string TEAM_ATTR = 'urn:oid:1.3.6.1.4.1.5923.1.1.1.7';

    private const string FNAME_ATTR = 'urn:oid:2.5.4.42';

    private const string LNAME_ATTR = 'urn:oid:2.5.4.4';

    private const string ORGID_ATTR = 'urn:oid:0.9.2342.19200300.100.1.1';

    protected Db $Db;

    public function __construct(private Users $requester, ?int $id = null)
    {
        $this->Db = Db::getConnection();
        $this->id = $id;
    }

    public function getApiPath(): string
    {
        return 'api/v2/idps/';
    }

    public function postAction(Action $action, array $reqBody): int
    {
        $this->canWriteOrExplode();
        return $this->create(
            name: $reqBody['name'],
            entityid: $reqBody['entityid'],
            sso_url: $reqBody['sso_url'],
            sso_binding: $reqBody['sso_binding'],
            slo_url: $reqBody['slo_url'],
            slo_binding: $reqBody['slo_binding'],
            x509: $reqBody['x509'],
            x509_new: $reqBody['x509_new'] ?? $reqBody['x509'],
            email_attr: $reqBody['email_attr'],
            team_attr: $reqBody['team_attr'] ?? null,
            fname_attr: $reqBody['fname_attr'],
            lname_attr: $reqBody['lname_attr'],
            orgid_attr: $reqBody['orgid_attr'] ?? null,
        );
    }

    public function readOne(): array
    {
        $sql = 'SELECT * FROM idps WHERE id = :id';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':id', $this->id, PDO::PARAM_INT);
        $this->Db->execute($req);

        return $this->Db->fetch($req);
    }

    public function readAll(): array
    {
        $sql = 'SELECT idps.*, idps_sources.url AS source_url FROM idps LEFT JOIN idps_sources ON idps.source = idps_sources.id ORDER BY name';
        $req = $this->Db->prepare($sql);
        $this->Db->execute($req);

        return $req->fetchAll();
    }

    public function patch(Action $action, array $params): array
    {
        $this->canWriteOrExplode();
        foreach ($params as $key => $value) {
            $this->update($key, $value);
        }
        return $this->readOne();
    }

    public function toggleEnabledFromSource(int $sourceId, int $enabled): bool
    {
        $this->canWriteOrExplode();
        $sql = 'UPDATE idps SET enabled = :enabled WHERE source = :id';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':id', $sourceId, PDO::PARAM_INT);
        $req->bindParam(':enabled', $enabled, PDO::PARAM_INT);
        return $this->Db->execute($req);
    }

    public function getEnabled(?int $id = null): array
    {
        $sql = 'SELECT * FROM idps WHERE enabled = 1';
        if ($id !== null) {
            $sql .= ' AND id = :id';
        }
        $req = $this->Db->prepare($sql);
        if ($id !== null) {
            $req->bindParam(':id', $id, PDO::PARAM_INT);
        }
        $this->Db->execute($req);

        return $this->Db->fetch($req);
    }

    public function getEnabledByEntityId(string $entId): array
    {
        $sql = 'SELECT * FROM idps WHERE enabled = 1 AND entityid = :entId';
        $req = $this->Db->prepare($sql);

        $req->bindParam(':entId', $entId);
        $this->Db->execute($req);

        return $this->Db->fetch($req);
    }

    public function destroy(): bool
    {
        $this->canWriteOrExplode();
        $sql = 'DELETE FROM idps WHERE id = :id';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':id', $this->id, PDO::PARAM_INT);

        return $this->Db->execute($req);
    }

    public function create(
        string $name,
        string $entityid,
        string $sso_url,
        string $x509,
        string $x509_new = '',
        ?string $slo_url = '',
        string $sso_binding = self::SSO_BINDING,
        string $slo_binding = self::SLO_BINDING,
        string $email_attr = self::EMAIL_ATTR,
        ?string $team_attr = self::TEAM_ATTR,
        string $fname_attr = self::FNAME_ATTR,
        string $lname_attr = self::LNAME_ATTR,
        ?string $orgid_attr = self::ORGID_ATTR,
        int $enabled = 1,
        ?int $source = null,
    ): int {
        $this->canWriteOrExplode();
        if (empty($x509_new)) {
            $x509_new = $x509;
        }
        $sql = 'INSERT INTO idps(name, entityid, sso_url, sso_binding, slo_url, slo_binding, x509, x509_new, email_attr, team_attr, fname_attr, lname_attr, orgid_attr, enabled, source)
            VALUES(:name, :entityid, :sso_url, :sso_binding, :slo_url, :slo_binding, :x509, :x509_new, :email_attr, :team_attr, :fname_attr, :lname_attr, :orgid_attr, :enabled, :source)';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':name', $name);
        $req->bindParam(':entityid', $entityid);
        $req->bindParam(':sso_url', $sso_url);
        $req->bindParam(':sso_binding', $sso_binding);
        $req->bindParam(':slo_url', $slo_url);
        $req->bindParam(':slo_binding', $slo_binding);
        $req->bindParam(':x509', $x509);
        $req->bindParam(':x509_new', $x509_new);
        $req->bindParam(':email_attr', $email_attr);
        $req->bindParam(':team_attr', $team_attr);
        $req->bindParam(':fname_attr', $fname_attr);
        $req->bindParam(':lname_attr', $lname_attr);
        $req->bindParam(':orgid_attr', $orgid_attr);
        $req->bindParam(':enabled', $enabled, PDO::PARAM_INT);
        $req->bindParam(':source', $source);
        $this->Db->execute($req);

        return $this->Db->lastInsertId();
    }

    public function findByEntityId(string $entityId): int
    {
        $sql = 'SELECT id FROM idps WHERE entityid = :entityId';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':entityId', $entityId);
        $this->Db->execute($req);
        $res = $req->fetchColumn();
        if ($res === false) {
            return 0;
        }
        return (int) $res;
    }

    private function canWriteOrExplode(): void
    {
        if ($this->requester->userData['is_sysadmin'] !== 1) {
            throw new IllegalActionException('Only a Sysadmin can modify this!');
        }
    }

    private function update(string $target, string $value): array
    {
        $sql = 'UPDATE idps SET ' . $target . ' = :value WHERE id = :id';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':value', $value);
        $req->bindParam(':id', $this->id, PDO::PARAM_INT);
        $this->Db->execute($req);
        return $this->readOne();
    }
}
