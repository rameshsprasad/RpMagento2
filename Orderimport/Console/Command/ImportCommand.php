<?php
/**
 * RP Order Import
 * Ramesh Prasad
 * Email: rameshsprasad@gmail.com
 *
 * Updated for Magento 2.4.9 Compatibility
 */

namespace RpMagento2\Orderimport\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\State;

/**
 * Class ImportCommand
 */
class ImportCommand extends Command
{
    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var State
     */
    private State $state;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param State $state
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        State $state
    ) {
        $this->objectManager = $objectManager;
        $this->state = $state;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('rporder:import')
            ->setDescription('Import Orders from CSV File')
            ->addArgument('filename', InputArgument::REQUIRED, "CSV file path");
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $import = $this->getImportModel();
            $filename = $input->getArgument('filename');
            $output->writeln("<info>Starting import from: {$filename}</info>");
            
            $conn = $this->objectManager
                ->create('\\Magento\\Framework\\App\\ResourceConnection')
                ->getConnection();
            
            $result = $import->execute(realpath($filename), $conn);
            
            if ($result) {
                $output->writeln('<info>✓ The import was successful.</info>');
                return Command::SUCCESS;
            } else {
                $output->writeln('<error>✗ Import failed.</error>');
                return Command::FAILURE;
            }
        } catch (FileNotFoundException $e) {
            $output->writeln('<error>✗ File not found.</error>');
            return Command::FAILURE;
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>✗ Invalid source.</error>');
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        } catch (\Exception $e) {
            $output->writeln("<error>✗ An error occurred: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }

    /**
     * @return \RpMagento2\Orderimport\Model\Import
     */
    protected function getImportModel()
    {
        $this->state->setAreaCode('adminhtml');
        return $this->objectManager->create('RpMagento2\\Orderimport\\Model\\Import');
    }
}
