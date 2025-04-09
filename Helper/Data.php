<?php
namespace Atelier\EmailSender\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
// use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const XML_PATH_ATELIER_EMAIL = 'atelier_email/';

    public function getConfigValue($field, $storeId = null): mixed
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_ATELIER_EMAIL . $field,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isEnabled($storeId = null): string
    {
        return $this->getConfigValue('general/enabled', $storeId);
    }

    public function getApiKey($storeId = null): string
    {
        return $this->getConfigValue('general/api_key', $storeId);
    }

    public function isTestMode($storeId = null): string
    {
        return $this->getConfigValue('general/test_mode', $storeId);
    }

    public function getTestEmail($storeId = null): string
    {
        return $this->getConfigValue('general/test_email', $storeId);
    }
}