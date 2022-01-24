<?php
namespace Boostsales\BankTransfer\Model\Mail\Template;
 
class TransportBuilder extends \Magento\Framework\Mail\Template\TransportBuilder
{
    /**
    * addAttachment
    *
    * @param mixed $body
    * @param string $filename
    * @param mixed $mimeType
    * @param mixed $disposition
    * @param mixed $encoding
    * @return object
    */
    public function addAttachment(
        $body,
        $filename = null,
        $mimeType = \Zend_Mime::TYPE_OCTETSTREAM,
        $disposition = \Zend_Mime::DISPOSITION_ATTACHMENT,
        $encoding = \Zend_Mime::ENCODING_BASE64
    ) {
        $attachmentPart = new \Zend\Mime\Part();
        $attachmentPart->setContent($body)
            ->setType($mimeType)
            ->setFileName($filename)
            ->setEncoding($encoding)
            ->setDisposition($disposition);

        return $attachmentPart;
    }
}