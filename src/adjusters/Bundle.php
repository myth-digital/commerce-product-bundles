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
     * Event::on(Bundle::class, Bundle::EVENT_AFTER_BUNDLE_ADJUSTMENTS_CREATED, function(BundleAdjustmentsEvent $e) {
     *     // Do something - perhaps use a 3rd party to check order data and cancel all adjustments for this bundle or modify the adjustments.
     * });
     * ```
     */
    const EVENT_AFTER_BUNDLE_ADJUSTMENTS_CREATED = 'afterBundleAdjustmentsCreated';


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
            $bundleMatchCount = Bundles::getInstance()->getBundles()->matchOrder($order, $orderLineItems, $bundle);
            if ($bundleMatchCount > 0) {
                for ($i = 0; $i < $bundleMatchCount; $i++) {
                    $availableBundles[] = $bundle;
                }
            }
        }

        foreach ($availableBundles as $bundle) {
            $newOrderAdjustment = $this->_createOrderAdjustment($bundle);
            $adjustments[] = $newOrderAdjustment;
        }

        return $adjustments;
    }

    // Private Methods
    // =========================================================================

    /**
     * @param Bundle $discount
     * @return OrderAdjustment
     */
    private function _createOrderAdjustment(BundleModel $bundle): OrderAdjustment
    {
        //preparing model
        $adjustment = new OrderAdjustment();
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->name = $bundle->name;
        $adjustment->setOrder($this->_order);
        $adjustment->description = $bundle->description;
        $adjustment->sourceSnapshot = $bundle->toArray();
        $adjustment->amount = -$bundle->bundleDiscount;

        return $adjustment;
    }
}
