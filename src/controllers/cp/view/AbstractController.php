<?php
/**
 * Created by PhpStorm.
 * User: dsmrt
 * Date: 3/9/18
 * Time: 2:48 PM
 */

namespace flipbox\saml\core\controllers\cp\view;

use flipbox\saml\core\AbstractPlugin;
use flipbox\saml\core\controllers\AbstractController as BaseController;
use flipbox\saml\core\records\ProviderInterface;
use flipbox\saml\core\traits\EnsureSamlPlugin;
use flipbox\saml\core\web\assets\bundles\SamlCore;
use SAML2\XML\md\IDPSSODescriptor;
use SAML2\XML\md\IndexedEndpointType;
use SAML2\XML\md\SPSSODescriptor;

/**
 * Class AbstractController
 * @package flipbox\saml\core\controllers\cp\view
 */
abstract class AbstractController extends BaseController
{
    use EnsureSamlPlugin;

    const TEMPLATE_INDEX = DIRECTORY_SEPARATOR . '_cp';

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        parent::init();
        \Craft::$app->view->registerAssetBundle(
            SamlCore::class
        );
    }

    /**
     * @return string
     */
    protected function getTemplateIndex()
    {
        return $this->getSamlPlugin()->getTemplateRootKey();
    }

    /**
     * @return array
     */
    protected function getBaseVariables()
    {
        $variables = [
            'title' => $this->getSamlPlugin()->name,
            'pluginHandle' => $this->getSamlPlugin()->getHandle(),
            'pluginVariable' => $this->getSamlPlugin()->getPluginVariableHandle(),
            'ownEntityId' => $this->getSamlPlugin()->getSettings()->getEntityId(),
            'settings' => $this->getSamlPlugin()->getSettings(),

            // Set the "Continue Editing" URL
            'continueEditingUrl' => $this->getBaseCpPath(),
            'baseActionPath' => $this->getBaseCpPath(),
            'baseCpPath' => $this->getBaseCpPath(),
            'templateIndex' => $this->getTemplateIndex(),
            'ownProvider' => $ownProvider = $this->getSamlPlugin()->getProvider()->findOwn(),

            'actions' => [],
        ];

        $variables['selectedSubnavItem'] = $this->getSubNavKey();

        /** @var ProviderInterface $ownProvider */
        if ($ownProvider) {
            $variables = array_merge(
                $this->addUrls($ownProvider),
                $variables
            );
        }

        return $variables;
    }

    /**
     * @return null|string
     */
    protected function getSubNavKey()
    {
        $request = \Craft::$app->request;

        $key = null;
        $path = implode(
            '/',
            [
                $request->getSegment(2),
                $request->getSegment(3),
                $request->getSegment(4),
            ]
        );

        if (preg_match('#^/+$#', $path)) {
            $key = 'saml.setup';
        } elseif (preg_match('#metadata/my-provider/#', $path)) {
            $key = 'saml.myProvider';
        } elseif (preg_match('#metadata/+$#', $path)) {
            $key = 'saml.providers';
        } elseif (preg_match('#keychain/+$#', $path)) {
            $key = 'saml.keychain';
        } elseif (preg_match('#settings/+$#', $path)) {
            $key = 'saml.settings';
        }
        return $key;
    }

    /**
     * @return string
     */
    protected function getBaseCpPath(): string
    {
        return $this->getSamlPlugin()->getHandle();
    }

    /**
     * @param ProviderInterface $provider
     * @param array $variables
     * @return array
     */
    protected function addUrls(ProviderInterface $provider)
    {

        $variables = [];
        $variables['assertionConsumerServices'] = null;
        $variables['singleLogoutServices'] = null;
        $variables['singleSignOnServices'] = null;

        if (! $provider->getMetadataModel()) {
            return $variables;
        }

        /** @var AbstractPlugin $plugin */
        $plugin = $this->getSamlPlugin();

        /**
         * Metadata/EntityDescriptor Model
         */
        $entityDescriptor = $provider->getMetadataModel();

        /**
         * Add SP URLs
         */
        if ($provider->getType() === $plugin::SP) {

            foreach ($entityDescriptor->getRoleDescriptor() as $roleDescriptor) {
                if (! ($roleDescriptor instanceof SPSSODescriptor)) {
                    continue;
                }

                $sloBinding = $roleDescriptor->getSingleLogoutService()[0]->getBinding();
                $sloResponseLocation = $roleDescriptor->getSingleLogoutService()[0]->getResponseLocation();
                $variables['singleLogoutServices'][$sloBinding] = $sloResponseLocation;

                /** @var IndexedEndpointType $firstACS */
                $firstACS = $roleDescriptor->getAssertionConsumerService()[0];
                $acsBinding = $firstACS->getBinding();
                $acsLocation = $firstACS->getLocation();
                $variables['assertionConsumerServices'][$acsBinding] = $acsLocation;
            }
        }

        /**
         * Add IDP URLs
         */
        if ($provider->getType() === $plugin::IDP) {
            foreach ($entityDescriptor->getRoleDescriptor() as $roleDescriptor) {

                if (! ($roleDescriptor instanceof IDPSSODescriptor)) {
                    continue;
                }

                $sloBinding = $roleDescriptor->getSingleLogoutService()[0]->getBinding();
                $sloLocation = $roleDescriptor->getSingleLogoutService()[0]->getLocation();
                $variables['singleLogoutServices'][$sloBinding] = $sloLocation;

                $ssoBinding = $roleDescriptor->getSingleSignOnService()[0]->getBinding();
                $ssoLocation = $roleDescriptor->getSingleSignOnService()[0]->getLocation();
                $variables['singleSignOnServices'][$ssoBinding] = $ssoLocation;
            }

        }

        return $variables;
    }


    /**
     * @param $type
     * @return string
     */
    protected function getTitle($type)
    {
        /** @var AbstractPlugin $plugin */
        $plugin = $this->getSamlPlugin();
        return $type === $plugin::SP ? 'Service Provider (SP)' : 'Identity Provider (IDP)';
    }
}
