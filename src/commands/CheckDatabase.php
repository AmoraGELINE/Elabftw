<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Commands;

use Elabftw\Elabftw\Update;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Check the the current schema version versus the required one
 */
class CheckDatabase extends Command
{
    protected static $defaultName = 'db:check';

    public function __construct(private int $currentSchema)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Check the database version')
            ->setHelp('This command allows you to compare the database version with the current required schema.');
    }

    /**
     * Execute
     *
     * @return int 0 if no need to upgrade, 1 if need to upgrade
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $requiredSchema = Update::getRequiredSchema();

        $output->writeln(array(
            'Database check',
            '==============',
            sprintf('Current version: %d', $this->currentSchema),
            sprintf('Required version: %d', $requiredSchema),
        ));
        if ($this->currentSchema === $requiredSchema) {
            $output->writeln('No upgrade required.');
            return Command::SUCCESS;
        }

        $output->writeln('An upgrade is required.');
        return Command::FAILURE;
    }
}
