<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticMailgunMailerBundle\EventListener;

use Mautic\ConfigBundle\ConfigEvents;
use Mautic\ConfigBundle\Event\ConfigBuilderEvent;
use Mautic\ConfigBundle\Event\ConfigEvent;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use MauticPlugin\MauticMailgunMailerBundle\Form\Type\ConfigType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConfigSubscriber implements EventSubscriberInterface
{
    /**
     * @var CoreParametersHelper
     */
    private $coreParametersHelper;

    private function addNewMailgunAccount($config)
    {
        // Try to add new host.
        // Get domain (Located at: [1]).
        $parts = explode('@', $config['mailer_mailgun_new_host']);
        if (count($parts) < 2) {
            // @todo: Trigger error in the future.
            return $config;
        }

        $config['mailer_mailgun_accounts'][$parts[1]] = [
                'host'    => $config['mailer_mailgun_new_host'],
                'api_key' => $config['mailer_mailgun_new_api_key'],
        ];
        unset(
            $config['mailer_mailgun_new_host'],
            $config['mailer_mailgun_new_api_key']
        );

        return $config;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            ConfigEvents::CONFIG_ON_GENERATE => ['onConfigGenerate', 0],
            ConfigEvents::CONFIG_PRE_SAVE    => ['onConfigPreSave', 0],
        ];
    }

    public function onConfigGenerate(ConfigBuilderEvent $event)
    {
        $event->addForm([
            'bundle'     => 'MailgunMailerBundle',
            'formAlias'  => 'mailgunconfig',
            'formType'   => ConfigType::class,
            'formTheme'  => 'MauticMailgunMailerBundle:FormTheme\Config',
            'parameters' => $event->getParametersFromConfig('MauticMailgunMailerBundle'),
        ]);
    }

    public function onConfigPreSave(ConfigEvent $event)
    {
        $event->unsetIfEmpty([
            'mailer_mailgun_new_host',
            'mailer_mailgun_new_api_key',
        ]);

        $config = $event->getConfig('mailgunconfig');

        if (!isset($config['mailer_mailgun_accounts'])) {
            $config['mailer_mailgun_accounts'] = [];
        }

        if (!empty($config['mailer_mailgun_new_host']) && !empty($config['mailer_mailgun_new_api_key'])) {
            $config = $this->addNewMailgunAccount($config);
        }

        // Unset keys for individual account (prevent editing).
        $keys = \array_keys($config);
        foreach ($keys as $k) {
            if (0 === \strpos($k, 'mailer_mailgun_account_')) {
                unset($config[$k]);
            }
        }

        $event->setConfig($config, 'mailgunconfig');
    }
}
