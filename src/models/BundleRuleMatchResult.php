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
class BundleRuleMatchResult extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var array The rule type.
     */
    public $ruleType;

    /**
     * @var array The result of the match operation.
     */
    public $rule;

    /**
     * @var string The result of the match for this rule.
     */
    public $matchResult;

    /**
     * @var int The number of matches made.
     */
    public $matchesMade;

    /**
     * @var int The number of matches required.
     */
    public $matchesRequired;
}
