<?php
/* Icinga Web 2 Splunk Module | (c) 2016 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Splunk\RestApi;

class IndexApiRequest extends DocumentApiRequest
{
    /**
     * {@inheritdoc}
     */
    protected $method = 'POST';

    /**
     * {@inheritdoc}
     */
    public function setPayload($data, $contentType = null)
    {
        if (empty($data)) {
            $data = (object) $data;
        }

        return parent::setPayload($data, $contentType);
    }

    /**
     * {@inheritdoc}
     */
    protected function createPath()
    {
        if ($this->id === null) {
            return sprintf('/%s/%s', $this->index, $this->documentType);
        }

        return sprintf('/%s/%s/%s/_create', $this->index, $this->documentType, $this->id);
    }
}
