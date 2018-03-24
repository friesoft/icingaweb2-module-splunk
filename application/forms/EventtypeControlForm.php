<?php
/* Icinga Web 2 Splunk Module (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Splunk\Forms;

use Icinga\Module\Splunk\Eventtypes;
use Icinga\Web\Form;

class EventtypeControlForm extends Form
{
    public function init()
    {
        $this->setAttrib('class', 'eventtype-control');
        $this->setAttrib('data-base-target', '_self');
    }

    public function createElements(array $formData)
    {
        $this->addElement(
            'select',
            'eventtype',
            array(
                'autosubmit'    => true,
                'label'         => $this->translate('Event Type'),
                'multiOptions'  => (new Eventtypes())->select(['name', 'name'])->fetchPairs(),
                'value'         => $this->getRequest()->getUrl()->getParam('eventtype', '')
            )
        );
    }

    public function getRedirectUrl()
    {
        return $this->getRequest()->getUrl()
            ->setParam('eventtype', $this->getElement('eventtype')->getValue());
    }

    /**
     * Control is always successful
     *
     * @return  bool
     */
    public function onSuccess()
    {
        return true;
    }
}
