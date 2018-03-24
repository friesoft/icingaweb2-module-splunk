<?php
/* Icinga Web 2 Splunk Module (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Splunk;

use Icinga\Repository\IniRepository;

class Instances extends IniRepository
{
    protected $configs = [
        'instances' => [
            'name'      => 'instances',
            'keyColumn' => 'name',
            'module'    => 'splunk'
        ]
    ];

    protected $queryColumns = [
        'instances' => [
            'name',
            'uri',
            'user',
            'password',
            'ca',
            'client_certificate',
            'client_private_key'
        ]
    ];
}
