<?php
/* Icinga Web 2 Splunk Module | (c) 2016 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Splunk\RestApi;

use Icinga\Exception\IcingaException;

class RestApiResponse
{
    /**
     * The status code of this response
     *
     * @var int
     */
    protected $statusCode;

    /**
     * The response payload
     *
     * @var string
     */
    protected $payload;

    /**
     * The content-type of the response payload
     *
     * @var string
     */
    protected $contentType;

    /**
     * Create a new RestApiResponse
     *
     * @param   int     $statusCode     The status code of this response
     */
    public function __construct($statusCode)
    {
        $this->setStatusCode($statusCode);
    }

    /**
     * Set the status code of this response
     *
     * @param   int     $statusCode
     *
     * @return  $this
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Return the status code of this response
     *
     * @return  int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Set the response payload
     *
     * @param   string  $data
     *
     * @return  $this
     */
    public function setPayload($data)
    {
        $this->payload = $data;
        return $this;
    }

    /**
     * Return the response payload
     *
     * @return  string
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * Set the content-type of the response payload
     *
     * @param   string  $contentType
     *
     * @return  $this
     */
    public function setContentType($contentType)
    {
        $this->contentType = str_replace(' ', '', $contentType);
        return $this;
    }

    /**
     * Return the content-type of the response payload
     *
     * @return  string
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * Return whether this is the response of a successful request
     *
     * @return  bool
     */
    public function isSuccess()
    {
        $statusCode = $this->getStatusCode();
        return ($statusCode >= 200) && ($statusCode < 300);
    }

    /**
     * Parse the response payload as JSON and return the result
     *
     * @return  mixed
     *
     * @throws  IcingaException     In case of an error
     */
    public function json()
    {
        $json = json_decode($this->getPayload(), true);
        if ($json !== null) {
            return $json;
        }

        if ($this->contentType && substr($this->contentType, 0, 16) !== 'application/json') {
            throw new IcingaException('Cannot parse content of type "%s" as JSON', $this->contentType);
        }

        throw new IcingaException(
            json_last_error() === JSON_ERROR_DEPTH ? 'Too deeply nested JSON' : 'Invalid JSON'
        );
    }
}
