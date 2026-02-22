<?php

namespace App\Form;

use App\Entity\Style;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class StyleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Stilname',
                'attr'  => ['class' => 'form-control'],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Beschreibung',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'rows' => 3],
            ])
            ->add('color', TextType::class, [
                'label'    => 'Farbe 1 (Verlauf Anfang)',
                'required' => false,
                'attr'     => ['class' => 'form-control form-control-color', 'type' => 'color'],
            ])
            ->add('color2', TextType::class, [
                'label'    => 'Farbe 2 (Verlauf Ende)',
                'required' => false,
                'attr'     => ['class' => 'form-control form-control-color', 'type' => 'color'],
            ])
            ->add('imagePosition', ChoiceType::class, [
                'label'    => 'Bildposition',
                'required' => false,
                'placeholder' => '– keine –',
                'attr'     => ['class' => 'form-select'],
                'choices'  => [
                    'Oben links'   => 'top-left',
                    'Oben Mitte'   => 'top',
                    'Oben rechts'  => 'top-right',
                    'Rechts'       => 'right',
                    'Unten rechts' => 'bottom-right',
                    'Unten Mitte'  => 'bottom',
                    'Unten links'  => 'bottom-left',
                    'Links'        => 'left',
                ],
            ])
            ->add('imageFile', FileType::class, [
                'label'    => 'Bild hochladen',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['class' => 'form-control', 'accept' => 'image/*'],
                'constraints' => [
                    new File([
                        'maxSize'   => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        'mimeTypesMessage' => 'Bitte ein gültiges Bild hochladen (JPG, PNG, GIF, WebP).',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Style::class,
        ]);
    }
}
