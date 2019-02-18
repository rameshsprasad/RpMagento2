<?php
/**
 * RP Order Import
 * Ramesh Prasad
 * Email: rameshsprasad@gmail.com
 */
namespace RpMagento2\Orderimport\Model\Order;

use Magento\Framework\DataObject;

/**
 * Class Processor
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Processor
{
    /**
     * @var \Magento\Framework\Registry
     */
    protected $coreRegistry;

    /**
     * @var \Magento\Framework\Phrase\Renderer\CompositeFactory
     */
    protected $rendererCompositeFactory;

    /**
     * @var \Magento\Sales\Model\AdminOrder\CreateFactory
     */
    protected $createOrderFactory;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceManagement;

    /**
     * @var \Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoaderFactory
     */
    protected $shipmentLoaderFactory;

    /**
     * @var \Magento\Sales\Controller\Adminhtml\Order\CreditmemoLoaderFactory
     */
    protected $creditmemoLoaderFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Sales\Api\CreditmemoManagementInterface
     */
    protected $creditmemoManagement;

    /**
     * @var \Magento\Backend\Model\Session\QuoteFactory
     */
    protected $sessionQuoteFactory;

    /**
     * @var \Magento\Backend\Model\Session\Quote
     */
    protected $currentSession;

    /**
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Magento\Framework\Phrase\Renderer\CompositeFactory $rendererCompositeFactory
     * @param \Magento\Sales\Model\AdminOrder\CreateFactory $createOrderFactory
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerFactory
     * @param \Magento\Backend\Model\Session\QuoteFactory $sessionQuoteFactory
     * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceManagement
     * @param \Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoaderFactory $shipmentLoaderFactory
     * @param \Magento\Sales\Controller\Adminhtml\Order\CreditmemoLoaderFactory $creditmemoLoaderFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Sales\Api\CreditmemoManagementInterface $creditmemoManagement
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Framework\Phrase\Renderer\CompositeFactory $rendererCompositeFactory,
        \Magento\Sales\Model\AdminOrder\CreateFactory $createOrderFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerFactory,
        \Magento\Backend\Model\Session\QuoteFactory $sessionQuoteFactory,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceManagement,
        \Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoaderFactory $shipmentLoaderFactory,
        \Magento\Sales\Controller\Adminhtml\Order\CreditmemoLoaderFactory $creditmemoLoaderFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Sales\Api\CreditmemoManagementInterface $creditmemoManagement
    ) {
        $this->coreRegistry = $coreRegistry;
        $this->rendererCompositeFactory = $rendererCompositeFactory;
        $this->createOrderFactory = $createOrderFactory;
        $this->customerRepository = $customerFactory;
        $this->sessionQuoteFactory = $sessionQuoteFactory;
        $this->transactionFactory = $transactionFactory;
        $this->orderFactory = $orderFactory;
        $this->invoiceManagement = $invoiceManagement;
        $this->shipmentLoaderFactory = $shipmentLoaderFactory;
        $this->creditmemoLoaderFactory = $creditmemoLoaderFactory;
        $this->storeManager = $storeManager;
        $this->creditmemoManagement = $creditmemoManagement;
    }

    /**
     * @param array $orderData
     * @return void
     */
    public function createOrder($orderData,$connection)
    {          
       //  print_r($orderData);exit;
         if($orderData['customer_email'] == ''){
             return false;
         }
         /** @var check Customer Id $customer */
         $customer = $connection->select()->from(['c' => 'customer_entity'])
            ->where('c.email = ?', $orderData['customer_email']);
         $customerId = $connection->fetchOne($customer);
         if (!$customerId) {             
            /** Create Customer If not created **/
            $customerId = $this->createCustomer($orderData,$connection);
         }     

         if(!$customerId || $customerId == ''){
            return false;
         }

         $orderData['otherData']['Date'] = date('Y-m-d H:i:s',strtotime($orderData['otherData']['Date']));
         /** Insert data into sales_order table **/
         if(!empty($orderData['otherData'])){      
               echo 'Creating order started for #'.$orderData['order_id']."\n";        
               if($orderData['otherData']['S_ShipVia'] == 'UPS GROUND'){
                   $shipVia = 'United Parcel Service - Ground';
                   $shipMethod  = 'ups_GND';
               }else if($orderData['otherData']['S_ShipVia'] == 'WILL CALL'){
                   $shipVia = $orderData['otherData']['S_ShipVia']; 
                   $shipMethod  = 'flatrate_flatrate';
               }else if($orderData['otherData']['S_ShipVia'] == 'UPS NEXT DAY AIR'){
                   $shipVia = $orderData['otherData']['S_ShipVia']; 
                   $shipMethod  = 'ups_1DA';
               }else if($orderData['otherData']['S_ShipVia'] == 'UPS INTERNATIONAL'){
                   $shipVia = $orderData['otherData']['S_ShipVia']; 
                   $shipMethod  = 'ups_INTL';
               }else if($orderData['otherData']['S_ShipVia'] == 'UPS Worldwide Saver'){
                   $shipVia = $orderData['otherData']['S_ShipVia']; 
                   $shipMethod  = 'ups_WXS';
               }else if($orderData['otherData']['S_ShipVia'] == 'UPS Standard Canada'){
                   $shipVia = $orderData['otherData']['S_ShipVia']; 
                   $shipMethod  = 'ups_STD';
               }else if($orderData['otherData']['S_ShipVia'] == 'UPS Expedited Canada'){
                   $shipVia = $orderData['otherData']['S_ShipVia']; 
                   $shipMethod  = 'ups_XPD';
               }else if($orderData['otherData']['S_ShipVia'] == 'UPS 3 DAY SELECT'){
                   $shipVia = $orderData['otherData']['S_ShipVia']; 
                   $shipMethod  = 'ups_3DS';
               }else if($orderData['otherData']['S_ShipVia'] == 'UPS 2ND DAY'){
                   $shipVia = $orderData['otherData']['S_ShipVia']; 
                   $shipMethod  = 'ups_2DA';
               }else if($orderData['otherData']['S_ShipVia'] == 'TRUCK'){
                   $shipVia = $orderData['otherData']['S_ShipVia']; 
                   $shipMethod  = 'flatrate_flatrate';
               }else if($orderData['otherData']['S_ShipVia'] == 'SUREPOST'){
                   $shipVia = $orderData['otherData']['S_ShipVia']; 
                   $shipMethod  = 'flatrate_flatrate';
               }else if($orderData['otherData']['S_ShipVia'] == 'Stamps.com Pri Mail'){
                   $shipVia = $orderData['otherData']['S_ShipVia']; 
                   $shipMethod  = 'usps_1';
               }else if($orderData['otherData']['S_ShipVia'] == 'Stamps.com Pri Mail'){
                   $shipVia = $orderData['otherData']['S_ShipVia']; 
                   $shipMethod  = 'flatrate_flatrate';
               }else if($orderData['otherData']['S_ShipVia'] == '2-5 DAY MAIL'){
                   $shipVia = $orderData['otherData']['S_ShipVia']; 
                   $shipMethod  = 'usps_1';
               }else if($orderData['otherData']['S_ShipVia'] == 'Ship To Store'){
                   $shipVia = $orderData['otherData']['S_ShipVia']; 
                   $shipMethod  = 'storepickup_storepickup';
               }else if($orderData['otherData']['S_ShipVia'] == 'FBA Amazon' || $orderData['otherData']['S_ShipVia'] == 'Amazon Same Day'){
                   $shipVia = $orderData['otherData']['S_ShipVia']; 
                   $shipMethod  = 'storepickup_storepickup';
               }else if($orderData['otherData']['S_ShipVia'] == 'ERG SHIPPING'){
                   $shipVia = $orderData['otherData']['S_ShipVia']; 
                   $shipMethod  = 'freeshipping_freeshipping';
               }else if($orderData['otherData']['S_ShipVia'] == 'DROP SHIP'){
                   $shipVia = $orderData['otherData']['S_ShipVia']; 
                   $shipMethod  = 'freeshipping_freeshipping';
               }else{
                   $shipVia = $orderData['otherData']['S_ShipVia']; 
                   $shipMethod  = 'freeshipping_freeshipping';
               }
               $ordOrgData = $orderData['otherData'];
               $connection->beginTransaction();
               $orData = array();
               if($ordOrgData['Status'] == 'Completed'){
                 $state = 'complete';
                 $status = 'complete';
               }else{
                 $state = 'processing';
                 $status = 'processing';
               }
               $orData['state'] = $state;
               $orData['status'] = $status;
               $orData['shipping_description'] = $shipVia; 
               $orData['store_id'] = 1; 
               $orData['customer_id'] = $customerId;
               $orData['base_grand_total'] = $ordOrgData['GrandTotal'];
               $orData['base_shipping_amount'] = $ordOrgData['ShippingAmount'];
               $orData['base_subtotal'] = $ordOrgData['SubTotal'];
               $orData['base_tax_amount'] = $ordOrgData['TaxAmount'];
               $orData['coupon_code'] = $ordOrgData['DiscountCode'];
               $orData['base_discount_amount'] = $ordOrgData['DiscountAmount'];
               $orData['discount_amount'] = $ordOrgData['DiscountAmount'];
               $orData['grand_total'] = $ordOrgData['GrandTotal'];
               $orData['shipping_amount'] = $ordOrgData['ShippingAmount'];
               $orData['subtotal'] = $ordOrgData['SubTotal'];
               $orData['tax_amount'] = $ordOrgData['TaxAmount'];
               $orData['total_qty_ordered'] = $ordOrgData['TotalQuantity'];
               $orData['customer_is_guest'] = NULL;
               $orData['customer_group_id'] = 1;
               $orData['email_sent'] = 1;
               $orData['send_email'] = 1;
               $orData['shipping_address_id'] = NULL;
               $orData['billing_address_id'] = NULL;
               $orData['weight'] = 
               $orData['increment_id'] = $ordOrgData['OrderNumber'];
               $orData['customer_email'] = $orderData['customer_email'];
               $orData['customer_firstname'] = $ordOrgData['B_FirstName'] != '' ? $ordOrgData['B_FirstName'] : $ordOrgData['S_FirstName'];
               $orData['customer_lastname'] = $ordOrgData['B_LastName'] != '' ? $ordOrgData['B_LastName'] : $ordOrgData['S_LastName'];
               $orData['global_currency_code'] = $ordOrgData['Currency'];
               $orData['shipping_method'] = $shipMethod;
               $orData['store_currency_code'] = $ordOrgData['Currency'];
               $orData['created_at'] = $ordOrgData['Date'];
               $orData['updated_at'] = $ordOrgData['Date'];
               $orData['source_code'] = $ordOrgData['SourceCode'];               
               $connection->insert('sales_order', $orData);
               $connection->commit();


               $order = $connection->select()->from(['o' => 'sales_order'])
                            ->where('o.increment_id = ?', $ordOrgData['OrderNumber']);
               $orderLastId = $connection->fetchOne($order);               

               /** Insert data into sales_order_grid **/ 
               $connection->beginTransaction();
               $gridData = array();
               $billfname = $ordOrgData['B_FirstName'] != '' ? $ordOrgData['B_FirstName'] : $ordOrgData['S_FirstName'];
               $billlname = $ordOrgData['B_LastName'] != '' ? $ordOrgData['B_LastName'] : $ordOrgData['S_LastName'];
               $shipfname = $ordOrgData['S_FirstName'] != '' ? $ordOrgData['S_FirstName'] : $ordOrgData['B_FirstName'];
               $shiplname = $ordOrgData['S_LastName'] != '' ? $ordOrgData['S_LastName'] : $ordOrgData['B_LastName'];
               $gridData['entity_id'] = $orderLastId;
               $gridData['status'] = 'complete';
               $gridData['store_id'] = 1;
               $gridData['customer_id'] = $customerId;
               $gridData['base_grand_total'] = $ordOrgData['GrandTotal'];
               $gridData['base_total_paid'] = $ordOrgData['GrandTotal'];
               $gridData['grand_total'] = $ordOrgData['GrandTotal'];
               $gridData['total_paid'] = $ordOrgData['GrandTotal'];
               $gridData['increment_id'] = $ordOrgData['OrderNumber'];
               $gridData['base_currency_code'] = $ordOrgData['Currency'];
               $gridData['order_currency_code'] = $ordOrgData['Currency'];
               $gridData['shipping_name'] = $shipfname.' '.$shiplname;
               $gridData['billing_name'] = $billfname.' '.$billlname;
               $gridData['created_at'] = $ordOrgData['Date'];
               $gridData['updated_at'] = $ordOrgData['Date'];
               $gridData['billing_address'] = NULL;
               $gridData['shipping_address'] = NULL;
               $gridData['shipping_information'] = $shipVia;
               $gridData['customer_email'] = $orderData['customer_email'];
               $gridData['customer_group'] = 1;
               $gridData['subtotal'] = $ordOrgData['SubTotal'];
               $gridData['customer_name'] = $billfname.' '.$billlname;
               $gridData['payment_method'] = 'Credit Card'; 
               $connection->insert('sales_order_grid', $gridData);
               $connection->commit();   
         }  

         /** Insert data into sales_order_address (shipping,billing) table **/
         if($orderLastId){
            
              $shipState = $ordOrgData['S_State'] != '' ? $ordOrgData['S_State'] : $ordOrgData['B_State'];
              $shipcntry = $ordOrgData['S_Country'] != '' ? $ordOrgData['S_Country'] : $ordOrgData['B_Country'];
              $shiReg = $connection->select()->from(['d' => 'directory_country_region'],['*'])
                            ->where('d.code = ?', $shipState);
              $shiReg = $connection->fetchRow($shiReg);

             /** Shipping Address **/
             $connection->beginTransaction();
             $shipData = array();
             $shipData['parent_id'] = $orderLastId;
             $shipData['region_id'] = $shiReg['region_id'] ? $shiReg['region_id'] : NULL;
             $shipData['customer_id'] = $customerId;
             $shipData['region'] =  isset($shiReg['default_name']) ? $shiReg['default_name'] : NULL;
             $shipData['postcode'] =  $ordOrgData['S_PostalCode'] != '' ? $ordOrgData['S_PostalCode'] : $ordOrgData['B_PostalCode'];
             $shipData['lastname'] =  $ordOrgData['S_LastName'] != '' ? $ordOrgData['S_LastName'] : $ordOrgData['B_LastName'];
             $shipData['street'] =  $ordOrgData['S_Address1'] != '' ? $ordOrgData['S_Address1'] : $ordOrgData['B_Address1'];
             $shipData['city'] =  $ordOrgData['S_City'] != '' ? $ordOrgData['S_City'] : $ordOrgData['B_City'];
             $shipData['email'] = $ordOrgData['S_Email'] != '' ? $ordOrgData['S_Email'] : $ordOrgData['B_Email'];
             $shipData['telephone'] =  $ordOrgData['S_Phone'] != '' ? $ordOrgData['S_Phone'] : $ordOrgData['B_Phone'];
             $shipData['country_id'] =  $shipcntry;
             $shipData['firstname'] =  $ordOrgData['S_FirstName'] != '' ? $ordOrgData['S_FirstName'] : $ordOrgData['B_FirstName'];
             $shipData['address_type'] = 'shipping';
             $shipData['company'] =  $ordOrgData['S_BusinessName'] != '' ? $ordOrgData['S_BusinessName'] : $ordOrgData['B_BusinessName'];
             $connection->insert('sales_order_address', $shipData);
             $connection->commit();

             /** Billing Address **/

             $billState = $ordOrgData['B_State'] != '' ? $ordOrgData['B_State'] : $ordOrgData['S_State'];
             $billcntry = $ordOrgData['B_Country'] != '' ? $ordOrgData['B_Country'] : $ordOrgData['S_Country'];
             $billReg = $connection->select()->from(['d' => 'directory_country_region'],['*'])
                            ->where('d.code = ?', $billState);
             $billReg = $connection->fetchRow($billReg);        

             $connection->beginTransaction();
             $billData = array();
             $billData['parent_id'] = $orderLastId;
             $billData['region_id'] = $billReg['region_id'] ? $billReg['region_id'] : NULL;
             $billData['customer_id'] = $customerId;
             $billData['region'] =  isset($billReg['default_name']) ? $billReg['default_name'] : NULL;
             $billData['postcode'] =  $ordOrgData['B_PostalCode'] != '' ? $ordOrgData['B_PostalCode'] : $ordOrgData['S_PostalCode'];
             $billData['lastname'] =  $ordOrgData['B_LastName'] != '' ? $ordOrgData['B_LastName'] : $ordOrgData['S_LastName'];
             $billData['street'] =  $ordOrgData['B_Address1'] != '' ? $ordOrgData['B_Address1'] : $ordOrgData['S_Address1'];
             $billData['city'] =  $ordOrgData['B_City'] != '' ? $ordOrgData['B_City'] : $ordOrgData['S_City'];
             $billData['email'] = $ordOrgData['B_Email'] != '' ? $ordOrgData['B_Email'] : $ordOrgData['S_Email'];
             $billData['telephone'] =  $ordOrgData['B_Phone'] != '' ? $ordOrgData['B_Phone'] : $ordOrgData['S_Phone'];
             $billData['country_id'] =  $billcntry;
             $billData['firstname'] =  $ordOrgData['B_FirstName'] != '' ? $ordOrgData['B_FirstName'] : $ordOrgData['S_FirstName'];
             $billData['address_type'] = 'billing';
             $billData['company'] =  $ordOrgData['B_BusinessName'] != '' ? $ordOrgData['B_BusinessName'] : $ordOrgData['S_BusinessName'];
             $connection->insert('sales_order_address', $billData);
             $connection->commit();
         }
         /** Insert data into sales_order_item table **/
         if(!empty($orderData['product_data'])){
                foreach($orderData['product_data'] as $item){
                        $connection->beginTransaction();
                        $itemData = array();
                        $itemData['order_id'] = $orderLastId;
                        $itemData['store_id'] = 1;
                        $itemData['created_at'] = $ordOrgData['Date'];
                        $itemData['updated_at'] = $ordOrgData['Date'];
                        $itemData['product_id'] = NULL;
                        $itemData['product_type'] = 'simple';
                        $itemData['weight'] = $item['weight'];
                        $itemData['sku'] = $item['sku'];
                        $itemData['name'] = $item['name'];
                        $itemData['qty_ordered'] = $item['qty'];
                        $itemData['price'] = $item['price'];
                        $itemData['base_price'] = $item['price'];
                        $itemData['original_price'] = $item['default_price'];
                        $itemData['base_original_price'] = $item['default_price'];
                        $itemData['row_total'] = $item['qty'] * $item['price'];
                        $itemData['base_row_total'] = $item['qty'] * $item['price'];
                        $connection->insert('sales_order_item', $itemData);
                        $connection->commit();
                }
         }

         /** Insert data into sales_order_payment table **/
         if($orderLastId){
                $connection->beginTransaction();
                $payData = array(); 
                $payData['parent_id'] = $orderLastId;
                $payData['base_shipping_captured'] = $ordOrgData['ShippingAmount'];
                $payData['shipping_captured'] = $ordOrgData['ShippingAmount'];
                $payData['base_amount_paid'] = $ordOrgData['GrandTotal'];
                $payData['base_amount_authorized'] = $ordOrgData['GrandTotal'];
                $payData['base_amount_paid_online'] = $ordOrgData['GrandTotal'];
                $payData['base_shipping_amount'] = $ordOrgData['ShippingAmount'];
                $payData['shipping_amount'] = $ordOrgData['ShippingAmount'];
                $payData['amount_paid'] = $ordOrgData['GrandTotal'];
                $payData['amount_authorized'] = $ordOrgData['GrandTotal'];
                $payData['base_amount_ordered'] = $ordOrgData['GrandTotal'];
                $payData['amount_ordered'] = $ordOrgData['GrandTotal'];
                $payData['additional_information'] = '';
                $payData['method'] = 'credit_card';
                $payData['cc_type'] = $ordOrgData['PaymentMethod'];  
                $connection->insert('sales_order_payment', $payData);
                $connection->commit();   
                echo 'INSERTED INTO sales_order_payment - '.$ordOrgData['OrderNumber']."\n";           
         }

         echo 'Order #'.$ordOrgData['OrderNumber'].' Successfully created'."\n";
         if (!empty($orderData) && $orderLastId && $state == 'complete') {            
              /** Create Invoice **/
              echo 'Creating invoice started for #'.$ordOrgData['OrderNumber']."\n";
              $this->createInvoice($orderLastId,$connection);
              echo 'Invoice created for #'.$ordOrgData['OrderNumber'].' successfully'."\n";

              /** Create Shipment **/
              echo 'Creating shipment started for #'.$ordOrgData['OrderNumber']."\n";
              $this->createShipment($orderLastId,$connection);
              echo 'Shipment created for #'.$ordOrgData['OrderNumber'].' successfully'."\n";
              echo "\n";echo "\n";echo "\n";
         }
          
         return true;
    }

    protected function createCustomer($orderData,$connection){ 

             /* Insert Data into customer_entity table */
             $customerData = array();
             $connection->beginTransaction();
             $customerData['website_id'] = 1;
             $customerData['email'] = $orderData['customer_email'];
             $customerData['group_id'] = 1;
             $customerData['store_id'] = 1;
             $customerData['created_at'] = date('Y-m-d H:i:s',strtotime($orderData['otherData']['Date']));
             $customerData['is_active'] = 1;
             $customerData['created_in'] = 'Default Store View';
             $customerData['firstname'] = $orderData['otherData']['B_FirstName'] != '' ? $orderData['otherData']['B_FirstName'] : $orderData['otherData']['S_FirstName'];
            $customerData['lastname'] = $orderData['otherData']['B_LastName'] != '' ? $orderData['otherData']['B_LastName'] : $orderData['otherData']['S_LastName'];
             $customerData['source_code'] = $orderData['otherData']['SourceCode'];
             $connection->insert('customer_entity', $customerData);
             $connection->commit(); 

             $customer = $connection->select()->from(['c' => 'customer_entity'])->where('c.email = ?', $orderData['customer_email']);
             $customerId = $connection->fetchOne($customer);
             echo 'CUSTOMER CREATED WITH EMAIL-'.$orderData['customer_email']."\n";
	     return $customerId;
    }

    /**
     * @param int $orderId
     * @param array $invoiceData
     * @return bool | \Magento\Sales\Model\Order\Invoice
     */
    protected function createInvoice($orderId, $connection)
    {
        $orderData = $connection->select()->from(['s' => 'sales_order'])
                            ->where('s.entity_id = ?', $orderId);
        $orderData = $connection->fetchRow($orderData);   
               /** Insert data into sales_invoice table **/
               $invoiceData = array();
               $connection->beginTransaction();
               $invoiceData['store_id'] = 1; 
               $invoiceData['state'] = 2;
               $invoiceData['base_grand_total'] = $orderData['base_grand_total'];
               $invoiceData['base_shipping_amount'] = $orderData['base_shipping_amount'];
               $invoiceData['base_subtotal'] = $orderData['base_subtotal'];
               $invoiceData['base_tax_amount'] = $orderData['base_tax_amount'];
               $invoiceData['base_discount_amount'] = $orderData['discount_amount'];
               $invoiceData['discount_amount'] = $orderData['base_discount_amount'];
               $invoiceData['grand_total'] = $orderData['grand_total'];
               $invoiceData['shipping_amount'] = $orderData['shipping_amount'];
               $invoiceData['subtotal_incl_tax'] = $orderData['subtotal']+$orderData['base_tax_amount'];
               $invoiceData['base_subtotal_incl_tax'] = $orderData['subtotal']+$orderData['base_tax_amount'];
               $invoiceData['subtotal'] = $orderData['subtotal'];
               $invoiceData['tax_amount'] = $orderData['tax_amount'];
               $invoiceData['total_qty'] = $orderData['total_qty_ordered'];               
               $invoiceData['email_sent'] = 1;
               $invoiceData['send_email'] = 1;
               $invoiceData['order_id'] = $orderId;
               $invoiceData['increment_id'] = $orderData['increment_id'];
               $invoiceData['base_subtotal'] = $orderData['subtotal'];
               $connection->insert('sales_invoice', $invoiceData);
               $connection->commit();

               $invoiceId = $connection->select()->from(['si' => 'sales_invoice'])
                            ->where('si.order_id = ?', $orderId);
               $invoiceId = $connection->fetchOne($invoiceId);
               /** Insert data into sales_invoice_grid table **/
               $connection->beginTransaction();  
               $invoiceGridData = array();
               $invoiceGridData['entity_id'] = $invoiceId;
               $invoiceGridData['increment_id'] = $orderData['increment_id'];
               $invoiceGridData['store_id'] = 1;
               $invoiceGridData['state'] = 2;
               $invoiceGridData['order_id'] = $orderId;
               $invoiceGridData['order_increment_id'] = $orderData['increment_id'];
               $invoiceGridData['order_created_at'] = $orderData['created_at'];
               $invoiceGridData['customer_name'] = $orderData['customer_firstname'].' '.$orderData['customer_lastname'];
               $invoiceGridData['billing_name'] = $orderData['customer_firstname'].' '.$orderData['customer_lastname'];
               $invoiceGridData['customer_email'] = $orderData['customer_email'];
               $invoiceGridData['customer_group_id'] = $orderData['customer_group_id'];
               $invoiceGridData['store_currency_code'] = $orderData['store_currency_code'];
               $invoiceGridData['order_currency_code'] = $orderData['order_currency_code'];
               $invoiceGridData['subtotal'] = $orderData['subtotal'];
               $invoiceGridData['grand_total'] = $orderData['grand_total'];
               $invoiceGridData['base_grand_total'] = $orderData['base_grand_total'];
               $invoiceGridData['created_at'] = $orderData['created_at'];
               $invoiceGridData['updated_at'] = $orderData['updated_at'];
               $connection->insert('sales_invoice_grid', $invoiceGridData);
               $connection->commit(); 
            

		$orderItemData = $connection->select()->from(['i' => 'sales_order_item'])
		                    ->where('i.order_id = ?', $orderId);
		$orderItemData = $connection->fetchAll($orderItemData);   
		foreach($orderItemData as $item){
                        /** Insert Data into sales_invoice_item table **/
		        $connection->beginTransaction();  
                        $invoiceItemData = array();
                        $invoiceItemData['parent_id'] = $invoiceId;           
                        $invoiceItemData['product_id'] = NULL;
                        $invoiceItemData['order_item_id'] = $item['item_id'];
                        $invoiceItemData['sku'] = $item['sku'];
                        $invoiceItemData['name'] = $item['name'];
                        $invoiceItemData['qty'] = $item['qty_ordered'];
                        $invoiceItemData['price'] = $item['price'];
                        $invoiceItemData['base_price'] = $item['base_price'];
                        $invoiceItemData['row_total'] = $item['row_total'];
                        $invoiceItemData['base_row_total'] = $item['qty_ordered'] * $item['price'];
                        $connection->insert('sales_invoice_item', $invoiceItemData);
                        $connection->commit();
                        
                        /** Update Order Item Table **/
                        $connection->beginTransaction();  
                        $orderItemData = array();
                        $orderItemData['qty_invoiced'] = $item['qty_ordered'];
                        $orderItemData['row_invoiced'] = $item['row_total'];
                        $orderItemData['base_row_invoiced'] = $item['row_total'];
                        $updateItem = $connection->quoteInto('item_id =?', $item['item_id']);
                        $connection->update('sales_order_item', $orderItemData,$updateItem);
                        $connection->commit();
		}
                        /** Update Order Table **/
                        $connection->beginTransaction();  
                        $orderOrgData = array();
                        $orderOrgData['base_discount_invoiced'] = $orderData['base_discount_amount'];
                        $orderOrgData['base_shipping_invoiced'] = $orderData['base_shipping_invoiced'];
                        $orderOrgData['base_subtotal_invoiced'] = $orderData['base_subtotal_invoiced'];
                        $orderOrgData['base_total_invoiced'] = $orderData['base_subtotal'];
                        $orderOrgData['base_total_paid'] = $orderData['base_subtotal'];
                        $orderOrgData['discount_invoiced'] = $orderData['base_discount_amount'];
                        $orderOrgData['shipping_invoiced'] = $orderData['base_shipping_invoiced'];
                        $orderOrgData['subtotal_invoiced'] = $orderData['base_subtotal'];
                        $orderOrgData['total_invoiced'] = $orderData['base_grand_total'];
                        $orderOrgData['total_paid'] = $orderData['base_grand_total'];
                        $orderOrgData['total_due'] = 0;
                        $orderOrgData['base_total_due'] = 0; 
                        $updateOrder = $connection->quoteInto('entity_id =?', $orderData['entity_id']);                       
                        $connection->update('sales_order', $orderOrgData,$updateOrder);
                        $connection->commit();
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment
     * @return void
     */
    protected function createShipment($orderId, $connection)
    {
        $orderData = $connection->select()->from(['s' => 'sales_order'])
                            ->where('s.entity_id = ?', $orderId);
        $orderData = $connection->fetchRow($orderData); 
  
        /** Insert data into sales_shipment table **/
        $shipmentData = array();
        $connection->beginTransaction();
        $shipmentData['store_id'] = 1;
        $shipmentData['total_weight'] = 
        $shipmentData['total_qty'] = $orderData['total_qty_ordered'];      
        $shipmentData['email_sent'] = 1;
        $shipmentData['send_email'] = 1;
        $shipmentData['order_id'] = $orderId;
        $shipmentData['customer_id'] = $orderData['customer_id'];
        $shipmentData['shipping_address_id'] = $orderData['shipping_address_id'];
        $shipmentData['billing_address_id'] = $orderData['billing_address_id'];
        $shipmentData['shipment_status'] = $orderData['status'];
        $shipmentData['increment_id'] = $orderData['increment_id'];
        $shipmentData['created_at'] = $orderData['created_at'];
        $shipmentData['updated_at'] = $orderData['updated_at'];
        $connection->insert('sales_shipment', $shipmentData);
        $connection->commit();

        $shipmentId = $connection->select()->from(['si' => 'sales_shipment'])
                            ->where('si.order_id = ?', $orderId);
        $shipmentId = $connection->fetchOne($shipmentId);
       /** Insert data into sales_shipment_grid table **/
       $connection->beginTransaction();  
       $shipmentGridData = array();
       $shipmentGridData['entity_id'] = $shipmentId;
       $shipmentGridData['increment_id'] = $orderData['increment_id'];
       $shipmentGridData['store_id'] = 1;
       $shipmentGridData['order_id'] = $orderId;
       $shipmentGridData['order_increment_id'] = $orderData['increment_id'];
       $shipmentGridData['total_qty'] = $orderData['total_qty_ordered'];      
       $shipmentGridData['order_created_at'] = $orderData['created_at'];
       $shipmentGridData['order_status'] = $orderData['status'];
       $shipmentGridData['shipment_status'] = $orderData['status'];
       $shipmentGridData['billing_name'] = $orderData['customer_firstname'].' '.$orderData['customer_lastname'];
       $shipmentGridData['shipping_name'] = $orderData['customer_firstname'].' '.$orderData['customer_lastname'];
       $shipmentGridData['customer_email'] = $orderData['customer_email'];
       $shipmentGridData['customer_group_id'] = $orderData['customer_group_id'];
       $shipmentGridData['shipping_information'] = $orderData['shipping_description'];
       $shipmentGridData['created_at'] = $orderData['created_at'];
       $shipmentGridData['updated_at'] = $orderData['updated_at'];
       $connection->insert('sales_shipment_grid', $shipmentGridData);
       $connection->commit(); 

        $orderItemData = $connection->select()->from(['i' => 'sales_order_item'])
		                    ->where('i.order_id = ?', $orderId);
	$orderItemData = $connection->fetchAll($orderItemData);   
	foreach($orderItemData as $item){
                /** Insert Data into sales_shipment_item table **/
	        $connection->beginTransaction();  
                $shipmentItemData = array();
                $shipmentItemData['parent_id'] = $shipmentId;           
                $shipmentItemData['product_id'] = NULL;
                $shipmentItemData['order_item_id'] = $item['item_id'];
                $shipmentItemData['sku'] = $item['sku'];
                $shipmentItemData['name'] = $item['name'];
                $shipmentItemData['qty'] = $item['qty_ordered'];
                $shipmentItemData['price'] = $item['price']; 
                $shipmentItemData['weight'] = $item['weight'];
                $connection->insert('sales_shipment_item', $shipmentItemData);
                $connection->commit();
                
                /** Update Order Item Table **/
                $connection->beginTransaction();  
                $orderItemData = array();
                $orderItemData['qty_shipped'] = $item['qty_ordered'];
                $updateItem = $connection->quoteInto('item_id =?', $item['item_id']);
                $connection->update('sales_order_item', $orderItemData,$updateItem);
                $connection->commit();
	}   

        return true;     
    }
}
