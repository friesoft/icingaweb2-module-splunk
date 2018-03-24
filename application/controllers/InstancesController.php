<?php
/* Icinga Web 2 Splunk Module (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Splunk\Controllers;

use Icinga\Module\Splunk\Controller;
use Icinga\Module\Splunk\Eventtypes;
use Icinga\Module\Splunk\Forms\InstanceConfigForm;
use Icinga\Module\Splunk\Instances;
use Icinga\Web\Url;

class InstancesController extends Controller
{
    public function init()
    {
        $this->assertPermission('splunk/config');
    }

    public function indexAction()
    {
        $this->setTitle($this->translate('Instances'));

        $this->getTabs()->add(uniqid(), [
            'label'     => $this->translate('Event Types'),
            'url'       => Url::fromPath('splunk/eventtypes')
        ]);

        $this->view->instances = (new Instances())->select(['name', 'uri']);
        $this->view->noEventtypes = ! (new Eventtypes())->select()->hasResult();
    }

    public function newAction()
    {
        $form = new InstanceConfigForm([
            'mode'  => InstanceConfigForm::MODE_INSERT
        ]);

        $form->handleRequest();

        $this->setTitle($this->translate('New Instance'));

        $this->view->form = $form;

        $this->_helper->viewRenderer->setRender('form', null, true);
    }

    public function updateAction()
    {
        $name = $this->params->getRequired('instance');

        $form = new InstanceConfigForm([
            'mode'          => InstanceConfigForm::MODE_UPDATE,
            'identifier'    => $name
        ]);

        $form->handleRequest();

        $this->setTitle($this->translate('Update Instance'));

        $this->view->form = $form;

        $this->_helper->viewRenderer->setRender('form', null, true);
    }

    public function deleteAction()
    {
        $name = $this->params->getRequired('instance');

        $form = new InstanceConfigForm([
            'mode'          => InstanceConfigForm::MODE_DELETE,
            'identifier'    => $name
        ]);

        $form->handleRequest();

        $this->setTitle($this->translate('Remove Instance'));

        $this->view->form = $form;

        $this->_helper->viewRenderer->setRender('form', null, true);
    }
}
