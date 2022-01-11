<?php

namespace Boostsales\BankTransfer\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Email\Model\Template;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Framework\Translate\Inline\StateInterface;
use Fooman\PrintOrderPdf\Model\Pdf\Order;
use Psr\Log\LoggerInterface;
use Boostsales\BankTransfer\Model\Mail\Template\TransportBuilder;

class BankTransferProforma implements ObserverInterface
{
    const XML_PATH_EMAIL_IDENTITY = 'trans_email/ident_general/email';
    const XML_PATH_EMAIL_NAME = 'trans_email/ident_general/name';

    protected $_template;
    protected $paymentHelper;
    protected $state;
    protected $_scopeConfig;
    protected $_pdf;
    protected $_logger;
    protected $transportBuilder;

    public function __construct(Template $template, PaymentHelper $paymentHelper, StateInterface $state,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        Order $pdf,
        LoggerInterface $logger,
        TransportBuilder $transportBuilder
    )

    {
        $this->_template = $template;
        $this->paymentHelper = $paymentHelper;
        $this->inlineTranslation = $state;
        $this->_scopeConfig = $scopeConfig;
        $this->_pdf = $pdf;
        $this->_logger = $logger;
        $this->transportBuilder = $transportBuilder;
    }

    public function execute(Observer $observer){

        $order = $observer->getEvent()->getOrder();
        $paymentMethod = $order->getPayment()->getMethod();
        if(!$paymentMethod == "banktransfer"){
            return $this;
        }

        $customerEmail = $order->getCustomerEmail();
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        if($order->getBillingAddress()->getData('invoice_email')){
            $customerEmail = $order->getBillingAddress()->getData('invoice_email');
        }
        $fromEmail = $this->_scopeConfig->getValue(self::XML_PATH_EMAIL_IDENTITY, $storeScope);
        $fromName = $this->_scopeConfig->getValue(self::XML_PATH_EMAIL_NAME, $storeScope);
        try {
                $from = ['email' => $fromEmail, 'name' => $fromName];

                $this->inlineTranslation->suspend();
                $storeCode = strtoupper($order->getStore()->getCode());
                $emailtemplate = $this->_template->load($storeCode.' Proforma Invoice', 'template_code');
                $templateOptions = [
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $order->getStore()->getId()
                ];
                $templateVars = [
                    'order' => $order,
                    'invoice' => '',
                    'store' => $order->getStore(),
                    'payment_html' => $this->paymentHelper->getInfoBlockHtml($order->getPayment(), $order->getStore()->getStoreId()),
                ];
                $pdfattachment = $this->_pdf->getpdf([$order]);
                $file = $pdfattachment->render(false,null,false,false,true);

                $transport = $this->transportBuilder->setTemplateIdentifier($emailtemplate, $storeScope)
                            ->setTemplateOptions($templateOptions)
                            ->setTemplateVars($templateVars)
                            ->setFrom($from)
                            ->addTo($customerEmail)
                            ->addAttachment($file,'proforma-invoice.pdf')
                            ->getTransport();

                $transport->sendMessage();
                $this->inlineTranslation->resume();
        } catch (\Exception $e) {
            $this->_logger->info($e->getMessage());
        }
    }
}