<?php

namespace Firebed\AadeMyData\Xml\DigitalGoodsMovement;

use Firebed\AadeMyData\Models\DigitalGoodsMovement\DeliveryOutcome;
use Firebed\AadeMyData\Xml\XMLWriter;

/**
 * @extends XMLWriter<DeliveryOutcome>
 * @version 2.0.1
 */
class DeliveryOutcomeWriter extends XMLWriter
{
    /** @noinspection PhpUnhandledExceptionInspection */
    public function asXml($data): string
    {
        $rootNode = $this->document->createElement('ConfirmDeliveryOutcomeRequest');
        $this->document->appendChild($rootNode);

        foreach ($data->sortedAttributes() as $key => $value) {
            $this->build($rootNode, $key, $value);
        }

        return (string) $this->document->saveXML();
    }
}