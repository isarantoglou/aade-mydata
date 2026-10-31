<?php

namespace Firebed\AadeMyData\Http\Traits;

use DOMDocument;

trait HasRequestDom
{
    private ?DOMDocument $requestDom = null;

    public function getRequestDom(): ?DOMDocument
    {
        return $this->requestDom;
    }

    public function getRequestXml(bool $formatOutput = true): ?string
    {
        if ($this->requestDom === null) {
            return null;
        }

        $this->requestDom->formatOutput = $formatOutput;
        return $this->requestDom->saveXML() ?: null;
    }

    public function getRequestElement(string $localName, int $index): ?string
    {
        if ($this->requestDom === null) {
            return null;
        }

        $element = $this->requestDom->getElementsByTagName($localName)->item($index);
        if ($element === null) {
            return null;
        }

        return $this->requestDom->saveXML($element) ?: null;
    }
}