<?php
/**
 * \Elabftw\Elabftw\Update
 *
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
namespace Elabftw\Elabftw;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use Defuse\Crypto\Crypto as Crypto;
use Defuse\Crypto\Exception as Ex;
use Defuse\Crypto\Key as Key;

/**
 * Use this to check for latest version or update the database schema
 */
class Update
{
    /** 1.1.4 */
    private $version;
    /** release date of the version */
    protected $releaseDate;

    /** our favorite pdo object */
    private $pdo;

    /** this is used to check if we managed to get a version or not */
    public $success = false;

    /** instance of Config */
    public $Config;

    /** where to get info from */
    const URL = 'https://get.elabftw.net/updates.ini';
    /** if we can't connect in https for some reason, use http */
    const URL_HTTP = 'http://get.elabftw.net/updates.ini';

    /**
     * ////////////////////////////
     * UPDATE THIS AFTER RELEASING
     * UPDATE IT ALSO IN package.json
     * ///////////////////////////
     */
    const INSTALLED_VERSION = '1.5.3';

    /**
     * /////////////////////////////////////////////////////
     * UPDATE THIS AFTER ADDING A BLOCK TO runUpdateScript()
     * UPDATE IT ALSO IN INSTALL/ELABFTW.SQL (last line)
     * AND REFLECT THE CHANGE IN INSTALL/ELABFTW.SQL
     * AND REFLECT THE CHANGE IN tests/_data/phpunit.sql
     * /////////////////////////////////////////////////////
     */
    const REQUIRED_SCHEMA = '16';

    /**
     * Create the pdo object
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->Config = $config;
        $this->pdo = Db::getConnection();
    }

    /**
     * Return the installed version of elabftw
     *
     * @return string
     */
    public function getInstalledVersion()
    {
        return self::INSTALLED_VERSION;
    }

    /**
     * Make a get request with cURL, using proxy setting if any
     *
     * @param string $url URL to hit
     * @param bool|string $toFile path where we want to save the file
     * @return string|boolean Return true if the download succeeded, else false
     */
    protected function get($url, $toFile = false)
    {
        if (!extension_loaded('curl')) {
            throw new Exception('Please install php5-curl package.');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        // this is to get content
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // add proxy if there is one
        if (strlen($this->Config->configArr['proxy']) > 0) {
            curl_setopt($ch, CURLOPT_PROXY, $this->Config->configArr['proxy']);
        }
        // disable certificate check
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        // add user agent
        // http://developer.github.com/v3/#user-agent-required
        curl_setopt($ch, CURLOPT_USERAGENT, "Elabftw/" . self::INSTALLED_VERSION);

        // add a timeout, because if you need proxy, but don't have it, it will mess up things
        // 5 seconds
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        // we don't want the header
        curl_setopt($ch, CURLOPT_HEADER, 0);

        if ($toFile) {
            $handle = fopen($toFile, 'w');
            curl_setopt($ch, CURLOPT_FILE, $handle);
        }

        // DO IT!
        return curl_exec($ch);
    }

    /**
     * Return the latest version of elabftw
     * Will fetch updates.ini file from elabftw.net
     *
     * @throws Exception the version we have doesn't look like one
     * @return string|bool|null latest version or false if error
     */
    public function getUpdatesIni()
    {
        $ini = $this->get(self::URL);
        // try with http if https failed (see #176)
        if (!$ini) {
            $ini = $this->get(self::URL_HTTP);
        }
        if (!$ini) {
            $this->success = false;
            throw new Exception('Error getting latest version information from server! Check the proxy setting.');
        }
        // convert ini into array. The `true` is for process_sections: to get multidimensionnal array.
        $versions = parse_ini_string($ini, true);
        // get the latest version
        $this->version = array_keys($versions)[0];
        $this->releaseDate = $versions[$this->version]['date'];

        if (!$this->validateVersion()) {
            throw new Exception('Error getting latest version information from server! Check the proxy setting.');
        }
        $this->success = true;
    }

    /**
     * Check if the version string actually looks like a version
     *
     * @return int 1 if version match
     */
    private function validateVersion()
    {
        return preg_match('/[0-99]+\.[0-99]+\.[0-99]+.*/', $this->version);
    }

    /**
     * Return true if there is a new version out there
     *
     * @return bool
     */
    public function updateIsAvailable()
    {
        return self::INSTALLED_VERSION != $this->version;
    }

    /**
     * Return the latest version string
     *
     * @return string|int 1.1.4
     */
    public function getLatestVersion()
    {
        return $this->version;
    }

    /**
     * Get when the latest version was released
     *
     * @return string
     */
    public function getReleaseDate()
    {
        return $this->releaseDate;
    }

    /**
     * Get the documentation link for the changelog button
     *
     * @return string URL for changelog
     */
    public function getChangelogLink()
    {
        $base = "https://elabftw.readthedocs.io/en/latest/changelog.html#version-";
        $dashedVersion = str_replace(".", "-", $this->version);

        return $base . $dashedVersion;
    }

    /**
     * Update the database schema if needed.
     *
     * @return string[] $msg_arr
     */
    public function runUpdateScript()
    {
        $msg_arr = array();

        $current_schema = $this->Config->configArr['schema'];

        if ($current_schema < 2) {
            // 20150727
            $this->schema2();
            $this->updateSchema(2);
        }
        if ($current_schema < 3) {
            // 20150728
            $this->schema3();
            $this->updateSchema(3);
        }
        if ($current_schema < 4) {
            // 20150801
            $this->schema4();
            $this->updateSchema(4);
        }
        if ($current_schema < 5) {
            // 20150803
            $this->schema5();
            $this->updateSchema(5);
        }
        if ($current_schema < 6) {
            // 20160129
            $this->schema6();
            $this->updateSchema(6);
        }
        if ($current_schema < 7) {
            // 20160209
            $this->schema7();
            $this->updateSchema(7);
        }
        if ($current_schema < 8) {
            // 20160420
            $this->schema8();
            $this->updateSchema(8);
        }
        if ($current_schema < 9) {
            // 20160623
            $this->schema9();
            $this->updateSchema(9);
            $msg_arr[] = "[WARNING] The config file has been changed! If you are running Docker, make sure to copy your secret key to the yml file. Check the release notes!";
        }
        if ($current_schema < 10) {
            // 20160722
            $this->schema10();
            $this->updateSchema(10);
        }
        if ($current_schema < 11) {
            // 20160812
            $this->schema11();
            $this->updateSchema(11);
        }
        if ($current_schema < 12) {
            // 20161016
            $this->schema12();
            $this->updateSchema(12);
        }
        if ($current_schema < 13) {
            // 20161219
            $this->schema13();
            $this->updateSchema(13);
        }

        if ($current_schema < 14) {
            // 20170121
            $this->schema14();
            $this->updateSchema(14);
        }

        if ($current_schema < 15) {
            // 20170124
            $this->schema15();
            $this->updateSchema(15);
        }

        if ($current_schema < 16) {
            // 20170124
            $this->schema16();
            $this->updateSchema(16);
        }
        // place new schema functions above this comment

        // remove files in uploads/tmp
        $this->cleanTmp();

        $msg_arr[] = "[SUCCESS] You are now running the latest version of eLabFTW. Have a great day! :)";

        return $msg_arr;
    }

    /**
     * Delete things in the tmp folder
     */
    private function cleanTmp()
    {
        // cleanup files in tmp
        $dir = ELAB_ROOT . '/uploads/tmp';
        $di = new \RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        $ri = new \RecursiveIteratorIterator($di, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($ri as $file) {
            $file->isDir() ? rmdir($file) : unlink($file);
        }
    }

    /**
     * Update the schema value in config to latest because we did the update functions before
     *
     * @param string|null $schema the version we want to update
     */
    private function updateSchema($schema = null)
    {
        if (is_null($schema)) {
            $schema = self::REQUIRED_SCHEMA;
        }
        $config_arr = array('schema' => $schema);
        if (!$this->Config->Update($config_arr)) {
            throw new Exception('Failed at updating the schema!');
        }
    }

    /**
     * Add a default value to deletable_xp.
     * Can't do the same for link_href and link_name because they are text
     *
     * @throws Exception if there is a problem
     */
    private function schema2()
    {
        $sql = "ALTER TABLE teams CHANGE deletable_xp deletable_xp TINYINT(1) NOT NULL DEFAULT '1'";
        if (!$this->pdo->q($sql)) {
            throw new Exception('Problem updating!');
        }
    }

    /**
     * Change the experiments_revisions structure to allow code reuse
     *
     * @throws Exception if there is a problem
     */
    private function schema3()
    {
        $sql = "ALTER TABLE experiments_revisions CHANGE exp_id item_id INT(10) UNSIGNED NOT NULL";
        if (!$this->pdo->q($sql)) {
            throw new Exception('Problem updating!');
        }
    }

    /**
     * Add user groups
     *
     * @throws Exception if there is a problem
     */
    private function schema4()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `team_groups` ( `id` INT UNSIGNED NOT NULL AUTO_INCREMENT , `name` VARCHAR(255) NOT NULL , `team` INT UNSIGNED NOT NULL , PRIMARY KEY (`id`));";
        $sql2 = "CREATE TABLE IF NOT EXISTS `users2team_groups` ( `userid` INT UNSIGNED NOT NULL , `groupid` INT UNSIGNED NOT NULL );";
        if (!$this->pdo->q($sql) || !$this->pdo->q($sql2)) {
            throw new Exception('Problem updating!');
        }
    }

    /**
     * Switch the crypto lib to defuse/php-encryption
     *
     * EDIT 20160624: this function will not work now because of switch from 1.2 to 2.0
     * So tell the user to first update to 1.2.0-p3.
     * @throws Exception
     */
    private function schema5()
    {
        throw new Exception('Please update first to 1.2.0-p3 (git checkout 1.2.0-p3) before updating to the latest version.');
    }

    /**
     * Change column type of body in 'items' and 'experiments' to 'mediumtext'
     *
     * @throws Exception
     */
    private function schema6()
    {
        $sql = "ALTER TABLE experiments MODIFY body MEDIUMTEXT";
        $sql2 = "ALTER TABLE items MODIFY body MEDIUMTEXT";

        if (!$this->pdo->q($sql)) {
            throw new Exception('Cannot change type of column "body" in table "experiments"!');
        }
        if (!$this->pdo->q($sql2)) {
            throw new Exception('Cannot change type of column "body" in table "items"!');
        }
    }

    /**
     * Change md5 to generic hash column in uploads
     * Create column to store the used algorithm type
     *
     * @throws Exception
     */
    private function schema7()
    {
        // First rename the column and then change its type to VARCHAR(128).
        // This column will be able to keep any sha2 hash up to sha512.
        // Add a hash_algorithm column to store the algorithm used to create
        // the hash.
        $sql3 = "ALTER TABLE `uploads` CHANGE `md5` `hash` VARCHAR(32);";
        if (!$this->pdo->q($sql3)) {
            throw new Exception('Error renaming column "md5" in table "uploads"!');
        }
        $sql4 = "ALTER TABLE `uploads` MODIFY `hash` VARCHAR(128);";
        if (!$this->pdo->q($sql4)) {
            throw new Exception('Error changing column type of "hash" in table "uploads"!');
        }
        // Already existing hashes are exclusively md5
        $sql5 = "ALTER TABLE `uploads` ADD `hash_algorithm` VARCHAR(10) DEFAULT NULL; UPDATE `uploads` SET `hash_algorithm`='md5' WHERE `hash` IS NOT NULL;";
        if (!$this->pdo->q($sql5)) {
            throw new Exception('Error setting hash algorithm for existing entries!');
        }

    }

    /**
     * Remove username from users
     *
     * @throws Exception
     */
    private function schema8()
    {
        $sql = "ALTER TABLE `users` DROP `username`";
        if (!$this->pdo->q($sql)) {
            throw new Exception('Error removing username column');
        }
    }

    /**
     * Update the crypto lib to the latest version
     *
     * @throws Exception
     */
    private function schema9()
    {
        if (!is_writable(ELAB_ROOT . 'config.php')) {
            throw new Exception('Please make your config file writable by server for this update.');
        }
        // grab old key
        $legacy_key = hex2bin(SECRET_KEY);
        // make a new one too
        $new_key = Key::createNewRandomKey();

        // update smtp_password first
        if ($this->Config->configArr['smtp_password']) {
            try {
                $plaintext = Crypto::legacyDecrypt(hex2bin($this->Config->configArr['smtp_password']), $legacy_key);
            } catch (Ex\WrongKeyOrModifiedCiphertextException $ex) {
                throw new Exception('Wrong key or modified ciphertext error.');
            }
            // now encrypt it with the new method
            $new_ciphertext = Crypto::encrypt($plaintext, $new_key);
            $this->Config->update(array('smtp_password' => $new_ciphertext));
        }

        // now update the stamppass from the teams
        $sql = 'SELECT team_id, stamppass FROM teams';
        $req = $this->pdo->prepare($sql);
        $req->execute();
        while ($teams = $req->fetch()) {
            if ($teams['stamppass']) {
                try {
                    $plaintext = Crypto::legacyDecrypt(hex2bin($teams['stamppass']), $legacy_key);
                } catch (Ex\WrongKeyOrModifiedCiphertextException $ex) {
                    throw new Exception('Wrong key or modified ciphertext error.');
                }
                $new_ciphertext = Crypto::encrypt($plaintext, $new_key);
                $sql = 'UPDATE teams SET stamppass = :stamppass WHERE team_id = :team_id';
                $update = $this->pdo->prepare($sql);
                $update->bindParam(':stamppass', $new_ciphertext);
                $update->bindParam(':team_id', $teams['team_id']);
                $update->execute();
            }
        }

        // update the main stamppass
        if ($this->Config->configArr['stamppass']) {
            try {
                $plaintext = Crypto::legacyDecrypt(hex2bin($this->Config->configArr['stamppass']), $legacy_key);
            } catch (Ex\WrongKeyOrModifiedCiphertextException $ex) {
                throw new Exception('Wrong key or modified ciphertext error.');
            }
            // now encrypt it with the new method
            $new_ciphertext = Crypto::encrypt($plaintext, $new_key);
            $this->Config->update(array('stamppass' => $new_ciphertext));
        }

            // rewrite the config file with the new key
            $contents = "<?php
define('DB_HOST', '" . DB_HOST . "');
define('DB_NAME', '" . DB_NAME . "');
define('DB_USER', '" . DB_USER . "');
define('DB_PASSWORD', '" . DB_PASSWORD . "');
define('ELAB_ROOT', '" . ELAB_ROOT . "');
define('SECRET_KEY', '" . $new_key->saveToAsciiSafeString() . "');
";

        if (file_put_contents(ELAB_ROOT . 'config.php', $contents) == 'false') {
            throw new Exception('There was a problem writing the file!');
        }
    }

    /**
     * Add team calendar
     *
     */
    private function schema10()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `team_events` ( `id` INT UNSIGNED NOT NULL AUTO_INCREMENT , `team` INT UNSIGNED NOT NULL , `item` INT UNSIGNED NOT NULL, `start` VARCHAR(255) NOT NULL, `end` VARCHAR(255), `title` VARCHAR(255) NULL DEFAULT NULL, `userid` INT UNSIGNED NOT NULL, PRIMARY KEY (`id`));";
        $sql2 = "ALTER TABLE `items_types` ADD `bookable` BOOL NULL DEFAULT FALSE";
        if (!$this->pdo->q($sql) || !$this->pdo->q($sql2)) {
            throw new Exception('Problem updating to schema 10!');
        }
    }

    /**
     * Add show_team in user prefs
     *
     */
    private function schema11()
    {
        $sql = "ALTER TABLE `users` ADD `show_team` TINYINT NOT NULL DEFAULT '0'";
        if (!$this->pdo->q($sql)) {
            throw new Exception('Problem updating to schema 11!');
        }
    }
    /**
     * Change path to pki cert
     *
     */
    private function schema12()
    {
        if ($this->Config->configArr['stampcert'] === 'vendor/pki.dfn.pem') {
            if (!$this->Config->update(array('stampcert' => 'app/dfn-cert/pki.dfn.pem'))) {
                throw new Exception('Error changing path to timestamping cert. (updating to schema 12)');
            }
        }
    }

    /**
     * Add todolist table and update any old documentation link (local one)
     *
     */
    private function schema13()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `todolist` (
          `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
          `body` text NOT NULL,
          `creation_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `ordering` int(10) UNSIGNED DEFAULT NULL,
          `userid` int(10) UNSIGNED NOT NULL,
          PRIMARY KEY (`id`));";

        if (!$this->pdo->q($sql)) {
            throw new Exception('Problem updating to schema 13!');
        }

        // update the links. Use % because we might have index.html at the end
        $sql = "UPDATE teams
            SET link_href = 'https://elabftw.readthedocs.io'
            WHERE link_href LIKE 'doc/_build/html%'";
        if (!$this->pdo->q($sql)) {
            throw new Exception('Problem updating to schema 13!');
        }
    }

    /**
     * Make bgcolor be color
     *
     */
    private function schema14()
    {
        $sql = "ALTER TABLE `items_types` CHANGE `bgcolor` `color` VARCHAR(6)";
        if (!$this->pdo->q($sql)) {
            throw new Exception('Error updating to schema14');
        }
    }

    /**
     * Add api key to users
     *
     */
    private function schema15()
    {
        $sql = "ALTER TABLE `users` ADD `api_key` VARCHAR(255) NULL DEFAULT NULL AFTER `show_team`;";
        if (!$this->pdo->q($sql)) {
            throw new Exception('Error updating to schema15');
        }
    }
    /**
     * Add default_vis to users
     *
     */
    private function schema16()
    {
        $sql = "ALTER TABLE `users` ADD `default_vis` VARCHAR(255) NULL DEFAULT 'team';";
        if (!$this->pdo->q($sql)) {
            throw new Exception('Error updating to schema16');
        }
    }
}
