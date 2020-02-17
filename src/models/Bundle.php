<?php
/**
 * Bundles plugin for Craft CMS 3.x
 *
 * Craft Commerce Bundles Plugin
 *
 * @link      https://myth.digital/
 * @copyright Copyright (c) 2020 Myth Digital
 */

namespace mythdigital\bundles\models;

use mythdigital\bundles\Bundles;

use Craft;
use craft\base\Model;
use craft\helpers\UrlHelper;

/**
 * @author    Myth Digital
 * @package   Bundles
 * @since     1.0.0
 */
class Bundle extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var string Name of the bundle
     */
    public $name;

    /**
     * @var string The description of this bundle
     */
    public $description;

    /**
     * @var DateTime|null Date the discount is valid from
     */
    public $dateFrom;

    /**
     * @var DateTime|null Date the discount is valid to
     */
    public $dateTo;    

    /**
     * @var bool Discount enabled?
     */
    public $enabled = true;

    /**
     * @var float Total minimum spend on matching items
     */
    public $bundleDiscount = 0;    

    /**
     * @var int Per user coupon use limit
     */
    public $purchaseQty = 0;

    /**
     * @var int Total use counter;
     */
    public $totalUses = 0;

    /**
     * @var int sortOrder
     */
    public $sortOrder;

    /**
     * @var DateTime|null
     */
    public $dateCreated;

    /**
     * @var DateTime|null
     */
    public $dateUpdated;

    /**
     * @var int[] Product Ids
     */
    private $_purchasableIds;

    /**
     * @var int[] Product Type IDs
     */
    private $_categoryIds;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'dateFrom';
        $attributes[] = 'dateTo';

        return $attributes;
    }

    /**
     * @return string|false
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('bundles/' . $this->id);
    }

    /**
     * @return array
     */
    public function getCategoryIds(): array
    {
        if (null === $this->_categoryIds) {
            $this->_loadRelations();
        }

        return $this->_categoryIds;
    }

    /**
     * @return array
     */
    public function getPurchasableIds(): array
    {
        if (null === $this->_purchasableIds) {
            $this->_loadRelations();
        }

        return $this->_purchasableIds;
    }

    /**
     * Sets the related product type ids
     *
     * @param array $categoryIds
     */
    public function setCategoryIds(array $categoryIds)
    {
        $this->_categoryIds = array_unique($categoryIds);
    }

    /**
     * Sets the related product ids
     *
     * @param array $purchasableIds
     */
    public function setPurchasableIds(array $purchasableIds)
    {
        $this->_purchasableIds = array_unique($purchasableIds);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();

        $rules[] = [['name', 'purchaseQty', 'bundleDiscount'], 'required'];
        $rules[] = [
            [
                'bundleDiscount',
                'purchaseQty',
                'totalUses'
            ], 'number', 'skipOnEmpty' => false
        ];

        return $rules;
    }

    // Private Methods
    // =========================================================================

    /**
     * Loads the sale relations
     */
    private function _loadRelations()
    {
        Bundles::getInstance()->getBundles()->populateBundleRelations($this);
    }
}
