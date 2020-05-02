<?php

namespace Softwex\Carriers\Model\Config\Source;


class Flatrate implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Carriers flatrate options to use in Configuration
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '', 'label' => __('None')],
            ['value' => 'O', 'label' => __('Per Order')],
            ['value' => 'I', 'label' => __('Per Item')]
        ];
    }
}
