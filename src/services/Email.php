<?php
/**
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
declare(strict_types=1);

namespace Elabftw\Services;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Elabftw\Elabftw\Tools;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Models\Config;
use Elabftw\Models\Users;
use PDO;
use Swift_Mailer;
use Swift_Message;
use Swift_SendmailTransport;
use Swift_SmtpTransport;
use Symfony\Component\HttpFoundation\Request;

/**
 * Email service
 */
class Email
{
    /** @var Config $Config instance of Config */
    private $Config;

    /**
     * Constructor
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->Config = $config;
    }

    /**
     * Fetch the email(s) of the admin(s) for a team
     *
     * @param int $team
     * @return array
     */
    private function getAdminEmail($team): array
    {
        // array for storing email adresses of admin(s)
        $arr = array();
        $Db = Db::getConnection();

        $sql = "SELECT email FROM users WHERE (`usergroup` = 1 OR `usergroup` = 2) AND `team` = :team";
        $req = $Db->prepare($sql);
        $req->bindParam(':team', $team, PDO::PARAM_INT);
        $req->execute();

        while ($email = $req->fetchColumn()) {
            $arr[] = $email;
        }

        // if we have only one admin, we need to have an associative array
        if (\count($arr) === 1) {
            return array($arr[0] => 'Admin eLabFTW');
        }

        return $arr;
    }

    /**
     * Return Swift_Mailer instance and choose between sendmail and smtp
     *
     * @return Swift_Mailer
     */
    private function getMailer(): Swift_Mailer
    {

        // Choose mail transport method; either smtp or sendmail
        if ($this->Config->configArr['mail_method'] === 'smtp') {
            if ($this->Config->configArr['smtp_encryption'] === 'none') {
                $transport = new Swift_SmtpTransport(
                    $this->Config->configArr['smtp_address'],
                    $this->Config->configArr['smtp_port']
                );
            } else {
                $transport = new Swift_SmtpTransport(
                    $this->Config->configArr['smtp_address'],
                    $this->Config->configArr['smtp_port'],
                    $this->Config->configArr['smtp_encryption']
                );
            }

            if ($this->Config->configArr['smtp_password']) {
                $transport->setUsername($this->Config->configArr['smtp_username'])
                ->setPassword(Crypto::decrypt(
                    $this->Config->configArr['smtp_password'],
                    Key::loadFromAsciiSafeString(\SECRET_KEY)
                ));
            }
        } else {
            // Use locally installed MTA (aka sendmail); Default
            $transport = new Swift_SendmailTransport($this->Config->configArr['sendmail_path'] . ' -bs');
        }

        return new Swift_Mailer($transport);
    }

    /**
     * Send an email
     *
     * @param Swift_Message $message
     * @throws ImproperActionException
     * @return int number of email sent
     */
    public function send(Swift_Message $message): int
    {
        $mailer = $this->getMailer();
        $res = $mailer->send($message);
        if ($res === 0) {
            throw new ImproperActionException('Could not send email!');
        }
        return $res;
    }

    /**
     * Send a test email
     *
     * @param string $email
     * @throws ImproperActionException
     * @return bool
     */
    public function testemailSend(string $email): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ImproperActionException('Bad email!');
        }

        $footer = "\n\n~~~\nSent from eLabFTW https://www.elabftw.net\n";
        $message = (new Swift_Message())
        // Give the message a subject
        ->setSubject(_('[eLabFTW] Test email'))
        // Set the From address with an associative array
        ->setFrom(array($this->Config->configArr['mail_from'] => 'eLabFTW'))
        // Set the To addresses with an associative array
        ->setTo(array($email => 'Admin eLabFTW'))
        // Give it a body
        ->setBody(_('Congratulations, you correctly configured eLabFTW to send emails :)') . $footer);

        return (bool) $this->send($message);
    }

    /**
     * Send a mass email to all users
     *
     * @param string $subject
     * @param string $body
     * @return int number of emails sent
     */
    public function massEmail($subject, $body): int
    {
        if (empty($subject)) {
            $subject = 'No subject';
        }

        // get all email adresses
        $Users = new Users();
        $UsersArr = $Users->getAllEmails();
        $to = array();
        foreach ($UsersArr as $user) {
            $to[] = $user['email'];
        }

        $footer = "\n\n~~~\nSent from eLabFTW https://www.elabftw.net\n";
        $message = (new Swift_Message())
        ->setSubject($subject)
        ->setFrom(array($this->Config->configArr['mail_from'] => 'eLabFTW'))
        ->setTo(array($this->Config->configArr['mail_from'] => 'eLabFTW'))
        // Set recipients in BCC to protect email addresses
        ->setBcc($to)
        ->setBody($body . $footer);

        return $this->send($message);
    }

    /**
     * Send an email to the admin of a team
     *
     * @param int $team
     * @return void
     */
    public function alertAdmin($team): void
    {
        if ($this->Config->configArr['mail_from'] === 'notconfigured@example.com') {
            return;
        }
        // Create the message
        $footer = "\n\n~~~\nSent from eLabFTW https://www.elabftw.net\n";
        $message = (new Swift_Message())
        // Give the message a subject
        ->setSubject(_('[eLabFTW] New user registered'))
        // Set the From address with an associative array
        ->setFrom(array($this->Config->configArr['mail_from'] => 'eLabFTW'))
        // Set the To
        ->setTo($this->getAdminEmail($team))
        // Give it a body
        ->setBody(_('Hi. A new user registered on elabftw. Head to the admin panel to validate the account.') . $footer);
        // SEND EMAIL
        $this->send($message);
    }

    /**
     * Alert a user that they are validated
     *
     * @param string $email email of the newly validated user
     * @return void
     */
    public function alertUserIsValidated($email): void
    {
        if ($this->Config->configArr['mail_from'] === 'notconfigured@example.com') {
            return;
        }

        // now let's get the URL so we can have a nice link in the email
        $Request = Request::createFromGlobals();
        $url = Tools::getUrl($Request) . '/login.php';

        $footer = "\n\n~~~\nSent from eLabFTW https://www.elabftw.net\n";
        // Create the message
        $message = (new Swift_Message())
        // Give the message a subject
        // no i18n here
        ->setSubject('[eLabFTW] Account validated')
        // Set the From address with an associative array
        ->setFrom(array($this->Config->configArr['mail_from'] => 'eLabFTW'))
        // Set the To addresses with an associative array
        ->setTo(array($email => 'eLabFTW'))
        // Give it a body
        ->setBody('Hello. Your account on eLabFTW was validated by an admin. Follow this link to login: ' . $url . $footer);
        // now we try to send the email
        $this->send($message);
    }
}
