<?php

namespace mythdigital\bundles\migrations;

use Craft;
use craft\db\Migration;

/**
 * m200223_235852_extendedbundlespercentage migration.
 */
class m200223_235852_extendedbundlespercentage extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {       
        $this->addColumn('{{%bundles_bundle}}', 'pricePercentage', 'integer'); 
        
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn('{{%bundles_bundle}}', 'pricePercentage');
        echo "m200223_235852_extendedbundlespercentage cannot be reverted.\n";
        return false;
    }
}
