<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace mythdigital\bundles\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;
use craft\commerce\records\Purchasable;

/**
 * Bundle product record.
 *
 * @property ActiveQueryInterface $bundle
 * @property int $bundleId
 * @property int $id
 * @property ActiveQueryInterface $purchasable
 * @property int $purchasableId
 * @property int $purchasableType
 * @author Myth Digital
 * @since 1.0
 */
class BundlePurchasable extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%bundle_bundle_purchasables}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getBundle(): ActiveQueryInterface
    {
        return $this->hasOne(Bundle::class, ['id' => 'bundleId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getPurchasable(): ActiveQueryInterface
    {
        return $this->hasOne(Purchasable::class, ['id' => 'purchasableId']);
    }
}
