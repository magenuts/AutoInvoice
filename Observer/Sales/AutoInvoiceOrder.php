<?php

namespace Magenuts\AutoInvoice\Observer\Sales;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

class AutoInvoiceOrder implements ObserverInterface {

    protected $_orderRepository;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $_invoiceService;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    protected $_transaction;
    const AUTO_INVOICE_VALUE = 'autoinvoice/general/enable';
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository, 
        \Magento\Sales\Model\Service\InvoiceService $invoiceService, 
        \Magento\Framework\DB\Transaction $transaction
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->_orderRepository = $orderRepository;
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;
    }

    /**
     * @param EventObserver $observer
     * @return $this
     */
    public function execute(EventObserver $observer) {

        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $autoInvoiceEnable = $this->scopeConfig->getValue(self::AUTO_INVOICE_VALUE, $storeScope);
        if($autoInvoiceEnable){
            $order = $observer->getEvent()->getOrder();
            $orderId = $order->getId();
            $order = $this->_orderRepository->get($orderId);
            if($order->getGrandTotal()==0){
                $orderState = Order::STATE_COMPLETE;
                $order->setState($orderState)->setStatus(Order::STATE_COMPLETE);
                $order->save();
                if ($order->canInvoice()) {
                    $invoice = $this->_invoiceService->prepareInvoice($order);
                    $invoice->register();
                    $invoice->save();
                    $transactionSave = $this->_transaction->addObject($invoice)->addObject($invoice->getOrder());
                    $transactionSave->save();
                }
            }
        }
    }

}
