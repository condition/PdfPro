<?php
/**
 * VES_PdfPro_Adminhtml_PrintController
 *
 * @author		VnEcoms Team <support@vnecoms.com>
 * @website		http://www.vnecoms.com
 */
include 'VES/PdfPro/Model/PdfPro.php';
class VES_PdfPro_PrintController extends Mage_Adminhtml_Controller_action
{
	protected $_defaultTotalModel = 'sales/order_pdf_total_default';
	
	/**
	 * Get Options of items
	 * @param Mage_Sales_Model_Order_Invoice_Item $item
	 * @return array:
	 */
	protected function getInvoiceItemOptions($item) {
        $result = array();
        if ($options = $item->getOrderItem()->getProductOptions()) {
            if (isset($options['options'])) {
                $result = array_merge($result, $options['options']);
            }
            if (isset($options['additional_options'])) {
                $result = array_merge($result, $options['additional_options']);
            }
            if (isset($options['attributes_info'])) {
                $result = array_merge($result, $options['attributes_info']);
            }
        }
        return $result;
    }
    
    /**
     * Get Total List
     * @param Mage_Sales_Model_Order_Invoice $source
     * @return array
     */
    
	protected function _getTotalsList($source)
    {
        $totals = Mage::getConfig()->getNode('global/pdf/totals')->asArray();
        usort($totals, array($this, '_sortTotalsList'));
        $totalModels = array();
        foreach ($totals as $index => $totalInfo) {
            if (!empty($totalInfo['model'])) {
                $totalModel = Mage::getModel($totalInfo['model']);
                if ($totalModel instanceof Mage_Sales_Model_Order_Pdf_Total_Default) {
                    $totalInfo['model'] = $totalModel;
                } else {
                    Mage::throwException(
                        Mage::helper('sales')->__('PDF total model should extend Mage_Sales_Model_Order_Pdf_Total_Default')
                    );
                }
            } else {
                $totalModel = Mage::getModel($this->_defaultTotalModel);
            }
            $totalModel->setData($totalInfo);
            $totalModels[] = $totalModel;
        }
        return $totalModels;
    }
    
    /**
     * Init invoice data
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @return array
     */
    
    protected function _initInvoiceData($invoice){
    	$order = $invoice->getOrder();
    	$invoiceData = array();
    	$invoiceData['id']				= $invoice->getIncrementId();
    	$invoiceData['order_id']		= $order->getIncrementId();
    	$invoiceData['created_at']		= $invoice->getCreatedAt();
    	$invoiceData['billing']			= $invoice->getBillingAddress()->getFormated(true);
    	if($invoice->getShippingAddress()) $invoiceData['shipping']		= $invoice->getShippingAddress()->getFormated(true);
    	$invoiceData['payment_method']	= $order->getPayment()->getMethodInstance()->getTitle();
    	$invoiceData['shipping_method']	= $order->getShippingDescription();
    	$invoiceData['shipping_charges']= $order->formatPriceTxt($order->getShippingAmount());
    	$invoiceData['totals']	= array();
    	$invoiceData['items']	= array();
    	/*
    	 * Get Items information
    	*/
    	foreach($invoice->getAllItems() as $item){
    		if ($item->getOrderItem()->getParentItem()) {
    			continue;
    		}
    		$itemData = array('name'=>$item->getName(),'sku'=>$item->getSku(),'price'=>Mage::helper('core')->currency($item->getPrice()),'qty'=>round($item->getQty(),0),'tax'=>Mage::helper('core')->currency($item->getTaxAmount()),'subtotal'=>Mage::helper('core')->currency($item->getRowTotal()));
    		$options = $this->getInvoiceItemOptions($item);
    		$itemData['options']	= array();
    		if ($options) {
    			foreach ($options as $option) {
    				$optionData = array();
    				$optionData['label']	= strip_tags($option['label']);
    	
    				if ($option['value']) {
    					$printValue = isset($option['print_value']) ? $option['print_value'] : strip_tags($option['value']);
    					$optionData['value']	= $printValue;
       				}
    				$itemData['options'][] = $optionData;
    			}
    		}
    		$invoiceData['items'][]	= $itemData;
    	}
    	/*
    	 * Get Totals information.
    	*/
    	$totals = $this->_getTotalsList($invoice);
    	foreach ($totals as $total) {
    		$total->setOrder($order)
    		->setSource($invoice);
    		if ($total->canDisplay()) {
    			$area = $total->getSourceField()=='grand_total'?'footer':'body';
    			foreach ($total->getTotalsForDisplay() as $totalData) {
    				$invoiceData['totals'][$area][] = array('label'=>$totalData['label'], 'value'=>$totalData['amount']);
    				 
    			}
    		}
    	}
    	
    	return $invoiceData;
    }
    
    /**
     * Print An Invoice
     */
	public function invoiceAction(){
		$invoiceId = $this->getRequest()->getParam('invoice_id');
		$invoice = Mage::getModel('sales/order_invoice')->load($invoiceId);
        if (!$invoice->getId()) {
        	$this->_getSession()->addError($this->__('The invoice no longer exists.'));
            $this->_forward('no-route');
            return;
		}
		$invoiceData = $this->_initInvoiceData($invoice);
        try{
        	$apiKey 	= Mage::getStoreConfig('pdfpro/config/key');
        	$pdfPro = new PdfPro($apiKey);
        	$result = $pdfPro->getPDF(array($invoiceData));
        	if($result['success']){
        		$this->_prepareDownloadResponse('invoice'.Mage::getSingleton('core/date')->date('Y-m-d_H-i-s').'.pdf', $result['content']);
        	}else{
        		echo $result['msg'];
        	}
        }catch(Exception $e){
        	Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        	$this->_redirect('adminhtml/sales_order/index');
        }
	}
	
	
	
	/**
	 * Print invoices
	 */
	public function invoicesAction(){
		$orderIds = $this->getRequest()->getParam('order_ids');
		$flag = false;
		if (!empty($orderIds)) {
			$invoiceDatas = array();
			foreach ($orderIds as $orderId) {
				$invoices = Mage::getResourceModel('sales/order_invoice_collection')
				->setOrderFilter($orderId)
				->load();
				if($invoices->count() > 0) $flag = true;
				foreach($invoices as $invoice){
					$invoiceDatas[] = $this->_initInvoiceData($invoice);
				}
			}			
			if ($flag) {
				try{
					$apiKey 	= Mage::getStoreConfig('pdfpro/config/key');
					$pdfPro = new PdfPro($apiKey);
					$result = $pdfPro->getPDF($invoiceDatas);
					if($result['success']){
						$this->_prepareDownloadResponse('invoice'.Mage::getSingleton('core/date')->date('Y-m-d_H-i-s').'.pdf', $result['content']);
					}else{
						echo $result['msg'];
					}
				}catch(Exception $e){
					Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        			$this->_redirect('adminhtml/sales_order');
				}
			} else {
				$this->_getSession()->addError($this->__('There are no printable documents related to selected orders.'));
				$this->_redirect('adminhtml/sales_order');
			}
		}
	}
}