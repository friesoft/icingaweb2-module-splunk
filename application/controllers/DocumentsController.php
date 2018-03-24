<?php
/* Icinga Web 2 Splunk Module (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Splunk\Controllers;

use Icinga\Module\Splunk\Controller;
use Icinga\Module\Splunk\Splunk;
use Icinga\Module\Splunk\Instances;

class DocumentsController extends Controller
{
    public function indexAction()
    {
        $index = $this->params->getRequired('index');
        $type = $this->params->getRequired('type');
        $id = $this->params->getRequired('id');

        $instance = (new Instances())
            ->select()
            ->where('name', $this->params->getRequired('instance'))
            ->fetchRow();

        if ($instance === false) {
            $this->httpNotFound($this->translate('Instance not found'));
        }

        $this->setTitle($this->translate('Document'));

        $document = (new Splunk($instance))
            ->select()
            ->get("{$index}/{$type}/{$id}");

        $this->view->document = $document;
    }
}
