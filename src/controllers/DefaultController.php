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
                'category' => null
            ],
            'categoryElementType' => Category::class
        ];

        return $this->renderTemplate('bundles/bundles/_categoryPartial', $variables);
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
        $dateDate = $request->getBodyParam('dateFrom[date]');
        if ($date && $dateDate) {
            $dateTime = DateTimeHelper::toDateTime($date) ?: null;
            $discount->dateFrom = $dateTime;
        }

        $date = $request->getBodyParam('dateTo');
        $dateDate = $request->getBodyParam('dateFrom[date]');

        if ($date && $dateDate) {
            $dateTime = DateTimeHelper::toDateTime($date) ?: null;
            $discount->dateTo = $dateTime;
        }

        $categories = $request->getBodyParam('categories', []);
        if (!$categories) {
            $categories = [];
        }

        $categoryQuantities = $request->getBodyParam('categoriesPurchaseQty', []);
        if (!$categoryQuantities) {
            $categoryQuantities = [];
        }

        // Combine the fields together.
        $hasCategoryError = false;

        $categories = array_filter($categories, function($c) {
            return !empty($c) && sizeof($c) == 1;
        });

        $categoryQuantities = array_filter($categoryQuantities, function($c) {
            return !empty($c) && $c > 0;
        });

        if (sizeof($categories) !== sizeof($categoryQuantities)) {
            $hasCategoryError = true;
            $bundle->addError('categories', 'A category and quantity must be specified for each row');
        }

        $mergedCategories = [];

        if (!$hasCategoryError) {
            for ($i = 0; $i < sizeof($categories); $i++) {
                $mergedCategories[] = [
                    'index' => $i,
                    'id' => $categories[$i][0],
                    'purchaseQty' => $categoryQuantities[$i],
                    'category' => Craft::$app->getElements()->getElementById($categories[$i][0])
                ];     
            }
        }

        if (!$hasCategoryError && empty($mergedCategories)) {
            $hasCategoryError = true;
            $bundle->addError('categories', 'At least one valid row must be added');
        } else {
            $bundle->setCategoryIds($mergedCategories);
        }

        // Save it
        if (!$hasCategoryError && Bundles::getInstance()->getBundles()->saveBundle($bundle)) {
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
    }
}
