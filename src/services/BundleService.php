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
use mythdigital\bundles\records\BundlePurchasable as BundlePurchasableRecord;
use mythdigital\bundles\records\BundleCategory as BundleCategoryRecord;

use craft\commerce\base\PurchasableInterface;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
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
                    'bp.purchasableId',
                    'bpt.categoryId',
                ])
                ->leftJoin('{{%bundle_bundle_purchasables}}' . ' bp', '[[bp.bundleId]]=[[bundles.id]]')
                ->leftJoin('{{%bundles_bundle_categories}}' . ' bpt', '[[bpt.bundleId]]=[[bundles.id]]')
                ->all();

            $this->_allBundles = $this->_populatBundleRelations($bundles);
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
                    'bp.purchasableId',
                    'bpt.categoryId',
                ])
                ->leftJoin('{{%bundle_bundle_purchasables}}' . ' bp', '[[bp.bundleId]]=[[bundles.id]]')
                ->leftJoin('{{%bundles_bundle_categories}}' . ' bpt', '[[bpt.bundleId]]=[[bundles.id]]')
                // Restricted by enabled discounts
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

            $this->_allActiveBundles = $this->_populatBundleRelations($discounts);
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
            'bp.purchasableId,
            bpt.categoryId')
            ->from('{{%bundles_bundle}}' . ' bundles')
            ->leftJoin('{{%bundle_bundle_purchasables}}' . ' bp', '[[bp.bundleId]]=[[bundles.id]]')
            ->leftJoin('{{%bundles_bundle_categories}}' . ' bpt', '[[bpt.bundleId]]=[[bundles.id]]')
            ->where(['bundles.id' => $bundle->id])
            ->all();

        $purchasableIds = [];
        $categoryIds = [];

        foreach ($rows as $row) {
            if ($row['purchasableId']) {
                $purchasableIds[] = $row['purchasableId'];
            }

            if ($row['categoryId']) {
                $categoryIds[] = $row['categoryId'];
            }
        }

        $bundle->setPurchasableIds($purchasableIds);
        $bundle->setCategoryIds($categoryIds);
    }

    /**
     * @param PurchasableInterface $purchasable
     * @return array
     * @since 1.0.0
     */
    public function getBundlesRelatedToPurchasable(PurchasableInterface $purchasable): array
    {
        $bundles = [];

        if ($purchasable->getId()) {
            foreach ($this->getAllBundles() as $bundle) {
                // Get bundle by related purchasable
                $purchasableIds = $bundle->getPurchasableIds();
                $id = $purchasable->getId();

                // Get bundle by related category
                $relatedTo = ['sourceElement' => $purchasable->getPromotionRelationSource()];
                $categoryIds = $bundle->getCategoryIds();
                $relatedCategories = Category::find()->id($categoryIds)->relatedTo($relatedTo)->ids();

                if (in_array($id, $purchasableIds) || !empty($relatedCategories)) {
                    $bundles[$bundle->id] = $bundle;
                }
            }
        }

        return $bundles;
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
        $record->bundleDiscount = $model->bundleDiscount;
        $record->purchaseQty = $model->purchaseQty;
        $record->totalUses = $model->totalUses;
        $record->sortOrder = $record->sortOrder ?: 999;

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $record->save(false);
            $model->id = $record->id;

            BundlePurchasableRecord::deleteAll(['bundleId' => $model->id]);
            BundleCategoryRecord::deleteAll(['bundleId' => $model->id]);

            foreach ($model->getCategoryIds() as $categoryId) {
                $relation = new BundleCategoryRecord();
                $relation->categoryId = $categoryId;
                $relation->bundleId = $model->id;
                $relation->save(false);
            }

            foreach ($model->getPurchasableIds() as $purchasableId) {
                $relation = new BundlePurchasableRecord();
                $element = Craft::$app->getElements()->getElementById($purchasableId);
                $relation->purchasableType = get_class($element);
                $relation->purchasableId = $purchasableId;
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
                'bundles.bundleDiscount',
                'bundles.purchaseQty',
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
    private function _populatBundleRelations($bundles): array
    {
        $allBundlesById = [];

        if (empty($bundles)) {
            return $allBundlesById;
        }

        $purchasables = [];
        $categories = [];

        foreach ($bundles as $bundle) {
            $id = $bundle['id'];
            if ($bundle['purchasableId']) {
                $purchasables[$id][] = $bundle['purchasableId'];
            }

            if ($bundle['categoryId']) {
                $categories[$id][] = $bundle['categoryId'];
            }

            unset($bundle['purchasableId'], $bundle['categoryId']);

            if (!isset($allBundlesById[$id])) {
                $allBundlesById[$id] = new Bundle($bundle);
            }
        }

        foreach ($allBundlesById as $id => $bundle) {
            $bundle->setPurchasableIds($purchasables[$id] ?? []);
            $bundle->setCategoryIds($categories[$id] ?? []);
        }

        return $allBundlesById;
    }    
}
