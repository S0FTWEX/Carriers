<?php

namespace Softwex\Carriers\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Asset\Repository;
use Softwex\Carriers\Model\Config\Source\PackageStates;


class PackageState extends \Magento\Ui\Component\Listing\Columns\Column
{
    /**
     * @var Repository
     */
    protected $viewAssetRepo;

    /**
     * @var PackageStates
     */
    protected $packageStates;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponent
     * @param array $components
     * @param array $data
     * @param Repository $viewAssetRepo
     * @param PackageStates $packageStates
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponent,
        Repository $viewAssetRepo,
        PackageStates $packageStates,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponent, $components, $data);
        $this->viewAssetRepo = $viewAssetRepo;
        $this->packageStates = $packageStates;
    }

    /**
     * Prepare Data Source for column content
     *
     * @param  array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if ($item['pkg_state'] !== null) {
                    $pkgState = $item['pkg_state'];
                    $states = $this->packageStates->getOptions();
                    $imageUrl = $this->viewAssetRepo->getUrl('Softwex_Carriers::images/'.$pkgState.'.png');
                    $item['pkg_state'] = '<div style="text-align: center;">'.
                                            '<img src="'.$imageUrl.'" title="'.$states[$pkgState].'" alt="" />'.
                                         '</div>';
                }
            }
        }
        return $dataSource;
    }

}
