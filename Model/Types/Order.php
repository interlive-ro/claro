<?php
/**
 * Intelive
 * @package Intelive Claro
 * @copyright Copyright (c) 2018 Intelive Metrics Srl
 * @author Adrian Roman
 */

namespace Intelive\Claro\Model\Types;

class Order
{
    const ENTITY_TYPE = 'sales_order';
    const CHANNEL_UNTRACKED = '(untracked)';

    protected $helper;
    protected $objectManager;

    /**
     * @param \Intelive\Claro\Helper\Data $helper
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     */
    public function __construct(
        \Intelive\Claro\Helper\Data $helper,
        \Magento\Framework\ObjectManagerInterface $objectManager
    ) {
        $this->helper = $helper;
        $this->objectManager = $objectManager;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return $this
     */
    public function parse($order)
    {
        $this->entity_name = self::ENTITY_TYPE;
        $this->id = $order->getId();
        $this->increment_id = $order->getIncrementId();
        $this->store_id = $order->getStoreId();
        $this->coupon_code = $order->getCouponCode();

        $this->customer_id = $order->getCustomerId();
        $this->customer_name = $order->getCustomerName();
        $this->customer_email = $order->getCustomerEmail();
        $this->customer_group = $order->getCustomerGroupId();
        $this->grand_total = $order->getGrandTotal();
        $this->tax_amount = $order->getTaxAmount();
        $this->shipping_amount = $order->getShippingAmount();
        $this->shipping_tax_amount = $order->getShippingTaxAmount();
        $this->subtotal = $order->getSubtotal();
        $this->discount_amount = $order->getDiscountAmount();
        $this->currency_code = $order->getBaseCurrencyCode();
        $this->total_qty_ordered = $order->getTotalQtyOrdered();
        $this->created_at = $order->getCreatedAt();
        $this->total_item_count = $order->getTotalItemCount();
        $attributes = $order->getData();
        $this->source = $attributes['source'] ? $attributes['source'] : self::CHANNEL_UNTRACKED;
        $this->medium = $attributes['medium'] ? $attributes['medium'] : self::CHANNEL_UNTRACKED;
        $this->content = $attributes['content'] ? $attributes['content'] : self::CHANNEL_UNTRACKED;
        $this->campaign = $attributes['campaign'] ? $attributes['campaign'] : self::CHANNEL_UNTRACKED;
        $this->gclid = $attributes['gclid'] ? $attributes['gclid'] : self::CHANNEL_UNTRACKED;

        $billingAddress = new \stdClass();
        $shippingAddress = new \stdClass();

        $orderBillingAddress = $order->getBillingAddress();
        if (!is_null($orderBillingAddress) && is_object($orderBillingAddress)) {
            $billingAddress->postcode = $order->getBillingAddress()->getPostcode();
            $billingAddress->city = $order->getBillingAddress()->getCity();
            $billingAddress->region = $order->getBillingAddress()->getRegion();
            $billingAddress->country = $order->getBillingAddress()->getCountryId();
            $this->billing_address = $billingAddress;
        }
        // Check expedition data
        $orderShippingAddress = $order->getShippingAddress();
        if (!is_null($orderShippingAddress) && is_object($orderShippingAddress)) {
            $shippingAddress->postcode = $orderShippingAddress->getPostcode();
            $shippingAddress->city = $orderShippingAddress->getCity();
            $shippingAddress->region = $orderShippingAddress->getRegion();
            $shippingAddress->country = $orderShippingAddress->getCountryId();
            $this->shipping_address = $shippingAddress;
        }

        $orderItems = [];
        /** @var \Magento\Sales\Model\Order\Item $orderItem */
        foreach ($order->getAllItems() as $orderItem) {
            $item = new \stdClass();
            $item->item_id = $orderItem->getId();
            $item->order_id = $orderItem->getOrderId();
            $item->sku = $orderItem->getSku();
            $item->name = $orderItem->getName();
            $item->qty = $orderItem->getQtyOrdered();
            $item->price = $orderItem->getPrice();
            $item->tax_amount = $orderItem->getTaxAmount();
            $item->parent_item_id = $orderItem->getParentItemId();
            $item->parent_item_sku = !is_null($orderItem->getParentItem()) ? $orderItem->getParentItem()->getSku() : null;
            $item->product_type = $orderItem->getProductType();
            $item->created_at = $orderItem->getCreatedAt();
            $item->update_date = $orderItem->getUpdatedAt();

            if (isset($orderItem->getProductOptions()['attributes_info'])) {
                $orderItemOptions = [];
                foreach ($orderItem->getProductOptions()['attributes_info'] as $option) {
                    $itemAttribute = new \stdClass();
                    $itemAttribute->attribute_id = $option['option_id'];
                    $itemAttribute->item_id = $orderItem->getId();
                    $itemAttribute->label = $option['label'];
                    $itemAttribute->value = $option['value'];
                    $orderItemOptions[] = $itemAttribute;
                }
                $item->options = $orderItemOptions;
            }
            $orderItems['item_' . $orderItem->getItemId()] = $item;
        }
        $this->items = $orderItems;
        $this->status = $order->getStatus();
        $this->state = $order->getState();
        $this->shipping_description = $order->getShippingDescription();
        $this->payment_method = $order->getPayment()->getMethod();

        return $this;
    }
}