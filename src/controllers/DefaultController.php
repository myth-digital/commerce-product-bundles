<?php
/**
 * Bundles plugin for Craft CMS 3.x
 *
 * Craft Commerce Bundles Plugin
 *
 * @link      https://myth.digital/
 * @copyright Copyright (c) 2020 Myth Digital
 */

namespace mythdigital\bundles\controllers;

use mythdigital\bundles\Bundles;

use Craft;
use craft\web\Controller;
use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\base\Purchasable;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use mythdigital\bundles\models\Bundle;
use mythdigital\bundles\services\BundleService;
use craft\elements\Category;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\Localization;
use craft\i18n\Locale;
use yii\web\HttpException;
use yii\web\Response;
use function explode;
use function get_class;

/**
 * @author    Myth Digital
 * @package   Bundles
 * @since     1.0.0
 */
class DefaultController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
    }

    /**
     * @throws HttpException
     */
    public function actionIndex(): Response
    {
        $bundles = Bundles::getInstance()->getBundles()->getAllBundles();
        return $this->renderTemplate('bundles/bundles/index', compact('bundles'));
    }

    /**
     * @param int|null $id
     * @param Bundle|null $discount
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, Bundle $bundle = null): Response
    {
        $variables = compact('id', 'bundle');

        if (!$variables['bundle']) {
            if ($variables['id']) {
                $variables['bundle'] = Bundles::getInstance()->getBundles()->getBundleById($variables['id']);

                if (!$variables['bundle']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['bundle'] = new Bundle();

            }
        }

        $this->_populateVariables($variables);

        return $this->renderTemplate('bundles/bundles/_edit', $variables);
    }

    /**
     * @return Response
     * @throws HttpException
     */
    public function actionCategoryPartial(int $index = 0): Response
    {
        $variables = [
            'category' => [
                'index' => $index,
                'id' => -1,
                'purchaseQty' => 0,
                'categories' => null
            ],
            'categoryElementType' => Category::class
        ];

        return $this->renderTemplate('bundles/bundles/_categoryPartial', $variables);
    }   
    
    /**
     * @return Response
     * @throws HttpException
     */
    public function actionPurchasablePartial(int $index = 0): Response
    {
        $variables = [
            'purchasable' => [
                'index' => $index,
                'id' => -1,
                'purchaseQty' => 0,
                'purchasables' => null
            ],
            'purchasableElementType' => Product::class
        ];

        return $this->renderTemplate('bundles/bundles/_purchasablePartial', $variables);
    }     

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $bundle = new Bundle();
        $request = Craft::$app->getRequest();

        $bundle->id = $request->getBodyParam('id');
        $bundle->name = $request->getBodyParam('name');
        $bundle->description = $request->getBodyParam('description');
        $bundle->enabled = (bool)$request->getBodyParam('enabled');
        $bundle->bundlePrice = $request->getBodyParam('bundlePrice');
        $bundle->totalUses = $bundle->totalUses ? $bundle->totalUses : 0;

        $date = $request->getBodyParam('dateFrom');
        $dateDate = $date['date'];
        if ($date && $dateDate) {
            $dateTime = DateTimeHelper::toDateTime($date) ?: null;
            $bundle->dateFrom = $dateTime;
        }

        $date = $request->getBodyParam('dateTo');
        $dateDate = $date['date'];
        if ($date && $dateDate) {
            $dateTime = DateTimeHelper::toDateTime($date) ?: null;
            $bundle->dateTo = $dateTime;
        }

        $categories = $request->getBodyParam('categories', []);
        if (!$categories) {
            $categories = [];
        }

        $categoryQuantities = $request->getBodyParam('categoriesPurchaseQty', []);
        if (!$categoryQuantities) {
            $categoryQuantities = [];
        }

        $purchasables = $request->getBodyParam('purchasables', []);
        if (!$purchasables) {
            $purchasables = [];
        }

        $purchasableQuantities = $request->getBodyParam('purchasablesPurchaseQty', []);
        if (!$purchasableQuantities) {
            $purchasableQuantities = [];
        }        

        // Combine the fields together.
        $hasCategoryError = false;
        $hasPurchasableError = false;

        $categories = array_filter($categories, function($c) {
            return !empty($c);
        });

        $categoryQuantities = array_filter($categoryQuantities, function($c) {
            return $c >= 0;
        });

        if (sizeof($categories) !== sizeof($categoryQuantities)) {
            $hasCategoryError = true;
            $bundle->addError('categories', 'A category and quantity must be specified for each row');
        }        

        $purchasables = array_filter($purchasables, function($p) {
            return !empty($p);
        });

        $purchasableQuantities = array_filter($purchasableQuantities, function($p) {
            return $p >= 0;
        });        

        if (sizeof($purchasables) !== sizeof($purchasableQuantities)) {
            $hasPurchasableError = true;
            $bundle->addError('purchasables', 'A purchasable and quantity must be specified for each row');
        }        

        $mergedCategories = [];

        if (!$hasCategoryError) {
            for ($i = 0; $i < sizeof($categories); $i++) {

                if ($categoryQuantities[$i] == 0) continue;

                $loadedCategories = [];

                foreach ($categories[$i] as $categoryId) {
                    $loadedCategories[] = Craft::$app->getElements()->getElementById($categoryId);
                }

                $mergedCategories[] = [
                    'index' => $i,
                    'ids' => $categories[$i],
                    'purchaseQty' => $categoryQuantities[$i],
                    'categories' => $loadedCategories
                ];     
            }
        }

        $bundle->setCategoryIds($mergedCategories);

        $mergedPurchasables = [];

        if (!$hasPurchasableError) {
            for ($i = 0; $i < sizeof($purchasables); $i++) {

                if ($purchasableQuantities[$i] == 0) continue;

                $loadedPurchasables = [];

                foreach ($purchasables[$i] as $purchasableId) {
                    $loadedPurchasables[] = Craft::$app->getElements()->getElementById($purchasableId);
                }

                $mergedPurchasables[] = [
                    'index' => $i,
                    'ids' => $purchasables[$i],
                    'purchaseQty' => $purchasableQuantities[$i],
                    'purchasables' => $loadedPurchasables
                ];     
            }
        }   
        
        $bundle->setPurchasableIds($mergedPurchasables);
        
        if (!$hasPurchasableError && !$hasCategoryError && empty($mergedPurchasables) && empty($mergedCategories)) {
            $hasPurchasableError = true;
            $hasCategoryError = true;
            $bundle->addError('purchasables', 'At least one valid row must be added');
            $bundle->addError('categories', 'At least one valid row must be added');
        }

        // Save it
        if (!$hasCategoryError && !$hasPurchasableError && Bundles::getInstance()->getBundles()->saveBundle($bundle)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Bundle saved.'));
            $this->redirectToPostedUrl($bundle);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save bundle.'));
        }

        // Send the model back to the template
        $variables = [
            'bundle' => $bundle
        ];
        $this->_populateVariables($variables);

        Craft::$app->getUrlManager()->setRouteParams($variables);
    }

    /**
     *
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Json::decode(Craft::$app->getRequest()->getRequiredBodyParam('ids'));
        if ($success = Bundles::getInstance()->getBundles()->reorderBundles($ids)) {
            return $this->asJson(['success' => $success]);
        }

        return $this->asJson(['error' => Craft::t('commerce', 'Couldn’t reorder bundles.')]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        Bundles::getInstance()->getBundles()->deleteBundleById($id);

        return $this->asJson(['success' => true]);
    }

    /**
     * @throws HttpException
     */
    public function actionClearCouponUsageHistory()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        Bundles::getInstance()->getBundles()->clearCouponUsageHistoryById($id);

        return $this->asJson(['success' => true]);
    }

    // Private Methods
    // =========================================================================

    /**
     * @param array $variables
     */
    private function _populateVariables(&$variables)
    {
        if ($variables['bundle']->id) {
            $variables['title'] = $variables['bundle']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a Bundle');
        }

        $variables['categoryElementType'] = Category::class;
        $variables['purchasableElementType'] = Product::class;
    }
}
