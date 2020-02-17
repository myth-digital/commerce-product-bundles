<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace mythdigital\bundles\records;

use craft\db\ActiveRecord;
use craft\elements\Category;
use yii\db\ActiveQueryInterface;

/**
 * Bundle Product type record.
 *
 * @property ActiveQueryInterface $category
 * @property int $categoryId
 * @property ActiveQueryInterface $bundle
 * @property int $bundleId
 * @property int $id
 * @author Myth Digital
 * @since 1.0
 */
class BundleCategory extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%bundles_bundle_categories}}';
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
    public function getCategory(): ActiveQueryInterface
    {
        return $this->hasOne(Category::class, ['id' => 'categoryId']);
    }
}
