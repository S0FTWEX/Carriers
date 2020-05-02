<?php

namespace Softwex\Carriers\Model;

use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\UrlInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Convert\OrderFactory as ConvertOrder;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Model\Order\Email\Sender\ShipmentSender;
use Magento\Framework\Exception\LocalizedException;
use \libphonenumber\PhoneNumberUtil;
use \libphonenumber\NumberParseException;
use \libphonenumber\PhoneNumberFormat;
use \Exception;


abstract class AbstractCarriers extends AbstractModel
{
    /**
     * @var array
     */
    public static $countryCurrencies;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var TrackFactory
     */
    protected $shipmentTrackFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var UrlInterface
     */
    protected $urlInterface;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var ConvertOrder
     */
    protected $convertOrder;

    /**
     * @var TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @var ShipmentSender
     */
    protected $shipmentSender;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ObjectManagerInterface $objectManager
     * @param TrackFactory $shipmentTrackFactory
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param UrlInterface $urlInterface
     * @param StoreManagerInterface $storeManagerInterface
     * @param OrderFactory $orderFactory
     * @param ConvertOrder $convertOrder
     * @param TransactionFactory $transactionFactory
     * @param ShipmentSender $shipmentSender
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ObjectManagerInterface $objectManager,
        TrackFactory $shipmentTrackFactory, 
        ScopeConfigInterface $scopeConfigInterface, 
        UrlInterface $urlInterface, 
        StoreManagerInterface $storeManagerInterface,
        OrderFactory $orderFactory,
        ConvertOrder $convertOrder,
        TransactionFactory $transactionFactory,
        ShipmentSender $shipmentSender,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
        $this->objectManager = $objectManager;
        $this->shipmentTrackFactory = $shipmentTrackFactory;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->urlInterface = $urlInterface;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->orderFactory = $orderFactory;
        $this->convertOrder = $convertOrder;
        $this->transactionFactory = $transactionFactory;
        $this->shipmentSender = $shipmentSender;
    }

    /**
     * Get Payment method from Order
     *
     * @param  \Magento\Sales\Model\Order $order
     * @return string
     */
    public function getPaymentMethod($order)
    {
        return $order->getPayment()->getMethod();
    }

    /**
     * Get Shipping method from Order
     *
     * @param  \Magento\Sales\Model\Order $order
     * @return string
     */
    public function getShippingMethod($order)
    {
        if (!$order->getIsVirtual() && $order->getShippingDescription()) {
            return $order->getShippingDescription();
        } elseif (!$order->getIsVirtual() && $order->getShippingMethod()) {
            return $order->getShippingMethod();
        }
        return '';
    }

    /**
     * Get Full Street from Address
     *
     * @param  \Magento\Sales\Model\Order\Address $address
     * @return string
     */
    public function getStreet($address)
    {
        $street = $address->getStreet();
        if (is_array($street)) {
            return implode(', ', $street);
        }
        return $street;
    }

    /**
     * Add Shipment to Order
     *
     * @param  \Magento\Sales\Model\Order $order
     * @param  string $carrierCode
     * @param  int|string $trackNumbers
     * @return \Magento\Sales\Model\Order\Shipment|bool
     */
    public function addShipmentToOrder($order, $carrierCode, $trackNumbers)
    {
        if (!is_array($trackNumbers)) {
            $trackNumbers = [$trackNumbers];
        }
        if (empty(array_diff($trackNumbers, $this->getAllTrackingNumbers($order)))) {
            return $this->getLastShipment($order, $carrierCode);
        }
        if ($order->isCanceled()) {
            $this->unCancel($order);
        }
        if (!$order->canShip()) {
            $this->resetItems($order);
        }
        $this->deleteShipmentsWithoutTracking($order);

        // Initialize the order shipment object
        $convertOrder = $this->convertOrder->create();
        $shipment = $convertOrder->toShipment($order);

        // Loop through order items
        foreach ($order->getAllVisibleItems() as $item) {
            // Check if order item is virtual or has quantity to ship
            if (!$item->getQtyToShip() || $item->getIsVirtual()) {
                continue;
            }
            $qtyShipped = $item->getQtyToShip();
            // Create shipment item with qty
            $shipmentItem = $convertOrder->itemToShipmentItem($item)->setQty($qtyShipped);
            // Add shipment item to shipment
            $shipment->addItem($shipmentItem);
        }
        
        $carrierTitle = $this->scopeConfigInterface->getValue('carriers/'.$carrierCode.'/title');
        if (mb_strlen($carrierTitle, 'UTF-8') > 3) {
            $carrierTitle = strtoupper($carrierCode);
        }

        // Register shipment
        $shipment->register();

        try {
            foreach ($trackNumbers as $trackNumber) {
                $track = $this->shipmentTrackFactory->create();
                $track->setCarrierCode($carrierCode)
                      ->setTitle($carrierTitle)
                      ->setTrackNumber($trackNumber);

                $shipment->addTrack($track)->save();
            }
            $order->save();

            return $shipment;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Complete order and notify customer
     *
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @param bool $notifyCustomer
     */
    public function completeOrder($shipment, $notifyCustomer = false)
    {
        if (!$shipment->getId()) {
            return;
        }

        $order = $shipment->getOrder();

        if ($order->getStatus() == Order::STATE_COMPLETE) {
            return;
        }

        if ($order->getStatus() == Order::STATE_HOLDED) {
            $order->unhold()->save();
        }

        $order->setIsInProcess(true);

        if ($notifyCustomer) {
            $shipment->setCustomerNoteNotify($notifyCustomer);
            $this->shipmentSender->send($shipment);
            $shipment->save();
            $order->save();
        }
        
        if (!$order->hasInvoices()) {
            $invoice = $order->prepareInvoice();
            $invoice->register();
            $invoice->setCustomerNoteNotify($notifyCustomer);

            $transactionSave = $this->transactionFactory->create()
                ->addObject($invoice)->addObject($invoice->getOrder());
            $transactionSave->save();
        }

        $this->setOrderState($order, Order::STATE_COMPLETE);
        $order->setStatus(Order::STATE_COMPLETE);
        $order->save();

        if ($order->getStatus() == Order::STATE_COMPLETE) {
            $order->addStatusHistoryComment('')
                  ->setIsCustomerNotified($notifyCustomer)
                  ->save();
        }

    }

    /**
     * @param  $order \Magento\Sales\Model\Order
     * @param  $newOrderStatus
     * @return bool
     */
    protected function setOrderState(&$order, $newOrderStatus)
    {
        $orderConfig = $order->getConfig();
        $orderStates = $orderConfig->getStates();
        foreach ($orderStates as $state => $label) {
            $stateStatuses = $orderConfig->getStateStatuses($state, false);
            foreach ($stateStatuses as $status) {
                if ($status == $newOrderStatus) {
                    $order->setData('state', $state);
                    return true;
                }
            }
        }
        return false;
    }


    /**
     * Delete Shipments from Order by Carrier Code
     *
     * @param  \Magento\Sales\Model\Order $order
     */
    public function deleteShipments($order, $carrierCode = null)
    {
        if (!$order->hasShipments()) {
            return;
        }
        // get permissions to delete
        if ($this->_registry->registry('isSecureArea') === null) {
            $this->_registry->register('isSecureArea', true);
        }
        if ($shipmentsCollection = $order->getShipmentsCollection()) {
            foreach ($shipmentsCollection as $shipment) {
                if ($carrierCode) {
                    $flag = false;
                    foreach ($shipment->getAllTracks() as $track) {
                        if ($track->getCarrierCode() == $carrierCode) {
                            $shipment->delete();
                            $flag = true;
                        }
                    }
                    if ($flag) {
                        $this->resetItems($order);
                    }
                } else {
                    $shipment->delete();
                    $this->resetItems($order);
                }
            }
        }
    }

    /**
     * Check if Order has a Shipment of Carrier
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

    /**
     * Get Last Shipment of Carrier
     *
     * @param  \Magento\Sales\Model\Order $order
     * @param  string $carrierCode
     * @return \Magento\Sales\Model\Order\Shipment|false
     */
    public function getLastShipment($order, $carrierCode = null)
    {
        if (!$order->hasShipments()) {
            return false;
        }
        $shipmentsCollection = $order->getShipmentsCollection();
        if ($carrierCode = null) {
            return $shipmentsCollection->getLastItem();
        }

        $result = false;
        foreach ($shipmentsCollection as $shipment) {
            foreach ($shipment->getAllTracks() as $track) {
                if ($track->getCarrierCode() == $carrierCode) {
                    $result = $shipment;
                }
            }
        }

        return $result;
    }

    /**
     * Delete all the shipments which have no tracks
     *
     * @param  \Magento\Sales\Model\Order $order
     */
    public function deleteShipmentsWithoutTracking($order)
    {
        if (!$order->hasShipments()) {
            return;
        }
        // get permissions to delete
        if ($this->_registry->registry('isSecureArea') === null) {
            $this->_registry->register('isSecureArea', true);
        }
        if ($shipmentsCollection = $order->getShipmentsCollection()) {
            foreach ($shipmentsCollection as $shipment) {
                if ($this->hasNoTrackingNumber($shipment)) {
                    $shipment->delete();
                }
            }
        }
    }

    /**
     * Check if Shipment has no tracking numbers
     *
     * @param  \Magento\Sales\Model\Order\Shipment $shipment
     * @return bool
     */
    public function hasNoTrackingNumber($shipment)
    {
        $trackNumbers = [];
        foreach ($shipment->getAllTracks() as $track) {
            $trackNumbers[] = $track->getTrackNumber();
        }
        return empty($trackNumbers);
    }

    /**
     * Reset all ordered items states
     *
     * @param  \Magento\Sales\Model\Order $order
     */
    public function resetItems($order)
    {
        $items = $order->getAllItems();
        foreach ($items as $item) {
            $item->setQtyInvoiced(0)->setQtyShipped(0)->setQtyRefunded(0)->setQtyCanceled(0)->setLockedDoInvoice(null)->setLockedDoShip(null)->save();
        }
    }

    /**
     * Uncancel Order
     *
     * @param  \Magento\Sales\Model\Order $order
     */
    public function unCancel($order)
    {
        $old_status = $order->getStatus();
        $order->setStatus(Order::STATE_PROCESSING)->setState(Order::STATE_PROCESSING)->save();
        $order->setStatus($old_status)->save();
        $this->resetItems($order);
    }

    /**
     * Get all tracking numbers from Order by carrier code
     *
     * @param  \Magento\Sales\Model\Order $order
     * @param  string $carrierCode
     * @return array
     */
    public function getAllTrackingNumbers($order, $carrierCode = null)
    {
        $trackNumbers = [];
        if ($shipmentsCollection = $order->getShipmentsCollection()) {
            foreach ($shipmentsCollection as $shipment) {
                foreach ($shipment->getAllTracks() as $track) {
                    if ($carrierCode) {
                        if ($track->getCarrierCode() == $carrierCode) {
                            $trackNumbers[] = $track->getTrackNumber();
                        }
                    } else {
                        $trackNumbers[] = $track->getTrackNumber();
                    }
                }
            }
        }
        return $trackNumbers;
    }

    /**
     * Get Shipping Adrees Object from Order
     *
     * @param  \Magento\Sales\Model\Order $order
     * @return \Magento\Sales\Model\Order\Address
     */
    public function getShippingAddress($order)
    {
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = !$order->getIsVirtual() ? $order->getShippingAddress() : null;
        if (!$shippingAddress) {
            $shippingAddress = $billingAddress;
        }
        return $shippingAddress;
    }

    /**
     * Get next working day from today
     *
     * @return string
     */
    public function getNextWorkingDay()
    {
        $today = date('Y-m-d');
        return date('Y-m-d', strtotime($today . ' +1 Weekday'));
    }

    /**
     * Set order status
     *
     * @param \Magento\Sales\Model\Order $order
     * @param string $status New status
     * @param bool $notify Notify customer or not
     */
    public function setOrderStatus($order, $status, $notify)
    {
        $order->setStatus($status)->save();
        if ($notify) {
            $order->sendOrderUpdateEmail(true, '');
        }
        $order->addStatusHistoryComment('')->setIsCustomerNotified(true)->save();
    }

    /**
     * Get Currencies by theirs countries
     *
     * @return array
     */
    public function getCountryCurrencies()
    {
        if (self::$countryCurrencies === null) {
            $jsonString = file_get_contents('http://country.io/currency.json');
            if (!$jsonString) {
                return self::$countryCurrencies;
            }
            $currJson = json_decode($jsonString);
            $currencies = json_decode(json_encode((array)$currJson), true);
            ksort($currencies);
            self::$countryCurrencies = $currencies;
        }
        return self::$countryCurrencies;
    }

    /**
     * Check if order has valid Order Currency Code to its Shipping country
     *
     * @param  \Magento\Sales\Model\Order $order
     * @return bool
     */
    public function hasValidCurrencyToCountry($order)
    {
        $shippingAddress = $this->getShippingAddress($order);
        $countryId = $shippingAddress->getCountryId();
        $currencies = $this->getCountryCurrencies();

        if ($currencies === null) {  // if service http://country.io  from getCountryCurrencies() is unavailable
            return true;
        }
        if (isset($currencies[$countryId]) && !empty($currencies[$countryId])) {
            $countryCurr = $currencies[$countryId];
            $orderCurr = $order->getOrderCurrencyCode();
            return $orderCurr == $countryCurr;
        }
        return false;
    }

    /**
     * Replace Magento Base URL to Magento Base Dir
     *
     * @param  string $url
     * @return string
     */
    public function replaceBaseUrlToDir($url)
    {
        return str_replace($this->urlInterface->getBaseUrl(), BP.'/', $url);
    }

    /**
     * Check if Ghostscript is installed on server
     *
     * @return bool
     */
    public function isGhostscriptInstalled()
    {
        system('which gs > /dev/null', $retval);
        return $retval == 0;
    }

    /**
     * Round up any number to $x delimiter
     *
     * @param  int|string $n
     * @param  int $x
     * @return float
     */
    public function roundUpToAny($n, $x = 5)
    {
        return (ceil($n)%$x === 0) ? ceil($n) : round(($n+$x/2)/$x)*$x;
    }

    /**
     * Get telephone number from Order in international format E.164
     *
     * @param  mixed $entity
     * @return bool|string
     */
    public function getIntPhonenumber($entity)
    {
        if ($entity instanceof Order) {
            $shippingAddress = $this->getShippingAddress($entity);
        } elseif ($entity instanceof Address) {
            $shippingAddress = $entity;
        } else {
            return false;
        }
        $country = $shippingAddress->getCountryId();
        $phoneNumber = $shippingAddress->getTelephone();

        $phoneUtil = PhoneNumberUtil::getInstance();
        try {
            $phoneNumberObj = $phoneUtil->parse($phoneNumber, $country);
        } catch (NumberParseException $e) {
            return false;
        }
        if ($phoneUtil->isValidNumber($phoneNumberObj)) {
            return $phoneUtil->format($phoneNumberObj, PhoneNumberFormat::E164);
        }
        return false;
    }

    /**
     * Removes accents from string
     *
     * @param  $string
     * @return string
     */
    public function removeAccents($string)
    {
        return Smscounter::removeAccents($string);
    }

}
