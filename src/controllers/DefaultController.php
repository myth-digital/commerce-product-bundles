<?php
/**
 * Bundles plugin for Craft CMS 3.x
 *
 * Craft Commerce Bundles Plugin
 *
 * @link      https://myth.digital/
 * @copyright Copyright (c) 2020 Myth Digital
 */

namespace mythdigital\bundles\controllers;

use mythdigital\bundles\Bundles;

use Craft;
use craft\web\Controller;

/**
 * @author    Myth Digital
 * @package   Bundles
 * @since     1.0.0
 */
class DefaultController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['index', 'do-something'];

    // Public Methods
    // =========================================================================

    /**
     * @return mixed
     */
    public function actionIndex()
    {
        $result = 'Welcome to the DefaultController actionIndex() method';

        return $result;
    }

    /**
     * @return mixed
     */
    public function actionDoSomething()
    {
        $result = 'Welcome to the DefaultController actionDoSomething() method';

        return $result;
    }
}
