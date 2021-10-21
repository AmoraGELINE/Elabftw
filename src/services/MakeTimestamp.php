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

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use function dirname;
use Elabftw\Elabftw\App;
use Elabftw\Exceptions\FilesystemErrorException;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Models\Config;
use Elabftw\Models\Experiments;
use function file_get_contents;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use function hash_file;
use function is_dir;
use function is_readable;
use function mkdir;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use PDO;
use Psr\Http\Message\StreamInterface;
use const SECRET_KEY;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Timestamp an experiment with RFC 3161
 * Based on:
 * http://www.d-mueller.de/blog/dealing-with-trusted-timestamps-in-php-rfc-3161
 */
class MakeTimestamp extends AbstractMake
{
    /** default hash algo for file */
    private const TS_HASH = 'sha256';

    /** @var Experiments $Entity */
    protected $Entity;

    private string $pdfPath = '';

    // name of the pdf (elabid-timestamped.pdf)
    private string $pdfRealName = '';

    // a random long string
    private string $pdfLongName = '';

    // config (url, login, password, cert)
    private array $stampParams = array();

    // things that get deleted with destruct method
    private array $trash = array();

    // where we store the request file
    private string $requestfilePath = '';

    // where we store the asn1 token
    private string $responsefilePath = '';

    public function __construct(protected array $configArr, Experiments $entity, private ClientInterface $client)
    {
        parent::__construct($entity);
        $this->Entity->canOrExplode('write');

        // stampParams contains login/pass/cert/url/hash information
        $this->stampParams = $this->getTimestampParameters();

        // set the name of the pdf (elabid + -timestamped.pdf)
        $this->pdfRealName = $this->getFileName();
        $this->requestfilePath = $this->getTmpPath() . $this->getUniqueString();
        // we don't keep this file around
        $this->trash[] = $this->requestfilePath;
    }

    /**
     * Delete all temporary files once the processus is completed
     */
    public function __destruct()
    {
        foreach ($this->trash as $file) {
            unlink($file);
        }
    }

    /**
     * The realname is $elabid-timestamped.pdf
     */
    public function getFileName(): string
    {
        return $this->Entity->entityData['elabid'] . '-timestamped.pdf';
    }

    /**
     * The main function.
     * Request a timestamp and parse the response.
     *
     * @throws ImproperActionException
     */
    public function timestamp(): bool
    {
        if (!$this->Entity->isTimestampable()) {
            throw new ImproperActionException('Timestamping is not allowed for this experiment.');
        }

        // generate the pdf of the experiment that will be timestamped
        $this->generatePdf();

        // create the request file that will be sent to the TSA
        $this->createRequestfile();

        // get an answer from the TSA and
        // save the token to .asn1 file
        $this->saveToken($this->postData()->getBody());

        // validate everything so we are sure it is OK
        $this->validate();

        // SQL
        $responseTime = $this->formatResponseTime($this->getTimestampFromResponseFile());
        $this->Entity->updateTimestamp($responseTime, $this->responsefilePath);
        return $this->sqlInsertPdf();
    }

    /**
     * Return the needed parameters to request/verify a timestamp
     *
     * @return array<string,string>
     */
    protected function getTimestampParameters(): array
    {
        $config = $this->configArr;
        // make sure we use system configuration if override_tsa is not active
        if ($config['override_tsa'] === '0') {
            $config = Config::getConfig()->configArr;
        }

        $login = $config['stamplogin'];

        $password = '';
        if (($config['ts_password'] ?? '') !== '') {
            $password = Crypto::decrypt($config['ts_password'], Key::loadFromAsciiSafeString(SECRET_KEY));
        }
        $provider = $config['stampprovider'];
        $cert = $config['stampcert'];
        $hash = $config['stamphash'];

        $allowedAlgos = array('sha256', 'sha384', 'sha512');
        if (!in_array($hash, $allowedAlgos, true)) {
            $hash = self::TS_HASH;
        }

        return array(
            'stamplogin' => $login,
            'ts_password' => $password,
            'stampprovider' => $provider,
            'stampcert' => $cert,
            'hash' => $hash,
            );
    }

    protected function getTimestampFromResponseFile(): string
    {
        if (!is_readable($this->responsefilePath)) {
            throw new ImproperActionException('The token is not readable.');
        }

        $output = $this->runProcess(array(
            'openssl',
            'ts',
            '-reply',
            '-in',
            $this->responsefilePath,
            '-text',
        ));

        /*
         * Format of output:
         *
         * Status info:
         *   Status: Granted.
         *   Status description: unspecified
         *   Failure info: unspecified
         *
         *   TST info:
         *   Version: 1
         *   Policy OID: 1.3.6.1.4.1.15819.5.2.2
         *   Hash Algorithm: sha256
         *   Message data:
         *       0000 - 3a 9a 6c 32 12 7f b0 c7-cd e0 c9 9e e2 66 be a9   :.l2.........f..
         *       0010 - 20 b9 b1 83 3d b1 7c 16-e4 ac b0 5f 43 bc 40 eb    ...=.|...._C.@.
         *   Serial number: 0xA7452417D851301981FA9A7CC2CF776B5934D3E5
         *   Time stamp: Apr 27 13:37:34.363 2015 GMT
         *   Accuracy: unspecified seconds, 0x01 millis, unspecified micros
         *   Ordering: yes
         *   Nonce: unspecified
         *   TSA: DirName:/CN=Universign Timestamping Unit 012/OU=0002 43912916400026/O=Cryptolog International/C=FR
         *   Extensions:
         */

        $matches = array();
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            if (preg_match("~^Time\sstamp\:\s(.*)~", $line, $matches)) {
                return $matches[1];
            }
        }
        throw new ImproperActionException('Could not get response time!');
    }

    /**
     * Convert the time found in the response file to the correct format for sql insertion
     */
    protected function formatResponseTime(string $timestamp): string
    {
        $time = strtotime($timestamp);
        if ($time === false) {
            throw new ImproperActionException('Could not get response time!');
        }
        return date('Y-m-d H:i:s', $time);
    }

    /**
     * Generate the pdf to timestamp
     */
    private function generatePdf(): void
    {
        $userData = $this->Entity->Users->userData;
        $MpdfProvider = new MpdfProvider(
            $userData['fullname'],
            $userData['pdf_format'],
            (bool) $userData['pdfa'],
        );
        $MakePdf = new MakePdf($MpdfProvider, $this->Entity);
        $MakePdf->outputToFile();
        $this->pdfPath = $MakePdf->filePath;
        $this->pdfLongName = $MakePdf->longName;
    }

    /**
     * Run a process
     *
     * @param array<string> $args arguments including the executable
     * @param string|null $cwd command working directory
     */
    private function runProcess(array $args, ?string $cwd = null): string
    {
        $Process = new Process($args, $cwd);
        $Process->mustRun();

        return $Process->getOutput();
    }

    /**
     * Creates a Timestamp Requestfile from a filename
     *
     * @throws ImproperActionException
     */
    private function createRequestfile(): void
    {
        $this->runProcess(array(
            'openssl',
            'ts',
            '-query',
            '-data',
            $this->pdfPath,
            '-cert',
            '-' . $this->stampParams['hash'],
            '-no_nonce',
            '-out',
            $this->requestfilePath,
        ));
    }

    /**
     * Contact the TSA and receive a token after successful timestamp
     *
     * @throws ImproperActionException
     */
    private function postData(): \Psr\Http\Message\ResponseInterface
    {
        $options = array(
            // add user agent
            // http://developer.github.com/v3/#user-agent-required
            'headers' => array(
                'User-Agent' => 'Elabftw/' . App::INSTALLED_VERSION,
                'Content-Type' => 'application/timestamp-query',
                'Content-Transfer-Encoding' => 'base64',
            ),
            // add proxy if there is one
            'proxy' => $this->configArr['proxy'],
            // add a timeout, because if you need proxy, but don't have it, it will mess up things
            // in seconds
            'timeout' => 5,
            'body' => file_get_contents($this->requestfilePath),
        );

        if ($this->stampParams['stamplogin'] && $this->stampParams['ts_password']) {
            $options['auth'] = array(
                $this->stampParams['stamplogin'],
                $this->stampParams['ts_password'],
            );
        }

        try {
            return $this->client->request('POST', $this->stampParams['stampprovider'], $options);
        } catch (RequestException $e) {
            throw new ImproperActionException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Get the hash of a file
     *
     * @param string $file Path to the file
     * @throws ImproperActionException if file is not readable
     * @return string Hash of the file
     */
    private function getHash($file): string
    {
        $hash = hash_file($this->stampParams['hash'], $file);
        if ($hash === false) {
            throw new ImproperActionException('The file is not readable.');
        }
        return $hash;
    }

    /**
     * Save the binaryToken to a .asn1 file
     *
     * @throws ImproperActionException
     * @param StreamInterface $binaryToken asn1 response from TSA
     */
    private function saveToken(StreamInterface $binaryToken): bool
    {
        $longName = $this->getLongName() . '.asn1';
        $filePath = $this->getUploadsPath() . $longName;
        $dir = dirname($filePath);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new FilesystemErrorException('Cannot create folder! Check permissions of uploads folder.');
        }
        if (!file_put_contents($filePath, $binaryToken)) {
            throw new FilesystemErrorException('Cannot save token to disk!');
        }
        $this->responsefilePath = $filePath;

        $realName = $this->pdfRealName . '.asn1';
        $hash = $this->getHash($this->responsefilePath);

        // keep a trace of where we put the token
        $sql = 'INSERT INTO uploads(real_name, long_name, comment, item_id, userid, type, hash, hash_algorithm)
            VALUES(:real_name, :long_name, :comment, :item_id, :userid, :type, :hash, :hash_algorithm)';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':real_name', $realName);
        $req->bindParam(':long_name', $longName);
        $req->bindValue(':comment', 'Timestamp token');
        $req->bindParam(':item_id', $this->Entity->id, PDO::PARAM_INT);
        $req->bindParam(':userid', $this->Entity->Users->userData['userid'], PDO::PARAM_INT);
        $req->bindValue(':type', 'timestamp-token');
        $req->bindParam(':hash', $hash);
        $req->bindParam(':hash_algorithm', $this->stampParams['hash']);
        return $this->Db->execute($req);
    }

    /**
     * Validates a file against its timestamp and optionally check a provided time for consistence with the time encoded
     * in the timestamp itself.
     *
     * @throws ImproperActionException
     */
    private function validate(): bool
    {
        $certPath = $this->stampParams['stampcert'];

        if (!is_readable($certPath)) {
            throw new ImproperActionException('Cannot read the certificate file!');
        }

        try {
            $this->runProcess(array(
                'openssl',
                'ts',
                '-verify',
                '-data',
                $this->pdfPath,
                '-in',
                $this->responsefilePath,
                '-CAfile',
                $certPath,
            ));
        } catch (ProcessFailedException) {
            // we are facing the OpenSSL bug discussed here:
            // https://github.com/elabftw/elabftw/issues/242#issuecomment-212382182
            return $this->validateWithJava();
        }

        return true;
    }

    /**
     * Check if we have java
     */
    private function isJavaInstalled(): void
    {
        try {
            $this->runProcess(array('which', 'java'));
        } catch (ProcessFailedException $e) {
            throw new ImproperActionException("Could not validate the timestamp due to a bug in OpenSSL library. See <a href='https://github.com/elabftw/elabftw/issues/242#issuecomment-212382182'>issue #242</a>. Tried to validate with failsafe method but Java is not installed.", (int) $e->getCode(), $e);
        }
    }

    /**
     * Validate the timestamp with java and BouncyCastle lib
     * We need this because of the openssl bug
     *
     * @throws ImproperActionException
     */
    private function validateWithJava(): bool
    {
        $this->isJavaInstalled();

        $cwd = dirname(__DIR__, 2) . '/src/dfn-cert/timestampverifier/';
        try {
            $output = $this->runProcess(array(
                './verify.sh',
                $this->requestfilePath,
                $this->responsefilePath,
            ), $cwd);
        } catch (ProcessFailedException $e) {
            $Log = new Logger('elabftw');
            $Log->pushHandler(new ErrorLogHandler());
            $Log->error('', array(array('userid' => $this->Entity->Users->userData['userid']), array('Error', $e)));
            $msg = 'Could not validate the timestamp with java failsafe method. Maybe your java version is too old? Please report this bug on GitHub.';
            throw new ImproperActionException($msg, (int) $e->getCode(), $e);
        }
        return (bool) stripos($output, 'matches');
    }

    /**
     * Add also our pdf to the attached files of the experiment, this way it is kept safely :)
     * I had this idea when realizing that if you comment an experiment, the hash won't be good anymore. Because the pdf will contain the new comments.
     * Keeping the pdf here is the best way to go, as this leaves room to leave comments.
     */
    private function sqlInsertPdf(): bool
    {
        $hash = $this->getHash($this->pdfPath);

        $sql = 'INSERT INTO uploads(real_name, long_name, comment, item_id, userid, type, hash, hash_algorithm) VALUES(:real_name, :long_name, :comment, :item_id, :userid, :type, :hash, :hash_algorithm)';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':real_name', $this->pdfRealName);
        $req->bindParam(':long_name', $this->pdfLongName);
        $req->bindValue(':comment', 'Timestamped PDF');
        $req->bindParam(':item_id', $this->Entity->id, PDO::PARAM_INT);
        $req->bindParam(':userid', $this->Entity->Users->userData['userid'], PDO::PARAM_INT);
        $req->bindValue(':type', 'exp-pdf-timestamp');
        $req->bindParam(':hash', $hash);
        $req->bindParam(':hash_algorithm', $this->stampParams['hash']);

        return $this->Db->execute($req);
    }
}
