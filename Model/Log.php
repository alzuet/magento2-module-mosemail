<?php
namespace Atelier\EmailSender\Model;

use Magento\Framework\Model\AbstractModel;
use Atelier\EmailSender\Model\ResourceModel\Log as LogResourceModel;

class Log extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(LogResourceModel::class);
    }
}