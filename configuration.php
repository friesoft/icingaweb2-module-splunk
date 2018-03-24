<?php
/* Icinga Web 2 Splunk Module (c) 2017 Icinga Development Team | GPLv2+ */

/** @var Icinga\Application\Modules\Module $this */

$this->providePermission(
    'splunk/config',
    $this->translate('Allow to configure Splunk instances and event types')
);

$this->provideRestriction(
    'splunk/eventtypes',
    $this->translate('Restrict the event types the user may use')
);

$this->provideConfigTab('splunk/instances', array(
    'title' => $this->translate('Configure Splunk Instances'),
    'label' => $this->translate('Splunk Instances'),
    'url'   => 'instances'
));

$this->provideConfigTab('splunk/eventtypes', array(
    'title' => $this->translate('Configure Event Types'),
    'label' => $this->translate('Event Types'),
    'url'   => 'eventtypes'
));
