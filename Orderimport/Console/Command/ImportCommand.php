<?php
/**
 * RP Order Import
 * Ramesh Prasad
 * Email: rameshsprasad@gmail.com
 */

namespace RpMagento2\Orderimport\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

/**
 * Class ImportCommand
 *
 * @package CedricBlondeau\CatalogImportCommand\Console\Command
 */
class ImportCommand extends Command
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var \Magento\Framework\App\State
     */
    private $state;

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\App\State $state
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\App\State $state
    ) {
        $this->objectManager = $objectManager;
        $this->state = $state;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('kenorder:import')
            ->setDescription('Import Orders')
            ->addArgument('filename', InputArgument::REQUIRED, "CSV file path");
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $import = $this->getImportModel();
        $output->writeln($input->getArgument('filename'));
        $conn = $this->objectManager->create('\Magento\Framework\App\ResourceConnection')->getConnection();;
        try {
            $result = $import->execute(realpath($input->getArgument('filename')),$conn);
            if ($result) {
                $output->writeln('<info>The import was successful.</info>');
            } else {
                $output->writeln('<error>Import failed.</error>');
            }

        } catch (FileNotFoundException $e) {
            $output->writeln('<error>File not found.</error>');

        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>Invalid source.</error>');
            $output->writeln("Log trace:");
        }
    }

    /**
     * @return \RpMagento2\Orderimport\Model\Import
     */
    protected function getImportModel()
    {
        $this->state->setAreaCode('adminhtml');
        return $this->objectManager->create('RpMagento2\Orderimport\Model\Import');
    }
}

