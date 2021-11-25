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
use mythdigital\bundles\models\BundleOrderMatchResult;
use mythdigital\bundles\models\BundleRuleMatchResult;
use mythdigital\bundles\events\BundleEvent;
use mythdigital\bundles\records\BundleCategory as BundleCategoryRecord;
use mythdigital\bundles\records\BundlePurchasable as BundlePurchasableRecord;

use craft\commerce\base\PurchasableInterface;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
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

    /**
     * Instructs the matcher to require a full match between an order and a bundle.
     */
    const BUNDLE_MATCH_MODE_REQUIRE_ALL = 'matchingModeAll';

    /**
     * Instructs the matcher to allow a partial match between an order and a bundle.
     */
    const BUNDLE_MATCH_MODE_ALLOW_PARTIAL = 'matchingModePartial';

    /**
     * Specifies that the matching failed.
     */
    const MATCH_RESULT_FAILED = 'matchFailed';

    /**
     * Specifies that the matching partially succeeded.
     */
    const MATCH_RESULT_PARTIAL = 'matchPartial';

    /**
     * Specifies that the matching succeeded.
     */
    const MATCH_RESULT_SUCCESS = 'matchSuccessful';

    /**
     * A product rule.
     */
    const BUNDLE_RULE_TYPE_PRODUCT = 'product';

    /**
     * A category rule.
     */
    const BUNDLE_RULE_TYPE_CATEGORY = 'category';

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
                    'bc.categories',
                    'bc.purchaseQty as bcPurchaseQty',
                    'bc.uid as bcUid',
                    'bp.purchasables',
                    'bp.purchaseQty as bpPurchaseQty',
                    'bp.uid as bpUid'                    
                ])
                ->leftJoin('{{%bundles_bundle_categories}}' . ' bc', '[[bc.bundleId]]=[[bundles.id]]')
                ->leftJoin('{{%bundle_bundle_purchasables}}' . ' bp', '[[bp.bundleId]]=[[bundles.id]]')
                ->all();

            $this->_allBundles = $this->_populateBundleRelations($bundles);
        }

        return $this->_allBundles;
    }

    /**
     * Get all currently active bundles
     *
     * @return Bundle[]
     * @throws \Exception
     * @since 1.0.0
     */
    public function getAllActiveBundles(): array
    {
        if (null === $this->_allActiveBundles) {
            $date = new DateTime();

            $discounts = $this->_createBundleQuery()
                ->addSelect([
                    'bc.categories',
                    'bc.purchaseQty as bcPurchaseQty',
                    'bc.uid as bcUid',
                    'bp.purchasables',
                    'bp.purchaseQty as bpPurchaseQty',
                    'bp.uid as bpUid'
                ])
                ->leftJoin('{{%bundles_bundle_categories}}' . ' bc', '[[bc.bundleId]]=[[bundles.id]]')
                ->leftJoin('{{%bundle_bundle_purchasables}}' . ' bp', '[[bp.bundleId]]=[[bundles.id]]')
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
            'bc.categories,
            bc.purchaseQty as bcPurchaseQty,
            bc.uid as bcUid,
            bp.purchaseQty as bpPurchaseQty,
            bp.purchasables,
            bp.uid as bpUid')
            ->from('{{%bundles_bundle}}' . ' bundles')
            ->leftJoin('{{%bundles_bundle_categories}}' . ' bc', '[[bc.bundleId]]=[[bundles.id]]')
            ->leftJoin('{{%bundle_bundle_purchasables}}' . ' bp', '[[bp.bundleId]]=[[bundles.id]]')
            ->where(['bundles.id' => $bundle->id])
            ->all();

        $mappedCategoryIds = [];
        $mappedPurchasableIds = [];

        $processedCategoryUids = [];
        $processedPurchasableUids = [];

        foreach ($rows as $row) {

            if ($row['categories'] && !in_array($row['bcUid'], $processedCategoryUids)) {

                $categoryIds = json_decode($row['categories']);
                $categories = [];

                foreach ($categoryIds as $categoryId) {
                    $categories[] = Craft::$app->getElements()->getElementById($categoryId);
                }

                $mappedCategoryIds[] = [
                    'ids' => $categoryIds,
                    'purchaseQty' => $row['bcPurchaseQty'],
                    'categories' => $categories
                ];

                $processedCategoryUids[] = $row['bcUid'];
            }

            if ($row['purchasables'] && !in_array($row['bpUid'], $processedPurchasableUids)) {

                $purchasableIds = json_decode($row['purchasables']);
                $purchasables = [];

                foreach ($purchasableIds as $purchasableId) {
                    $purchasables[] = Craft::$app->getElements()->getElementById($purchasableId);
                }

                $mappedPurchasableIds[] = [
                    'ids' => $purchasableIds,
                    'purchaseQty' => $row['bpPurchaseQty'],
                    'purchasables' => $purchasables
                ];

                $processedPurchasableUids[] = $row['bpUid'];
            }         
        }

        for ($i = 0; $i < sizeof($mappedCategoryIds); $i++) {
            $mappedCategoryIds[$i]["index"] = $i;
        }

        for ($i = 0; $i < sizeof($mappedPurchasableIds); $i++) {
            $mappedPurchasableIds[$i]["index"] = $i;
        }        

        $bundle->setCategoryIds($mappedCategoryIds);
        $bundle->setPurchasableIds($mappedPurchasableIds);
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
        $record->pricePercentage = $model->pricePercentage;
        $record->totalUses = $model->totalUses;
        $record->sortOrder = $record->sortOrder ?: 999;

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $record->save(false);
            $model->id = $record->id;

            BundleCategoryRecord::deleteAll(['bundleId' => $model->id]);
            BundlePurchasableRecord::deleteAll(['bundleId' => $model->id]);

            foreach ($model->getCategoryIds() as $categoryItem) {
                $relation = new BundleCategoryRecord();
                $relation->categories = json_encode($categoryItem['ids']);
                $relation->purchaseQty = $categoryItem['purchaseQty'];
                $relation->bundleId = $model->id;
                $relation->save(false);
            }

            foreach ($model->getPurchasableIds() as $purchasableItem) {
                $relation = new BundlePurchasableRecord();
                $relation->purchasables = json_encode($purchasableItem['ids']);
                $relation->purchaseQty = $purchasableItem['purchaseQty'];
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
     * @param String $matchMode The mode to use when matching.
     * @return BundleOrderMatchResult
     */
    public function matchOrder(Order $order, $lineItems, Bundle $bundle, $matchMode = self::BUNDLE_MATCH_MODE_REQUIRE_ALL) : BundleOrderMatchResult
    {
        // Check the bundle is enabled.
        if (!$bundle->enabled) return BundleOrderMatchResult::failedResult($bundle);

        // Check the dates align.
        $currentDate = new \DateTime();

        if (!empty($bundle->dateFrom) && $bundle->dateFrom > $currentDate) return BundleOrderMatchResult::failedResult($bundle);
        if (!empty($bundle->dateTo) && $bundle->dateTo < $currentDate) return BundleOrderMatchResult::failedResult($bundle);

        // For the bundle to match, we need to find line items that match the product and category constraints. 
        // Each constraint needs to match with unique line items.
        
        $products = $bundle->getPurchasableIds();
        $categories = $bundle->getCategoryIds();

        $availableLineItems = [];

        // Regardless of the matching mode, clone the line items so that we can modify them as we see fit.
        foreach ($lineItems as $originalLineItems) {
            $availableLineItems[] = clone $originalLineItems;
        }

        // Assume that it's a match initially and try and prove ourselves wrong.
        $lineItemsMatch = true;
        $anyRuleMatched = false;
        $lineItemRawPrice = 0;

        $totalProductRules = sizeof($products);
        $totalCategoryRules = sizeof($categories);

        $productRuleMatches = [];
        $categoryRuleMatches = [];

        // Match the product rules.
        foreach ($products as $product) {

            $matchedSoFar = 0;

            $ruleMatchResult = new BundleRuleMatchResult();
            $ruleMatchResult->ruleType = self::BUNDLE_RULE_TYPE_PRODUCT;
            $ruleMatchResult->rule = $product;
            $ruleMatchResult->matchesRequired = intval($product['purchaseQty']);

            // Check each available line item. If it matches the category rule, increment.
            foreach ($availableLineItems as $li) {
                $purchasedItem = $li->getPurchasable();
                $purchasedProduct = null;
    
                if (\is_a($purchasedItem, 'craft\commerce\elements\Variant')) {
                    $purchasedProduct = $purchasedItem->getProduct();
                }

                $productMatch = in_array($purchasedProduct->id, $product['ids']);
                
                if ($productMatch) {

                    // We have a match. Exchange quantity for matches against the rule as far as possible.
                    while ($li->qty > 0 && $matchedSoFar < $product['purchaseQty']) {
                        $matchedSoFar = $matchedSoFar + 1;
                        $li->qty = $li->qty - 1;

                        $lineItemRawPrice = $lineItemRawPrice + $li->salePrice;
                    }

                    // If there is no quantity left, exclude the item in future.
                    if ($li->qty == 0) {
                        $availableLineItems = array_filter($availableLineItems, function($aLi) use ($li) {
                            return $aLi->id != $li->id;
                        });
                    }

                    // If we have fully matched this product rule, stop considering other line items.
                    if ($matchedSoFar == $product['purchaseQty']) {
                        break;
                    }
                }
            }

            // Regardless, store the number of matches we made for the rule.
            $ruleMatchResult->matchesMade = $matchedSoFar;

            // We've now either:
            // - Matched the product rule -> Proceed to the next rule.
            if ($matchedSoFar == $product['purchaseQty']) {
                $ruleMatchResult->matchResult = self::MATCH_RESULT_SUCCESS;
                $anyRuleMatched = true;
                $productRuleMatches[] = $ruleMatchResult;
                continue;
            }

            // Or:
            // - Considered every line item and not matched the product rule. Thus, the overall match fails and we end matching against this rule.
            $ruleMatchResult->matchResult = $matchedSoFar > 0 ? self::MATCH_RESULT_PARTIAL : self::MATCH_RESULT_FAILED;
            $productRuleMatches[] = $ruleMatchResult;
            $lineItemsMatch = false;

            // If we require a match against all, we're done now.
            if ($matchMode == self::BUNDLE_MATCH_MODE_REQUIRE_ALL) {
                break;
            }
        }

        // There's no point in running the Category matching if we already failed at product level.
        // Unless we are allowing a partial match
        if ($lineItemsMatch || $matchMode == self::BUNDLE_MATCH_MODE_ALLOW_PARTIAL) {

            // Match the category rules.        
            foreach ($categories as $category) {

                $matchedSoFar = 0;
                $ruleMatchResult = new BundleRuleMatchResult();
                $ruleMatchResult->ruleType = self::BUNDLE_RULE_TYPE_CATEGORY;
                $ruleMatchResult->rule = $category;
                $ruleMatchResult->matchesRequired = intval($category['purchaseQty']);

                // Check each available line item. If it matches the category rule, increment.
                foreach ($availableLineItems as $li) {
                    $purchasedItem = $li->getPurchasable();
                    $purchasedProduct = null;
        
                    if (\is_a($purchasedItem, 'craft\commerce\elements\Variant')) {
                        $purchasedProduct = $purchasedItem->getProduct();
                    }

                    $categoryByVariant = Category::find()->id($category['ids'])->relatedTo($purchasedItem)->count() > 0;
                    $categoryByProduct = false;

                    if (!empty($purchasedProduct)) {
                        $categoryByProduct = Category::find()->id($category['ids'])->relatedTo($purchasedProduct)->count() > 0;
                    }
                    
                    if ($categoryByVariant || $categoryByProduct) {

                        // We have a match. Exchange quantity for matches against the rule as far as possible.
                        while ($li->qty > 0 && $matchedSoFar < $category['purchaseQty']) {
                            $matchedSoFar = $matchedSoFar + 1;
                            $li->qty = $li->qty - 1;

                            $lineItemRawPrice = $lineItemRawPrice + $li->salePrice;
                        }

                        // If there is no quantity left, exclude the item in future.
                        if ($li->qty == 0) {
                            $availableLineItems = array_filter($availableLineItems, function($aLi) use ($li) {
                                return $aLi->id != $li->id;
                            });
                        }

                        // If we have fully matched this category rule, stop considering other line items.
                        if ($matchedSoFar == $category['purchaseQty']) {
                            break;
                        }
                    }
                }

                // Regardless, store the number of matches we made for the rule.
                $ruleMatchResult->matchesMade = $matchedSoFar;          

                // We've now either:
                // - Matched the category rule -> Proceed to the next rule.
                if ($matchedSoFar == $category['purchaseQty']) {
                    $ruleMatchResult->matchResult = self::MATCH_RESULT_SUCCESS;
                    $categoryRuleMatches[] = $ruleMatchResult;
                    $anyRuleMatched = true;
                    continue;
                }

                // Or:
                // - Considered every line item and not matched the category rule. Thus, the overall match fails and we end.
                $ruleMatchResult->matchResult = $matchedSoFar > 0 ? self::MATCH_RESULT_PARTIAL : self::MATCH_RESULT_FAILED;
                $categoryRuleMatches[] = $ruleMatchResult;
                $lineItemsMatch = false;

                // If we require a match against all, we're done now.
                if ($matchMode == self::BUNDLE_MATCH_MODE_REQUIRE_ALL) {
                    break;
                }
            }
        }

        $res = new BundleOrderMatchResult();
        $res->bundle = $bundle;
        $res->productRules = $productRuleMatches;
        $res->categoryRules = $categoryRuleMatches;

        // If this flag is set, we have matched the bundle. 
        // Return to say successful and return the line items to use moving forward.
        if ($lineItemsMatch) {

            $res->matchResult = self::MATCH_RESULT_SUCCESS;
            $res->remainingAvailableLineItems = $availableLineItems;
            $res->lineItemRawPrice = $lineItemRawPrice;
            return $res;

        } else {

            if ($matchMode == self::BUNDLE_MATCH_MODE_ALLOW_PARTIAL && $anyRuleMatched) {

                $res->matchResult = self::MATCH_RESULT_PARTIAL;
                $res->remainingAvailableLineItems = $availableLineItems;
                return $res;
            } 

            $res->remainingAvailableLineItems = $lineItems;
            $res->matchResult = self::MATCH_RESULT_FAILED;
            return $res;
        }
    }

    /**
     * Finds active bundles that this order is someway towards matching.
     *
     * @param Order $order
     * @return BundleOrderMatchResult[]
     */
    public function findPartialMatchingBundlesForOrder(Order $order) 
    {
        $bundles = $this->getAllActiveBundles();
        $matchingBundles = [];

        foreach ($bundles as $bundle) {

            $result = $this->matchOrder($order, $order->getLineItems(), $bundle, BUNDLE_MATCH_MODE_ALLOW_PARTIAL);

            if ($result !== MATCH_RESULT_FAILED) {
                $matchingBundles[] = $result;
            }
        }

        return $matchingBundles;
    }

    /**
     * Finds the bundles that apply to the specified product.
     *
     * @param Product $product The product.
     * @return Bundle[]
     */
    public function findBundlesForProduct(Product $product) 
    {
        $bundles = $this->getAllActiveBundles();
        $validBundles = [];

        foreach ($bundles as $bundle) {

            $match = false;
            $bundleProductRules = $bundle->getPurchasableIds();
            $bundleCategoryRules = $bundle->getCategoryIds();

            // We only need to match the product against a single rule. We don't care which rule matches.
            foreach ($bundleProductRules as $productRule) {

                $productIds = $productRule['ids'];

                if (in_array($product->id, $productIds)) {
                    $match = true;
                    break;
                }
            }

            if (!$match) {

                foreach ($bundleCategoryRules as $categoryRule) {

                    $categoryIds = $categoryRule['ids'];

                    // Check for any categories with these IDs that relate to the product.
                    $categoryByProduct = Category::find()->id($categoryIds)->relatedTo($product)->count() > 0;

                    if ($categoryByProduct > 0) {
                        $match = true;
                        break;
                    }

                    $variants = $product->getVariants();
                    $categoryByVariant = Category::find()->id($categoryIds)->relatedTo($variants)->count() > 0;

                    if ($categoryByProduct > 0) {
                        $match = true;
                        break;
                    }
                }
            }

            if ($match) {
                $validBundles[] = $bundle;
            }
        }

        return $validBundles;
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
                'bundles.pricePercentage',
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
        $purchasables = [];

        $processedCategoryUids = [];
        $processedPurchasableUids = [];

        foreach ($bundles as $bundle) {
            $id = $bundle['id'];

            if ($bundle['categories'] && !in_array($bundle['bcUid'], $processedCategoryUids)) {

                $categoryIds = json_decode($bundle['categories']);
                $loadedCategories = [];

                foreach ($categoryIds as $categoryId) {
                    $loadedCategories[] = Craft::$app->getElements()->getElementById($categoryId);
                }

                $categories[$id][] = [
                    'ids' => $categoryIds,
                    'purchaseQty' => $bundle['bcPurchaseQty'],
                    'categories' => $loadedCategories
                ];

                $processedCategoryUids[] = $bundle['bcUid'];
            }

            if ($bundle['purchasables'] && !in_array($bundle['bpUid'], $processedPurchasableUids)) {

                $purchasableIds = json_decode($bundle['purchasables']);
                $loadedPurchasables = [];

                foreach ($purchasableIds as $purchasableId) {
                    $loadedPurchasables[] = Craft::$app->getElements()->getElementById($purchasableId);
                }

                $purchasables[$id][] = [
                    'ids' => $purchasableIds,
                    'purchaseQty' => $bundle['bpPurchaseQty'],
                    'purchasables' => $loadedPurchasables
                ];

                $processedPurchasableUids[] = $bundle['bpUid'];
            }

            unset($bundle['categories'], $bundle['bcPurchaseQty'], $bundle['bcUid'], $bundle['purchasables'], $bundle['bpPurchaseQty'], $bundle['bpUid']);

            if (!isset($allBundlesById[$id])) {
                $allBundlesById[$id] = new Bundle($bundle);
            }
        }

        foreach ($allBundlesById as $id => $bundle) {
    
            if (!empty($categories[$id])) {
                for ($i = 0; $i < sizeof($categories[$id]); $i++) {
                    $categories[$id][$i]["index"] = $i;
                }
            }

            if (!empty($purchasables[$id])) {
                for ($i = 0; $i < sizeof($purchasables[$id]); $i++) {
                    $purchasables[$id][$i]["index"] = $i;
                }            
            }

            $bundle->setCategoryIds($categories[$id] ?? []);
            $bundle->setPurchasableIds($purchasables[$id] ?? []);
        }

        return $allBundlesById;
    }    
}
