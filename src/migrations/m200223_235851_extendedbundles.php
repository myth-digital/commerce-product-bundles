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
        
        // Add the quantity column to the Categories table.
        $this->addColumn('{{%bundles_bundle_categories}}', 'purchaseQty', 'integer not null'); 

        // Drop the Purchasable table. 
        $this->dropTable('{{%bundle_bundle_purchasables}}');

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
