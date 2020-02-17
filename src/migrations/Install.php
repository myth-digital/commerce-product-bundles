<?php
/**
 * Bundles plugin for Craft CMS 3.x
 *
 * Craft Commerce Bundles Plugin
 *
 * @link      https://myth.digital/
 * @copyright Copyright (c) 2020 Myth Digital
 */

namespace mythdigital\bundles\migrations;

use mythdigital\bundles\Bundles;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;

/**
 * @author    Myth Digital
 * @package   Bundles
 * @since     1.0.0
 */
class Install extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
            $this->insertDefaultData();
        }

        return true;
    }

   /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @return bool
     */
    protected function createTables()
    {
        $tablesCreated = false;

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%bundle_bundle_purchasables}}');
        if ($tableSchema == null) {
            $this->createTable('{{%bundle_bundle_purchasables}}', [
                'id' => $this->primaryKey(),
                'bundleId' => $this->integer()->notNull(),
                'purchasableId' => $this->integer()->notNull(),
                'purchasableType' => $this->string()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%bundles_bundle_categories}}');
        if ($tableSchema == null) {

            $this->createTable('{{%bundles_bundle_categories}}', [
                'id' => $this->primaryKey(),
                'bundleId' => $this->integer()->notNull(),
                'categoryId' => $this->integer()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%bundles_bundle}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable('{{%bundles_bundle}}', [
                    'id' => $this->primaryKey(),
                    'name' => $this->mediumText()->notNull(),
                    'description' => $this->longText(),
                    'dateFrom' => $this->dateTime(),
                    'dateTo' => $this->dateTime(),     
                    'enabled' => $this->boolean()->notNull(),
                    'bundleDiscount' => $this->float()->notNull(),
                    'purchaseQty' => $this->integer()->notNull(),
                    'sortOrder' => $this->integer()->notNull(),
                    'totalUses' => $this->integer()->notNull(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid()
                ]
            );
        }

        return $tablesCreated;
    }

    /**
     * @return void
     */
    protected function createIndexes()
    {
        $this->createIndex(null, '{{%bundle_bundle_purchasables}}', ['bundleId', 'purchasableId'], true);
        $this->createIndex(null, '{{%bundle_bundle_purchasables}}', 'purchasableId', false);
        $this->createIndex(null, '{{%bundles_bundle_categories}}', ['bundleId', 'categoryId'], true);
        $this->createIndex(null, '{{%bundles_bundle_categories}}', 'categoryId', false);        
    }

    /**
     * @return void
     */
    protected function addForeignKeys()
    {
        $this->addForeignKey(null, '{{%bundle_bundle_purchasables}}', ['bundleId'], '{{%bundles_bundle}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%bundle_bundle_purchasables}}', ['purchasableId'], '{{%commerce_purchasables}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%bundles_bundle_categories}}', ['bundleId'], '{{%bundles_bundle}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%bundles_bundle_categories}}', ['categoryId'], '{{%categories}}', ['id'], 'CASCADE', 'CASCADE');        
    }

    /**
     * @return void
     */
    protected function insertDefaultData()
    {
    }

    /**
     * @return void
     */
    protected function removeTables()
    {
        $this->dropTableIfExists('{{%bundle_bundle_purchasables}}');
        $this->dropTableIfExists('{{%bundles_bundle_categories}}');
        $this->dropTableIfExists('{{%bundles_bundle}}');
    }
}
