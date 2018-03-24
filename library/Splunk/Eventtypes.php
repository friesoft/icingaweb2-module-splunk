<?php
/* Icinga Web 2 Splunk Module (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Splunk;

use Icinga\Repository\IniRepository;

class Eventtypes extends IniRepository
{
    protected $configs = [
        'eventtypes' => [
            'name'      => 'eventtypes',
            'keyColumn' => 'name',
            'module'    => 'splunk'
        ]
    ];

    protected $queryColumns = [
        'eventtypes' => [
            'name',
            'instance',
            'index',
            'filter',
            'fields'
        ]
    ];
}
