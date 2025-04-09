<?php
namespace Atelier\EmailSender\Model\ResourceModel\Log;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init('Atelier\EmailSender\Model\Log', 'Atelier\EmailSender\Model\ResourceModel\Log');
    }
}