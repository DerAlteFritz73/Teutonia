<?php

namespace App\Form;

use App\Entity\Style;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
            ->add('image', TextType::class, [
                'label'    => 'Emoji',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'placeholder' => '🎵', 'maxlength' => 10],
                'help'     => 'Ein Emoji, das diesen Stil repräsentiert.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Style::class,
        ]);
    }
}
