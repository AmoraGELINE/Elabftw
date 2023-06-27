<?php declare(strict_types=1);
/**
 * @author Marcel Bolten <marcel.bolten@msl.ubc.ca>
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Commands;

use Elabftw\Services\MfaHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command line tool to emulate a 2FA phone app. It returns a 2FA code calculated from the provided secret.
 */
#[AsCommand(name: 'dev:2fa')]
class MfaCode extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Get a 2FA code')
            ->setHelp('This command allows you to get a 2FA code if you provide a secret token.')
            ->addArgument('secret', InputArgument::REQUIRED, 'Please provide the 2FA secret.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // remove spaces from input so we don't have to do it manually
        $secret = str_replace(' ', '', $input->getArgument('secret'));

        $MfaHelper = new MfaHelper(0, $secret);
        $code = $MfaHelper->getCode();

        $output->writeln(array(
            'Secret: ' . $secret,
            '2FA code: ' . $code,
        ));

        return Command::SUCCESS;
    }
}
