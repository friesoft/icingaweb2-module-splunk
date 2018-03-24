<?php
/* Icinga Web 2 Splunk Module (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Splunk\ProvidedHook\Monitoring;

use Icinga\Module\Monitoring\Web\Hook\HostActionsHook;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Web\Url;

class HostActions extends HostActionsHook
{
    public function getActionsForHost(Host $host)
    {
        return $this->createNavigation([
            mt('splunk', 'Splunk Events') => [
                'icon'          => 'doc-text',
                'permission'    => 'splunk/events',
                'url'           => Url::fromPath('splunk/events', ['host' => $host->getName()])
            ]
        ]);
    }
}
