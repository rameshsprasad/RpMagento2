<?php
/**
 * RP Order Import
 * Ramesh Prasad
 * Email: rameshsprasad@gmail.com
 */
namespace RpMagento2\Orderimport\Model;

use Magento\Framework\Setup\SampleData\Context as SampleDataContext;

/**
 * Class Order
 */
class Import
{
    /**
     * @var \Magento\Framework\File\Csv
     */
    protected $csvReader;

    /**
     * @var \Magento\Framework\Setup\SampleData\FixtureManager
     */
    protected $fixtureManager;

    /**
     * @var Order\Converter
     */
    protected $converter;

    /**
     * @var Order\Processor
     */
    protected $orderProcessor;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @param SampleDataContext $sampleDataContext
     * @param Order\Converter $converter
     * @param Order\Processor $orderProcessor
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        SampleDataContext $sampleDataContext,
        Order\Converter $converter,
        Order\Processor $orderProcessor,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
    ) {
        $this->fixtureManager = $sampleDataContext->getFixtureManager();
        $this->csvReader = $sampleDataContext->getCsvReader();
        $this->converter = $converter;
        $this->orderProcessor = $orderProcessor;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->customerRepository = $customerRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function execute($file,$connection)
    {
        if (file_exists($file)) {
            $rows = $this->csvReader->getData($file);
            $header = array_shift($rows);
            $rows = $connection->fetchAll('select * from OrderDataOct162017');
            $isFirst = true;
            $orderData= array();
            /*foreach ($rows as $row) {
                $data = [];
                foreach ($row as $key => $value) {
                    $data[$header[$key]] = $value;
                }
                $row = $data;   
                $orderData[$row['Order Number']][] = $row;             
            }*/
            foreach ($rows as $row) {
                $orderData[$row['OrderNumber']][] = $row;             
            }
            $finalOrderData = array();
            foreach($orderData as $key => $oData){
                $finalOrderData['order_id'] = $key;                
                $finalOrderData['product_data'] = array();
                $finalOrderData['customer_email'] = '';
                foreach($oData as $iData){
                     $finalOrderData['product_data'][] = array('sku' => $iData['InventoryID'],'qty' => $iData['Quantity'],'name' => $iData['ProductName'],'price' => $iData['UnitPrice'],'weight' => $iData['Weight'],'default_price' => $iData['DefaultPrice']);
                     $finalOrderData['customer_email'] = $iData['S_Email'] != '' ? $iData['S_Email'] : $iData['S_Email'];
                     $finalOrderData['customer_email'] = $finalOrderData['customer_email'] != '' ? $finalOrderData['customer_email'] : $iData['B_Email'];
                     $finalOrderData['otherData'] = $iData;
                }
                $orderLastId = '';
                $order = $connection->select()->from(['o' => 'sales_order'])
		                    ->where('o.increment_id = ?', $key);
		$orderLastId = $connection->fetchOne($order); 
                if($orderLastId || $finalOrderData['otherData']['S_ShipVia'] == ''){
                   continue;
                }   
                $this->orderProcessor->createOrder($finalOrderData,$connection);
            }
        }
        return true;
    }
}
