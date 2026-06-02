<?php
/**
 * RP Order Import
 * Ramesh Prasad
 * Email: rameshsprasad@gmail.com
 *
 * Updated for Magento 2.4.9 Compatibility
 */
namespace RpMagento2\Orderimport\Model;

use Magento\Framework\File\Csv;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

/**
 * Class Import
 */
class Import
{
    /**
     * @var Csv
     */
    private Csv $csvReader;

    /**
     * @var Order\Converter
     */
    private Order\Converter $converter;

    /**
     * @var Order\Processor
     */
    private Order\Processor $orderProcessor;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $orderCollectionFactory;

    /**
     * @var CustomerRepositoryInterface
     */
    private CustomerRepositoryInterface $customerRepository;

    /**
     * @param Csv $csvReader
     * @param Order\Converter $converter
     * @param Order\Processor $orderProcessor
     * @param CollectionFactory $orderCollectionFactory
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        Csv $csvReader,
        Order\Converter $converter,
        Order\Processor $orderProcessor,
        CollectionFactory $orderCollectionFactory,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->csvReader = $csvReader;
        $this->converter = $converter;
        $this->orderProcessor = $orderProcessor;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->customerRepository = $customerRepository;
    }

    /**
     * Execute import from CSV file
     *
     * @param string $file
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @return bool
     * @throws \Exception
     */
    public function execute(string $file, $connection): bool
    {
        if (!file_exists($file)) {
            throw new \Exception("File does not exist: {$file}");
        }

        try {
            $rows = $this->csvReader->getData($file);
            array_shift($rows); // Remove header

            if (empty($rows)) {
                throw new \Exception("CSV file is empty or has no data rows");
            }

            // Fetch order data from source table
            $sourceRows = $connection->fetchAll('SELECT * FROM OrderDataOct162017');
            
            if (empty($sourceRows)) {
                throw new \Exception("No data found in source table");
            }

            $orderData = [];
            foreach ($sourceRows as $row) {
                $orderNumber = $row['OrderNumber'] ?? null;
                if ($orderNumber) {
                    $orderData[$orderNumber][] = $row;
                }
            }

            $processedCount = 0;
            foreach ($orderData as $orderNumber => $oData) {
                $finalOrderData = $this->prepareOrderData($orderNumber, $oData);
                
                // Check if order already exists
                $existingOrder = $connection->select()
                    ->from(['o' => 'sales_order'])
                    ->where('o.increment_id = ?', $orderNumber);
                $orderLastId = $connection->fetchOne($existingOrder);
                
                if ($orderLastId || empty($finalOrderData['otherData']['S_ShipVia'])) {
                    continue;
                }

                $this->orderProcessor->createOrder($finalOrderData, $connection);
                $processedCount++;
            }

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Import error: " . $e->getMessage());
        }
    }

    /**
     * Prepare order data for processing
     *
     * @param string $orderNumber
     * @param array $orderRows
     * @return array
     */
    private function prepareOrderData(string $orderNumber, array $orderRows): array
    {
        $finalOrderData = [
            'order_id' => $orderNumber,
            'product_data' => [],
            'customer_email' => '',
            'otherData' => []
        ];

        foreach ($orderRows as $itemData) {
            $finalOrderData['product_data'][] = [
                'sku' => $itemData['InventoryID'] ?? '',
                'qty' => $itemData['Quantity'] ?? 0,
                'name' => $itemData['ProductName'] ?? '',
                'price' => $itemData['UnitPrice'] ?? 0,
                'weight' => $itemData['Weight'] ?? 0,
                'default_price' => $itemData['UnitPrice'] ?? 0
            ];

            $finalOrderData['customer_email'] = $itemData['S_Email'] ?? $itemData['B_Email'] ?? '';
            $finalOrderData['otherData'] = $itemData;
        }

        return $finalOrderData;
    }
}
