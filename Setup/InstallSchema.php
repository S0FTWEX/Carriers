<?php

namespace Softwex\Carriers\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;


class InstallSchema implements InstallSchemaInterface
{
    /**
     * {@inheritdoc}
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        $orderTable = $installer->getTable('sales_order');

        $orderColumns = [
            'dispatch_date' => [
                'type' => Table::TYPE_TIMESTAMP,
                'comment' => 'Dispatch Date'
            ],
            'pkg_is_new' => [
                'type' => Table::TYPE_TEXT,
                'length' => 32,
                'nullable' => true,
                'default' => NULL,
                'comment' => 'Is Package New?'
            ],
            'pkg_state' => [
                'type' => Table::TYPE_SMALLINT,
                'default' => null,
                'comment' => 'Package State'
            ],
            'pkg_delivery_date' => [
                'type' => Table::TYPE_DATE,
                'comment' => 'Package Delivery Date'
            ]
        ];

        $orderGridTable = $installer->getTable('sales_order_grid');

        $orderGridColumns = [
            'dispatch_date' => [
                'type' => Table::TYPE_TIMESTAMP,
                'comment' => 'Dispatch Date'
            ],
            'pkg_state' => [
                'type' => Table::TYPE_SMALLINT,
                'default' => NULL,
                'comment' => 'Package State'
            ],
            'pkg_delivery_date' => [
                'type' => Table::TYPE_DATE,
                'comment' => 'Package Delivery Date'
            ]
        ];

        $connection = $installer->getConnection();

        foreach ($orderColumns as $columnName => $definition) {
            if (!$connection->tableColumnExists($orderTable, $columnName)) {
                $connection->addColumn($orderTable, $columnName, $definition);
            }
        }

        foreach ($orderGridColumns as $columnName => $definition) {
            if (!$connection->tableColumnExists($orderGridTable, $columnName)) {
                $connection->addColumn($orderGridTable, $columnName, $definition);
            }
        }

        $installer->endSetup();
    }

}
