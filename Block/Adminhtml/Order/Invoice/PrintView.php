<?php

namespace FireGento\Pdf\Block\Adminhtml\Order\Invoice;

class PrintView extends \Magento\Backend\Block\Template
{
    protected $invoice;

    /**
     * @return \Magento\Sales\Model\Order\Invoice
     */
    public function getInvoice()
    {
        return $this->invoice;
    }

    /**
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     */
    public function setInvoice($invoice)
    {
        $this->invoice = $invoice;
    }
    
    /**
     * @return string
     */
    public function getConfig()
    {
        return $this->_scopeConfig;
    }
    
    public function getAssetRepository() 
    {
        return $this->_assetRepo;
    }
    
    /**
     * @return string
     */
    public function getLogoUrl()
    {
        $logo = $this->_scopeConfig->getValue(
            'sales/identity/logo',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if($logo) {
            $logoPath = '/sales/store/logo/' . $logo;
            return $this->getMediaDirectory()->getAbsolutePath($logoPath);
            
        }
    }
    
    public function getTotals()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $pdfConfig = $objectManager->create('Magento\Sales\Model\Order\Pdf\Config');
        $pdfTotalFactory = $objectManager->create('Magento\Sales\Model\Order\Pdf\Total\Factory');
        $totals = $pdfConfig->getTotals();
        
        $order = $this->getInvoice()->getOrder();
        
        usort($totals, [$this, '_sortTotalsList']);
        
        $totalsList = [];
        
        foreach ($totals as $totalInfo) {
            $class = empty($totalInfo['model']) ? null : $totalInfo['model'];
            $totalModel = $pdfTotalFactory->create($class);
            $totalModel->setData($totalInfo);
            
            $totalModel->setOrder($order);
            $totalModels[] = $totalModel;
            
            $totalModel->setOrder($order)->setSource($this->getInvoice());
            
            if($totalModel->canDisplay()) {
                foreach ($totalModel->getTotalsForDisplay() as $totalData) {
                    $totalsList[] = [
                        'label' => $totalData['label'],
                        'amount' => $totalData['amount']
                    ];
                }
            }
        }

        return $totalsList;
        
        
        
    }
    
    /**
     * Sort totals list
     *
     * @param  array $a
     * @param  array $b
     * @return int
     */
    protected function _sortTotalsList($a, $b)
    {
        if (!isset($a['sort_order']) || !isset($b['sort_order'])) {
            return 0;
        }

        if ($a['sort_order'] == $b['sort_order']) {
            return 0;
        }

        return $a['sort_order'] > $b['sort_order'] ? 1 : -1;
    }
    
    /**
     *  @return array
     */
    public function getFullTaxInfo()
    {
        $order = $this->getInvoice()->getOrder();
        
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $taxItem = $objectManager->create('Magento\Sales\Model\ResourceModel\Order\Tax\Item');
        $taxItems = $taxItem->getTaxItemsByOrderId($order->getId());
        
        $taxes = [];
        
        $shippingPercent = false;
        
        foreach($taxItems as $tax) {
        
        
            $taxPercent = (float) $tax['tax_percent'];
            
            if(!isset($taxes[$taxPercent])) {
                $taxes[$taxPercent] = [
                        "tax_base_amount" => 0,
                        "title" => $tax['title'],
                        "amount" => $tax['real_base_amount'],
                ];
            } else {
                $taxes[$taxPercent]["amount"] += $tax['real_base_amount'];
            }
            
            if($tax['taxable_item_type'] == "shipping") {
                $shippingPercent = $taxPercent;
                $taxes[$taxPercent]["tax_base_amount"] += $this->getInvoice()->getShippingAmount();
            }
            
        }
        
        if(count($taxes)) {
            foreach($this->getInvoice()->getAllItems() as $item) {
                $percent = (float) $item->getOrderItem()->getTaxPercent();
                $taxes[$percent]["tax_base_amount"] += $item->getRowTotal();
                $taxes[$percent]["tax_base_amount"] -= $item->getDiscountAmount();
                $taxes[$percent]["tax_base_amount"] += $item->getHiddenTaxAmount();
            }
        }
        
        
        return $taxes;
    }
}
