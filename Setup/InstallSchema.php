<?php
namespace Atelier\EmailSender\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        $installer = $setup;
        $installer->startSetup();

        $table = $installer->getConnection()->newTable(
            $installer->getTable('atelier_email_log')
        )->addColumn(
            'log_id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
            'Log ID'
        )->addColumn(
            'email_to',
            Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'Email To'
        )->addColumn(
            'email_subject',
            Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'Email Subject'
        )->addColumn(
            'email_body',
            Table::TYPE_TEXT,
            '64k',
            ['nullable' => false],
            'Email Body'
        )->addColumn(
            'created_at',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
            'Created At'
        )->addColumn(
            'status',
            Table::TYPE_TEXT,
            50,
            ['nullable' => false],
            'Status'
        )->setComment(
            'Atelier Email Log Table'
        );
        $installer->getConnection()->createTable($table);

        $installer->endSetup();
    }
}