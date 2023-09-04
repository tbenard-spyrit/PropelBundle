<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Propel\Bundle\PropelBundle\Command;

use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Propel\Runtime\ServiceContainer\StandardServiceContainer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * DatabaseCreateCommand class.
 * Useful to create a database.
 *
 * @author William DURAND
 */
class DatabaseCreateCommand extends AbstractCommand
{
    /**
     * @see Command
     */
    protected function configure(): void
    {
        $this
            ->setName('propel:database:create')
            ->setDescription('Create a given database or the default one.')

            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Set this parameter to define a connection to use')
        ;
    }

    /**
     * @see Command
     *
     * @throws \InvalidArgumentException When the target directory does not exist
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connectionName = $input->getOption('connection') ?: $this->getDefaultConnection();
        $config = $this->getConnectionData($connectionName);
        $dbName = $this->parseDbName($config['dsn']);

        if (null === $dbName) {
            $output->writeln('<error>No database name found.</error>');

            return \Propel\Generator\Command\AbstractCommand::CODE_ERROR;
        } else {
            $query  = 'CREATE DATABASE '. $dbName .';';
        }

        $manager = new ConnectionManagerSingle($connectionName);
        $manager->setConfiguration($this->getTemporaryConfiguration($config));

        /** @var StandardServiceContainer $serviceContainer */
        $serviceContainer = Propel::getServiceContainer();
        $serviceContainer->setAdapterClass($connectionName, $config['adapter']);
        $serviceContainer->setConnectionManager($manager);

        $connection = Propel::getConnection($connectionName);

        $statement = $connection->prepare($query);
        $statement->execute();

        $output->writeln(sprintf('<info>Database <comment>%s</comment> has been created.</info>', $dbName));

        // s 5.1 expect integer to be returned. Introduces Command::SUCCESS and Command::FAILURE constants
        return \Propel\Generator\Command\AbstractCommand::CODE_SUCCESS;
    }

    /**
     * Create a temporary configuration to connect to the database in order
     * to create a given database. This idea comes from Doctrine1.
     *
     * @see https://github.com/doctrine/doctrine1/blob/master/lib/Doctrine/Connection.php#L1491
     *
     * @param  array<string, mixed> $config A Propel connection configuration.
     * @return array<string, mixed>
     */
    private function getTemporaryConfiguration(array $config): array
    {
        $dbName = $this->parseDbName($config['dsn']);

        $config['dsn'] = preg_replace(
            '#;?(dbname|Database)='.$dbName.'#',
            '',
            $config['dsn']
        );

        return $config;
    }
}
