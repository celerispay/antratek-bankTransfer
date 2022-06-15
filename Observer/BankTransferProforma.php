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
use Fooman\EmailAttachments\Model\AttachmentFactory;
use Fooman\EmailAttachments\Model\Api\AttachmentContainerInterface as ContainerInterface;

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
    protected $attachmentFactory;
    protected $attachmentContainer;
    protected $orderDocumentFactory;
    protected $pdfRenderer;

    public function __construct(
        Template $template,
        PaymentHelper $paymentHelper,
        StateInterface $state,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        Order $pdf,
        LoggerInterface $logger,
        TransportBuilder $transportBuilder,
        AttachmentFactory $attachmentFactory,
        ContainerInterface $attachmentContainer,
	\Fooman\PdfCore\Model\PdfRenderer $pdfRenderer,
        \Fooman\PdfCustomiser\Block\OrderFactory $orderDocumentFactory
    ) {
        $this->_template = $template;
        $this->paymentHelper = $paymentHelper;
        $this->inlineTranslation = $state;
        $this->_scopeConfig = $scopeConfig;
        $this->_pdf = $pdf;
        $this->_logger = $logger;
        $this->transportBuilder = $transportBuilder;
        $this->attachmentFactory = $attachmentFactory;
        $this->attachmentContainer = $attachmentContainer;
    	$this->orderDocumentFactory = $orderDocumentFactory;
        $this->pdfRenderer = $pdfRenderer;	
    }

    public function execute(Observer $observer)
    {

        $order = $observer->getEvent()->getOrder();
        $paymentMethod = $order->getPayment()->getMethod();
        if ($paymentMethod != "banktransfer") {
            return $this;
        }

        $customerEmail = $order->getCustomerEmail();
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        if ($order->getBillingAddress()->getData('invoice_email')) {
            $customerEmail = $order->getBillingAddress()->getData('invoice_email');
        }
        $fromEmail = $this->_scopeConfig->getValue(self::XML_PATH_EMAIL_IDENTITY, $storeScope);
        $fromName = $this->_scopeConfig->getValue(self::XML_PATH_EMAIL_NAME, $storeScope);

        $from = ['email' => $fromEmail, 'name' => $fromName];

        $this->inlineTranslation->suspend();
        $storeCode = strtoupper($order->getStore()->getCode());
       # $emailtemplate = $this->_template->load(47, 'template_id');
	$emailtemplate = $this->_template->load($storeCode . ' Proforma Invoice', 'template_code');
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
//	$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
//	$fooman = $objectManager->get(get_parent_class($this->_pdf));
//	var_dump(get_class($fooman));
//	$pdfattachment = $fooman->getFoomanPdf([$order]);
//	$file = $pdfattachment->render(false, null, false, false, true);
	
	  if ($order) {
                 $document = $this->orderDocumentFactory->create(
                         ['data' => ['order' => $order]]
                 );
                 $this->pdfRenderer->addDocument($document);
                 $fileOrder = $this->pdfRenderer->getPdfAsString();
         }
        // $transport = $this->transportBuilder->setTemplateIdentifier($emailtemplate->getId(), $storeScope)
        //     ->setTemplateOptions($templateOptions)
        //     ->setTemplateVars($templateVars)
        //     ->setFrom($from)
        //     ->addTo($customerEmail)
        //     ->getTransport();

        //	$transport = $this->transportBuilder->getTransport();

        /*  $attachment = $this->attachmentFactory->create(
                    [
                        'content' => $file,
                        'mimeType' => 'application/pdf',
                        'fileName' => 'performa-invoice.pdf'
                    ]
                );
                $this->attachmentContainer->addAttachment($attachment); */

        // $attachment = $this->transportBuilder->addAttachment(
        //     $file,
        //     'performainvoice.pdf',
        //     'application/pdf'
        // );

        // $message = $transport->getMessage();
        // $body = \Zend\Mail\Message::fromString($message->getRawMessage())->getBody();
        // $body = \Zend_Mime_Decode::decodeQuotedPrintable($body);
        // $html = '';

        // if ($body instanceof \Zend\Mime\Message) {
        //     $html = $body->generateMessage(\Zend\Mail\Headers::EOL);
        // } elseif ($body instanceof \Magento\Framework\Mail\MimeMessage) {
        //     $html = (string) $body->getMessage();
        // } elseif ($body instanceof \Magento\Framework\Mail\EmailMessage) {
        //     $html = (string) $body->getBodyText();
        // } else {
        //     $html = (string) $body;
        // }

        // $htmlPart = new \Zend\Mime\Part($html);
        // $htmlPart->setCharset('utf-8');
        // $htmlPart->setEncoding(\Zend_Mime::ENCODING_QUOTEDPRINTABLE);
        // $htmlPart->setDisposition(\Zend_Mime::DISPOSITION_INLINE);
        // $htmlPart->setType(\Zend_Mime::TYPE_HTML);
        // $parts = [$htmlPart, $attachment];

        // $bodyPart = new \Zend\Mime\Message();
        // $bodyPart->setParts($parts);
        // $message->setBody($bodyPart);

        /* $transport = $this->transportBuilder->setTemplateIdentifier($emailtemplate->getId(), $storeScope)
                            ->setTemplateOptions($templateOptions)
                            ->setTemplateVars($templateVars)
                            ->setFrom($from)
                            ->addTo($customerEmail)
                            ->getTransport(); */
	 $filename = 'Proforma Invoice.pdf';
        if($storeCode == 'DE')
         $filename = 'Proformarechnung.pdf';
        if($storeCode == 'NL')
         $filename = 'Proforma factuur.pdf';

        try {
            $transport = $this->transportBuilder->setTemplateIdentifier($emailtemplate->getId(), $storeScope)
                ->setTemplateOptions($templateOptions)
                ->setTemplateVars($templateVars)
                ->setFrom($from)
                ->addTo($customerEmail)
		->addBcc('info@antratek.com')
   	        ->addAttachment($fileOrder, $filename, 'application/pdf')
                ->getTransport();

            $transport->sendMessage();

            $this->inlineTranslation->resume();

        } catch (\Exception $e) {
            $this->_logger->info($e->getMessage());
        }
    }
}
