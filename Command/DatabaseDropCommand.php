<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Propel\Bundle\PropelBundle\Command;

use Propel\Runtime\Propel;
use Propel\Runtime\ServiceContainer\StandardServiceContainer;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;

/**
 * DatabaseDropCommand class.
 * Useful to drop a database.
 *
 * @author William DURAND
 */
class DatabaseDropCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('propel:database:drop')
            ->setDescription('Drop a given database or the default one.')
            ->setHelp(<<<EOT
The <info>propel:database:drop</info> command will drop your database.

  <info>php app/console propel:database:drop</info>

The <info>--force</info> parameter has to be used to actually drop the database.
The <info>--connection</info> parameter allows you to change the connection to use.
The default connection is the active connection (propel.dbal.default_connection).
EOT
            )

            ->addOption('connection',   null, InputOption::VALUE_OPTIONAL, 'Connection to use. Example: default, bookstore')
            ->addOption('force',        null, InputOption::VALUE_NONE, 'Set this parameter to execute this action.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('force')) {
            $output->writeln('<error>You have to use the "--force" option to drop the database.</error>');

            return \Propel\Generator\Command\AbstractCommand::CODE_ERROR;
        }

        if ('prod' === $this->getKernel()->getEnvironment()) {
            $this->writeSection($output, 'WARNING: you are about to drop a database in production !', 'bg=red;fg=white');

            if (false === $this->askConfirmation($input, $output, 'Are you sure ? (y/n) ', false)) {
                $output->writeln('Aborted, nice decision !');

                // s 5.1 expect integer to be returned
                return -2;
            }
        }

        $connectionName = $input->getOption('connection') ?: $this->getDefaultConnection();
        $config = $this->getConnectionData($connectionName);
        $connection = Propel::getConnection($connectionName);
        $dbName = $this->parseDbName($config['dsn']);

        if (null === $dbName) {
            $output->writeln('<error>No database name found.</error>');

            return \Propel\Generator\Command\AbstractCommand::CODE_ERROR;
        } else {
            $query  = 'DROP DATABASE '. $dbName .';';
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

        $output->writeln(sprintf('<info>Database <comment>%s</comment> has been dropped.</info>', $dbName));

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
