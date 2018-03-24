<?php
/* Icinga Web 2 Splunk Module | (c) 2016 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Splunk\RestApi;

class GetMappingApiRequest extends MappingApiRequest
{
    /**
     * {@inheritdoc}
     */
    protected $method = 'GET';
}
