<?php
namespace Atelier\EmailSender\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Log extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('atelier_email_log', 'log_id');
    }
}