<?php

namespace mythdigital\bundles\migrations;

use Craft;
use craft\db\Migration;

/**
 * m200223_235851_extendedbundles migration.
 */
class m200223_235851_extendedbundles extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Drop the old quantity column
        $this->dropColumn('{{%bundles_bundle}}', 'purchaseQty');
        
        // Change the column name.
        $this->renameColumn('{{%bundles_bundle}}', 'bundleDiscount', 'bundlePrice');        

        // Drop the Foreign Keys
        $this->dropForeignKey(str_replace('%', $this->db->tablePrefix, '%bundles_bundle_categories_categoryId_fk'), '{{%bundles_bundle_categories}}');
        $this->dropForeignKey(str_replace('%', $this->db->tablePrefix, '%bundles_bundle_categories_bundleId_fk'), '{{%bundles_bundle_categories}}');
        $this->dropForeignKey(str_replace('%', $this->db->tablePrefix, '%bundle_bundle_purchasables_purchasableId_fk'), '{{%bundle_bundle_purchasables}}');
        $this->dropForeignKey(str_replace('%', $this->db->tablePrefix, '%bundle_bundle_purchasables_bundleId_fk'), '{{%bundle_bundle_purchasables}}');        

        // Drop the indexes
        $this->dropIndex(str_replace('%', $this->db->tablePrefix, '%bundles_bundle_categories_bundleId_categoryId_unq_idx'), '{{%bundles_bundle_categories}}');
        $this->dropIndex(str_replace('%', $this->db->tablePrefix, '%bundles_bundle_categories_categoryId_idx'), '{{%bundles_bundle_categories}}');
        $this->dropIndex(str_replace('%', $this->db->tablePrefix, '%bundle_bundle_purchasables_bundleId_purchasableId_unq_idx'), '{{%bundle_bundle_purchasables}}');
        $this->dropIndex(str_replace('%', $this->db->tablePrefix, '%bundle_bundle_purchasables_purchasableId_idx'), '{{%bundle_bundle_purchasables}}');

        // Modify the Categories table.
        $this->dropColumn('{{%bundles_bundle_categories}}', 'categoryId'); 
        $this->addColumn('{{%bundles_bundle_categories}}', 'purchaseQty', 'integer not null'); 
        $this->addColumn('{{%bundles_bundle_categories}}', 'categories', 'string not null'); 

        // Modify the Purchasable table. 
        $this->dropColumn('{{%bundle_bundle_purchasables}}', 'purchasableId'); 
        $this->dropColumn('{{%bundle_bundle_purchasables}}', 'purchasableType');        
        $this->addColumn('{{%bundle_bundle_purchasables}}', 'purchaseQty', 'integer not null'); 
        $this->addColumn('{{%bundle_bundle_purchasables}}', 'purchasables', 'string not null');

        $this->createIndex(null, '{{%bundle_bundle_purchasables}}', ['bundleId', 'purchasables'], false);
        $this->createIndex(null, '{{%bundles_bundle_categories}}', ['bundleId', 'categories'], false);

        $this->addForeignKey(null, '{{%bundle_bundle_purchasables}}', ['bundleId'], '{{%bundles_bundle}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%bundles_bundle_categories}}', ['bundleId'], '{{%bundles_bundle}}', ['id'], 'CASCADE', 'CASCADE');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200223_235851_extendedbundles cannot be reverted.\n";
        return false;
    }
}
