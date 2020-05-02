<?php

namespace Softwex\Carriers\Plugin;

use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as SalesOrderGridCollection;
use Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory as DataProviderCollection;


class AddDataToOrdersGrid
{
    /**
     * @var SalesOrderGridCollection
     */
    private $collection;

    /**
     * @param SalesOrderGridCollection $collection
     */
    public function __construct(
        SalesOrderGridCollection $collection
    ) {
        $this->collection = $collection;
    }

    /**
     * @param  DataProviderCollection $subject
     * @param  \Closure $proceed
     * @param  $requestName
     * @return mixed
     */
    public function aroundGetReport(
        DataProviderCollection $subject,
        \Closure $proceed,
        $requestName
    ) {
        $result = $proceed($requestName);
        if ($requestName == 'sales_order_grid_data_source') {
            if ($result instanceof $this->collection) {
                $select = $this->collection->getSelect();
                $resource = $this->collection->getResource();

                $select->joinLeft(
                    ['sst' => $resource->getTable('sales_shipment_track')],
                    'sst.order_id = main_table.entity_id',
                    ['track_number' => new \Zend_Db_Expr('GROUP_CONCAT(sst.track_number SEPARATOR "|")')]
                )->group('main_table.entity_id');

                return $this->collection;
            }
        }

        return $result;
    }
}