<?php
/**
 * VES_PdfPro_Model_Observer
 *
 * @author		VnEcoms Team <support@vnecoms.com>
 * @website		http://www.vnecoms.com
 */
class VES_PdfPro_Model_Observer
{
	/**
	 * Add new link to Sales Order Massaction
	 * @param Varien_Event_Observer $observer
	 */
	public function core_block_abstract_prepare_layout_before(Varien_Event_Observer $observer){
		if(!Mage::getStoreConfig('pdfpro/config/enabled')) return;
		$block = $observer->getEvent()->getBlock();
		if(get_class($block) =='Mage_Adminhtml_Block_Widget_Grid_Massaction'
				&& $block->getRequest()->getControllerName() == 'sales_order')
		{
			$block->addItem('pdf_pro', array(
					'label'=> Mage::helper('pdfpro')->__('PDF Pro Print Invoices'),
					'url'  => Mage::getUrl('pdfpro/adminhtml_print/invoices'),
			));
		}
    }
}