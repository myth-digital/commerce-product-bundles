<?php
/**
 * Craft Commerce Bundles Plugin plugin for Craft CMS 3.x
 *
 * Craft Commerce Bundles Plugin
 *
 * @link      https://myth.digital/
 * @copyright Copyright (c) 2020 Myth Digital
 */

namespace mythdigital\bundles\records;

use mythdigital\bundles\bundles;

use Craft;
use craft\db\ActiveRecord;
use craft\elements\Category;
use craft\commerce\records\Purchasable;

/**
 * Bundle Record
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property DateTime $dateFrom
 * @property DateTime $dateTo
 * @property bool $enabled
 * @property float $bundlePrice
 * @property int $sortOrder
 * @property int $totalUses
 * @author Myth Digital
 * @since 1.0
 */
class Bundle extends ActiveRecord
{
    // Public Static Methods
    // =========================================================================

     /**
     *
     * @return string the table name
     */
    public static function tableName()
    {
        return '{{%bundles_bundle}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getBundleCategories(): ActiveQueryInterface
    {
        return $this->hasMany(BundleCategory::class, ['bundleId' => 'id']);
    }  

    /**
     * @return ActiveQueryInterface
     */
    public function getBundlePurchasables(): ActiveQueryInterface
    {
        return $this->hasMany(BundlePurchasable::class, ['bundleId' => 'id']);
    }      
}
