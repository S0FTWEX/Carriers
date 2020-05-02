<?php

namespace Softwex\Carriers\Model\Config\Source;


class PackageStates implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Initial options of packages states
     *
     * @return array
     */
    public function getOptions()
    {
        return [
            0 => __('Not delivered'),
            1 => __('Delivered'),
            2 => __('Delivering'),
            3 => __('Not sent yet')
            ];
    }

    /**
     * Packages state options to use in Configuration
     *
     * @return array
     */
    public function toOptionArray()
    {
        $states = [];
        foreach ($this->getOptions() as $value => $label) {
            $states[] = ['value' => $value, 'label' => $label];
        }
        return $states;
    }

}
