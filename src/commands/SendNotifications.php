<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2021 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Commands;

use Elabftw\Models\Config;
use Elabftw\Services\Email;
use Elabftw\Services\EmailNotifications;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

/**
 * Send the notifications emails
 */
#[AsCommand(name: 'notifications:send')]
class SendNotifications extends Command
{
    protected function configure(): void
    {
        $this->setDescription('Send the notifications emails')
            ->setHelp('Look for all notifications that need to be sent by email and send them');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $Config = Config::getConfig();
        $Logger = new Logger('elabftw');
        $Logger->pushHandler(new ErrorLogHandler());
        $Email = new Email(
            new Mailer(Transport::fromDsn($Config->getDsn())),
            $Logger,
            $Config->configArr['mail_from'],
        );
        $Notifications = new EmailNotifications($Email);
        $count = $Notifications->sendEmails();
        if ($output->isVerbose()) {
            $output->writeln(sprintf('Sent %d emails', $count));
        }

        return Command::SUCCESS;
    }
}
