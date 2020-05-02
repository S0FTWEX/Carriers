<?php

namespace Softwex\Carriers\Ui\Component\Listing\Column;

use Magento\Store\Model\ScopeInterface;
use Magento\Sales\Model\Order;


class CarrierTracking extends \Magento\Ui\Component\Listing\Columns\Column
{
    /**
     * @var \Magento\Framework\Escaper
     */
    protected $escaper;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

    /**
     * @var \Magento\Shipping\Model\Config
     */
    protected $shippingConfig;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var \Magento\Shipping\Helper\Data
     */
    protected $shippingHelper;

    /**
     * CarrierTrack constructor.
     * @param \Magento\Framework\View\Element\UiComponent\ContextInterface $context
     * @param \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory
     * @param \Magento\Framework\Escaper $escaper
     * @param \Magento\Shipping\Model\Config $shippingConfig
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfigInterface
     * @param \Magento\Shipping\Helper\Data $shippingHelper
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param array $components
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        \Magento\Framework\Escaper $escaper,
        \Magento\Shipping\Model\Config $shippingConfig,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfigInterface,
        \Magento\Shipping\Helper\Data $shippingHelper,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->escaper = $escaper;
        $this->shippingConfig = $shippingConfig;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->shippingHelper = $shippingHelper;
        $this->orderFactory = $orderFactory;
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $item[$this->getData('name')] = $this->prepareItem($item);
            }
        }

        return $dataSource;
    }

    /**
     * Get data
     *
     * @param array $item
     * @return string
     */
    protected function prepareItem(array $item)
    {
        $html = '';
        $orderId = $item['entity_id'];
        /** @var $order \Magento\Sales\Model\Order */
        $order = $this->orderFactory->create()->load($orderId);
        if (!$order->getId()) {
            return $html;
        }
        $html .= $this->getTrackNumbers($order);

        return $html;
    }

    /**
     * @param $order
     * @return string
     */
    protected function getTrackNumbers($order)
    {
        $html = '<span style="display: table;">';
        $sendToPrintLabel = 'Odoslané do tlače';
        $trackingUrl = $this->shippingHelper->getTrackingPopupUrlBySalesModel($order);
        $storeId = $order->getStoreId();
        $country = $this->scopeConfigInterface->getValue('general/country/default', ScopeInterface::SCOPE_STORE, $storeId);

        $reprintOutput = '';
        $printOutput = '';
        $carrier = $order->getShippingDescription();

        if (stripos($carrier, 'gls') !== false) {
            if ($this->hasShipments($order, 'gls')) {
                if (!$order->getPkgDeliveryDate()) {
                    $reprintOutput = '<a href="'.$this->context->getUrl('softwex_gls/order/printlabel', array('order_id' => $order->getId())).'" target="_blank" title="Znovu vytlačiť GLS štítok" style="text-decoration: none; font-size: 15px; font-weight: bold; color: #555;"> ↺</a>';
                }
            } else {
                $printOutput = '<button class="button label-print" onclick="jQuery(this).html(\''.$sendToPrintLabel.'\').addClass(\'green-bg\');window.open(\''.$this->context->getUrl('softwex_gls/order/printlabel', array('order_id' => $order->getId())).'\');" target="_blank">Vytlačiť GLS štítok</button>';
            }
        } elseif (stripos($carrier, 'ppl') !== false) {
            $reprintOutput = '';
            $printOutput = '<button class="button label-print" onclick="jQuery(this).html(\''.$sendToPrintLabel.'\').addClass(\'green-bg\');window.open(\''.$this->context->getUrl('softwex_ppl/order/printlabel', array('order_id' => $order->getId())).'\');" target="_blank">Vytlačiť PPL štítok</button>';
        } elseif (stripos($carrier, 'cargus') !== false) {
            $reprintOutput = '';
            $printOutput = '<button class="button label-print" onclick="jQuery(this).html(\''.$sendToPrintLabel.'\').addClass(\'green-bg\');window.open(\''.$this->context->getUrl('softwex_urgentcargus/order/printlabel', array('order_id' => $order->getId())).'\');" target="_blank">Vytlačiť URG štítok</button>';
        } elseif (stripos($carrier, 'zásielkovňa') !== false || stripos($carrier, 'zásilkovna') !== false) {
            $reprintOutput = '';
            $printOutput = '<a class="button label-print" href="'.$this->context->getUrl('adminhtml/sales_order_export/zasielkovna_export_one', array('order_id' => $order->getId())).'" onclick="sendToPrintJs(this);" target="_blank">Vytlačiť ZAS štítok</a>';
        } elseif (stripos($carrier, 'slov') !== false || 
                stripos($carrier, 'amazon') !== false) {
            $reprintOutput = '';
            $printOutput = '';
        } elseif (stripos($carrier, 'dpd curier') !== false || 
                stripos($carrier, 'dpd kurir') !== false || 
            //  stripos($carrier, 'fan curier') !== false || 
            //  stripos($carrier, 'urgent cargus') !== false ||
                stripos($carrier, 'speedy') !== false ||
                stripos($carrier, 'Еконт') !== false ||
                stripos($carrier, 'kurier dhl') !== false) {
            $reprintOutput = '';
            $printOutput = '<a class="button label-print" href="'.$this->context->getUrl('adminhtml/sales_order_export/joy_api_printlabel', array('order_id' => $order->getId())).'" onclick="sendToPrintJs(this);" target="_blank">Vytlačiť JOY štítok</a>';
        } elseif (stripos($carrier, 'meest') !== false || stripos($carrier, 'Міст') !== false || 
                stripos($carrier, 'poshta') !== false || stripos($carrier, 'Нова Пошта') !== false) {
            $reprintOutput = '';
            $printOutput = '<a class="button label-print" href="'.$this->context->getUrl('adminhtml/sales_order_export/meest_api_printlabel', array('order_id' => $order->getId())).'" onclick="sendToPrintJs(this);" target="_blank">Vytlačiť Meest štítok</a>';
        } else {
            $reprintOutput = '';
            $printOutput = '<a class="button label-print" href="'.$this->context->getUrl('adminhtml/sales_order_export/postabh_export_one', array('order_id' => $order->getId())).'" onclick="sendToPrintJs(this);" target="_blank">Vytlačiť PBH štítok</a>';
        }

        $tracks = $order->getTracksCollection();
        foreach ($tracks as $track) {
            $trackCode = $track->getCarrierCode();
            $trackTitle = $track->getTitle();
            $trackNumber = trim($track->getTrackNumber());

            if ($trackCode == 'slp' || $trackCode == 'slpo')                 $trackingUrl = 'https://www.posta.sk/sps-embed#tnt?q='.$trackNumber;
            elseif ($trackCode == 'gls' && $country == 'SK')                 $trackingUrl = 'https://gls-group.eu/SK/sk/sledovanie-zasielok?match='.$trackNumber;
            elseif ($trackCode == 'gls' && $country == 'CZ')                 $trackingUrl = 'https://gls-group.eu/CZ/cs/sledovani-zasilek?match='.$trackNumber;
            elseif ($trackCode == 'gls' && $country == 'HU')                 $trackingUrl = 'https://gls-group.eu/HU/hu/csomagkovetes?match='.$trackNumber;
            elseif ($trackCode == 'dhl')                                     $trackingUrl = 'https://www.dhlparcel.sk/main2.aspx?cls=Package&idSearch='.$trackNumber;
            elseif ($trackCode == 'ppl')                                     $trackingUrl = 'https://ppl.cz/main2.aspx?cls=Package&idSearch='.$trackNumber;
            elseif ($trackCode == 'urgentcargus')                            $trackingUrl = 'https://app.urgentcargus.ro/Private/Tracking.aspx?CodBara='.$trackNumber;
            elseif ($trackCode == 'dpdro')                                   $trackingUrl = 'https://tracking.dpd.ro/?shipmentNumber='.$trackNumber.'&language=en';
            elseif ($trackCode == 'zasielkovna')                             $trackingUrl = 'https://www.zasielkovna.sk/vyhladavanie?det='.$trackNumber;
            elseif ($trackCode == 'pbh' && $trackTitle == 'Pbh')             $trackingUrl = 'https://www.postabezhranic.cz/sledovani.php?sledovani='.$trackNumber;
            elseif ($trackCode == 'pbh' && $trackTitle == 'CZ-Česká pošta')  $trackingUrl = 'https://www.postaonline.cz/trackandtrace/-/zasilka/cislo?parcelNumbers='.$trackNumber;
            elseif ($trackCode == 'pbh' && $trackTitle == 'RO-FAN Courier')  $trackingUrl = 'https://www.fancourier.ro/en/awb-tracking/?home_awb_code='.$trackNumber;
            elseif ($trackCode == 'pbh' && $trackTitle == 'HU-Magyar Posta') $trackingUrl = 'https://www.posta.hu/nyomkovetes/nyitooldal?searchvalue='.$trackNumber;
            elseif ($trackCode == 'pbh' && $trackTitle == 'DE-DHL')          $trackingUrl = 'https://nolp.dhl.de/nextt-online-public/de/set_identcodes.do?lang=en&idc='.$trackNumber.'&rfn=&extendedSearch=true';
            elseif ($trackCode == 'joy' && $trackTitle == 'Fan')             $trackingUrl = 'https://www.fancourier.ro/en/awb-tracking/?home_awb_code='.$trackNumber;
            elseif ($trackCode == 'joy' && $trackTitle == 'DPD')             $trackingUrl = 'https://tracking.dpd.de/status/sk_SK/parcel/'.$trackNumber;
            elseif ($trackCode == 'joy' && $trackTitle == 'Spd')             $trackingUrl = 'https://www.speedy.bg/en/track-shipment?shipmentNumber='.$trackNumber;
            elseif ($trackCode == 'joy' && $trackTitle == 'Ecnt')            $trackingUrl = 'https://www.econt.com/en/services/track-shipment/'.$trackNumber;
            elseif ($trackCode == 'meest' || $trackCode == 'novaposhta')     $trackingUrl = 'https://t.meest-group.com/en/'.$trackNumber;

            
            $html .= '<span style="display: table-row;"><span 
                            style="display: table-cell; text-align:right; padding-right: 3px; min-width: 25px; width: 25px;"
                        >'.$this->shortenTitle($trackTitle).':</span>
                        <span 
                            style="display: table-cell; padding-right: 3px; min-width: 105px;"
                        ><a href="'.$trackingUrl.'" target="_blank"
                        >' . $this->escaper->escapeHtml($trackNumber) . '</a>'.$reprintOutput.'</span></span>';
        }
        
        if (count($tracks)) {
            $html .= $this->getCompleteDate($order);
            $html .= $this->getDeliveryDate($order);
        }
        $html .= '</span>';
        if ($order->getStatus() !== Order::STATE_COMPLETE
            && $order->getStatus() !== Order::STATE_CANCELED)
        {
            $html .= $printOutput;
        }

        return $html;
    }

    public function getCompleteDate($order)
    {
        $date = $order->getDispatchDate();

        if (empty($date)) {
            $date = $this->getDispatchDate($order);
            if (!empty($date)) {
                $order->setDispatchDate($date)->save();
            }
        }

        if (!empty($date)) {
            $date = \DateTime::createFromFormat('Y-m-d H:i:s', $date)->format('j.n.Y');
            return '<span style="display: table-row;"><span 
                         style="display: table-cell;
                                text-align:right;
                                padding-right: 3px;
                                min-width: 25px;
                                width: 25px;
                                color: #666;"
                    >'.__('Exp').':</span>
                    <span 
                         style="display: table-cell;
                                padding-right: 3px;
                                min-width: 105px;
                                color: #666;"
                    >'.$date.'</span></span>';
        }

        return '';
    }

    public function getDispatchDate($order)
    {
        $dates = [];
        foreach ($order->getStatusHistoryCollection() as $historyItem) {
            if ($historyItem->getStatus() == Order::STATE_COMPLETE)
                $dates[] = $historyItem->getCreatedAt();
        }
        if (!empty($dates) && isset($dates[0])) {
            return $dates[0];
        }
        return null;
    }

    public function getDeliveryDate($order)
    {
        if ($delivery_date = $order->getPkgDeliveryDate()) {
            $date = \DateTime::createFromFormat('Y-m-d', $delivery_date)->format('j.n.Y');
            $output = '<span style="display: table-row;">
                        <span style="display: table-cell; text-align:right; padding-right: 3px; min-width: 25px; width: 25px; color: #666;">'.__('Dor').':</span>
                        <span style="display: table-cell; padding-right: 3px; min-width: 105px; color: #666;">'.$date.'</span>
                       </span>';
        } else {
            $output = '';
        }

        return $output;
    }

    public function shortenTitle($title)
    {
        $output = explode('-', $title);
        return $output[0];
    }

    /**
     * Check if Order has a Shipment of Carrier $carrierCode
     *
     * @param  \Magento\Sales\Model\Order $order
     * @param  string $carrierCode
     * @return bool
     */
    public function hasShipments($order, $carrierCode = null)
    {
        if (!$order->hasShipments()) {
            return false;
        }
        if ($carrierCode === null) {
            return $order->hasShipments();
        }
        if ($shipmentsCollection = $order->getShipmentsCollection()) {
            foreach ($shipmentsCollection as $shipment) {
                foreach ($shipment->getAllTracks() as $track) {
                    if ($track->getCarrierCode() == $carrierCode) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

}
