<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticMailgunMailerBundle\Form\Type;

use Mautic\CoreBundle\Form\DataTransformer\ArrayStringTransformer;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class MailgunAccountType extends AbstractType
{
    private $coreParametersHelper;  // not used yet

    public function __construct()
    {
        $this->coreParametersHelper = null;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'host',
            TextType::class,
            [
                'label'      => 'mautic.mailgunmailer.form.new.host',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    // 'tooltip' => 'mautic.asset.config.form.max.size.tooltip',
                    ],
                'constraints' => [
                    /*new NotBlank([
                        'message' => 'mautic.core.value.required',
                    ]),*/
                ],
                'data' => $options['data']['host'],
            ]
        );

        $apiKey = '';

        if (is_string($options['data']['api_key']) && strlen($options['data']['api_key']) > 4) {
            $apiKey = '***'.substr($options['data']['api_key'], -1, 4);
        }
        $builder->add(
            'api_key',
            TextType::class,
            [
                'label'      => 'mautic.mailgunmailer.form.new.key',
                'label_attr' => [
                    'class' => 'control-label', ],
                'attr'       => [
                    'class'   => 'form-control',
                    // 'tooltip' => 'mautic.asset.config.form.max.size.tooltip',
                    ],
                'constraints' => [
                    /*new NotBlank([
                        'message' => 'mautic.core.value.required',
                    ]),*/
                ],
                'data' => $apiKey,
            ]
        );

        $builder->add(
            'delete',
            CheckboxType::class,
            [
                'label'      => 'mautic.mailgunmailer.form.new.delete',
                'label_attr' => [
                    'class' => 'control-label',
                ],
                'attr' => [
                    'class' => '',
                ],
                'data' => false,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'mailgunconfig_account';
    }
}
