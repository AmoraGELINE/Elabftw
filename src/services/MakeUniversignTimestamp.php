<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @author Alexander Minges <alexander.minges@gmail.com>
 * @author David Müller
 * @copyright 2015 Nicolas CARPi, Alexander Minges
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Services;

use DateTime;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use function dirname;
use Elabftw\Exceptions\ImproperActionException;
use const SECRET_KEY;

/**
 * RFC3161 timestamping with Universign service
 * https://www.universign.com/en/
 */
class MakeUniversignTimestamp extends MakeTimestamp
{
    protected const TS_URL = 'https://ws.universign.eu/tsa';

    protected const TS_CHAIN = 'universign-chain.pem';

    protected const TS_CERT = 'universign.pem';

    protected const TS_HASH = 'sha256';

    /**
     * Return the needed parameters to request/verify a timestamp
     *
     * @return array<string,string>
     */
    public function getTimestampParameters(): array
    {
        $config = $this->configArr;

        if (empty($config['ts_login'])) {
            throw new ImproperActionException('Universign timestamping requires a login!');
        }

        if (empty($config['ts_password'])) {
            throw new ImproperActionException('Universign timestamping requires a password!');
        }
        $password = Crypto::decrypt($config['ts_password'], Key::loadFromAsciiSafeString(SECRET_KEY));

        return array(
            'ts_login' => $config['ts_login'],
            'ts_password' => $password,
            // use static here so the dev class ts_url override is taken into account
            'ts_url' => static::TS_URL,
            'ts_cert' => dirname(__DIR__) . '/ts-certs/' . self::TS_CERT,
            'ts_hash' => self::TS_HASH,
            'ts_chain' => dirname(__DIR__) . '/ts-certs/' . self::TS_CHAIN,
            );
    }

    /**
     * Convert the time found in the response file to the correct format for sql insertion
     */
    protected function formatResponseTime(string $timestamp): string
    {
        $date = DateTime::createFromFormat('M j H:i:s.u Y T', $timestamp);
        if ($date instanceof DateTime) {
            // Return formatted time as this is what we will store in the database.
            // PHP will take care of correct timezone conversions (if configured correctly)
            return date('Y-m-d H:i:s', $date->getTimestamp());
        }
        throw new ImproperActionException('Could not get response time!');
    }
}
