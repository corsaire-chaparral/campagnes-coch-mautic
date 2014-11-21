<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\EmailBundle\Form\Type;

use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Form\EventListener\CleanFormSubscriber;
use Mautic\CoreBundle\Form\EventListener\FormExitSubscriber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceList;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class EmailType
 *
 * @package Mautic\EmailBundle\Form\Type
 */
class EmailType extends AbstractType
{

    private $translator;
    private $themes;
    private $defaultTheme;
    private $em;

    /**
     * @param MauticFactory $factory
     */
    public function __construct(MauticFactory $factory) {
        $this->translator   = $factory->getTranslator();
        $this->themes       = $factory->getInstalledThemes('email');
        $this->defaultTheme = $factory->getParameter('theme');
        $this->em           = $factory->getEntityManager();
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm (FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber(new CleanFormSubscriber(array('content' => 'html')));
        $builder->addEventSubscriber(new FormExitSubscriber('email.email', $options));

        $variantParent = $options['data']->getVariantParent();
        $isVariant     = !empty($variantParent);

        $builder->add('subject', 'text', array(
            'label'      => 'mautic.email.form.subject',
            'label_attr' => array('class' => 'control-label'),
            'attr'       => array('class' => 'form-control')
        ));

        //add category
        $builder->add('category', 'category', array(
            'bundle' => 'email',
            'disabled' => $isVariant
        ));

        //add lead lists
        $transformer = new \Mautic\CoreBundle\Form\DataTransformer\IdToEntityModelTransformer(
            $this->em,
            'MauticLeadBundle:LeadList',
            'id',
            true
        );
        $builder->add(
            $builder->create('lists', 'leadlist_choices', array(
                'label'      => 'mautic.email.form.list',
                'label_attr' => array('class' => 'control-label'),
                'attr'       => array(
                    'class' => 'form-control'
                ),
                'multiple' => true,
                'expanded' => false,
                'disabled' => $isVariant
            ))
                ->addModelTransformer($transformer)
        );

        //build a list
        $template = $options['data']->getTemplate();
        if (empty($template)) {
            $template = $this->defaultTheme;
        }
        $builder->add('template', 'choice', array(
            'choices'       => $this->themes,
            'expanded'      => false,
            'multiple'      => false,
            'label'         => 'mautic.email.form.template',
            'empty_value'   => false,
            'required'      => false,
            'label_attr'    => array('class' => 'control-label'),
            'attr'          => array(
                'class'   => 'form-control',
                'tooltip' => 'mautic.email.form.template.help'
            ),
            'data'          => $template
        ));

        $builder->add('language', 'locale', array(
            'label'      => 'mautic.email.form.language',
            'label_attr' => array('class' => 'control-label'),
            'attr'       => array(
                'class'   => 'form-control chosen'
            ),
            'required'   => false,
            'disabled' => $isVariant
        ));

        $builder->add('isPublished', 'button_group', array(
            'choice_list' => new ChoiceList(
                array(false, true),
                array('mautic.core.form.no', 'mautic.core.form.yes')
            ),
            'expanded'      => true,
            'multiple'      => false,
            'label'         => 'mautic.core.form.ispublished',
            'empty_value'   => false,
            'required'      => false
        ));

        $builder->add('publishUp', 'datetime', array(
            'widget'     => 'single_text',
            'label'      => 'mautic.core.form.publishup',
            'label_attr' => array('class' => 'control-label'),
            'attr'       => array(
                'class'       => 'form-control',
                'data-toggle' => 'datetime'
            ),
            'format'     => 'yyyy-MM-dd HH:mm',
            'required'   => false
        ));

        $builder->add('publishDown', 'datetime', array(
            'widget'     => 'single_text',
            'label'      => 'mautic.core.form.publishdown',
            'label_attr' => array('class' => 'control-label'),
            'attr'       => array(
                'class'       => 'form-control',
                'data-toggle' => 'datetime'
            ),
            'format'     => 'yyyy-MM-dd HH:mm',
            'required'   => false
        ));

        $builder->add('plainText', 'textarea', array(
            'label'      => 'mautic.email.form.plaintext',
            'label_attr' => array('class' => 'control-label'),
            'attr'       => array(
                'tooltip' => 'mautic.email.form.plaintext.help',
                'class'   => 'form-control',
                'rows'    => '15'
            )
        ));

        $contentMode = $options['data']->getContentMode();
        if (empty($contentMode)) {
            $contentMode = 'custom';
        }
        $builder->add('contentMode', 'button_group', array(
            'choice_list' => new ChoiceList(
                array('custom', 'builder'),
                array('mautic.email.form.contentmode.custom', 'mautic.email.form.contentmode.builder')
            ),
            'expanded'      => true,
            'multiple'      => false,
            'label'         => 'mautic.email.form.contentmode',
            'empty_value'   => false,
            'required'      => false,
            'data'          => $contentMode,
            'attr'          => array(
                'onChange' => 'Mautic.toggleEmailContentMode(this);'
            )
        ));

        $builder->add('customHtml', 'textarea', array(
            'label'      => 'mautic.email.form.customhtml',
            'label_attr' => array('class' => 'control-label'),
            'attr'       => array(
                'tooltip' => 'mautic.email.form.customhtml.help',
                'class'   => 'form-control advanced_editor_2rows'
            )
        ));

        if ($isVariant) {
            $builder->add('variantSettings', 'emailvariant', array(
                'label'       => false
            ));
        }

        $builder->add('sessionId', 'hidden');

        $builder->add('buttons', 'form_buttons', array(
            'pre_extra_buttons' => array(
                array(
                    'name'  => 'builder',
                    'label' => 'mautic.email.launch.builder',
                    'attr'  => array(
                        'class'   => 'btn btn-default' . (($contentMode == 'custom') ? ' hide' : ''),
                        'icon'    => 'fa fa-cube text-mautic',
                        'onclick' => "Mautic.launchEmailEditor();"
                    )
                )
            )
        ));

        if (!empty($options["action"])) {
            $builder->setAction($options["action"]);
        }
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Mautic\EmailBundle\Entity\Email'
        ));
    }

    /**
     * @return string
     */
    public function getName() {
        return "emailform";
    }
}
