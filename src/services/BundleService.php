<?php
/**
 * Bundles plugin for Craft CMS 3.x
 *
 * Craft Commerce Bundles Plugin
 *
 * @link      https://myth.digital/
 * @copyright Copyright (c) 2020 Myth Digital
 */

namespace mythdigital\bundles\services;

use mythdigital\bundles\Bundles;
use mythdigital\bundles\records\Bundle as BundleRecord;
use mythdigital\bundles\models\Bundle;
use mythdigital\bundles\events\BundleEvent;
use mythdigital\bundles\records\BundleCategory as BundleCategoryRecord;

use craft\commerce\base\PurchasableInterface;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\elements\Category;
use craft\helpers\Db;
use DateTime;
use function in_array;

/**
 * @author    Myth Digital
 * @package   Bundles
 * @since     1.0.0
 */
class BundleService extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event BundleEvent The event that is raised before an discount is saved.
     *
     * Plugins can get notified before a bundle is being saved
     *
     * ```php
     * use mythdigital\bundles\BundleEvent;
     * use mythdigital\bundles\services\BundleService;
     * use yii\base\Event;
     *
     * Event::on(BundleService::class, BundleService::EVENT_BEFORE_SAVE_BUNDLE, function(BundleEvent $e) {
     *     // Do something - perhaps let an external CRM system know about a client's new bundle
     * });
     * ```
     */
    const EVENT_BEFORE_SAVE_BUNDLE = 'beforeSaveBundle';

    /**
     * @event BundleEvent The event that is raised after a bundle is saved.
     *
     * Plugins can get notified after a bundle has been saved
     *
     * ```php
     * use mythdigital\bundles\BundleEvent;
     * use mythdigital\bundles\services\BundleService;
     * use yii\base\Event;
     *
     * Event::on(BundleService::class, BundleService::EVENT_AFTER_SAVE_BUNDLE, function(BundleEvent $e) {
     *     // Do something - perhaps set this bundle as default in an external CRM system
     * });
     * ```
     */
    const EVENT_AFTER_SAVE_BUNDLE = 'afterSaveBundle';

    /**
     * @event BundleEvent The event that is raised after a bundle is deleted.
     *
     * Plugins can get notified after a bundle has been deleted.
     *
     * ```php
     * use mythdigital\bundles\BundleEvent;
     * use mythdigital\bundles\services\BundleService;
     * use yii\base\Event;
     *
     * Event::on(BundleService::class, BundleService::EVENT_AFTER_DELETE_BUNDLE, function(BundleEvent $e) {
     *     // Do something - perhaps remove this bundle from a payment gateway.
     * });
     * ```
     */
    const EVENT_AFTER_DELETE_BUNDLE = 'afterDeleteBundle';

    // Properties
    // =========================================================================

    /**
     * @var Bundle[]
     */
    private $_allBundles;

    /**
     * @var Bundle[]
     */
    private $_allActiveBundles;

    // Public Methods
    // =========================================================================

    /**
     * Get a bundle by its ID.
     *
     * @param int $id
     * @return Bundle|null
     */
    public function getBundleById($id)
    {
        foreach ($this->getAllBundles() as $bundle) {
            if ($bundle->id == $id) {
                return $bundle;
            }
        }

        return null;
    }

    /**
     * Get all bundles.
     *
     * @return Bundle[]
     */
    public function getAllBundles(): array
    {
        if (null === $this->_allBundles) {
            $bundles = $this->_createBundleQuery()
                ->addSelect([
                    'bpt.categoryId',
                    'bpt.purchaseQty as bptPurchaseQty'
                ])
                ->leftJoin('{{%bundles_bundle_categories}}' . ' bpt', '[[bpt.bundleId]]=[[bundles.id]]')
                ->all();

            $this->_allBundles = $this->_populateBundleRelations($bundles);
        }

        return $this->_allBundles;
    }

    /**
     * Get all currently active bundles
     *
     * @param Order|null $order
     * @return array
     * @throws \Exception
     * @since 1.0.0
     */
    public function getAllActiveBundles($order = null): array
    {
        if (null === $this->_allActiveBundles) {
            $date = $order && $order->dateOrdered ? $order->dateOrdered : new DateTime();

            $discounts = $this->_createBundleQuery()
                ->addSelect([
                    'bpt.categoryId',
                    'bpt.purchaseQty as bptPurchaseQty'
                ])
                ->leftJoin('{{%bundles_bundle_categories}}' . ' bpt', '[[bpt.bundleId]]=[[bundles.id]]')
                // Restricted by enabled bundles
                ->where([
                    'enabled' => 1,
                ])
                // Restrict by things that a definitely not in date
                ->andWhere([
                    'or',
                    ['dateFrom' => null],
                    ['<=', 'dateFrom', Db::prepareDateForDb($date)]
                ])
                ->andWhere([
                    'or',
                    ['dateTo' => null],
                    ['>=', 'dateTo', Db::prepareDateForDb($date)]
                ])
                ->all();

            $this->_allActiveBundles = $this->_populateBundleRelations($discounts);
        }

        return $this->_allActiveBundles;
    }

    /**
     * Populates a bundle's relations.
     *
     * @param Bundle $bundle
     */
    public function populateBundleRelations(Bundle $bundle)
    {
        $rows = (new Query())->select(
            'bpt.categoryId,
            bpt.purchaseQty as bptPurchaseQty')
            ->from('{{%bundles_bundle}}' . ' bundles')
            ->leftJoin('{{%bundles_bundle_categories}}' . ' bpt', '[[bpt.bundleId]]=[[bundles.id]]')
            ->where(['bundles.id' => $bundle->id])
            ->all();

        $categoryIds = [];

        foreach ($rows as $row) {

            if ($row['categoryId']) {
                $categoryIds[] = [
                    'id' => $row['categoryId'],
                    'purchaseQty' => $row['bptPurchaseQty'],
                    'category' => Craft::$app->getElements()->getElementById($row['categoryId'])
                ];
            }
        }

        for ($i = 0; $i < sizeof($categoryIds); $i++) {
            $categoryIds[$i]["index"] = $i;
        }

        $bundle->setCategoryIds($categoryIds);
    }

    /**
     * Save a bundle.
     *
     * @param Bundle $model the bundle being saved
     * @param bool $runValidation should we validate this discount before saving.
     * @return bool
     * @throws \Exception
     */
    public function saveBundle(Bundle $model, bool $runValidation = true): bool
    {
        $isNew = !$model->id;

        if ($model->id) {
            $record = BundleRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Plugin::t('No bundle exists with the ID “{id}”', ['id' => $model->id]));
            }
        } else {
            $record = new BundleRecord();
        }

        // Raise the beforeSaveBundle event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_BUNDLE)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_BUNDLE, new BundleEvent([
                'bundle' => $model,
                'isNew' => $isNew,
            ]));
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('Bundle not saved due to validation error.', __METHOD__);

            return false;
        }

        $record->name = $model->name;
        $record->description = $model->description;
        $record->dateFrom = $model->dateFrom;
        $record->dateTo = $model->dateTo;
        $record->enabled = $model->enabled;
        $record->bundlePrice = $model->bundlePrice;
        $record->totalUses = $model->totalUses;
        $record->sortOrder = $record->sortOrder ?: 999;

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $record->save(false);
            $model->id = $record->id;

            BundleCategoryRecord::deleteAll(['bundleId' => $model->id]);

            foreach ($model->getCategoryIds() as $categoryItem) {
                $relation = new BundleCategoryRecord();
                $relation->categoryId = $categoryItem['id'];
                $relation->purchaseQty = $categoryItem['purchaseQty'];
                $relation->bundleId = $model->id;
                $relation->save(false);
            }

            $transaction->commit();

            // Raise the afterSaveBundle event
            if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_BUNDLE)) {
                $this->trigger(self::EVENT_AFTER_SAVE_BUNDLE, new BundleEvent([
                    'bundle' => $model,
                    'isNew' => $isNew,
                ]));
            }

            return true;
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Delete a bundle by its ID.
     *
     * @param int $id
     * @return bool
     */
    public function deleteBundleById($id): bool
    {
        $bundleRecord = BundleRecord::findOne($id);

        if (!$bundleRecord) {
            return false;
        }

        // Get the Discount model before deletion to pass to the Event.
        $bundle = $this->getBundleById($id);

        $result = (bool)$bundleRecord->delete();

        //Raise the afterDeleteBundle event
        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_BUNDLE)) {
            $this->trigger(self::EVENT_AFTER_DELETE_BUNDLE, new BundleEvent([
                'bundle' => $bundle,
                'isNew' => false
            ]));
        }

        return $result;
    }

    /**
     * Clears a bundles usage history.
     *
     * @param int $id the bundle ID
     */
    public function clearCouponUsageHistoryById(int $id)
    {
        $db = Craft::$app->getDb();

        $db->createCommand()
            ->update('{{%bundles_bundle}}', ['totalUses' => 0], ['id' => $id])
            ->execute();
    }

    /**
     * Reorder bundles by an array of ids.
     *
     * @param array $ids
     * @return bool
     */
    public function reorderBundles(array $ids): bool
    {
        foreach ($ids as $sortOrder => $id) {
            Craft::$app->getDb()->createCommand()
                ->update('{{%bundles_bundle}}', ['sortOrder' => $sortOrder + 1], ['id' => $id])
                ->execute();
        }

        return true;
    }    

    /**
     * Matches the specified bundle to the specified order and line items in a greedy mannr.
     *
     * @param Order $order The order.
     * @param LineItem[] $lineItems The line items.
     * @param Bundle $bundle The bundle to match.
     * @return void
     */
    public function matchOrder(Order $order, $lineItems, Bundle $bundle)
    {
        // Check the bundle is enabled.
        if (!$bundle->enabled) return 0;

        // Check the dates align.
        $currentDate = new \DateTime();

        if (!empty($bundle->dateFrom) && $bundle->dateFrom > $currentDate) return 0;
        if (!empty($bundle->dateTo) && $bundle->dateTo < $currentDate) return 0;

        // For the bundle to match, we need to find line items that match the category and variant constraints.
        $matchingLineItems = [];

        foreach ($lineItems as $lineItem)
        {
            $purchasedItem = $lineItem->getPurchasable();
            $purchasedProduct = null;

            if (\is_a($purchasedItem, 'craft\commerce\elements\Variant')) {
                $purchasedProduct = $purchasedItem->getProduct();
            }

            $purchaseableMatch = empty($bundle->getPurchasableIds()) || \in_array($purchasedItem->getId(), $bundle->getPurchasableIds());

            $categoryMatch = empty($bundle->getCategoryIds());

            if (!$categoryMatch) {
                foreach ($bundle->getCategoryIds() as $categoryId) {
                    $categoryByVariant = Category::find()->id($categoryId)->relatedTo($purchasedItem)->count() > 0;
                    $categoryByProduct = false;

                    if (!empty($purchasedProduct)) {
                        $categoryByProduct = Category::find()->id($categoryId)->relatedTo($purchasedProduct)->count() > 0;
                    }

                    $categoryMatch = $categoryByVariant || $categoryByProduct;

                    if ($categoryMatch) break;
                }
            }

            if ($purchaseableMatch && $categoryMatch) $matchingLineItems[] = $lineItem;
        }

        $matchCount = 0;
        $matchingLineItemRemainingCount = 0;
        $purchaseQty = $bundle->purchaseQty;

        foreach ($matchingLineItems as $lineItem) {
            $matchingLineItemRemainingCount += $lineItem->qty;
        }

        while ($matchingLineItemRemainingCount >= $purchaseQty) {
            $matchCount++;
            $matchingLineItemRemainingCount = $matchingLineItemRemainingCount - $purchaseQty;
        }

        return $matchCount;
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns a Query object prepped for retrieving bundles
     *
     * @return Query
     */
    private function _createBundleQuery(): Query
    {
        return (new Query())
            ->select([
                'bundles.id',
                'bundles.name',
                'bundles.description',
                'bundles.dateFrom',
                'bundles.dateTo',
                'bundles.enabled',                
                'bundles.bundlePrice',
                'bundles.totalUses',
                'bundles.sortOrder',
                'bundles.dateCreated',
                'bundles.dateUpdated',
            ])
            ->from(['bundles' => '{{%bundles_bundle}}'])
            ->orderBy(['sortOrder' => SORT_ASC]);
    }    

    /**
     * @param $bundles
     * @return array
     * @since 1.0.0
     */
    private function _populateBundleRelations($bundles): array
    {
        $allBundlesById = [];

        if (empty($bundles)) {
            return $allBundlesById;
        }

        $categories = [];

        foreach ($bundles as $bundle) {
            $id = $bundle['id'];

            if ($bundle['categoryId']) {
                $categories[$id][] = [
                    'id' => $bundle['categoryId'],
                    'purchaseQty' => $bundle['bptPurchaseQty'],
                    'category' => Craft::$app->getElements()->getElementById($bundle['categoryId'])
                ];
            }

            unset($bundle['categoryId'], $bundle['bptPurchaseQty']);

            if (!isset($allBundlesById[$id])) {
                $allBundlesById[$id] = new Bundle($bundle);
            }
        }

        foreach ($allBundlesById as $id => $bundle) {
    
            for ($i = 0; $i < sizeof($categories[$id]); $i++) {
                $categories[$id][$i]["index"] = $i;
            }

            $bundle->setCategoryIds($categories[$id] ?? []);
        }

        return $allBundlesById;
    }    
}
