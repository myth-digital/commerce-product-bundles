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
use mythdigital\bundles\services\BundleService;
use mythdigital\bundles\models\Bundle;
use Craft;
use craft\base\Model;
use craft\helpers\UrlHelper;

/**
 * @author    Myth Digital
 * @package   Bundles
 * @since     1.0.0
 */
class BundleOrderMatchResult extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var Bundle the bundle.
     */
    public $bundle;

    /**
     * @var string The result of the match operation.
     */
    public $matchResult;

    /**
     * @var array The line items available after the match. Can be used for greedy forward matching.
     */
    public $remainingAvailableLineItems;

    /**
     * @var float The raw, non-discounted price of the line items.
     */
    public $lineItemRawPrice;

    /**
     * The product rules that were matched against
     *
     * @var array
     */
    public $productRules;

    /**
     * The category rules that were matched against.
     *
     * @var array
     */
    public $categoryRules;

    // Methods
    // =========================================================================

    /**
     * Returns a default failed match result.
     *
     * @return BundleOrderMatchResult
     */
    public static function failedResult($bundle) : BundleOrderMatchResult
    {
        $res = new BundleOrderMatchResult();
        $res->bundle = $bundle;
        $res->matchResult = BundleService::MATCH_RESULT_FAILED;
    }
}
