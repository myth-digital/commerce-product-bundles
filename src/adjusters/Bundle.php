<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace mythdigital\bundles\adjusters;

use mythdigital\bundles\Bundles;
use mythdigital\bundles\records\Bundle as BundleRecord;
use mythdigital\bundles\models\Bundle as BundleModel;
use mythdigital\bundles\events\BundleAdjustmentsEvent;
use craft\base\Component;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\OrderAdjustment;
use DateTime;

/**
 * Bundle Adjuster
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Bundle extends Component implements AdjusterInterface
{
    // Constants
    // =========================================================================

    /**
     * The bundle adjustment type.
     */
    const ADJUSTMENT_TYPE = 'bundle';

    /**
     * @event BundleAdjustmentsEvent The event that is raised after a bundle has matched the order and before it returns it's adjustments.
     *
     * Plugins can get notified before a line item is being saved
     *
     * ```php
     * use mythdigital\bundles\adjusters\Bundle;
     * use yii\base\Event;
     *
     * Event::on(Bundle::class, Bundle::EVENT_AFTER_BUNDLE_ADJUSTMENT_CREATED, function(BundleAdjustmentsEvent $e) {
     *     // Do something - perhaps use a 3rd party to check order data and cancel all adjustments for this bundle or modify the adjustments.
     * });
     * ```
     */
    const EVENT_AFTER_BUNDLE_ADJUSTMENT_CREATED = 'afterBundleAdjustmentCreated';


    // Properties
    // =========================================================================

    /**
     * @var Order
     */
    private $_order;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function adjust(Order $order): array
    {
        $this->_order = $order;

        $adjustments = [];
        $availableBundles = [];
        $bundles = Bundles::getInstance()->getBundles()->getAllActiveBundles($order);

        $orderLineItems = $order->getLineItems();

        foreach ($bundles as $bundle) {

            $bundleMatchResult = Bundles::getInstance()->getBundles()->matchOrder($order, $orderLineItems, $bundle);
            
            while ($bundleMatchResult['match']) {
                $availableBundles[] = $bundle;
                $orderLineItems = $bundleMatchResult['remainingAvailableLineItems'];
                $newAdjustment = $this->_createOrderAdjustment($bundle, $bundleMatchResult['lineItemRawPrice']);

                // Don't apply an adjustment that has no beneficial value.
                if ($newAdjustment && $newAdjustment->amount < 0) {
                    $adjustments[] = $newAdjustment;
                }

                $bundleMatchResult = Bundles::getInstance()->getBundles()->matchOrder($order, $orderLineItems, $bundle);                
            }
        }    

        return $adjustments;
    }

    // Private Methods
    // =========================================================================

    /**
     * @param Bundle $discount
     * @param $rawLineItemPrice
     * @return OrderAdjustment
     */
    private function _createOrderAdjustment(BundleModel $bundle, $rawLineItemPrice): OrderAdjustment
    {
        // Practically, the raw item price should be more than the bundle price. 
        // We need to adjust the difference between the raw line item price and the bundle price
        $bundleDiscount = $rawLineItemPrice - $bundle->bundlePrice;

        $bundleDiscount = $bundleDiscount > 0 ? -$bundleDiscount : 0;

        //preparing model
        $adjustment = new OrderAdjustment();
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->name = $bundle->name;
        $adjustment->setOrder($this->_order);
        $adjustment->description = $bundle->description;
        $adjustment->sourceSnapshot = $bundle->toArray();
        $adjustment->amount = $bundleDiscount;

        // Raise the 'EVENT_AFTER_BUNDLE_ADJUSTMENT_CREATED' event
        $event = new BundleAdjustmentsEvent([
            'order' => $this->_order,
            'bundle' => $bundle,
            'adjustment' => $adjustment
        ]);

        $this->trigger(self::EVENT_AFTER_BUNDLE_ADJUSTMENT_CREATED, $event);            

        if (!$event->isValid) {
            return null;
        }

        return $event->adjustment;
    }
}
