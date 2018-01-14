<?php

namespace TusPhp\Commands;

use TusPhp\Tus\Server as TusServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExpirationCommand extends Command
{
    /** @var TusServer */
    protected $server;

    /**
     * {@inheritDoc}
     */
    public function __construct(string $name = null)
    {
        $this->server = new TusServer('redis');

        parent::__construct($name);
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('tus:expired')
            ->setDescription('Remove expired uploads.')
            ->setHelp('Deletes all expired uploads to free server resources.');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln([
            '<info>Cleaning server resources</info>',
            '<info>=========================</info>',
            '',
        ]);

        $deleted = $this->server->handleExpiration();

        if (empty($deleted)) {
            $output->writeln('<comment>Nothing to delete.</comment>');
        } else {
            foreach ($deleted as $key => $item) {
                $output->writeln('<comment>' . ($key + 1) . ". Deleted {$item['name']} from {$item['file_path']}</comment>");
            }
        }

        $output->writeln('');
    }
}
