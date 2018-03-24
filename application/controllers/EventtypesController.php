<?php
/* Icinga Web 2 Splunk Module (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Splunk\Controllers;

use Icinga\Module\Splunk\Controller;
use Icinga\Module\Splunk\Eventtypes;
use Icinga\Module\Splunk\Forms\EventtypeConfigForm;
use Icinga\Module\Splunk\Instances;
use Icinga\Module\Eventdb\Event;
use Icinga\Web\Url;

class EventtypesController extends Controller
{
    public function init()
    {
        $this->assertPermission('splunk/config');
    }

    public function indexAction()
    {
        $this->getTabs()->add(uniqid(), [
            'label'     => $this->translate('Instances'),
            'url'       => Url::fromPath('splunk/instances')
        ]);

        $this->setTitle($this->translate('Event Types'));

        if (! (new Instances())->select()->hasResult()) {
            $this->_helper->viewRenderer->setRender('create-instance');

            return;
        }

        $this->view->eventtypes = (new Eventtypes())->select(['name', 'instance', 'index', 'filter', 'fields']);
    }

    public function newAction()
    {
        $form = new EventtypeConfigForm([
            'mode'  => EventtypeConfigForm::MODE_INSERT
        ]);

        $form->handleRequest();

        $this->setTitle($this->translate('New Event Type'));

        $this->view->form = $form;

        $this->_helper->viewRenderer->setRender('form', null, true);
    }

    public function updateAction()
    {
        $name = $this->params->getRequired('eventtype');

        $form = new EventtypeConfigForm([
            'mode'          => EventtypeConfigForm::MODE_UPDATE,
            'identifier'    => $name
        ]);

        $form->handleRequest();

        $this->setTitle($this->translate('Update Event Type'));

        $this->view->form = $form;

        $this->_helper->viewRenderer->setRender('form', null, true);
    }

    public function deleteAction()
    {
        $name = $this->params->getRequired('eventtype');

        $form = new EventtypeConfigForm([
            'mode'          => EventtypeConfigForm::MODE_DELETE,
            'identifier'    => $name
        ]);

        $form->handleRequest();

        $this->setTitle($this->translate('Remove Event Type'));

        $this->view->form = $form;

        $this->_helper->viewRenderer->setRender('form', null, true);
    }
}
